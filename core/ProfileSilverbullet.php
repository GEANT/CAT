<?php

/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
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
 * This file contains the ProfileSilverbullet class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

/**
 * Silverbullet (marketed as "Managed IdP") is a RADIUS profile which 
 * corresponds directly to a built-in RADIUS server and CA. 
 * It provides all functions needed for a admin-side web interface where users
 * can be added and removed, and new devices be enabled.
 * 
 * When downloading a Silverbullet based profile, the profile includes per-user
 * per-device client certificates which can be immediately used to log into 
 * eduroam.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class ProfileSilverbullet extends AbstractProfile
{

    const SB_ACKNOWLEDGEMENT_REQUIRED_DAYS = 365;

    /**
     * terms and conditions for use of this functionality
     * 
     * @var string
     */
    public $termsAndConditions;

    /**
     * the displayed name of this feature
     */
    const PRODUCTNAME = \config\ConfAssistant::SILVERBULLET['subproduct_idp_name'];

    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject = NULL)
    {
        parent::__construct($profileId, $idpObject);

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];

        $tempMaxUsers = 200; // abolutely last resort fallback if no per-fed and no config option
// set to global config value

        if (isset(\config\ConfAssistant::SILVERBULLET['default_maxusers'])) {
            $tempMaxUsers = \config\ConfAssistant::SILVERBULLET['default_maxusers'];
        }
        $myInst = new IdP($this->institution);
        $myFed = new Federation($myInst->federation);
        $fedMaxusers = $myFed->getAttributes("fed:silverbullet-maxusers");
        if (isset($fedMaxusers[0])) {
            $tempMaxUsers = $fedMaxusers[0]['value'];
        }

// realm is automatically calculated, then stored in DB

        $this->realm = "opaquehash@$myInst->identifier-$this->identifier." . strtolower($myInst->federation) . \config\ConfAssistant::SILVERBULLET['realm_suffix'];
        $localValueIfAny = "";

// but there's some common internal attributes populated directly
        $internalAttributes = [
            "internal:profile_count" => $this->idpNumberOfProfiles,
            "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
            "internal:use_anon_outer" => FALSE,
            "internal:checkuser_outer" => TRUE,
            "internal:checkuser_value" => "anonymous",
            "internal:anon_local_value" => $localValueIfAny,
            "internal:silverbullet_maxusers" => $tempMaxUsers,
            "profile:production" => "on",
        ];

        // and we need to populate eap:server_name and eap:ca_file with the NRO-specific EAP information
        $silverbulletAttributes = [
            "eap:server_name" => "auth." . strtolower($myFed->tld) . \config\ConfAssistant::SILVERBULLET['server_suffix'],
        ];
        $temp = array_merge($this->addInternalAttributes($internalAttributes), $this->addInternalAttributes($silverbulletAttributes));
        $x509 = new \core\common\X509();
        $caHandle = fopen(dirname(__FILE__) . "/../config/SilverbulletServerCerts/" . strtoupper($myFed->tld) . "/root.pem", "r");
        if ($caHandle !== FALSE) {
            $cAFile = fread($caHandle, 16000000);
            foreach ($x509->splitCertificate($cAFile) as $oneCa) {
                $temp = array_merge($temp, $this->addInternalAttributes(['eap:ca_file' => $oneCa]));
            }
        }

        $tempArrayProfLevel = array_merge($this->addDatabaseAttributes(), $temp);

// now, fetch and merge IdP-wide attributes

        $this->attributes = $this->levelPrecedenceAttributeJoin($tempArrayProfLevel, $this->idpAttributes, "IdP");

        $this->privEaptypes = $this->fetchEAPMethods();

        $this->name = ProfileSilverbullet::PRODUCTNAME;

        $this->loggerInstance->debug(3, "--- END Constructing new Profile object ... ---\n");

        $product = \core\ProfileSilverbullet::PRODUCTNAME;
        $nameIdP = \config\ConfAssistant::CONSORTIUM['nomenclature_idp'];
        $nameConsortium = \config\ConfAssistant::CONSORTIUM['display_name'];
        $nameFed = \config\ConfAssistant::CONSORTIUM['nomenclature_federation'];
        $this->termsAndConditions = "<h2>Product Definition</h2>
        <p>$product outsources the technical setup of $nameConsortium $nameIdP functions to the $nameConsortium Operations Team. The system includes</p>
            <ul>
                <li>a web-based user management interface where user accounts and access credentials can be created and revoked (there is a limit to the number of active users)</li>
                <li>a technical infrastructure ('CA') which issues and revokes credentials</li>
                <li>a technical infrastructure ('RADIUS') which verifies access credentials and subsequently grants access to $nameConsortium</li>           
            </ul>
        <h2>User Account Liability</h2>
        <p>As an $nameConsortium $nameIdP administrator using this system, you are authorized to create user accounts according to your local $nameIdP policy. You are fully responsible for the accounts you issue and are the data controller for all user information you deposit in this system; the system is a data processor.</p>";
        $this->termsAndConditions .= "<p>Your responsibilities include that you</p>
        <ul>
            <li>only issue accounts to members of your $nameIdP, as defined by your local policy.</li>
            <li>must make sure that all accounts that you issue can be linked by you to actual human end users</li>
            <li>have to immediately revoke accounts of users when they leave or otherwise stop being a member of your $nameIdP</li>
            <li>will act upon notifications about possible network abuse by your users and will appropriately sanction them</li>
        </ul>
        <p>";
        $this->termsAndConditions .= "Failure to comply with these requirements may make your $nameFed act on your behalf, which you authorise, and will ultimately lead to the deletion of your $nameIdP (and all the users you create inside) in this system.";
        $this->termsAndConditions .= "</p>
        <h2>Privacy</h2>
        <p>With $product, we are necessarily storing personally identifiable information about the end users you create. While the actual human is only identifiable with your help, we consider all the user data as relevant in terms of privacy jurisdiction. Please note that</p>
        <ul>
            <li>You are the only one who needs to be able to make a link to the human behind the usernames you create. The usernames you create in the system have to be rich enough to allow you to make that identification step. Also consider situations when you are unavailable or leave the organisation and someone else needs to perform the matching to an individual.</li>
            <li>The identifiers we create in the credentials are not linked to the usernames you add to the system; they are randomly generated pseudonyms.</li>
            <li>Each access credential carries a different pseudonym, even if it pertains to the same username.</li>
            <li>If you choose to deposit users' email addresses in the system, you authorise the system to send emails on your behalf regarding operationally relevant events to the users in question (e.g. notification of nearing expiry dates of credentials, notification of access revocation).
        </ul>";
    }

    /**
     * Updates database with new installer location; NOOP because we do not
     * cache anything in Silverbullet
     * 
     * @param string $device         the device identifier string
     * @param string $path           the path where the new installer can be found
     * @param string $mime           the mime type of the new installer
     * @param int    $integerEapType the inter-representation of the EAP type that is configured in this installer
     * @return void
     * @throws Exception
     */
    public function updateCache($device, $path, $mime, $integerEapType, $openRoaming)
    {
        // caching is not supported in SB (private key in installers)
        // the following merely makes the "unused parameter" warnings go away
        // the FALSE in condition one makes sure it never gets executed
        if (FALSE || $device == "Macbeth" || $path == "heath" || $mime == "application/witchcraft" || $integerEapType == 0) {
            throw new Exception("FALSE is TRUE, and TRUE is FALSE! Hover through the browser and filthy code!");
        }
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param \core\common\EAP $type       The EAP Type, as defined in class EAP
     * @param int              $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     * @return void
     * @throws Exception
     */
    public function addSupportedEapMethod(\core\common\EAP $type, $preference)
    {
        // the parameters really should only list SB and with prio 1 - otherwise,
        // something fishy is going on
        if ($type->getIntegerRep() != \core\common\EAP::INTEGER_SILVERBULLET || $preference != 1) {
            throw new Exception("Silverbullet::addSupportedEapMethod was called for a non-SP EAP type or unexpected priority!");
        }
        parent::addSupportedEapMethod($type, 1);
    }

    /**
     * It's EAP-TLS and there is no point in anonymity
     * @param boolean $shallwe always FALSE
     * @return void
     * @throws Exception
     */
    public function setAnonymousIDSupport($shallwe)
    {
        // we don't do anonymous outer IDs in SB
        if ($shallwe === TRUE) {
            throw new Exception("Silverbullet: attempt to add anonymous outer ID support to a SB profile!");
        }
        $this->databaseHandle->exec("UPDATE profile SET use_anon_outer = 0 WHERE profile_id = $this->identifier");
    }

    /**
     * find out about the status of a given SB user; retrieves the info regarding all his tokens (and thus all his certificates)
     * @param int $userId the userid
     * @return array of invitationObjects
     */
    public function userStatus($userId)
    {
        $retval = [];
        $userrows = $this->databaseHandle->exec("SELECT `token` FROM `silverbullet_invitation` WHERE `silverbullet_user_id` = ? AND `profile_id` = ? ", "ii", $userId, $this->identifier);
        // SELECT -> resource, not boolean
        while ($returnedData = mysqli_fetch_object(/** @scrutinizer ignore-type */ $userrows)) {
            $retval[] = new SilverbulletInvitation($returnedData->token);
        }
        return $retval;
    }

    /**
     * finds out the expiry date of a given user
     * @param int $userId the numerical user ID of the user in question
     * @return string
     */
    public function getUserExpiryDate($userId)
    {
        $query = $this->databaseHandle->exec("SELECT expiry FROM silverbullet_user WHERE id = ? AND profile_id = ? ", "ii", $userId, $this->identifier);
        // SELECT -> resource, not boolean
        while ($returnedData = mysqli_fetch_object(/** @scrutinizer ignore-type */ $query)) {
            return $returnedData->expiry;
        }
    }

    /**
     * retrieves the authentication records from the RADIUS servers 
     * 
     * @param int $userId the numerical user ID of the user in question
     * @return array
     */
    public function getUserAuthRecords($userId)
    {
        // find out all certificate CNs belonging to the user, including expired and revoked ones
        $userData = $this->userStatus($userId);
        $certNames = [];
        foreach ($userData as $oneSlice) {
            foreach ($oneSlice->associatedCertificates as $oneCert) {
                $certNames[] = $oneCert->username;
            }
        }
        if (empty($certNames)) {
            return [];
        }
        $namesCondensed = "'" . implode("' OR username = '", $certNames) . "'";
        $serverHandles = DBConnection::handle("RADIUS");
        $returnarray = [];
        foreach ($serverHandles as $oneDbServer) {
            $query = $oneDbServer->exec("SELECT username, authdate, reply, callingid, operatorname FROM eduroamauth WHERE username = $namesCondensed ORDER BY authdate DESC");
            // SELECT -> resource, not boolean
            while ($returnedData = mysqli_fetch_object(/** @scrutinizer ignore-type */ $query)) {
                $returnarray[] = ["CN" => $returnedData->username, "TIMESTAMP" => $returnedData->authdate, "RESULT" => $returnedData->reply, "MAC" => $returnedData->callingid, "OPERATOR" => $returnedData->operatorname];
            }
        }
        usort($returnarray, function($one, $another) {
            return $one['TIMESTAMP'] < $another['TIMESTAMP'];
        });

        return $returnarray;
    }

    /**
     * sets the expiry date of a user to a new date of choice
     * @param int       $userId the username
     * @param \DateTime $date   the expiry date
     * @return void
     */
    public function setUserExpiryDate($userId, $date)
    {
        $query = "UPDATE silverbullet_user SET expiry = ? WHERE profile_id = ? AND id = ?";
        $theDate = $date->format("Y-m-d H:i:s");
        $this->databaseHandle->exec($query, "sii", $theDate, $this->identifier, $userId);
    }

    /**
     * lists all users of this SB profile
     * @return array
     */
    public function listAllUsers()
    {
        $userArray = [];
        $users = $this->databaseHandle->exec("SELECT `id`, `username` FROM `silverbullet_user` WHERE `profile_id` = ? ", "i", $this->identifier);
        // SELECT -> resource, not boolean
        while ($res = mysqli_fetch_object(/** @scrutinizer ignore-type */ $users)) {
            $userArray[$res->id] = $res->username;
        }
        return $userArray;
    }

    /**
     * get the user of this SB profile identified by ID
     * @param int $userId the user id
     * @return array
     */
    public function getUserById($userId)
    {
        $users = $this->databaseHandle->exec("SELECT `id`, `username` FROM `silverbullet_user` WHERE `profile_id` = ? AND `id` = ? ", "ii", $this->identifier, $userId);
        // SELECT -> resource, not boolean
        while ($res = mysqli_fetch_object(/** @scrutinizer ignore-type */ $users)) {
            return [$res->id => $res->username];
        }
        return [];
    }

    /**
     * get the user of this SB profile identified by Username
     * @param string $userName the username
     * @return array
     */
    public function getUserByName($userName)
    {
        $users = $this->databaseHandle->exec("SELECT `id`, `username` FROM `silverbullet_user` WHERE `profile_id` = ? AND `username` = ? ", "is", $this->identifier, $userName);
        // SELECT -> resource, not boolean
        while ($res = mysqli_fetch_object(/** @scrutinizer ignore-type */ $users)) {
            return [$res->id => $res->username];
        }
        return [];
    }

    /**
     * lists all users which are currently active (i.e. have pending invitations and/or valid certs)
     * @return array
     */
    public function listActiveUsers()
    {
        // users are active if they have a non-expired invitation OR a non-expired, non-revoked certificate
        $userCount = [];
        $users = $this->databaseHandle->exec("SELECT DISTINCT u.id AS usercount FROM silverbullet_user u, silverbullet_invitation i, silverbullet_certificate c "
                . "WHERE u.profile_id = ? "
                . "AND ( "
                . "( u.id = i.silverbullet_user_id AND i.expiry >= NOW() )"
                . "     OR"
                . "  ( u.id = c.silverbullet_user_id AND c.expiry >= NOW() AND c.revocation_status != 'REVOKED' ) "
                . ")", "i", $this->identifier);
        // SELECT -> resource, not boolean
        while ($res = mysqli_fetch_object(/** @scrutinizer ignore-type */ $users)) {
            $userCount[$res->usercount] = "ACTIVE";
        }
        return $userCount;
    }

    /**
     * adds a new user to the profile
     * 
     * @param string    $username the username
     * @param \DateTime $expiry   the expiry date
     * @return int row ID of the new user in the database
     */
    public function addUser($username, \DateTime $expiry)
    {
        $query = "INSERT INTO silverbullet_user (profile_id, username, expiry) VALUES(?,?,?)";
        $date = $expiry->format("Y-m-d H:i:s");
        $this->databaseHandle->exec($query, "iss", $this->identifier, $username, $date);
        return $this->databaseHandle->lastID();
    }

    /**
     * revoke all active certificates and pending invitations of a user
     * @param int $userId the username
     * @return boolean was the user found and deactivated?
     * @throws Exception
     */
    public function deactivateUser($userId)
    {
        // does the user exist and is active, anyway?
        $queryExisting = "SELECT id FROM silverbullet_user WHERE profile_id = $this->identifier AND id = ? AND expiry >= NOW()";
        $execExisting = $this->databaseHandle->exec($queryExisting, "i", $userId);
        // this is a SELECT, and won't return TRUE
        if (mysqli_num_rows(/** @scrutinizer ignore-type */ $execExisting) < 1) {
            return FALSE;
        }
        // set the expiry date of any still valid invitations to NOW()
        $query = "SELECT token FROM silverbullet_invitation WHERE profile_id = $this->identifier AND silverbullet_user_id = ? AND expiry >= NOW()";
        $exec = $this->databaseHandle->exec($query, "i", $userId);
        // SELECT -> resource, not boolean
        while ($result = mysqli_fetch_object(/** @scrutinizer ignore-type */ $exec)) {
            $invitation = new SilverbulletInvitation($result->token);
            $invitation->revokeInvitation();
        }
        // and revoke all certificates
        $query2 = "SELECT serial_number, ca_type FROM silverbullet_certificate WHERE profile_id = $this->identifier AND silverbullet_user_id = ? AND expiry >= NOW() AND revocation_status = 'NOT_REVOKED'";
        $exec2 = $this->databaseHandle->exec($query2, "i", $userId);
        // SELECT -> resource, not boolean
        while ($result = mysqli_fetch_object(/** @scrutinizer ignore-type */ $exec2)) {
            $certObject = new SilverbulletCertificate($result->serial_number, $result->ca_type);
            $certObject->revokeCertificate();
        }
        // and finally set the user expiry date to NOW(), too
        $query3 = "UPDATE silverbullet_user SET expiry = NOW() WHERE profile_id = $this->identifier AND id = ?";
        $ret = $this->databaseHandle->exec($query3, "i", $userId);
        // this is an UPDATE, and always returns TRUE. Need to tell Scrutinizer all about it.
        if ($ret === TRUE) {
            return TRUE;
        } else {
            throw new Exception("The UPDATE statement could not be executed successfully.");
        }
    }

    /**
     * delete the user in question, including all expired and revoked certificates
     * @param int $userId the username
     * @return boolean was the user deleted?
     */
    public function deleteUser($userId)
    {
        // do we really not have any auth records that may need to be tied to this user?
        if (count($this->getUserAuthRecords($userId)) > 0) {
            return false;
        }
        // find and delete all certificates
        $certQuery = "DELETE FROM silverbullet_certificate WHERE profile_id = $this->identifier AND silverbullet_user_id = ?";
        $this->databaseHandle->exec($certQuery, "i", $userId);
        // find and delete obsolete invitation token track record
        $tokenQuery = "DELETE FROM silverbullet_invitation WHERE profile_id = $this->identifier AND silverbullet_user_id = ?";
        $this->databaseHandle->exec($tokenQuery, "i", $userId);
        // delete user record itself
        $userQuery = "DELETE FROM silverbullet_user WHERE profile_id = $this->identifier AND id = ?";
        $this->databaseHandle->exec($userQuery, "i", $userId);
    }

    /**
     * updates the last_ack for all users (invoked when the admin claims to have re-verified continued eligibility of all users)
     * 
     * @return void
     */
    public function refreshEligibility()
    {
        $query = "UPDATE silverbullet_user SET last_ack = NOW() WHERE profile_id = ?";
        $this->databaseHandle->exec($query, "i", $this->identifier);
    }
}