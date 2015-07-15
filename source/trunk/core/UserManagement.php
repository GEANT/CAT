<?php

/* *********************************************************************************
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
    private static $DB_TYPE = "INST";

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
        $token = DBConnection::escape_value(UserManagement::$DB_TYPE, $token);
        $check = DBConnection::exec(UserManagement::$DB_TYPE, "SELECT invite_token, cat_institution_id 
                           FROM invitations 
                           WHERE invite_token = '$token' AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");

        if ($a = mysqli_fetch_object($check)) {
            if ($a->cat_institution_id === NULL) {
                return "OK-NEW";
            } else {
                return "OK-EXISTING";
            }
        } else { // invalid token... be a little verbose what's wrong with it
            $check_reason = DBConnection::exec(UserManagement::$DB_TYPE, "SELECT invite_token, used FROM invitations WHERE invite_token = '$token'");
            if ($a = mysqli_fetch_object($check_reason)) {
                if ($a->used == 1)
                    return "FAIL-ALREADYCONSUMED";
                else
                    return "FAIL-EXPIRED";
            } else {
                return "FAIL-NONEXISTINGTOKEN";
            }
        }
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
        $token = DBConnection::escape_value(UserManagement::$DB_TYPE, $token);
        $owner = DBConnection::escape_value(UserManagement::$DB_TYPE, $owner);
        // the token either has cat_institution_id set -> new admin for existing inst
        // or contains a number of parameters from external DB -> set up new inst
        $instinfo = DBConnection::exec(UserManagement::$DB_TYPE, "SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, external_db_uniquehandle 
                             FROM invitations 
                             WHERE invite_token = '$token' AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        if ($a = mysqli_fetch_object($instinfo)) {
            if ($a->cat_institution_id !== NULL) { // add new admin to existing inst
                DBConnection::exec(UserManagement::$DB_TYPE, "INSERT INTO ownership (user_id, institution_id, blesslevel, orig_mail) VALUES('$owner', $a->cat_institution_id, '$a->invite_issuer_level', '$a->invite_dest_mail') ON DUPLICATE KEY UPDATE blesslevel='$a->invite_issuer_level', orig_mail='$a->invite_dest_mail' ");                
                CAT::writeAudit($owner, "OWN", "IdP " . $a->cat_institution_id . " - added user as owner");
                return new IdP($a->cat_institution_id);
            } else { // create new IdP
                $fed = new Federation($a->country);
                $idp = new IdP($fed->newIdP($owner, $a->invite_issuer_level, $a->invite_dest_mail));

                if ($a->external_db_uniquehandle != NULL) {
                    $idp->setExternalDBId($a->external_db_uniquehandle);
                    $externalinfo = Federation::getExternalDBEntityDetails($a->external_db_uniquehandle);
                    foreach ($externalinfo['names'] as $instlang => $instname) {
                        $idp->addAttribute("general:instname", serialize(array('lang' => $instlang, 'content' => $instname)));
                    }
                    // see if we had a C language, and if not, pick a good candidate
                    if (!array_key_exists('C', $externalinfo['names'])) {
                        if (array_key_exists('en', $externalinfo['names'])) { // English is a good candidate
                            $idp->addAttribute("general:instname", serialize(array('lang' => 'C', 'content' => $externalinfo['names']['en'])));
                            $bestnameguess = $externalinfo['names']['en'];
                        } else { // no idea, let's take the first language we found
                            $idp->addAttribute("general:instname", serialize(array('lang' => 'C', 'content' => reset($externalinfo['names']))));
                            $bestnameguess = reset($externalinfo['names']);
                        }
                    }
                } else {
                    $idp->addAttribute("general:instname", serialize(array('lang' => 'C', 'content' => $a->name)));
                    $bestnameguess = $a->name;
                }
                CAT::writeAudit($owner, "NEW", "IdP " . $idp->identifier . " - created from invitation");

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

%s"), $bestnameguess, Config::$CONSORTIUM['name'], strtoupper($fed->identifier), Config::$APPEARANCE['productname'], Config::$APPEARANCE['productname_long']);
                    $retval = $user->sendMailToUser(_("IdP in your federation was created"), $message);
                    if ($retval == FALSE)
                        debug (2, "Mail to federation admin was NOT sent!\n");
                }

                return $idp;
            }
        }
    }

    /**
     * Adds a new administrator to an existing IdP
     * @param IdP $idp institution to which the admin is to be added.
     * @param string $user persistent user ID that is to be added as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function addAdminToIdp($idp, $user) {
        $user = DBConnection::escape_value(UserManagement::$DB_TYPE, $user);
        DBConnection::exec(UserManagement::$DB_TYPE, "INSERT IGNORE into ownership (institution_id,user_id,blesslevel,orig_mail) VALUES($idp->identifier,'$user','FED','SELF-APPOINTED')");
        return TRUE;
    }
    
    /**
     * Deletes an administrator from the IdP. If the IdP and user combination doesn't match, nothing happens.
     * @param IdP $idp institution from which the admin is to be deleted.
     * @param string $user persistent user ID that is to be deleted as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function removeAdminFromIdP($idp, $user) {
        $user = DBConnection::escape_value(UserManagement::$DB_TYPE, $user);
        DBConnection::exec(UserManagement::$DB_TYPE, "DELETE from ownership WHERE institution_id = $idp->identifier AND user_id = '$user'");
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
        $token = DBConnection::escape_value(UserManagement::$DB_TYPE, $token);
        DBConnection::exec(UserManagement::$DB_TYPE, "UPDATE invitations SET used = 1 WHERE invite_token = '$token'");
        return TRUE;
    }

    /**
     * Creates a new invitation token. The token's main purpose is to be sent out by mail. The function either can generate a token for a new 
     * administrator of an existing institution, or for a new institution. In the latter case, the institution only actually gets 
     * created in the DB if the token is actually consumed via createIdPFromToken().
     * 
     * @param boolean $by_fedadmin is the invitation token created for a federation admin or from an existing inst admin
     * @param type $for identifier (typically email address) for which the invitation is created
     * @param mixed $inst either an instance of the IdP class (for existing institutions to invite new admins) or a string (new institution - this is the inst name then)
     * @param string $external_id if the IdP to be created is related to an external DB entity, this parameter contains that ID
     * @param type $country if the institution is new (i.e. $inst is a string) this parameter needs to specify the federation of the new inst
     * @return mixed The function returns either the token (as string) or FALSE if something went wrong
     */
    public function createToken($by_fedadmin, $for, $inst_identifier, $external_id = 0, $country = 0) {
        $for = DBConnection::escape_value(UserManagement::$DB_TYPE, $for);
        $token = sha1(base_convert(rand(10e16, 10e20), 10, 36)) . sha1(base_convert(rand(10e16, 10e20), 10, 36));

        $level = ($by_fedadmin ? "FED" : "INST");
        if ($inst_identifier instanceof IdP) {
            DBConnection::exec(UserManagement::$DB_TYPE, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,cat_institution_id) VALUES('$level', '$for', '$token',$inst_identifier->identifier)");
            return $token;
        } else if (func_num_args() == 4) { // string name, but no country - new IdP with link to external DB
            // what country are we talking about?
            $newname = DBConnection::escape_value(UserManagement::$DB_TYPE,valid_string_db($inst_identifier));
            $extinfo = Federation::getExternalDBEntityDetails($external_id);
            $externalhandle = DBConnection::escape_value(UserManagement::$DB_TYPE,valid_string_db($external_id));
            DBConnection::exec(UserManagement::$DB_TYPE, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country, external_db_uniquehandle) VALUES('$level', '$for', '$token', '" . $newname . "', '" . $extinfo['country'] . "',  '" . $externalhandle . "')");
            return $token;
        } else if (func_num_args() == 5) { // string name, and country set - whole new IdP
            $newname = DBConnection::escape_value(UserManagement::$DB_TYPE,valid_string_db($inst_identifier));
            $newcountry = DBConnection::escape_value(UserManagement::$DB_TYPE,valid_string_db($country));
            DBConnection::exec(UserManagement::$DB_TYPE, "INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country) VALUES('$level', '$for', '$token', '" . $newname . "', '" . $newcountry . "')");
            return $token;
        } else {
            echo "FAIL!";
            return FALSE;
        }
    }

    /**
     * Retrieves all pending invitations for an institution or for a federation.
     * 
     * @param type $idp_identifier the identifier of the institution. If not set, returns invitations for not-yet-created insts
     * @return if idp_identifier is set: an array of strings (mail addresses); otherwise an array of tuples (country;name;mail)
     */
    public function listPendingInvitations($idp_identifier = 0) {
        $retval = array();
        $invitations = DBConnection::exec(UserManagement::$DB_TYPE, "SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE cat_institution_id " . ( $idp_identifier != 0 ? "= $idp_identifier" : "IS NULL") . " AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        if ($idp_identifier != 0) { // list invitations for existing institution, must match cat_institution_id
            while ($a = mysqli_fetch_object($invitations)) {
                debug(4, "Retrieving pending invitations for IdP $idp_identifier.\n");
                if ($a->cat_institution_id == $idp_identifier)
                    $retval[] = $a->invite_dest_mail;
            }
        } else { // list all invitations for *new* institutions
            while ($a = mysqli_fetch_object($invitations)) {
                debug(4, "Retrieving pending invitations for NEW institutions.\n");
                if ($a->cat_institution_id == NULL)
                    $retval[] = array("country" => $a->country, "name" => $a->name, "mail" => $a->invite_dest_mail, "token" => $a->invite_token);
            }
        };
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
        $returnarray = array();
        $userid = DBConnection::escape_value(UserManagement::$DB_TYPE, $userid);
        $institutions = DBConnection::exec(UserManagement::$DB_TYPE, "SELECT institution_id FROM ownership WHERE user_id = '$userid' ORDER BY institution_id");
        while ($a = mysqli_fetch_object($institutions))
            $returnarray[] = $a->institution_id;
        return $returnarray;
    }

}

?>
