<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php

/**
 * This file contains Federation, IdP and Profile classes.
 * These should be split into separate files later.
 *
 * @package Developer
 */
/**
 * 
 */
namespace core;

require_once(__DIR__."/Helper.php"); // TODO: get rid of this by wrapping all Helper functions into a class which can be auto-loaded

define("EXTERNAL_DB_SYNCSTATE_NOT_SYNCED", 0);
define("EXTERNAL_DB_SYNCSTATE_SYNCED", 1);
define("EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING", 2);

/**
 * This class represents an Identity Provider (IdP).
 * IdPs have properties of their own, and may have one or more Profiles. The
 * profiles can override the institution-wide properties.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class IdP extends EntityWithDBProperties {

    /**
     *
     * @var int synchronisation state with external database, if any
     */
    private $externalDbSyncstate;

    /**
     * The shortname of this IdP's federation
     * @var string 
     */
    public $federation;

    /**
     * Constructs an IdP object based on its details in the database.
     * Cannot be used to define a new IdP in the database! This happens via Federation::newIdP()
     *
     * @param int $instId the database row identifier
     */
    public function __construct($instId) {
        $this->databaseType = "INST";
        parent::__construct(); // now databaseHandle and logging is available
        $this->loggerInstance->debug(3, "--- BEGIN Constructing new IdP object ... ---\n");
        $this->entityOptionTable = "institution_option";
        $this->entityIdColumn = "institution_id";
        if (!is_numeric($instId)) {
            throw new Exception("Institutions are identified by an integer index!");
        }
        $this->identifier = (int) $instId;

        $idp = $this->databaseHandle->exec("SELECT inst_id, country,external_db_syncstate FROM institution WHERE inst_id = $this->identifier");
        if (!$instQuery = mysqli_fetch_object($idp)) {
            throw new Exception("IdP $this->identifier not found in database!");
        }

        $this->federation = $instQuery->country;
        $this->externalDbSyncstate = $instQuery->external_db_syncstate;

        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = $this->identifier  
                                            ORDER BY option_name", "IdP");

        $this->attributes[] = ["name" => "internal:country",
            "lang" => NULL,
            "value" => $this->federation,
            "level" => "IdP",
            "row" => 0,
            "flag" => NULL];

        $this->name = $this->languageInstance->getLocalisedValue($this->getAttributes('general:instname'), $this->languageInstance->getLang());
        $this->loggerInstance->debug(3, "--- END Constructing new IdP object ... ---\n");
    }

    /**
     * This function retrieves all registered profiles for this IdP from the database
     *
     * @return array List of Profiles of this IdP
     * @param int $activeOnly if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     */
    public function listProfiles($activeOnly = 0) {
        $query = "SELECT profile_id FROM profile WHERE inst_id = $this->identifier" . ($activeOnly ? " AND showtime = 1" : "");
        $allProfiles = $this->databaseHandle->exec($query);
        $returnarray = [];
        while ($profileQuery = mysqli_fetch_object($allProfiles)) {
            $oneProfile = ProfileFactory::instantiate($profileQuery->profile_id, $this);
            $oneProfile->institution = $this->identifier;
            $returnarray[] = $oneProfile;
        }

        $this->loggerInstance->debug(2, "listProfiles: " . print_r($returnarray, true));
        return $returnarray;
    }

    public function isOneProfileConfigured() {
        $allProfiles = $this->databaseHandle->exec("SELECT profile_id FROM profile WHERE inst_id = $this->identifier AND sufficient_config = 1");
        if (mysqli_num_rows($allProfiles) > 0) {
            return TRUE;
        }
        return FALSE;
    }

    public function isOneProfileShowtime() {
        $allProfiles = $this->databaseHandle->exec("SELECT profile_id FROM profile WHERE inst_id = $this->identifier AND showtime = 1");
        if (mysqli_num_rows($allProfiles) > 0) {
            return TRUE;
        }
        return FALSE;
    }

    public function getAllProfileStatusOverview() {
        $allProfiles = $this->databaseHandle->exec("SELECT status_dns, status_cert, status_reachability, status_TLS, last_status_check FROM profile WHERE inst_id = $this->identifier AND sufficient_config = 1");
        $returnarray = ['dns' => RADIUSTests::RETVAL_SKIPPED, 'cert' => L_OK, 'reachability' => RADIUSTests::RETVAL_SKIPPED, 'TLS' => RADIUSTests::RETVAL_SKIPPED, 'checktime' => NULL];
        while ($statusQuery = mysqli_fetch_object($allProfiles)) {
            if ($statusQuery->status_dns < $returnarray['dns']) {
                $returnarray['dns'] = $statusQuery->status_dns;
            }
            if ($statusQuery->status_reachability < $returnarray['reachability']) {
                $returnarray['reachability'] = $statusQuery->status_reachability;
            }
            if ($statusQuery->status_TLS < $returnarray['TLS']) {
                $returnarray['TLS'] = $statusQuery->status_TLS;
            }
            if ($statusQuery->status_cert < $returnarray['cert']) {
                $returnarray['cert'] = $statusQuery->status_cert;
            }
            if ($statusQuery->last_status_check > $returnarray['checktime']) {
                $returnarray['checktime'] = $statusQuery->last_status_check;
            }
        }
        return $returnarray;
    }

    /** This function retrieves an array of authorised users which can
     * manipulate this institution.
     * 
     * @return array owners of the institution; numbered array with members ID, MAIL and LEVEL
     */
    public function owner() {
        $returnarray = [];
        $admins = $this->databaseHandle->exec("SELECT user_id, orig_mail, blesslevel FROM ownership WHERE institution_id = $this->identifier ORDER BY user_id");
        while ($ownerQuery = mysqli_fetch_object($admins)) {
            $returnarray[] = ['ID' => $ownerQuery->user_id, 'MAIL' => $ownerQuery->orig_mail, 'LEVEL' => $ownerQuery->blesslevel];
        }
        return $returnarray;
    }

    /**
     * This function gets the profile count for a given IdP
     * The count could be retreived from the listProfiles method
     * but this is less expensive.
     *
     * @return int profile count
     */
    public function profileCount() {
        $result = $this->databaseHandle->exec("SELECT profile_id FROM profile 
             WHERE inst_id = $this->identifier");
        return(mysqli_num_rows($result));
    }

    /**
     * This function sets the timestamp of last modification of the child profiles to the current timestamp. This is needed
     * for installer caching: all installers which are on disk must be re-created if an attribute changes. This timestamp here
     * is used to determine if the installer on disk is still new enough.
     */
    public function updateFreshness() {
        // freshness is always defined for *Profiles*
        // IdP needs to update timestamp of all its profiles if an IdP-wide attribute changed
        $this->databaseHandle->exec("UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE inst_id = '$this->identifier'");
    }

    /**
     * Adds a new profile to this IdP.
     * Only creates the DB entry for the Profile. If you want to add attributes later, see Profile::addAttribute().
     *
     * @return object new Profile object if successful, or FALSE if an error occured
     */
    public function newProfile($type) {
        $this->databaseHandle->exec("INSERT INTO profile (inst_id) VALUES($this->identifier)");
        $identifier = $this->databaseHandle->lastID();

        if ($identifier > 0) {
            switch ($type) {
                case "RADIUS":
                    return new ProfileRADIUS($identifier, $this);
                case "SILVERBULLET":
                    $theProfile = new ProfileSilverbullet($identifier, $this);
                    $theProfile->addSupportedEapMethod(\core\EAP::EAPTYPE_SILVERBULLET, 1);
                    return $theProfile;
                default:
                    throw new Exception("This type of profile is unknown and can not be added.");
            }
        }
        return NULL;
    }

    /**
     * deletes the IdP and all its profiles
     */
    public function destroy() {
        /* delete all profiles */
        foreach ($this->listProfiles() as $profile) {
            $profile->destroy();
        }
        /* double-check that all profiles are gone */
        $profiles = $this->listProfiles();

        if (count($profiles) > 0) {
            throw new Exception("This IdP shouldn't have any profiles any more!");
        }

        $this->databaseHandle->exec("DELETE FROM ownership WHERE institution_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM institution_option WHERE institution_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM institution WHERE inst_id = $this->identifier");

        // notify federation admins

        $fed = new Federation($this->federation);
        foreach ($fed->listFederationAdmins() as $id) {
            $user = new User($id);
            $message = sprintf(_("Hi,

the Identity Provider %s in your %s federation %s has been deleted from %s.

We thought you might want to know.

Best regards,

%s"), $this->name, CONFIG['CONSORTIUM']['name'], strtoupper($fed->name), CONFIG['APPEARANCE']['productname'], CONFIG['APPEARANCE']['productname_long']);
            $user->sendMailToUser(_("IdP in your federation was deleted"), $message);
        }
        unset($this);
    }

    /**
     * Performs a lookup in an external database to determine matching entities to this IdP. The business logic of this function is
     * roaming consortium specific; if no match algorithm is known for the consortium, FALSE is returned.
     * 
     * @return mixed list of entities in external database that correspond to this IdP or FALSE if no consortium-specific matching function is defined
     */
    public function getExternalDBSyncCandidates() {
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $list = [];
            $usedarray = [];
            // extract all institutions from the country
            $externalHandle = DBConnection::handle("EXTERNAL");
            $lowerFed = strtolower($this->federation);
            $candidateList = $externalHandle->exec("SELECT id_institution AS id, name AS collapsed_name FROM view_active_idp_institution WHERE country = ?", "s", $lowerFed);

            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution WHERE external_db_id IS NOT NULL AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            while ($alreadyUsedQuery = mysqli_fetch_object($alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }

            // and split them into ID, LANG, NAME pairs
            while ($candidateListQuery = mysqli_fetch_object($candidateList)) {
                if (in_array($candidateListQuery->id, $usedarray)) {
                    continue;
                }
                $names = explode('#', $candidateListQuery->collapsed_name);
                foreach ($names as $name) {
                    $perlang = explode(': ', $name, 2);
                    $list[] = ["ID" => $candidateListQuery->id, "lang" => $perlang[0], "name" => $perlang[1]];
                }
            }
            // now see if any of the languages in CAT match any of those in the external DB
            $mynames = $this->getAttributes("general:instname");
            $matchingCandidates = [];
            foreach ($mynames as $onename) {
                foreach ($list as $listentry) {
                    if (($onename['lang'] == $listentry['lang'] || $onename['lang'] == "C") && $onename['value'] == $listentry['name'] && array_search($listentry['ID'], $matchingCandidates) === FALSE) {
                        $matchingCandidates[] = $listentry['ID'];
                    }
                }
            }
            return $matchingCandidates;
        }
        return FALSE;
    }

    public function getExternalDBSyncState() {
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            return $this->externalDbSyncstate;
        }
        return EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING;
    }

    /**
     * Retrieves the external DB identifier of this institution. Returns FALSE if no ID is known.
     * 
     * @return mixed the external identifier; or FALSE if no external ID is known
     */
    public function getExternalDBId() {
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $idQuery = $this->databaseHandle->exec("SELECT external_db_id FROM institution WHERE inst_id = $this->identifier AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            if (mysqli_num_rows($idQuery) == 0) {
                return FALSE;
            }
            $externalIdQuery = mysqli_fetch_object($idQuery);
            return $externalIdQuery->external_db_id;
        }
        return FALSE;
    }

    public function setExternalDBId($identifier) {
        $escapedIdentifier = $this->databaseHandle->escapeValue($identifier);
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution WHERE external_db_id = '$escapedIdentifier' AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);

            if (mysqli_num_rows($alreadyUsed) == 0) {
                $this->databaseHandle->exec("UPDATE institution SET external_db_id = '$escapedIdentifier', external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED . " WHERE inst_id = $this->identifier");
            }
        }
    }

}
