<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
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
class UserManagement extends \core\common\Entity
{

    /**
     * our handle to the INST database
     * 
     * @var DBConnection
     */
    private $databaseHandle;

    /**
     * Class constructor. Nothing special to be done when constructing.
     * 
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $handle = DBConnection::handle(self::$databaseType);
        if ($handle instanceof DBConnection) {
            $this->databaseHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
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
     * @param string $token the invitation token
     * @return int
     */
    public function checkTokenValidity($token)
    {
        $check = $this->databaseHandle->exec("SELECT invite_token, cat_institution_id 
                           FROM invitations 
                           WHERE invite_token = ? AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0", "s", $token);
        // SELECT -> resource, not boolean
        if ($tokenCheck = mysqli_fetch_object(/** @scrutinizer ignore-type */ $check)) {
            if ($tokenCheck->cat_institution_id === NULL) {
                return self::TOKENSTATUS_OK_NEW;
            }
            return self::TOKENSTATUS_OK_EXISTING;
        }
        // if we haven't returned from the function yet, it is an invalid token... 
        // be a little verbose what's wrong with it
        $checkReason = $this->databaseHandle->exec("SELECT invite_token, used FROM invitations WHERE invite_token = ?", "s", $token);
        // SELECT -> resource, not boolean
        if ($invalidTokenCheck = mysqli_fetch_object(/** @scrutinizer ignore-type */ $checkReason)) {
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
    public function createIdPFromToken(string $token, string $owner)
    {
        new CAT(); // be sure that Entity's static members are initialised
        common\Entity::intoThePotatoes();
        // the token either has cat_institution_id set -> new admin for existing inst
        // or contains a number of parameters from external DB -> set up new inst
        $instinfo = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, external_db_uniquehandle, invite_fortype 
                             FROM invitations 
                             WHERE invite_token = ? AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0", "s", $token);
        // SELECT -> resource, no boolean
        if ($invitationDetails = mysqli_fetch_object(/** @scrutinizer ignore-type */ $instinfo)) {
            if ($invitationDetails->cat_institution_id !== NULL) { // add new admin to existing IdP
                // we can't rely on a unique key on this table (user IDs 
                // possibly too long), so run a query to find there's an
                // tuple already; and act accordingly
                $catId = $invitationDetails->cat_institution_id;
                $level = $invitationDetails->invite_issuer_level;
                $destMail = $invitationDetails->invite_dest_mail;
                $existing = $this->databaseHandle->exec("SELECT user_id FROM ownership WHERE user_id = ? AND institution_id = ?", "si", $owner, $catId);
                // SELECT -> resource, not boolean
                if (mysqli_num_rows(/** @scrutinizer ignore-type */ $existing) > 0) {
                    $this->databaseHandle->exec("UPDATE ownership SET blesslevel = ?, orig_mail = ? WHERE user_id = ? AND institution_id = ?", "sssi", $level, $destMail, $owner, $catId);
                } else {
                    $this->databaseHandle->exec("INSERT INTO ownership (user_id, institution_id, blesslevel, orig_mail) VALUES(?, ?, ?, ?)", "siss", $owner, $catId, $level, $destMail);
                }
                $this->loggerInstance->writeAudit((string) $owner, "OWN", "IdP " . $invitationDetails->cat_institution_id . " - added user as owner");
                common\Entity::outOfThePotatoes();
                return new IdP($invitationDetails->cat_institution_id);
            }
            // create new IdP
            $fed = new Federation($invitationDetails->country);
            // find the best name for the entity: C if specified, otherwise English, otherwise whatever
            if ($invitationDetails->external_db_uniquehandle != NULL) {
                // see if we had a C language, and if not, pick a good candidate 
                $cat = new CAT();
                $externalinfo = $cat->getExternalDBEntityDetails($invitationDetails->external_db_uniquehandle);
                $bestnameguess = $externalinfo['names']['C'] ?? $externalinfo['names']['en'] ?? reset($externalinfo['names']);
                $idp = new IdP($fed->newIdP($invitationDetails->invite_fortype, $owner, $invitationDetails->invite_issuer_level, $invitationDetails->invite_dest_mail, $bestnameguess));
                foreach ($externalinfo['names'] as $instlang => $instname) {
                    $idp->addAttribute("general:instname", $instlang, $instname);
                }
                $idp->setExternalDBId($invitationDetails->external_db_uniquehandle);
            } else {
                $bestnameguess = $invitationDetails->name;
                $idp = new IdP($fed->newIdP($invitationDetails->invite_fortype, $owner, $invitationDetails->invite_issuer_level, $invitationDetails->invite_dest_mail, $bestnameguess));
            }
            $idp->addAttribute("general:instname", 'C', $bestnameguess);
            $this->loggerInstance->writeAudit($owner, "NEW", "IdP " . $idp->identifier . " - created from invitation");

            // in case we have more admins in the queue which were invited to 
            // administer the same inst but haven't redeemed their invitations 
            // yet, then we will have to rewrite the invitations to point to the
            // newly created actual IdP rather than the placeholder entry in the
            // invitations table
            // which other pending invites do we have?

            $otherPending = $this->databaseHandle->exec("SELECT id
                             FROM invitations 
                             WHERE invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0 AND name = ? AND country = ? AND ( cat_institution_id IS NULL OR external_db_uniquehandle IS NULL ) ", "ss", $invitationDetails->name, $invitationDetails->country);
            // SELECT -> resource, no boolean
            while ($pendingDetail = mysqli_fetch_object(/** @scrutinizer ignore-type */ $otherPending)) {
                $this->databaseHandle->exec("UPDATE invitations SET cat_institution_id = " . $idp->identifier . " WHERE id = " . $pendingDetail->id);
            }

            common\Entity::outOfThePotatoes();
            return $idp;
        }
    }

    /**
     * Adds a new administrator to an existing IdP
     * @param IdP    $idp  institution to which the admin is to be added.
     * @param string $user persistent user ID that is to be added as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function addAdminToIdp($idp, $user)
    {
        $existing = $this->databaseHandle->exec("SELECT user_id FROM ownership WHERE user_id = ? AND institution_id = ?", "si", $user, $idp->identifier);
        // SELECT -> resource, not boolean
        if (mysqli_num_rows(/** @scrutinizer ignore-type */ $existing) == 0) {
            $this->databaseHandle->exec("INSERT INTO ownership (institution_id,user_id,blesslevel,orig_mail) VALUES(?, ?, 'FED', 'SELF-APPOINTED')", "is", $idp->identifier, $user);
        }
        return TRUE;
    }

    /**
     * Deletes an administrator from the IdP. If the IdP and user combination doesn't match, nothing happens.
     * @param IdP    $idp  institution from which the admin is to be deleted.
     * @param string $user persistent user ID that is to be deleted as an admin.
     * @return boolean This function always returns TRUE.
     */
    public function removeAdminFromIdP($idp, $user)
    {
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
    public function invalidateToken($token)
    {
        $this->databaseHandle->exec("UPDATE invitations SET used = 1 WHERE invite_token = ?", "s", $token);
        return TRUE;
    }

    /**
     * Creates a new invitation token. The token's main purpose is to be sent out by mail. The function either can generate a token for a new 
     * administrator of an existing institution, or for a new institution. In the latter case, the institution only actually gets 
     * created in the DB if the token is actually consumed via createIdPFromToken().
     * 
     * @param boolean $isByFedadmin   is the invitation token created for a federation admin (TRUE) or from an existing inst admin (FALSE)
     * @param array   $for            identifiers (typically email addresses) for which the invitation is created
     * @param mixed   $instIdentifier either an instance of the IdP class (for existing institutions to invite new admins) or a string (new institution - this is the inst name then)
     * @param string  $externalId     if the IdP to be created is related to an external DB entity, this parameter contains that ID
     * @param string  $country        if the institution is new (i.e. $inst is a string) this parameter needs to specify the federation of the new inst
     * @param string  $partType       the type of participant
     * @return mixed The function returns either the token (as string) or FALSE if something went wrong
     * @throws Exception
     */
    public function createTokens($isByFedadmin, $for, $instIdentifier, $externalId = 0, $country = 0, $partType = 0)
    {
        $level = ($isByFedadmin ? "FED" : "INST");
        $tokenList = [];
        foreach ($for as $oneDest) {
            $token = bin2hex(random_bytes(40));
            if ($instIdentifier instanceof IdP) {
                $this->databaseHandle->exec("INSERT INTO invitations (invite_fortype, invite_issuer_level, invite_dest_mail, invite_token,cat_institution_id) VALUES(?, ?, ?, ?, ?)", "ssssi", $instIdentifier->type, $level, $oneDest, $token, $instIdentifier->identifier);
                $tokenList[$token] = $oneDest;
            } else if (func_num_args() == 4) { // string name, but no country - new IdP with link to external DB
                // what country are we talking about?
                $cat = new CAT();
                $extinfo = $cat->getExternalDBEntityDetails($externalId);
                $extCountry = $extinfo['country'];
                $extType = $extinfo['type'];
                $this->databaseHandle->exec("INSERT INTO invitations (invite_fortype, invite_issuer_level, invite_dest_mail, invite_token,name,country, external_db_uniquehandle) VALUES(?, ?, ?, ?, ?, ?, ?)", "sssssss", $extType, $level, $oneDest, $token, $instIdentifier, $extCountry, $externalId);
                $tokenList[$token] = $oneDest;
            } else if (func_num_args() == 6) { // string name, and country set - whole new IdP
                $this->databaseHandle->exec("INSERT INTO invitations (invite_fortype, invite_issuer_level, invite_dest_mail, invite_token,name,country) VALUES(?, ?, ?, ?, ?, ?)", "ssssss", $partType, $level, $oneDest, $token, $instIdentifier, $country);
                $tokenList[$token] = $oneDest;
            } else {
                throw new Exception("The invitation is somehow ... wrong.");
            }
        }
        if (count($for) != count($tokenList)) {
            throw new Exception("Creation of a new token failed!");
        }
        return $tokenList;
    }

    /**
     * Retrieves all pending invitations for an institution or for a federation.
     * 
     * @param int $idpIdentifier the identifier of the institution. If not set, returns invitations for not-yet-created insts
     * @return array if idp_identifier is set: an array of strings (mail addresses); otherwise an array of tuples (country;name;mail)
     */
    public function listPendingInvitations($idpIdentifier = 0)
    {
        $retval = [];
        $invitations = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token , TIMESTAMPADD(DAY, 1, invite_created) as expiry
                                        FROM invitations 
                                        WHERE cat_institution_id " . ( $idpIdentifier != 0 ? "= $idpIdentifier" : "IS NULL") . " AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) AND used = 0");
        // SELECT -> resource, not boolean
        $this->loggerInstance->debug(4, "Retrieving pending invitations for " . ($idpIdentifier != 0 ? "IdP $idpIdentifier" : "IdPs awaiting initial creation" ) . ".\n");
        while ($invitationQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $invitations)) {
            $retval[] = ["country" => $invitationQuery->country, "name" => $invitationQuery->name, "mail" => $invitationQuery->invite_dest_mail, "token" => $invitationQuery->invite_token, "expiry" => $invitationQuery->expiry];
        }
        return $retval;
    }

    /** Retrieves all invitations which have expired in the last hour.
     * 
     * @return array of expired invitations
     */
    public function listRecentlyExpiredInvitations()
    {
        $retval = [];
        $invitations = $this->databaseHandle->exec("SELECT cat_institution_id, country, name, invite_issuer_level, invite_dest_mail, invite_token 
                                        FROM invitations 
                                        WHERE invite_created >= TIMESTAMPADD(HOUR, -25, NOW()) AND invite_created < TIMESTAMPADD(HOUR, -24, NOW()) AND used = 0");
        // SELECT -> resource, not boolean
        while ($expInvitationQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $invitations)) {
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
    public function listInstitutionsByAdmin($userid)
    {
        $returnarray = [];
        $institutions = $this->databaseHandle->exec("SELECT institution_id FROM ownership WHERE user_id = ? ORDER BY institution_id", "s", $userid);
        // SELECT -> resource, not boolean
        while ($instQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $institutions)) {
            $returnarray[] = $instQuery->institution_id;
        }
        return $returnarray;
    }
}