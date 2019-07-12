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
 * This file contains the Federation class.
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
 * This class represents an consortium federation.
 * 
 * It is semantically a country(!). Do not confuse this with a TLD; a federation
 * may span more than one TLD, and a TLD may be distributed across multiple federations.
 *
 * Example: a federation "fr" => "France" may also contain other TLDs which
 *              belong to France in spite of their different TLD
 * Example 2: Domains ending in .edu are present in multiple different
 *              federations
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class Federation extends EntityWithDBProperties {

    /**
     * the handle to the FRONTEND database (only needed for some stats access)
     * 
     * @var DBConnection
     */
    private $frontendHandle;

    /**
     * the top-level domain of the Federation
     * 
     * @var string
     */
    public $tld;

    /**
     * retrieve the statistics from the database in an internal array representation
     * 
     * @return array
     */
    private function downloadStatsCore() {
        $grossAdmin = 0;
        $grossUser = 0;
        $grossSilverbullet = 0;
        $dataArray = [];
        // first, find out which profiles belong to this federation
        $cohesionQuery = "SELECT downloads.device_id as dev_id, sum(downloads.downloads_user) as dl_user, sum(downloads.downloads_silverbullet) as dl_sb, sum(downloads.downloads_admin) as dl_admin FROM profile, institution, downloads WHERE profile.inst_id = institution.inst_id AND institution.country = ? AND profile.profile_id = downloads.profile_id group by device_id";
        $profilesList = $this->databaseHandle->exec($cohesionQuery, "s", $this->tld);
        $deviceArray = \devices\Devices::listDevices();
        // SELECT -> resource, no boolean
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $profilesList)) {
            if (isset($deviceArray[$queryResult->dev_id])) {
                $displayName = $deviceArray[$queryResult->dev_id]['display'];
            } else { // this device has stats, but doesn't exist in current config. We don't even know its display name, so display its raw representation
                $displayName = sprintf(_("(discontinued) %s"), $queryResult->dev_id);
            }
            $dataArray[$displayName] = ["ADMIN" => $queryResult->dl_admin, "SILVERBULLET" => $queryResult->dl_sb, "USER" => $queryResult->dl_user];
            $grossAdmin = $grossAdmin + $queryResult->dl_admin;
            $grossSilverbullet = $grossSilverbullet + $queryResult->dl_sb;
            $grossUser = $grossUser + $queryResult->dl_user;
        }
        $dataArray["TOTAL"] = ["ADMIN" => $grossAdmin, "SILVERBULLET" => $grossSilverbullet, "USER" => $grossUser];
        return $dataArray;
    }

    /**
     * when a Federation attribute changes, invalidate caches of all IdPs 
     * in that federation (e.g. change of fed logo changes the actual 
     * installers)
     * 
     * @return void
     */
    public function updateFreshness() {
        $idplist = $this->listIdentityProviders();
        foreach ($idplist as $idpDetail) {
            $idpDetail['instance']->updateFreshness();
        }
    }

    /**
     * gets the download statistics for the federation
     * @param string $format either as an html *table* or *XML* or *JSON*
     * @return string|array
     */
    public function downloadStats($format) {
        $data = $this->downloadStatsCore();
        $retstring = "";

        switch ($format) {
            case "table":
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "<tr><td>$device</td><td>" . $numbers['ADMIN'] . "</td><td>" . $numbers['SILVERBULLET'] . "</td><td>" . $numbers['USER'] . "</td></tr>";
                }
                $retstring .= "<tr><td><strong>TOTAL</strong></td><td><strong>" . $data['TOTAL']['ADMIN'] . "</strong></td><td><strong>" . $data['TOTAL']['SILVERBULLET'] . "</strong></td><td><strong>" . $data['TOTAL']['USER'] . "</strong></td></tr>";
                break;
            case "XML":
                // the calls to date() operate on current date, so there is no chance for a FALSE to be returned. Silencing scrutinizer.
                $retstring .= "<federation id='$this->tld' ts='" . /** @scrutinizer ignore-type */ date("Y-m-d") . "T" . /** @scrutinizer ignore-type */ date("H:i:s") . "'>\n";
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "  <device name='" . $device . "'>\n    <downloads group='admin'>" . $numbers['ADMIN'] . "</downloads>\n    <downloads group='managed_idp'>" . $numbers['SILVERBULLET'] . "</downloads>\n    <downloads group='user'>" . $numbers['USER'] . "</downloads>\n  </device>";
                }
                $retstring .= "<total>\n  <downloads group='admin'>" . $data['TOTAL']['ADMIN'] . "</downloads>\n  <downloads group='managed_idp'>" . $data['TOTAL']['SILVERBULLET'] . "</downloads>\n  <downloads group='user'>" . $data['TOTAL']['USER'] . "</downloads>\n</total>\n";
                $retstring .= "</federation>";
                break;
            case "array":
                return $data;
            default:
                throw new Exception("Statistics can be requested only in 'table' or 'XML' format!");
        }

        return $retstring;
    }

    /**
     *
     * Constructs a Federation object.
     *
     * @param string $fedname textual representation of the Federation object
     *                        Example: "lu" (for Luxembourg)
     */
    public function __construct($fedname) {

        // initialise the superclass variables

        $this->databaseType = "INST";
        $this->entityOptionTable = "federation_option";
        $this->entityIdColumn = "federation_id";

        $cat = new CAT();
        if (!isset($cat->knownFederations[$fedname])) {
            throw new Exception("This federation is not known to the system!");
        }
        $this->identifier = 0; // we do not use the numeric ID of a federation
        $this->tld = $fedname;
        $this->name = $cat->knownFederations[$this->tld];

        parent::__construct(); // we now have access to our database handle

        $handle = DBConnection::handle("FRONTEND");
        if ($handle instanceof DBConnection) {
            $this->frontendHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?
                                            ORDER BY option_name", "FED");


        $this->attributes[] = array("name" => "internal:country",
            "lang" => NULL,
            "value" => $this->tld,
            "level" => "FED",
            "row" => 0,
            "flag" => NULL);

        if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT_RADIUS'] != 'LOCAL' && CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT_SILVERBULLET'] == 'LOCAL') {
            // this instance exclusively does SB, so it is not necessary to ask
            // fed ops whether they want to enable it or not. So always add it
            // to the list of fed attributes
            $this->attributes[] = array("name" => "fed:silverbullet",
                "lang" => NULL,
                "value" => "on",
                "level" => "FED",
                "row" => 0,
                "flag" => NULL);
        }

        $this->idpListActive = [];
        $this->idpListAll = [];
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $ownerId       Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level         Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail          e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @param string $bestnameguess name of the IdP, if already known, in the best-match language
     * @return int identifier of the new IdP
     */
    public function newIdP($ownerId, $level, $mail = NULL, $bestnameguess = NULL) {
        $this->databaseHandle->exec("INSERT INTO institution (country) VALUES('$this->tld')");
        $identifier = $this->databaseHandle->lastID();

        if ($identifier == 0 || !$this->loggerInstance->writeAudit($ownerId, "NEW", "IdP $identifier")) {
            $text = "<p>Could not create a new " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_inst'] . "!</p>";
            echo $text;
            throw new Exception($text);
        }

        if ($ownerId != "PENDING") {
            if ($mail === NULL) {
                throw new Exception("New IdPs in a federation need a mail address UNLESS created by API without OwnerId");
            }
            $this->databaseHandle->exec("INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES(?,?,?,?)", "siss", $ownerId, $identifier, $level, $mail);
        }
        if ($bestnameguess === NULL) {
            $bestnameguess = "(no name yet, identifier $identifier)";
        }
        $admins = $this->listFederationAdmins();

        // notify the fed admins...

        foreach ($admins as $id) {
            $user = new User($id);
            /// arguments are: 1. nomenclature for "institution"
            //                 2. IdP name; 
            ///                3. consortium name (e.g. eduroam); 
            ///                4. federation shortname, e.g. "LU"; 
            ///                5. product name (e.g. eduroam CAT); 
            ///                6. product long name (e.g. eduroam Configuration Assistant Tool)
            $message = sprintf(_("Hi,

the invitation for the new %s %s in your %s federation %s has been used and the IdP was created in %s.

We thought you might want to know.

Best regards,

%s"), common\Entity::$nomenclature_inst, $bestnameguess, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], strtoupper($this->tld), CONFIG['APPEARANCE']['productname'], CONFIG['APPEARANCE']['productname_long']);
            $retval = $user->sendMailToUser(sprintf(_("%s in your federation was created"), common\Entity::$nomenclature_inst), $message);
            if ($retval === FALSE) {
                $this->loggerInstance->debug(2, "Mail to federation admin was NOT sent!\n");
            }
        }

        return $identifier;
    }

    private $idpListAll;
    private $idpListActive;

    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $activeOnly if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($activeOnly = 0) {
        // maybe we did this exercise before?
        if ($activeOnly != 0 && count($this->idpListActive) > 0) {
            return $this->idpListActive;
        }
        if ($activeOnly == 0 && count($this->idpListAll) > 0) {
            return $this->idpListAll;
        }
        // default query is:
        $allIDPs = $this->databaseHandle->exec("SELECT inst_id FROM institution
               WHERE country = '$this->tld' ORDER BY inst_id");
        // the one for activeOnly is much more complex:
        if ($activeOnly) {
            $allIDPs = $this->databaseHandle->exec("SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->tld' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        }

        $returnarray = [];
        // SELECT -> resource, not boolean
        while ($idpQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allIDPs)) {
            $idp = new IdP($idpQuery->inst_id);
            $name = $idp->name;
            $idpInfo = ['entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp];
            $returnarray[$idp->identifier] = $idpInfo;
        }
        if ($activeOnly != 0) { // we're only doing this once.
            $this->idpListActive = $returnarray;
        } else {
            $this->idpListAll = $returnarray;
        }
        return $returnarray;
    }

    /**
     * returns an array with information about the authorised administrators of the federation
     * 
     * @return array list of the admins of this federation
     */
    public function listFederationAdmins() {
        $returnarray = [];
        $query = "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = ?";
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = ?";
        }
        $userHandle = DBConnection::handle("USER"); // we need something from the USER database for a change
        $upperFed = strtoupper($this->tld);
        // SELECT -> resource, not boolean
        $admins = $userHandle->exec($query, "s", $upperFed);

        while ($fedAdminQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $admins)) {
            $returnarray[] = $fedAdminQuery->user_id;
        }
        return $returnarray;
    }

    public const EDUROAM_DB_TYPE_IDP = "1";
    public const EDUROAM_DB_TYPE_SP = "2";
    public const EDUROAM_DB_TYPE_IDPSP = "3";
    
    /**
     * cross-checks in the EXTERNAL customer DB which institutions exist there for the federations
     * 
     * @param bool   $unmappedOnly if set to TRUE, only returns those which do not have a known mapping to our internally known institutions
     * @param string $type         type of institution to search for, see constants above
     * @return array
     */
    public function listExternalEntities($unmappedOnly, $type) {
        $returnarray = [];

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $usedarray = [];
            $externalHandle = DBConnection::handle("EXTERNAL");
            $query = "SELECT id_institution AS id, country, inst_realm as realmlist, name AS collapsed_name, contact AS collapsed_contact FROM view_active_institution WHERE country = ? AND ( type = '".Federation::EDUROAM_DB_TYPE_IDPSP."' OR type = ? )";
            $externals = $externalHandle->exec($query, "ss", $this->tld, $type);
            $syncstate = IdP::EXTERNAL_DB_SYNCSTATE_SYNCED;
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = ?", "i", $syncstate);
            $pendingInvite = $this->databaseHandle->exec("SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
            // SELECT -> resource, no boolean
            while ($alreadyUsedQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }
            // SELECT -> resource, no boolean
            while ($pendingInviteQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $pendingInvite)) {
                if (!in_array($pendingInviteQuery->external_db_uniquehandle, $usedarray)) {
                    $usedarray[] = $pendingInviteQuery->external_db_uniquehandle;
                }
            }
            // was a SELECT query, so a resource and not a boolean
            while ($externalQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $externals)) {
                if (($unmappedOnly === TRUE) && (in_array($externalQuery->id, $usedarray))) {
                    continue;
                }
                $names = explode('#', $externalQuery->collapsed_name);
                // trim name list to current best language match
                $availableLanguages = [];
                foreach ($names as $name) {
                    $thislang = explode(': ', $name, 2);
                    $availableLanguages[$thislang[0]] = $thislang[1];
                }
                if (array_key_exists($this->languageInstance->getLang(), $availableLanguages)) {
                    $thelangauge = $availableLanguages[$this->languageInstance->getLang()];
                } else if (array_key_exists("en", $availableLanguages)) {
                    $thelangauge = $availableLanguages["en"];
                } else { // whatever. Pick one out of the list
                    $thelangauge = array_pop($availableLanguages);
                }
                $contacts = explode('#', $externalQuery->collapsed_contact);


                $mailnames = "";
                foreach ($contacts as $contact) {
                    $matches = [];
                    preg_match("/^n: (.*), e: (.*), p: .*$/", $contact, $matches);
                    if ($matches[2] != "") {
                        if ($mailnames != "") {
                            $mailnames .= ", ";
                        }
                        // extracting real names is nice, but the <> notation
                        // really gets screwed up on POSTs and HTML safety
                        // so better not do this; use only mail addresses
                        $mailnames .= $matches[2];
                    }
                }
                $returnarray[] = ["ID" => $externalQuery->id, "name" => $thelangauge, "contactlist" => $mailnames, "country" => $externalQuery->country, "realmlist" => $externalQuery->realmlist];
            }
            usort($returnarray, array($this, "usortInstitution"));
        }
        return $returnarray;
    }

    const UNKNOWN_IDP = -1;
    const AMBIGUOUS_IDP = -2;

    /**
     * for a MySQL list of institutions, find an institution or find out that
     * there is no single best match
     * 
     * @param \mysqli_result $dbResult the query object to work with
     * @param string         $country  used to return the country of the inst, if can be found out
     * @return int the identifier of the inst, or one of the special return values if unsuccessful
     */
    private static function findCandidates(\mysqli_result $dbResult, &$country) {
        $retArray = [];
        while ($row = mysqli_fetch_object($dbResult)) {
            if (!in_array($row->id, $retArray)) {
                $retArray[] = $row->id;
                $country = strtoupper($row->country);
            }
        }
        if (count($retArray) <= 0) {
            return Federation::UNKNOWN_IDP;
        }
        if (count($retArray) > 1) {
            return Federation::AMBIGUOUS_IDP;
        }

        return array_pop($retArray);
    }

    /**
     * If we are running diagnostics, our input from the user is the realm. We
     * need to find out which IdP this realm belongs to.
     * @param string $realm the realm to search for
     * @return array an array with two entries, CAT ID and DB ID, with either the respective ID of the IdP in the system, or UNKNOWN_IDP or AMBIGUOUS_IDP
     */
    public static function determineIdPIdByRealm($realm) {
        $country = NULL;
        $candidatesExternalDb = Federation::UNKNOWN_IDP;
        $dbHandle = DBConnection::handle("INST");
        $realmSearchStringCat = "%@$realm";
        $candidateCatQuery = $dbHandle->exec("SELECT p.profile_id as id, i.country as country FROM profile p, institution i WHERE p.inst_id = i.inst_id AND p.realm LIKE ?", "s", $realmSearchStringCat);
        // this is a SELECT returning a resource, not a boolean
        $candidatesCat = Federation::findCandidates(/** @scrutinizer ignore-type */ $candidateCatQuery, $country);

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED        
            $externalHandle = DBConnection::handle("EXTERNAL");
            $realmSearchStringDb1 = "$realm";
            $realmSearchStringDb2 = "%,$realm";
            $realmSearchStringDb3 = "$realm,%";
            $realmSearchStringDb4 = "%,$realm,%";
            $candidateExternalQuery = $externalHandle->exec("SELECT id_institution as id, country FROM view_active_idp_institution WHERE inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ?", "ssss", $realmSearchStringDb1, $realmSearchStringDb2, $realmSearchStringDb3, $realmSearchStringDb4);
            // SELECT -> resource, not boolean
            $candidatesExternalDb = Federation::findCandidates(/** @scrutinizer ignore-type */ $candidateExternalQuery, $country);
        }

        return ["CAT" => $candidatesCat, "EXTERNAL" => $candidatesExternalDb, "FEDERATION" => $country];
    }

    /**
     * helper function to sort institutions by their name
     * @param array $a an array with institution a's information
     * @param array $b an array with institution b's information
     * @return int the comparison result
     */
    private function usortInstitution($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    }

}
