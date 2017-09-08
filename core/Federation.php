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
        $cohesionQuery = "SELECT profile_id FROM profile, institution WHERE profile.inst_id = institution.inst_id AND institution.country = ?";
        $profilesList = $this->databaseHandle->exec($cohesionQuery, "s", $this->identifier);
        $profilesArray = [];
        while ($result = mysqli_fetch_object($profilesList)) {
            $profilesArray[] = $result->profile_id;
        }
        foreach (\devices\Devices::listDevices() as $index => $deviceArray) {
            $countDevice = [];
            $countDevice['ADMIN'] = 0;
            $countDevice['SILVERBULLET'] = 0;
            $countDevice['USER'] = 0;
            foreach ($profilesArray as $profileNumber) {
                $deviceQuery = "SELECT downloads_admin, downloads_silverbullet, downloads_user FROM downloads WHERE device_id = ? AND profile_id = ?";
                $statsList = $this->frontendHandle->exec($deviceQuery, "si", $index, $profileNumber);
                while ($queryResult = mysqli_fetch_object($statsList)) {
                    $countDevice['ADMIN'] = $countDevice['ADMIN'] + $queryResult->downloads_admin;
                    $countDevice['SILVERBULLET'] = $countDevice['SILVERBULLET'] + $queryResult->downloads_silverbullet;
                    $countDevice['USER'] = $countDevice['USER'] + $queryResult->downloads_user;
                    $grossAdmin = $grossAdmin + $queryResult->downloads_admin;
                    $grossSilverbullet = $grossSilverbullet + $queryResult->downloads_silverbullet;
                    $grossUser = $grossUser + $queryResult->downloads_user;
                }
                $dataArray[$deviceArray['display']] = ["ADMIN" => $countDevice['ADMIN'], "SILVERBULLET" => $countDevice['SILVERBULLET'], "USER" => $countDevice['USER']];
            }
        }
        $dataArray["TOTAL"] = ["ADMIN" => $grossAdmin, "SILVERBULLET" => $grossSilverbullet, "USER" => $grossUser];
        return $dataArray;
    }

    public function updateFreshness() {
        // Federation is always fresh
    }

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
                $retstring .= "<federation id='$this->identifier' ts='" . date("Y-m-d") . "T" . date("H:i:s") . "'>\n";
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "  <device name='" . $device . "'>\n    <downloads group='admin'>" . $numbers['ADMIN'] . "</downloads>\n    <downloads group='managed_idp'>" . $numbers['SILVERBULLET'] . "</downloads>\n    <downloads group='user'>" . $numbers['USER'] . "</downloads>\n  </device>";
                }
                $retstring .= "<total>\n  <downloads group='admin'>" . $data['TOTAL']['ADMIN'] . "</downloads>\n  <downloads group='managed_idp'>" . $data['TOTAL']['SILVERBULLET'] . "</downloads>\n  <downloads group='user'>" . $data['TOTAL']['USER'] . "</downloads>\n</total>\n";
                $retstring .= "</federation>";
                break;
            default:
                return false;
        }

        return $retstring;
    }

    /**
     *
     * Constructs a Federation object.
     *
     * @param string $fedname - textual representation of the Federation object
     *        Example: "lu" (for Luxembourg)
     */
    public function __construct($fedname = "") {

        // initialise the superclass variables

        $this->databaseType = "INST";
        $this->entityOptionTable = "federation_option";
        $this->entityIdColumn = "federation_id";

        $cat = new CAT();
        if (!isset($cat->knownFederations[$fedname])) {
            throw new Exception("This federation is not known to the system!");
        }
        $this->identifier = $fedname;
        $this->name = $cat->knownFederations[$this->identifier];

        parent::__construct(); // we now have access to our database handle

        $this->frontendHandle = DBConnection::handle("FRONTEND");

        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?
                                            ORDER BY option_name", "FED", "s", $this->identifier);


        $this->attributes[] = array("name" => "internal:country",
            "lang" => NULL,
            "value" => $this->identifier,
            "level" => "FED",
            "row" => 0,
            "flag" => NULL);
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $ownerId Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @return int identifier of the new IdP
     */
    public function newIdP($ownerId, $level, $mail) {
        $this->databaseHandle->exec("INSERT INTO institution (country) VALUES('$this->identifier')");
        $identifier = $this->databaseHandle->lastID();

        if ($identifier == 0 || !$this->loggerInstance->writeAudit($ownerId, "NEW", "IdP $identifier")) {
            $text = "<p>Could not create a new " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_inst'] . "!</p>";
            echo $text;
            throw new Exception($text);
        }

        if ($ownerId != "PENDING") {
            $this->databaseHandle->exec("INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES(?,?,?,?)", "siss", $ownerId, $identifier, $level, $mail);
        }
        return $identifier;
    }

    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $activeOnly if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($activeOnly = 0) {
        // default query is:
        $allIDPs = $this->databaseHandle->exec("SELECT inst_id FROM institution
               WHERE country = '$this->identifier' ORDER BY inst_id");
        // the one for activeOnly is much more complex:
        if ($activeOnly) {
            $allIDPs = $this->databaseHandle->exec("SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->identifier' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        }

        $returnarray = [];
        while ($idpQuery = mysqli_fetch_object($allIDPs)) {
            $idp = new IdP($idpQuery->inst_id);
            $name = $idp->name;
            $idpInfo = ['entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp];
            $returnarray[$idp->identifier] = $idpInfo;
        }
        return $returnarray;
    }

    public function listFederationAdmins() {
        $returnarray = [];
        $query = "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = ?";
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = ?";
        }
        $userHandle = DBConnection::handle("USER"); // we need something from the USER database for a change
        $upperFed = strtoupper($this->identifier);
        $admins = $userHandle->exec($query, "s", $upperFed);

        while ($fedAdminQuery = mysqli_fetch_object($admins)) {
            $returnarray[] = $fedAdminQuery->user_id;
        }
        return $returnarray;
    }

    public function listExternalEntities($unmappedOnly) {
        $returnarray = [];

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $usedarray = [];
            $query = "SELECT id_institution AS id, country, inst_realm as realmlist, name AS collapsed_name, contact AS collapsed_contact FROM view_active_idp_institution WHERE country = ?";


            $externalHandle = DBConnection::handle("EXTERNAL");
            $externals = $externalHandle->exec($query, "s", $this->identifier);
            $syncstate = IdP::EXTERNAL_DB_SYNCSTATE_SYNCED;
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = ?", "i", $syncstate);
            $pendingInvite = $this->databaseHandle->exec("SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
            while ($alreadyUsedQuery = mysqli_fetch_object($alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }
            while ($pendingInviteQuery = mysqli_fetch_object($pendingInvite)) {
                if (!in_array($pendingInviteQuery->external_db_uniquehandle, $usedarray)) {
                    $usedarray[] = $pendingInviteQuery->external_db_uniquehandle;
                }
            }
            while ($externalQuery = mysqli_fetch_object($externals)) {
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
            usort($returnarray, array($this, "usort_institution"));
        }
        return $returnarray;
    }

    const UNKNOWN_IDP = -1;
    const AMBIGUOUS_IDP = -2;

    /**
     * 
     * @param mysqli_result $dbResult
     */
    private static function findCandidates(\mysqli_result $dbResult, &$country) {
        $retArray = [];
        while ($row = mysqli_fetch_object($dbResult)) {
            if (!in_array($row->inst_id, $retArray)) {
                $retArray[] = $row->inst_id;
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
     * @return IdP|int either the ID of the IdP in the system, or UNKNOWN_IDP or AMBIGUOUS_IDP
     */
    public static function determineIdPIdByRealm($realm) {
        $country = NULL;
        $candidatesExternalDb = Federation::UNKNOWN_IDP;
        $dbHandle = DBConnection::handle("INST");
        $realmSearchStringCat = "%@$realm";
        $candidateCatQuery = $dbHandle->exec("SELECT p.inst_id as inst_id, i.country as country FROM profile p, institution i WHERE p.inst_id = i.inst_id AND p.realm LIKE ?", "s", $realmSearchStringCat);
        $candidatesCat = Federation::findCandidates($candidateCatQuery, $country);

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED        
            $externalHandle = DBConnection::handle("EXTERNAL");
            $realmSearchStringDb1 = "$realm";
            $realmSearchStringDb2 = "%,$realm";
            $realmSearchStringDb3 = "$realm,%";
            $realmSearchStringDb4 = "%,$realm,%";
            $candidateExternalQuery = $externalHandle->exec("SELECT id_institution as inst_id, country FROM view_active_idp_institution WHERE inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ?", "ssss", $realmSearchStringDb1, $realmSearchStringDb2, $realmSearchStringDb3, $realmSearchStringDb4);
            $candidatesExternalDb = Federation::findCandidates($candidateExternalQuery, $country);
        }

        return ["CAT" => $candidatesCat, "EXTERNAL" => $candidatesExternalDb, "FEDERATION" => $country];
    }

    private function usort_institution($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    }

}
