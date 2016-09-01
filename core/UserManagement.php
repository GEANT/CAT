<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the UserManagement class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @license see LICENSE file in root directory
 * 
 * @package Developer
 */
/**
 * necessary includes
 */
require_once('DBConnection.php');
require_once("Federation.php");
require_once("IdP.php");
require_once("CAT.php");

/**
 * This class manages user privileges and bindings to institutions
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 */
class UserManagement {

    /**
     * Class constructor. Nothing special to be done when constructing.
     */
    public function __construct() {
        
    }

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $databaseType = "INST";

    /**
     * Checks if a given invitation token exists and is valid in the invitations database
     * returns a string with the following values:
     * 
     * OK-NEW valid token exists, and is not attached to an existing institution. When consuming the token, a new inst will be created
     * OK-EXISTING valid token exists, and is attached to an existing institution. When consuming the token, user will be added as an admin
     * FAIL-NONEXISTINGTOKEN this token does not exist at all in the database
     * FAIL-ALREADYCONSUMED the token exists, but has been used before
     * FAIL-EXPIRED the token exists, but has expired
     * 
     * @param string $token
     * @return string
     */
    public function checkTokenValidity($token) {
        $escapedToken = DBConnection::escapeValue(UserManagement::$databaseType, $token);
        $check = DBConnection::exec(UserManagement::$databaseType, "SELECT invite_token, cat_institution_id 
                           FROM invitations 
                           WHERE invite_token = '$escapedToken' AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");

        if ($tokenCheck = mysqli_fetch_object($check)) {
            if ($tokenCheck->cat_institution_id === NULL) {
                return "OK-NEW";
            }
            return "OK-EXISTING";
        }
        // if we haven't returned from the function yet, it is an invalid token... 
        // be a little verbose what's wrong with it
        $checkReason = DBConnection::exec(UserManagement::$databaseType, "SELECT invite_token, used FROM invitations WHERE invite_token = '$escapedToken'");
        if ($invalidTokenCheck = mysqli_fetch_object($checkReason)) {
            if ($invalidTokenCheck->used == 1) {
                return "FAIL-ALREADYCONSUMED";
            }
            return "FAIL-EXPIRED";
        }
        return "FAIL-NONEXISTINGTOKEN";
    }

    /**
     * This function creates a new IdP in the database based on a valid invitation token - or adds a new administrator
     * to an existing one. The institution is created for the logged-in user (second argument) who presents the token (first 
     * argument). The tokens are created via createToken().
     * 
     * @param string $token The invitation token (must exist in the database and be valid). 
     * @param string $owner Persistent User ID who becomes the administrator of the institution
     * @return IdP 
     */
    public function createIdPFromToken($token, $owner) {
        $escapedToken = DBConnection::escapeValue(UserManagement::$databaseType, $token);
        $escapedOwner = DBConnection::escapeValue(UserManagement::$databaseType, $owner);
        // the token either has cat_institution_id set -> new admin for existing inst
        // or contains a number of parameters from external DB -> set up new inst
        $instinfo = DBConnection::exec(UserManagement::$databaseType, "SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, external_db_uniquehandle 
                             FROM invitations 
                             WHERE invite_token = '$escapedToken' AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        if ($invitationDetails = mysqli_fetch_object($instinfo)) {
            if ($invitationDetails->cat_institution_id !== NULL) { // add new admin to existing IdP
                DBConnection::exec(UserManagement::$databaseType, "INSERT INTO ownership (user_id, institution_id, blesslevel, orig_mail) VALUES('$escapedOwner', $invitationDetails->cat_institution_id, '$invitationDetails->invite_issuer_level', '$invitationDetails->invite_dest_mail') ON DUPLICATE KEY UPDATE blesslevel='$invitationDetails->invite_issuer_level', orig_mail='$invitationDetails->invite_dest_mail' ");
                CAT::writeAudit($escapedOwner, "OWN", "IdP " . $invitationDetails->cat_institution_id . " - added user as owner");
                return new IdP($invitationDetails->cat_institution_id);
            }
            // create new IdP
            $fed = new Federation($invitationDetails->country);
            $idp = new IdP($fed->newIdP($escapedOwner, $invitationDetails->invite_issuer_level, $invitationDetails->invite_dest_mail));

            if ($invitationDetails->external_db_uniquehandle != NULL) {
                $idp->setExternalDBId($invitationDetails->external_db_uniquehandle);
                $externalinfo = Federation::getExternalDBEntityDetails($invitationDetails->external_db_uniquehandle);
                foreach ($externalinfo['names'] as $instlang => $instname) {
                    $idp->addAttribute("general:instname", serialize(['lang' => $instlang, 'content' => $instname]));
                }
                // see if we had a C language, and if not, pick a good candidate
                if (!array_key_exists('C', $externalinfo['names'])) {
                    if (array_key_exists('en', $externalinfo['names'])) { // English is a good candidate
                        $idp->addAttribute("general:instname", serialize(['lang' => 'C', 'content' => $externalinfo['names']['en']]));
                        $bestnameguess = $externalinfo['names']['en'];
                    } else { // no idea, let's take the first language we found
                        $idp->addAttribute("general:instname", serialize(['lang' => 'C', 'content' => reset($externalinfo['names'])]));
                        $bestnameguess = reset($externalinfo['names']);
                    }
                } else {
                    $bestnameguess = $externalinfo['names']['C'];
                }
            } else {
                $idp->addAttribute("general:instname", serialize(['lang' => 'C', 'content' => $invitationDetails->name]));
                $bestnameguess = $invitationDetails->name;
            }
            CAT::writeAudit($escapedOwner, "NEW", "IdP " . $idp->identifier . " - created from invitation");

            $admins = $fed->listFederationAdmins();

            // notify the fed admins...

            foreach ($admins as $id) {
                $user = new User($id);
                /// arguments are: 1. IdP name; 
                ///                2. consortium name (e.g. eduroam); 
                ///                3. federation shortname, e.g. "LU"; 
                ///                4. product name (e.g. eduroam CAT); 
                ///                5. product long name (e.g. eduroam Configuration Assistant Tool)
                $message = sprintf(_("Hi,

the invitation for the new Identity Provider %s in your %s federation %s has been used and the IdP was created in %s.

We thought you might want to know.

Best regards,

%s"), $bestnameguess, Config::$CONSORTIUM['name'], strtoupper($fed->name), Config::$APPEARANCE['productname'], Config::$APPEARANCE['productname_long']);
                $retval = $user->sendMailToUser(_("IdP in your federation was created"), $message);
                if ($retval == FALSE) {
                    debug(2, "Mail to federation admin was NOT sent!\n");
                }
            }

            return $idp;
        }
    }

    /**
     * Adds a new administrator to an existing IdP
     * @param IdP $idp institution to which the admin is to be added.
     * @param string $user persistent user ID that is to be added as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function addAdminToIdp($idp, $user) {
        $escapedUser = DBConnection::escapeValue(UserManagement::$databaseType, $user);
        DBConnection::exec(UserManagement::$databaseType, "INSERT IGNORE into ownership (institution_id,user_id,blesslevel,orig_mail) VALUES($idp->identifier,'$escapedUser','FED','SELF-APPOINTED')");
        return TRUE;
    }

    /**
     * Deletes an administrator from the IdP. If the IdP and user combination doesn't match, nothing happens.
     * @param IdP $idp institution from which the admin is to be deleted.
     * @param string $user persistent user ID that is to be deleted as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function removeAdminFromIdP($idp, $user) {
        $escapedUser = DBConnection::escapeValue(UserManagement::$databaseType, $user);
        DBConnection::exec(UserManagement::$databaseType, "DELETE from ownership WHERE institution_id = $idp->identifier AND user_id = '$escapedUser'");
        return TRUE;
    }

    /**
     * Invalidates a token so that it can't be used any more. Tokens automatically expire after 24h, but can be invalidated
     * earlier, e.g. after having been used to create an institution. If the token doesn't exist in the DB or is already invalidated,
     * nothing happens.
     * 
     * @param string $token the token to invalidate
     * @return boolean This function always returns TRUE.
     */
    public function invalidateToken($token) {
        $escapedToken = DBConnection::escapeValue(UserManagement::$databaseType, $token);
        DBConnection::exec(UserManagement::$databaseType, "UPDATE invitations SET used = 1 WHERE invite_token = '$escapedToken'");
        return TRUE;
    }

    /**
     * Creates a new invitation token. The token's main purpose is to be sent out by mail. The function either can generate a token for a new 
     * administrator of an existing institution, or for a new institution. In the latter case, the institution only actually gets 
     * created in the DB if the token is actually consumed via createIdPFromToken().
     * 
     * @param boolean $isByFedadmin is the invitation token created for a federation admin or from an existing inst admin
     * @param type $for identifier (typically email address) for which the invitation is created
     * @param mixed $instIdentifier either an instance of the IdP class (for existing institutions to invite new admins) or a string (new institution - this is the inst name then)
     * @param string $externalId if the IdP to be created is related to an external DB entity, this parameter contains that ID
     * @param type $country if the institution is new (i.e. $inst is a string) this parameter needs to specify the federation of the new inst
     * @return mixed The function returns either the token (as string) or FALSE if something went wrong
     */
    public function createToken($isByFedadmin, $for, $instIdentifier, $externalId = 0, $country = 0) {
        $escapedFor = DBConnection::escapeValue(UserManagement::$databaseType, $for);
        $token = sha1(base_convert(rand(0, 10e16), 10, 36)) . sha1(base_convert(rand(0, 10e16), 10, 36));
        $level = ($isByFedadmin ? "FED" : "INST");

        if ($instIdentifier instanceof IdP) {
            DBConnection::exec(UserManagement::$databaseType, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,cat_institution_id) VALUES('$level', '$escapedFor', '$token',$instIdentifier->identifier)");
            return $token;
        } else if (func_num_args() == 4) { // string name, but no country - new IdP with link to external DB
            // what country are we talking about?
            $newname = DBConnection::escapeValue(UserManagement::$databaseType, valid_string_db($instIdentifier));
            $extinfo = Federation::getExternalDBEntityDetails($externalId);
            $externalhandle = DBConnection::escapeValue(UserManagement::$databaseType, valid_string_db($externalId));
            DBConnection::exec(UserManagement::$databaseType, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country, external_db_uniquehandle) VALUES('$level', '$escapedFor', '$token', '" . $newname . "', '" . $extinfo['country'] . "',  '" . $externalhandle . "')");
            return $token;
        } else if (func_num_args() == 5) { // string name, and country set - whole new IdP
            $newname = DBConnection::escapeValue(UserManagement::$databaseType, valid_string_db($instIdentifier));
            $newcountry = DBConnection::escapeValue(UserManagement::$databaseType, valid_string_db($country));
            DBConnection::exec(UserManagement::$databaseType, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country) VALUES('$level', '$escapedFor', '$token', '" . $newname . "', '" . $newcountry . "')");
            return $token;
        }
        throw new Exception("Creation of a new token failed!");
    }

    /**
     * Retrieves all pending invitations for an institution or for a federation.
     * 
     * @param type $idpIdentifier the identifier of the institution. If not set, returns invitations for not-yet-created insts
     * @return if idp_identifier is set: an array of strings (mail addresses); otherwise an array of tuples (country;name;mail)
     */
    public function listPendingInvitations($idpIdentifier = 0) {
        $retval = [];
        $invitations = DBConnection::exec(UserManagement::$databaseType, "SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE cat_institution_id " . ( $idpIdentifier != 0 ? "= $idpIdentifier" : "IS NULL") . " AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        debug(4, "Retrieving pending invitations for " . ($idpIdentifier != 0 ? "IdP $idpIdentifier" : "IdPs awaiting initial creation" ) . ".\n");
        while ($invitationQuery = mysqli_fetch_object($invitations)) {
            $retval[] = ["country" => $invitationQuery->country, "name" => $invitationQuery->name, "mail" => $invitationQuery->invite_dest_mail, "token" => $invitationQuery->invite_token];
        }
        return $retval;
    }

    /** Retrieves all invitations which have expired in the last hour.
     * 
     * @return array of expired invitations
     */
    public function listRecentlyExpiredInvitations() {
        $retval = [];
        $invitations = DBConnection::exec(UserManagement::$databaseType, "SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE invite_created >= TIMESTAMPADD(HOUR, -25, NOW()) AND invite_created < TIMESTAMPADD(HOUR, -24, NOW()) AND used = 0");
        while ($expInvitationQuery = mysqli_fetch_object($invitations)) {
            debug(4, "Retrieving recently expired invitations (expired in last hour)\n");
            if ($expInvitationQuery->cat_institution_id == NULL) {
                $retval[] = ["country" => $expInvitationQuery->country, "level" => $expInvitationQuery->invite_issuer_level, "name" => $expInvitationQuery->name, "mail" => $expInvitationQuery->invite_dest_mail];
            } else {
                $retval[] = ["country" => $expInvitationQuery->country, "level" => $expInvitationQuery->invite_issuer_level, "name" => "Existing IdP", "mail" => $expInvitationQuery->invite_dest_mail];
            }
        }
        return $retval;
    }

    /**
     * For a given persistent user identifier, returns an array of institution identifiers (not the actual objects!) for which this
     * user is the/a administrator.
     * 
     * @param string $userid persistent user identifier
     * @return array array of institution IDs
     */
    public function listInstitutionsByAdmin($userid) {
        $returnarray = [];
        $escapedUserid = DBConnection::escapeValue(UserManagement::$databaseType, $userid);
        $institutions = DBConnection::exec(UserManagement::$databaseType, "SELECT institution_id FROM ownership WHERE user_id = '$escapedUserid' ORDER BY institution_id");
        while ($instQuery = mysqli_fetch_object($institutions)) {
            $returnarray[] = $instQuery->institution_id;
        }
        return $returnarray;
    }

}
