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
 * This file contains Federation, IdP and Profile classes.
 * These should be split into separate files later.
 *
 * @package Developer
 */
/**
 * 
 */

namespace core;

use \Exception;

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

    const EXTERNAL_DB_SYNCSTATE_NOT_SYNCED = 0;
    const EXTERNAL_DB_SYNCSTATE_SYNCED = 1;
    const EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING = 2;

    /**
     *
     * @var integer synchronisation state with external database, if any
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
    public function __construct(int $instId) {
        $this->databaseType = "INST";
        parent::__construct(); // now databaseHandle and logging is available
        $this->entityOptionTable = "institution_option";
        $this->entityIdColumn = "institution_id";
        
        $this->identifier = $instId;

        $idp = $this->databaseHandle->exec("SELECT inst_id, country,external_db_syncstate FROM institution WHERE inst_id = $this->identifier");
        // SELECT -> returns resource, not boolean
        if (!$instQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $idp)) {
            throw new Exception("IdP $this->identifier not found in database!");
        }

        $this->federation = $instQuery->country;
        $this->externalDbSyncstate = $instQuery->external_db_syncstate;

        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?  
                                            ORDER BY option_name", "IdP");

        $this->attributes[] = ["name" => "internal:country",
            "lang" => NULL,
            "value" => $this->federation,
            "level" => "IdP",
            "row" => 0,
            "flag" => NULL];

        $this->name = $this->languageInstance->getLocalisedValue($this->getAttributes('general:instname'));
        $this->loggerInstance->debug(3, "--- END Constructing new IdP object ... ---\n");
    }

    /**
     * This function retrieves all registered profiles for this IdP from the database
     *
     * @param bool $activeOnly if and set to non-zero will cause listing of only those institutions which have some valid profiles defined.
     * @return array<AbstractProfile> list of Profiles of this IdP
     */
    public function listProfiles(bool $activeOnly = FALSE) {
        $query = "SELECT profile_id FROM profile WHERE inst_id = $this->identifier" . ($activeOnly ? " AND showtime = 1" : "");
        $allProfiles = $this->databaseHandle->exec($query);
        $returnarray = [];
        // SELECT -> resource, not boolean
        while ($profileQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allProfiles)) {
            $oneProfile = ProfileFactory::instantiate($profileQuery->profile_id, $this);
            $oneProfile->institution = $this->identifier;
            $returnarray[] = $oneProfile;
        }

        $this->loggerInstance->debug(4, "listProfiles: " . print_r($returnarray, true));
        return $returnarray;
    }

    const PROFILES_INCOMPLETE = 0;
    const PROFILES_CONFIGURED = 1;
    const PROFILES_SHOWTIME = 2;

    /**
     * looks through all the profiles of the inst and determines the highest prod-ready level among the profiles
     * @return int highest level of completeness of all the profiles of the inst
     */
    public function maxProfileStatus() {
        $allProfiles = $this->databaseHandle->exec("SELECT sufficient_config + showtime AS maxlevel FROM profile WHERE inst_id = $this->identifier ORDER BY maxlevel DESC LIMIT 1");
        // SELECT yields a resource, not a boolean
        while ($res = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allProfiles)) {
            return $res->maxlevel;
        }
        return self::PROFILES_INCOMPLETE;
    }

    /** This function retrieves an array of authorised users which can
     * manipulate this institution.
     * 
     * @return array owners of the institution; numbered array with members ID, MAIL and LEVEL
     */
    public function listOwners() {
        $returnarray = [];
        $admins = $this->databaseHandle->exec("SELECT user_id, orig_mail, blesslevel FROM ownership WHERE institution_id = $this->identifier ORDER BY user_id");
        // SELECT -> resource, not boolean
        while ($ownerQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $admins)) {
            $returnarray[] = ['ID' => $ownerQuery->user_id, 'MAIL' => $ownerQuery->orig_mail, 'LEVEL' => $ownerQuery->blesslevel];
        }
        return $returnarray;
    }

    /**
     * Primary owners are allowed to invite other (secondary) admins to the institution
     * 
     * @param string $user ID of a logged-in user
     * @return boolean TRUE if this user is an admin with FED-level blessing
     */
    public function isPrimaryOwner($user) {
        foreach ($this->listOwners() as $oneOwner) {
            if ($oneOwner['ID'] == $user && $oneOwner['LEVEL'] == "FED") {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * This function gets the profile count for a given IdP.
     * 
     * The count could be retreived from the listProfiles method
     * but this is less expensive.
     *
     * @return int profile count
     */
    public function profileCount() {
        $result = $this->databaseHandle->exec("SELECT profile_id FROM profile 
             WHERE inst_id = $this->identifier");
        // SELECT -> resource, not boolean
        return(mysqli_num_rows(/** @scrutinizer ignore-type */ $result));
    }

    /**
     * This function sets the timestamp of last modification of the child profiles to the current timestamp.
     * 
     * This is needed for installer caching: all installers which are on disk 
     * must be re-created if an attribute changes. This timestamp here
     * is used to determine if the installer on disk is still new enough.
     * 
     * @return void
     */
    public function updateFreshness() {
        // freshness is always defined for *Profiles*
        // IdP needs to update timestamp of all its profiles if an IdP-wide attribute changed
        $this->databaseHandle->exec("UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE inst_id = '$this->identifier'");
    }

    /**
     * Adds a new profile to this IdP.
     * 
     * Only creates the DB entry for the Profile. If you want to add attributes later, see Profile::addAttribute().
     *
     * @param string $type exactly "RADIUS" or "SILVERBULLET", all other values throw an Exception
     * @return AbstractProfile|NULL new Profile object if successful, or NULL if an error occured
     */
    public function newProfile(string $type) {
        $this->databaseHandle->exec("INSERT INTO profile (inst_id) VALUES($this->identifier)");
        $identifier = $this->databaseHandle->lastID();
        if ($identifier > 0) {
            switch ($type) {
                case AbstractProfile::PROFILETYPE_RADIUS:
                    return new ProfileRADIUS($identifier, $this);
                case AbstractProfile::PROFILETYPE_SILVERBULLET:
                    $theProfile = new ProfileSilverbullet($identifier, $this);
                    $theProfile->addSupportedEapMethod(new \core\common\EAP(\core\common\EAP::EAPTYPE_SILVERBULLET), 1);
                    $theProfile->setRealm($this->identifier . "-" . $theProfile->identifier . "." . strtolower($this->federation) . strtolower(CONFIG_CONFASSISTANT['SILVERBULLET']['realm_suffix']));
                    return $theProfile;
                default:
                    throw new Exception("This type of profile is unknown and can not be added.");
            }
        }
        return NULL;
    }

    /**
     * deletes the IdP and all its profiles
     * 
     * @return void
     */
    public function destroy() {
        common\Entity::intoThePotatoes();
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

the %s %s in your %s federation %s has been deleted from %s.

We thought you might want to know.

Best regards,

%s"), common\Entity::$nomenclature_inst, $this->name, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], strtoupper($fed->name), CONFIG['APPEARANCE']['productname'], CONFIG['APPEARANCE']['productname_long']);
            $user->sendMailToUser(sprintf(_("%s in your federation was deleted"), common\Entity::$nomenclature_inst), $message);
        }
        common\Entity::outOfThePotatoes();
    }

    /**
     * Performs a lookup in an external database to determine matching entities to this IdP. 
     * 
     * The business logic of this function is roaming consortium specific; if no match algorithm is known for the consortium, FALSE is returned.
     * 
     * @return mixed list of entities in external database that correspond to this IdP or FALSE if no consortium-specific matching function is defined
     */
    public function getExternalDBSyncCandidates() {
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $list = [];
            $usedarray = [];
            // extract all institutions from the country
            $externalHandle = DBConnection::handle("EXTERNAL");
            $lowerFed = strtolower($this->federation);
            $candidateList = $externalHandle->exec("SELECT id_institution AS id, name AS collapsed_name FROM view_active_idp_institution WHERE country = ?", "s", $lowerFed);
            $syncstate = self::EXTERNAL_DB_SYNCSTATE_SYNCED;
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution WHERE external_db_id IS NOT NULL AND external_db_syncstate = ?", "i", $syncstate);
            // SELECT -> resource, not boolean
            while ($alreadyUsedQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }

            // and split them into ID, LANG, NAME pairs (operating on a resource, not boolean)
            while ($candidateListQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $candidateList)) {
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

    /**
     * returns the state of sync with the external DB.
     * 
     * @return int
     */
    public function getExternalDBSyncState() {
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            return $this->externalDbSyncstate;
        }
        return self::EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING;
    }

    /**
     * Retrieves the external DB identifier of this institution. Returns FALSE if no ID is known.
     * 
     * @return string|boolean the external identifier; or FALSE if no external ID is known
     */
    public function getExternalDBId() {
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $idQuery = $this->databaseHandle->exec("SELECT external_db_id FROM institution WHERE inst_id = $this->identifier AND external_db_syncstate = " . self::EXTERNAL_DB_SYNCSTATE_SYNCED);
            // SELECT -> it's a resource, not a boolean
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $idQuery) == 0) {
                return FALSE;
            }
            $externalIdQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $idQuery);
            return $externalIdQuery->external_db_id;
        }
        return FALSE;
    }

    /**
     * Associates the external DB id with a CAT id
     * 
     * @param string $identifier the external DB id, which can be alpha-numeric
     * @return void
     */
    public function setExternalDBId(string $identifier) {
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $syncState = self::EXTERNAL_DB_SYNCSTATE_SYNCED;
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution WHERE external_db_id = ? AND external_db_syncstate = ?", "si", $identifier, $syncState);
            // SELECT -> resource, not boolean
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $alreadyUsed) == 0) {
                $this->databaseHandle->exec("UPDATE institution SET external_db_id = ?, external_db_syncstate = ? WHERE inst_id = ?", "sii", $identifier, $syncState, $this->identifier);
            }
        }
    }

    /**
     * removes the link between a CAT institution and the external DB
     * 
     * @return void
     */
    public function removeExternalDBId() {
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            if ($this->getExternalDBId() !== FALSE) {
                $syncState = self::EXTERNAL_DB_SYNCSTATE_NOT_SYNCED;
                $this->databaseHandle->exec("UPDATE institution SET external_db_id = NULL, external_db_syncstate = ? WHERE inst_id = ?", "ii", $syncState, $this->identifier);
            }
        }
    }

}
