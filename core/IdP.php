<?php

/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
require_once('Helper.php');
require_once('Profile.php');
require_once("CAT.php");
require_once("Options.php");
require_once("DBConnection.php");
require_once("RADIUSTests.php");

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
class IdP {

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $DB_TYPE = "INST";

    /**
     * This variable gets initialised with the known IdP attributes in the constructor. It never gets updated until the object
     * is destroyed. So if attributes change in the database, and IdP attributes are to be queried afterwards, the object
     * needs to be re-instantiated to have current values in this variable.
     * 
     * @var array of IdP attributes
     */
    private $priv_attributes;

    /**
     *
     * @var int synchronisation state with external database, if any
     */
    private $external_db_syncstate;
    
    /**
     * Constructs an IdP object based on its details in the database.
     * Cannot be used to define a new IdP in the database! This happens via Federation::newIdP()
     *
     * @param integer $i_id the database row identifier
     */
    public function __construct($i_id) {
        debug(3, "--- BEGIN Constructing new IdP object ... ---\n");
        
        $idp = DBConnection::exec(IdP::$DB_TYPE, "SELECT inst_id, country,external_db_syncstate FROM institution WHERE inst_id = $i_id");
        if (!$a = mysqli_fetch_object($idp)) {
            throw new Exception("IdP $i_id not found in database!");
        }

        $optioninstance = Options::instance();
        
        $this->identifier = $i_id;
        $this->federation = $a->country;

        $this->external_db_syncstate = $a->external_db_syncstate;
        // fetch attributes from DB and keep them in priv_attributes

        $IdPAttributes = DBConnection::exec(IdP::$DB_TYPE, "SELECT DISTINCT option_name,option_value, row FROM institution_option
              WHERE institution_id = $this->identifier  ORDER BY option_name");

        $this->priv_attributes = [];

        while ($a = mysqli_fetch_object($IdPAttributes)) {
            $lang = "";
            // decode base64 for files (respecting multi-lang)
            $optinfo = $optioninstance->optionType($a->option_name);
            $flag = $optinfo['flag'];

            if ($optinfo['type'] != "file") {
                $this->priv_attributes[] = ["name" => $a->option_name, "value" => $a->option_value, "level" => "IdP", "row" => $a->row, "flag" => $flag];
            } else {
                // suppress E_NOTICE on the following... we are testing *if*
                // we have a serialized value - so not having one is fine and
                // shouldn't throw E_NOTICE
                if (@unserialize($a->option_value) !== FALSE) { // multi-lang
                    $content = unserialize($a->option_value);
                    $lang = $content['lang'];
                    $content = $content['content'];
                } else { // single lang, direct content
                    $content = $a->option_value;
                }

                $content = base64_decode($content);

                $this->priv_attributes[] = ["name" => $a->option_name, "value" => ($lang == "" ? $content : serialize(['lang' => $lang, 'content' => $content])), "level" => "IdP", "row" => $a->row, "flag" => $flag];
            }
        }
        $this->priv_attributes[] = ["name" => "internal:country", 
                                         "value" => $this->federation, 
                                         "level" => "IdP", 
                                         "row" => 0, 
                                         "flag" => NULL];

        $this->name = getLocalisedValue($this->getAttributes('general:instname', 0, 0), CAT::get_lang());
        debug(3, "--- END Constructing new IdP object ... ---\n");
    }

    /**
     * This function retrieves all registered profiles for this IdP from the database
     *
     * @return array List of Profiles of this IdP
     * @param int $active_only if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     */
    public function listProfiles($active_only = 0) {
        $query = "SELECT profile_id FROM profile WHERE inst_id = $this->identifier";
        if ($active_only)
            $query .= " AND showtime = 1";
        $allProfiles = DBConnection::exec(IdP::$DB_TYPE, $query);
        $returnarray = [];
        while ($a = mysqli_fetch_object($allProfiles)) {
            $k = new Profile($a->profile_id, $this);
            $k->institution = $this->identifier;
            $returnarray[] = $k;
        }
        return $returnarray;
    }
    
    public function isOneProfileConfigured() {
        // migration phase: are there NULLs in the profile list sufficient_config column?
        // if so, run prepShowtime on all profiles
        $needTreatment = DBConnection::exec(IdP::$DB_TYPE, "SELECT profile_id FROM profile WHERE inst_id = $this->identifier AND sufficient_config IS NULL");
        if (mysqli_num_rows($needTreatment) > 0)
            foreach ($this->listProfiles() as $prof)
                $prof->prepShowtime();
       
        // now, just look in the DB
        $allProfiles = DBConnection::exec(IdP::$DB_TYPE, "SELECT profile_id FROM profile WHERE inst_id = $this->identifier AND sufficient_config = 1");
        if (mysqli_num_rows($allProfiles) > 0)
            return TRUE;
        else
            return FALSE;
    }

    public function isOneProfileShowtime() {
        $allProfiles = DBConnection::exec(IdP::$DB_TYPE, "SELECT profile_id FROM profile WHERE inst_id = $this->identifier AND showtime = 1");
        if (mysqli_num_rows($allProfiles) > 0)
            return TRUE;
        else
            return FALSE;
        
    }
    
    public function getAllProfileStatusOverview() {
        $allProfiles = DBConnection::exec(IdP::$DB_TYPE, "SELECT status_dns, status_cert, status_reachability, status_TLS, last_status_check FROM profile WHERE inst_id = $this->identifier AND sufficient_config = 1");
        $returnarray = ['dns' => RETVAL_SKIPPED, 'cert' => L_OK, 'reachability' => RETVAL_SKIPPED, 'TLS' => RETVAL_SKIPPED, 'checktime' => NULL];
        while ($a = mysqli_fetch_object($allProfiles)) {
            if ($a->status_dns < $returnarray['dns'])
                $returnarray['dns'] = $a->status_dns;
            if ($a->status_reachability < $returnarray['reachability'])
                $returnarray['reachability'] = $a->status_reachability;
            if ($a->status_TLS < $returnarray['TLS'])
                $returnarray['TLS'] = $a->status_TLS;
            if ($a->status_cert < $returnarray['cert'])
                $returnarray['cert'] = $a->status_cert;
            if ($a->last_status_check > $returnarray['checktime'])
                $returnarray['checktime'] = $a->last_status_check;
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
        $admins = DBConnection::exec(IdP::$DB_TYPE, "SELECT user_id, orig_mail, blesslevel FROM ownership WHERE institution_id = $this->identifier ORDER BY user_id");
        while ($a = mysqli_fetch_object($admins))
            $returnarray[] = ['ID' => $a->user_id, 'MAIL' => $a->orig_mail, 'LEVEL' => $a->blesslevel];
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
        $result = DBConnection::exec(IdP::$DB_TYPE, "SELECT profile_id FROM profile 
             WHERE inst_id = $this->identifier");
        return(mysqli_num_rows($result));
    }

    /**
     * This function returns the count of specific attributes in an IdP
     * This function will not retreive the values attributes (particularly important for large blobs),
     * it is mainly intended as a test for an attribute existance.
     *
     * @param string $option_name name of the attribute whose existence in the IdP is to be checked
     * @return int attribute count
     */
    public function isAttributeAvailable($option_name) {
        $option_name = DBConnection::escape_value(IdP::$DB_TYPE, $option_name);
        $result = DBConnection::exec(IdP::$DB_TYPE, "SELECT row FROM institution_option
              WHERE institution_id = $this->identifier AND option_name = '$option_name'");
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
        DBConnection::exec(IdP::$DB_TYPE, "UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE inst_id = '$this->identifier'");
    }

    /**
     * This function retrieves the IdP-wide attributes. If called with the optional parameter, only attribute values for the attribute
     * name in $option_name are retrieved; otherwise, all attributes are retrieved.
     *
     * @param string $option_name optionally, the name of the attribute that is to be retrieved
     * @return array of arrays of attributes which were set for this IdP
     */
    public function getAttributes($option_name = 0) {
        if ($option_name) {
            $returnarray = [];
            foreach ($this->priv_attributes as $the_attr)
                if ($the_attr['name'] == $option_name)
                    $returnarray[] = $the_attr;
            return $returnarray;
        }
        else {
            return $this->priv_attributes;
        }
    }

    /**
     * deletes all attributes in this profile except the _file ones, these are reported as array
     *
     * @return array list of row id's of file-based attributes which weren't deleted
     */
    public function beginFlushAttributes() {
        DBConnection::exec(IdP::$DB_TYPE, "DELETE FROM institution_option WHERE institution_id = $this->identifier AND option_name NOT LIKE '%_file'");
        $this->updateFreshness();
        $exec_q = DBConnection::exec(IdP::$DB_TYPE, "SELECT row FROM institution_option WHERE institution_id = $this->identifier");
        $return_array = [];
        while ($a = mysqli_fetch_object($exec_q))
            $return_array[$a->row] = "KILLME";
        return $return_array;
    }

    /**
     * after a beginFlushAttributes, deletes all attributes which are in the tobedeleted array
     *
     * @param array $tobedeleted array of database rows which are to be deleted
     */
    public function commitFlushAttributes($tobedeleted) {
        foreach (array_keys($tobedeleted) as $row) {
            DBConnection::exec(IdP::$DB_TYPE, "DELETE FROM institution_option WHERE institution_id = $this->identifier AND row = $row");
            $this->updateFreshness();
        }
    }

    /**
     * deletes all attributes of this IdP from the database
     */
    public function flushAttributes() {
        $this->commitFlushAttributes($this->beginFlushAttributes());
    }

    /**
     * Adds an attribute for the IdP instance into the database. Multiple instances of the same attribute are supported.
     *
     * @param string $attr_name Name of the attribute. This must be a well-known value from the profile_option_dict table in the DB.
     * @param mixed $attr_value Value of the attribute. Can be anything; will be stored in the DB as-is.
     */
    public function addAttribute($attr_name, $attr_value) {
        $attr_name = DBConnection::escape_value(IdP::$DB_TYPE, $attr_name);
        $attr_value = DBConnection::escape_value(IdP::$DB_TYPE, $attr_value);
        DBConnection::exec(IdP::$DB_TYPE, "INSERT INTO institution_option (institution_id, option_name, option_value) VALUES("
                . $this->identifier . ", '"
                . $attr_name . "', '"
                . $attr_value
                . "')");
        $this->updateFreshness();
    }

    /**
     * Adds a new profile to this IdP.
     * Only creates the DB entry for the Profile. If you want to add attributes later, see Profile::addAttribute().
     *
     * @return object new Profile object if successful, or FALSE if an error occured
     */
    public function newProfile() {
        DBConnection::exec(IdP::$DB_TYPE, "INSERT INTO profile (inst_id) VALUES($this->identifier)");
        $identifier = DBConnection::lastID(IdP::$DB_TYPE);

        if ($identifier > 0)
            return new Profile($identifier, $this);
        else
            return NULL;
    }

    /**
     * deletes the IdP and all its profiles
     */
    public function destroy() {
        /* delete all profiles */
        foreach ($this->listProfiles() as $profile)
            $profile->destroy();
        /* double-check that all profiles are gone */
        $profiles = $this->listProfiles();

        if (count($profiles) > 0)
            die("This IdP shouldn't have any profiles any more!");

        DBConnection::exec(IdP::$DB_TYPE, "DELETE FROM ownership WHERE institution_id = $this->identifier");
        DBConnection::exec(IdP::$DB_TYPE, "DELETE FROM institution_option WHERE institution_id = $this->identifier");
        DBConnection::exec(IdP::$DB_TYPE, "DELETE FROM institution WHERE inst_id = $this->identifier");

        // notify federation admins

        $fed = new Federation($this->federation);
        foreach ($fed->listFederationAdmins() as $id) {
            $user = new User($id);
            $message = sprintf(_("Hi,

the Identity Provider %s in your %s federation %s has been deleted from %s.

We thought you might want to know.

Best regards,

%s"), $this->name, Config::$CONSORTIUM['name'], strtoupper($fed->identifier), Config::$APPEARANCE['productname'], Config::$APPEARANCE['productname_long']);
            $user->sendMailToUser(_("IdP in your federation was deleted"), $message);
        }
        unset($this);
    }

    /**
     * Performs a lookup in an external database to determine matching entities to this IdP. The business logic of this function is
     * roaming consortium specific; if no match algorithm is known for the consortium, FALSE is returned.
     * 
     * @return array list of entities in external database that correspond to this IdP or FALSE if no consortium-specific matching function is defined
     */
    public function getExternalDBSyncCandidates() {
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $list = [];
            $usedarray = [];
            // extract all institutions from the country
            $candidate_list = DBConnection::exec("EXTERNAL", "SELECT id_institution AS id, name AS collapsed_name FROM view_active_idp_institution WHERE country = '" . strtolower($this->federation) . "'");

            $already_used = DBConnection::exec(IdP::$DB_TYPE, "SELECT DISTINCT external_db_id FROM institution WHERE external_db_id IS NOT NULL AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            while ($a = mysqli_fetch_object($already_used))
                $usedarray[] = $a->external_db_id;

            // and split them into ID, LANG, NAME pairs
            while ($a = mysqli_fetch_object($candidate_list)) {
                if (in_array($a->id, $usedarray))
                    continue;
                $names = explode('#', $a->collapsed_name);
                foreach ($names as $name) {
                    $perlang = explode(': ', $name, 2);
                    $list[] = ["ID" => $a->id, "lang" => $perlang[0], "name" => $perlang[1]];
                }
            }
            // now see if any of the languages in CAT match any of those in the external DB
            $mynames = $this->getAttributes("general:instname");
            $matching_candidates = [];
            foreach ($mynames as $onename)
                foreach ($list as $listentry) {
                    $unserialised = unserialize($onename['value']);
                    if (($unserialised['lang'] == $listentry['lang'] || $unserialised['lang'] == "C") && $unserialised['content'] == $listentry['name'])
                        if (array_search($listentry['ID'], $matching_candidates) === FALSE)
                            $matching_candidates[] = $listentry['ID'];
                }

            return $matching_candidates;
        }
        return FALSE;
    }

    public function getExternalDBSyncState() {
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            return $this->external_db_syncstate;
        }
        return EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING;
    }

    /**
     * Retrieves the external DB identifier of this institution. Returns FALSE if no ID is known.
     * 
     * @return int the external identifier; or FALSE if no external ID is known
     */
    public function getExternalDBId() {
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $id = DBConnection::exec(IdP::$DB_TYPE, "SELECT external_db_id FROM institution WHERE inst_id = $this->identifier AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            if (mysqli_num_rows($id) == 0) {
                return FALSE;
            } else {
                $a = mysqli_fetch_object($id);
                return $a->external_db_id;
            }
        }
        return FALSE;
    }

    /**
     * Fetches information from the external database about this IdP
     * 
     * @return array details about that institution. Array may be empty if entity is not synced
     */
    public function getExternalDBEntityDetails() {
        $external_id = $this->getExternalDBId();
        if ($external_id !== FALSE)
            return Federation::getExternalDBEntityDetails($external_id);
        else
            return [];
    }

    public function setExternalDBId($identifier) {
        $identifier = DBConnection::escape_value(IdP::$DB_TYPE, $identifier);
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $already_used = DBConnection::exec(IdP::$DB_TYPE, "SELECT DISTINCT external_db_id FROM institution WHERE external_db_id = '$identifier' AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);

            if (mysqli_num_rows($already_used) == 0)
                DBConnection::exec(IdP::$DB_TYPE, "UPDATE institution SET external_db_id = '$identifier', external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED . " WHERE inst_id = $this->identifier");
        }
    }

    /**
     * The shortname of this IdP's federation
     * @var string 
     */
    public $federation;

    /**
     * The row index of this IdP in the database
     * @var int 
     */
    public $identifier;

    /**
     * The name of this IdP in current locale.
     * @var string IdP's name
     */
    public $name;

}