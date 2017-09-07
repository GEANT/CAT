<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

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

namespace core;

use \Exception;

/**
 * This class manages user privileges and bindings to institutions
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 */
class UserManagement extends \core\common\Entity {

    /**
     * our handle to the INST database
     * 
     * @var DBConnection
     */
    private $databaseHandle;

    /**
     * Class constructor. Nothing special to be done when constructing.
     */
    public function __construct() {
        parent::__construct();
        $this->databaseHandle = DBConnection::handle(self::$databaseType);
    }

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $databaseType = "INST";

    const TOKENSTATUS_OK_NEW = 1;
    const TOKENSTATUS_OK_EXISTING = 2;
    const TOKENSTATUS_FAIL_ALREADYCONSUMED = -1;
    const TOKENSTATUS_FAIL_EXPIRED = -2;
    const TOKENSTATUS_FAIL_NONEXISTING = -3;

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
     * @return int
     */
    public function checkTokenValidity($token) {
        $check = $this->databaseHandle->exec("SELECT invite_token, cat_institution_id 
                           FROM invitations 
                           WHERE invite_token = ? AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0", "s", $token);

        if ($tokenCheck = mysqli_fetch_object($check)) {
            if ($tokenCheck->cat_institution_id === NULL) {
                return self::TOKENSTATUS_OK_NEW;
            }
            return self::TOKENSTATUS_OK_EXISTING;
        }
        // if we haven't returned from the function yet, it is an invalid token... 
        // be a little verbose what's wrong with it
        $checkReason = $this->databaseHandle->exec("SELECT invite_token, used FROM invitations WHERE invite_token = ?", "s", $token);
        if ($invalidTokenCheck = mysqli_fetch_object($checkReason)) {
            if ($invalidTokenCheck->used == 1) {
                return self::TOKENSTATUS_FAIL_ALREADYCONSUMED;
            }
            return self::TOKENSTATUS_FAIL_EXPIRED;
        }
        return self::TOKENSTATUS_FAIL_NONEXISTING;
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
    public function createIdPFromToken(string $token, string $owner) {
        // the token either has cat_institution_id set -> new admin for existing inst
        // or contains a number of parameters from external DB -> set up new inst
        $instinfo = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, external_db_uniquehandle 
                             FROM invitations 
                             WHERE invite_token = ? AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0", "s", $token);
        if ($invitationDetails = mysqli_fetch_object($instinfo)) {
            if ($invitationDetails->cat_institution_id !== NULL) { // add new admin to existing IdP
                // we can't rely on a unique key on this table (user IDs 
                // possibly too long), so run a query to find there's an
                // tuple already; and act accordingly
                $catId = $invitationDetails->cat_institution_id;
                $level = $invitationDetails->cat_institution_id;
                $destMail = $invitationDetails->invite_dest_mail;
                $existing = $this->databaseHandle->exec("SELECT user_id FROM ownership WHERE user_id = ? AND institution_id = ?", "si", $owner, $catId);
                if (mysqli_num_rows($existing) > 0) {
                    $this->databaseHandle->exec("UPDATE ownership SET blesslevel = ?, orig_mail = ? WHERE user_id = ? AND institution_id = ?", "sssi", $level, $destMail, $owner, $catId);
                } else {
                    $this->databaseHandle->exec("INSERT INTO ownership (user_id, institution_id, blesslevel, orig_mail) VALUES(?, ?, ?, ?)", "siss", $owner, $catId, $level, $destMail);
                }
                $this->loggerInstance->writeAudit((string) $owner, "OWN", "IdP " . $invitationDetails->cat_institution_id . " - added user as owner");
                return new IdP($invitationDetails->cat_institution_id);
            }
            // create new IdP
            $fed = new Federation($invitationDetails->country);
            $idp = new IdP($fed->newIdP($owner, $invitationDetails->invite_issuer_level, $invitationDetails->invite_dest_mail));

            if ($invitationDetails->external_db_uniquehandle != NULL) {
                $idp->setExternalDBId($invitationDetails->external_db_uniquehandle);
                $cat = new CAT();
                $externalinfo = $cat->getExternalDBEntityDetails($invitationDetails->external_db_uniquehandle);
                foreach ($externalinfo['names'] as $instlang => $instname) {
                    $idp->addAttribute("general:instname", $instlang, $instname);
                }
                // see if we had a C language, and if not, pick a good candidate
                if (!array_key_exists('C', $externalinfo['names'])) {
                    if (array_key_exists('en', $externalinfo['names'])) { // English is a good candidate
                        $idp->addAttribute("general:instname", 'C', $externalinfo['names']['en']);
                        $bestnameguess = $externalinfo['names']['en'];
                    } else { // no idea, let's take the first language we found
                        $idp->addAttribute("general:instname", 'C', reset($externalinfo['names']));
                        $bestnameguess = reset($externalinfo['names']);
                    }
                } else {
                    $bestnameguess = $externalinfo['names']['C'];
                }
            } else {
                $idp->addAttribute("general:instname", 'C', $invitationDetails->name);
                $bestnameguess = $invitationDetails->name;
            }
            $this->loggerInstance->writeAudit($owner, "NEW", "IdP " . $idp->identifier . " - created from invitation");

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

%s"), $bestnameguess, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], strtoupper($fed->identifier), CONFIG['APPEARANCE']['productname'], CONFIG['APPEARANCE']['productname_long']);
                $retval = $user->sendMailToUser(_("IdP in your federation was created"), $message);
                if ($retval == FALSE) {
                    $this->loggerInstance->debug(2, "Mail to federation admin was NOT sent!\n");
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
        $existing = $this->databaseHandle->exec("SELECT user_id FROM ownership WHERE user_id = ? AND institution_id = ?", "si", $user, $idp->identifier);
        if (mysqli_num_rows($existing) == 0) {
            $this->databaseHandle->exec("INSERT INTO ownership (institution_id,user_id,blesslevel,orig_mail) VALUES(?, ?, 'FED', 'SELF-APPOINTED')", "is", $idp->identifier, $user);
        }
        return TRUE;
    }

    /**
     * Deletes an administrator from the IdP. If the IdP and user combination doesn't match, nothing happens.
     * @param IdP $idp institution from which the admin is to be deleted.
     * @param string $user persistent user ID that is to be deleted as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function removeAdminFromIdP($idp, $user) {
        $this->databaseHandle->exec("DELETE from ownership WHERE institution_id = $idp->identifier AND user_id = ?", "s", $user);
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
        $this->databaseHandle->exec("UPDATE invitations SET used = 1 WHERE invite_token = ?", "s", $token);
        return TRUE;
    }

    /**
     * Creates a new invitation token. The token's main purpose is to be sent out by mail. The function either can generate a token for a new 
     * administrator of an existing institution, or for a new institution. In the latter case, the institution only actually gets 
     * created in the DB if the token is actually consumed via createIdPFromToken().
     * 
     * @param boolean $isByFedadmin is the invitation token created for a federation admin (TRUE) or from an existing inst admin (FALSE)
     * @param string $for identifier (typically email address) for which the invitation is created
     * @param mixed $instIdentifier either an instance of the IdP class (for existing institutions to invite new admins) or a string (new institution - this is the inst name then)
     * @param string $externalId if the IdP to be created is related to an external DB entity, this parameter contains that ID
     * @param string $country if the institution is new (i.e. $inst is a string) this parameter needs to specify the federation of the new inst
     * @return mixed The function returns either the token (as string) or FALSE if something went wrong
     */
    public function createToken($isByFedadmin, $for, $instIdentifier, $externalId = 0, $country = 0) {
        $token = sha1(base_convert(rand(0, 10e16), 10, 36)) . sha1(base_convert(rand(0, 10e16), 10, 36));
        $level = ($isByFedadmin ? "FED" : "INST");

        if ($instIdentifier instanceof IdP) {
            $this->databaseHandle->exec("INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,cat_institution_id) VALUES(?, ?, ?, ?)", "sssi", $level, $for, $token, $instIdentifier->identifier);
            return $token;
        } else if (func_num_args() == 4) { // string name, but no country - new IdP with link to external DB
            // what country are we talking about?
            $cat = new CAT();
            $extinfo = $cat->getExternalDBEntityDetails($externalId);
            $extCountry = $extinfo['country'];
            $this->databaseHandle->exec("INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country, external_db_uniquehandle) VALUES(?, ?, ?, ?, ?, ?)", "ssssss", $level, $for, $token, $instIdentifier, $extCountry, $externalId);
            return $token;
        } else if (func_num_args() == 5) { // string name, and country set - whole new IdP
            $this->databaseHandle->exec("INSERT INTO invitations (invite_issuer_level, invite_dest_mail, invite_token,name,country) VALUES(?, ?, ?, ?, ?)", "sssss", $level, $for, $token, $instIdentifier, $country);
            return $token;
        }
        throw new Exception("Creation of a new token failed!");
    }

    /**
     * Retrieves all pending invitations for an institution or for a federation.
     * 
     * @param int $idpIdentifier the identifier of the institution. If not set, returns invitations for not-yet-created insts
     * @return array if idp_identifier is set: an array of strings (mail addresses); otherwise an array of tuples (country;name;mail)
     */
    public function listPendingInvitations($idpIdentifier = 0) {
        $retval = [];
        $invitations = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE cat_institution_id " . ( $idpIdentifier != 0 ? "= $idpIdentifier" : "IS NULL") . " AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        $this->loggerInstance->debug(4, "Retrieving pending invitations for " . ($idpIdentifier != 0 ? "IdP $idpIdentifier" : "IdPs awaiting initial creation" ) . ".\n");
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
        $invitations = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE invite_created >= TIMESTAMPADD(HOUR, -25, NOW()) AND invite_created < TIMESTAMPADD(HOUR, -24, NOW()) AND used = 0");
        while ($expInvitationQuery = mysqli_fetch_object($invitations)) {
            $this->loggerInstance->debug(4, "Retrieving recently expired invitations (expired in last hour)\n");
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
        $institutions = $this->databaseHandle->exec("SELECT institution_id FROM ownership WHERE user_id = ? ORDER BY institution_id", "s", $userid);
        while ($instQuery = mysqli_fetch_object($institutions)) {
            $returnarray[] = $instQuery->institution_id;
        }
        return $returnarray;
    }

}
