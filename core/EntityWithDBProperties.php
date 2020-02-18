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

use Exception;

/**
 * This class represents an Entity with properties stored in the DB.
 * IdPs have properties of their own, and may have one or more Profiles. The
 * profiles can override the institution-wide properties.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 */
abstract class EntityWithDBProperties extends \core\common\Entity
{

    /**
     * This variable gets initialised with the known IdP attributes in the constructor. It never gets updated until the object
     * is destroyed. So if attributes change in the database, and IdP attributes are to be queried afterwards, the object
     * needs to be re-instantiated to have current values in this variable.
     * 
     * @var array of entity's attributes
     */
    protected $attributes;

    /**
     * The database to query for attributes regarding this entity
     * 
     * @var string DB type
     */
    protected $databaseType;

    /**
     * This variable contains the name of the table that stores the entity's options
     * 
     * @var string DB table name
     */
    protected $entityOptionTable;

    /**
     * column name to find entity in that table
     * 
     * @var string DB column name of entity
     */
    protected $entityIdColumn;

    /**
     * We need database access. Be sure to instantiate the singleton, and then
     * use its instance (rather than always accessing everything statically)
     * 
     * @var DBConnection the instance of the default database we talk to usually
     */
    protected $databaseHandle;

    /**
     * the unique identifier of this entity instance
     * refers to the integer row name in the DB -> int; Federation has no own
     * DB, so the identifier is of no use there -> use Fedearation->$tld
     * 
     * @var integer identifier of the entity instance
     */
    public $identifier;

    /**
     * the name of the entity in the current locale
     * 
     * @var string
     */
    public $name;

    /**
     * The constructor initialises the entity. Since it has DB properties,
     * this means the DB connection is set up for it.
     * 
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        // we are called after the sub-classes have declared their default
        // database instance in $databaseType
        $handle = DBConnection::handle($this->databaseType);
        if ($handle instanceof DBConnection) {
            $this->databaseHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
        $this->attributes = [];
    }

    /**
     * How is the object identified in the database?
     * @return string|int
     * @throws Exception
     */
    private function getRelevantIdentifier()
    {
        switch (get_class($this)) {
            case "core\ProfileRADIUS":
            case "core\ProfileSilverbullet":
            case "core\IdP":
            case "core\DeploymentManaged":
                return $this->identifier;
            case "core\Federation":
                return $this->tld;
            case "core\User":
                return $this->userName;
            default:
                throw new Exception("Operating on a class where we don't know the relevant identifier in the DB - " . get_class($this) . "!");
        }
    }

    /**
     * This function retrieves the entity's attributes. 
     * 
     * If called with the optional parameter, only attribute values for the attribute
     * name in $optionName are retrieved; otherwise, all attributes are retrieved.
     * The retrieval is in-memory from the internal attributes class member - no
     * DB callback, so changes in the database during the class instance lifetime
     * are not considered.
     *
     * @param string $optionName optionally, the name of the attribute that is to be retrieved
     * @return array of arrays of attributes which were set for this IdP
     */
    public function getAttributes(string $optionName = NULL)
    {
        if ($optionName !== NULL) {
            $returnarray = [];
            foreach ($this->attributes as $theAttr) {
                if ($theAttr['name'] == $optionName) {
                    $returnarray[] = $theAttr;
                }
            }
            return $returnarray;
        }
        return $this->attributes;
    }

    /**
     * deletes all attributes in this profile except the _file ones, these are reported as array
     *
     * @param string $extracondition a condition to append to the deletion query. RADIUS Profiles have eap-level or device-level options which shouldn't be purged; this can be steered in the overriding function.
     * @return array list of row id's of file-based attributes which weren't deleted
     */
    public function beginFlushAttributes($extracondition = "")
    {
        $quotedIdentifier = (!is_int($this->getRelevantIdentifier()) ? "\"" : "") . $this->getRelevantIdentifier() . (!is_int($this->getRelevantIdentifier()) ? "\"" : "");
        $this->databaseHandle->exec("DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND option_name NOT LIKE '%_file' $extracondition");
        $this->updateFreshness();
        $execFlush = $this->databaseHandle->exec("SELECT row FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier $extracondition");
        $returnArray = [];
        // SELECT always returns a resourse, never a boolean
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $execFlush)) {
            $returnArray[$queryResult->row] = "KILLME";
        }
        return $returnArray;
    }

    /**
     * after a beginFlushAttributes, deletes all attributes which are in the tobedeleted array.
     *
     * @param array $tobedeleted array of database rows which are to be deleted
     * @return void
     */
    public function commitFlushAttributes(array $tobedeleted)
    {
        $quotedIdentifier = (!is_int($this->getRelevantIdentifier()) ? "\"" : "") . $this->getRelevantIdentifier() . (!is_int($this->getRelevantIdentifier()) ? "\"" : "");
        foreach (array_keys($tobedeleted) as $row) {
            $this->databaseHandle->exec("DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND row = $row");
            $this->updateFreshness();
        }
    }

    /**
     * deletes all attributes of this entity from the database
     * 
     * @return void
     */
    public function flushAttributes()
    {
        $this->commitFlushAttributes($this->beginFlushAttributes());
    }

    /**
     * Adds an attribute for the entity instance into the database. Multiple instances of the same attribute are supported.
     *
     * @param string $attrName  Name of the attribute. This must be a well-known value from the profile_option_dict table in the DB.
     * @param string $attrLang  language of the attribute. Can be NULL.
     * @param mixed  $attrValue Value of the attribute. Can be anything; will be stored in the DB as-is.
     * @return void
     */
    public function addAttribute($attrName, $attrLang, $attrValue)
    {
        $relevantId = $this->getRelevantIdentifier();
        $identifierType = (is_int($relevantId) ? "i" : "s");
        $this->databaseHandle->exec("INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_lang, option_value) VALUES(?,?,?,?)", $identifierType . "sss", $relevantId, $attrName, $attrLang, $attrValue);
        $this->updateFreshness();
    }

    /**
     * retrieve attributes from a database. Only does SELECT queries.
     * @param string $query sub-classes set the query to execute to get to the options
     * @param string $level the retrieved options get flagged with this "level" identifier
     * @return array the attributes in one array
     * @throws Exception
     */
    protected function retrieveOptionsFromDatabase($query, $level)
    {
        if (substr($query, 0, 6) != "SELECT") {
            throw new Exception("This function only operates with SELECT queries!");
        }
        $optioninstance = Options::instance();
        $tempAttributes = [];
        $relevantId = $this->getRelevantIdentifier();
        $attributeDbExec = $this->databaseHandle->exec($query, is_int($relevantId) ? "i" : "s", $relevantId);
        if (empty($attributeDbExec)) {
            return $tempAttributes;
        }
        // with SELECTs, we always operate on a resource, not a boolean
        while ($attributeQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $attributeDbExec)) {
            $optinfo = $optioninstance->optionType($attributeQuery->option_name);
            $flag = $optinfo['flag'];
            $decoded = $attributeQuery->option_value;
            // file attributes always get base64-decoded.
            if ($optinfo['type'] == 'file') {
                $decoded = base64_decode($decoded);
            }
            $tempAttributes[] = ["name" => $attributeQuery->option_name, "lang" => $attributeQuery->option_lang, "value" => $decoded, "level" => $level, "row" => $attributeQuery->row, "flag" => $flag];
        }
        return $tempAttributes;
    }

    /**
     * Retrieves data from the underlying tables, for situations where instantiating the IdP or Profile object is inappropriate
     * 
     * @param string $table institution_option or profile_option
     * @param int    $row   rowindex
     * @return string|boolean the data, or FALSE if something went wrong
     */
    public static function fetchRawDataByIndex($table, $row)
    {
        // only for select tables!
        switch ($table) {
            case "institution_option":
            // fall-through intended
            case "profile_option":
            // fall-through intended
            case "federation_option":
                break;
            default:
                return FALSE;
        }
        $handle = DBConnection::handle("INST");
        $blobQuery = $handle->exec("SELECT option_value from $table WHERE row = $row");
        // SELECT -> returns resource, not boolean
        $dataset = mysqli_fetch_row(/** @scrutinizer ignore-type */ $blobQuery);
        return $dataset[0] ?? FALSE;
    }

    /**
     * Checks if a raw data pointer is public data (return value FALSE) or if 
     * yes who the authorised admins to view it are (return array of user IDs)
     * 
     * @param string $table which database table is this about
     * @param int    $row   row index of the table
     * @return mixed FALSE if the data is public, an array of owners of the data if it is NOT public
     */
    public static function isDataRestricted($table, $row)
    {
        if ($table != "institution_option" && $table != "profile_option" && $table != "federation_option" && $table != "user_options") {
            return []; // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
        }
        // we need to create our own DB handle as this is a static method
        $handle = DBConnection::handle("INST");
        switch ($table) {
            case "profile_option": // both of these are similar
                $columnName = "profile_id";
            // fall-through intended
            case "institution_option":
                $blobId = -1;
                $columnName = $columnName ?? "institution_id";
                $blobQuery = $handle->exec("SELECT $columnName as id from $table WHERE row = ?", "i", $row);
                // SELECT always returns a resourse, never a boolean
                while ($idQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $blobQuery)) { // only one row
                    $blobId = $idQuery->id;
                }
                if ($blobId == -1) {
                    return []; // err on the side of caution: we did not find any data. It's a severe error, but not fatal. Nobody owns non-existent data.
                }

                if ($table == "profile_option") { // is the profile in question public?
                    $profile = ProfileFactory::instantiate($blobId);
                    if ($profile->readinessLevel() == AbstractProfile::READINESS_LEVEL_SHOWTIME) { // public data
                        return FALSE;
                    }
                    // okay, so it's NOT public. prepare to return the owner
                    $inst = new IdP($profile->institution);
                } else { // does the IdP have at least one public profile?
                    $inst = new IdP($blobId);
                    // if at least one of the profiles belonging to the inst is public, the data is public
                    if ($inst->maxProfileStatus() == IdP::PROFILES_SHOWTIME) { // public data
                        return FALSE;
                    }
                }
                // okay, so it's NOT public. return the owner
                return $inst->listOwners();
            case "federation_option":
                // federation metadata is always public
                return FALSE;
            // user options are never public
            case "user_options":
                return [];
            default:
                return []; // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
        }
    }

    /**
     * join new attributes to existing ones, but only if not already defined on
     * a different level in the existing set
     * 
     * @param array  $existing the already existing attributes
     * @param array  $new      the new set of attributes
     * @param string $newlevel the level of the new attributes
     * @return array the new set of attributes
     */
    protected function levelPrecedenceAttributeJoin($existing, $new, $newlevel)
    {
        foreach ($new as $attrib) {
            $ignore = "";
            foreach ($existing as $approvedAttrib) {
                if (($attrib["name"] == $approvedAttrib["name"] && $approvedAttrib["level"] != $newlevel) && ($approvedAttrib["name"] != "device-specific:redirect")) {
                    $ignore = "YES";
                }
            }
            if ($ignore != "YES") {
                $existing[] = $attrib;
            }
        }
        return $existing;
    }

    /**
     * when options in the DB change, this can mean generated installers become stale. sub-classes must define whether this is the case for them
     * 
     * @return void
     */
    abstract public function updateFreshness();
}