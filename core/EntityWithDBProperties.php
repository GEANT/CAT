<?php

/* * ********************************************************************************
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
require_once("Entity.php");

/**
 * This class represents an Entity with properties stored in the DB.
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
abstract class EntityWithDBProperties extends Entity {

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
     * Federations are identified by their TLD -> string
     * everything else has an integer row name in the DB -> int
     * 
     * @var int,string identifier of the entity instance
     */
    public $identifier;

    /**
     * the name of the entity in the current locale
     */
    public $name;

    public function __construct() {
        parent::__construct();
        // we are called after the sub-classes have declared their default
        // databse instance in $databaseType
        $this->databaseHandle = DBConnection::handle($this->databaseType);
    }

    /**
     * This function retrieves the IdP-wide attributes. If called with the optional parameter, only attribute values for the attribute
     * name in $optionName are retrieved; otherwise, all attributes are retrieved.
     *
     * @param string $optionName optionally, the name of the attribute that is to be retrieved
     * @return array of arrays of attributes which were set for this IdP
     */
    public function getAttributes($optionName = 0) {
        if ($optionName) {
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
     * @return array list of row id's of file-based attributes which weren't deleted
     */
    public function beginFlushAttributes() {
        $quotedIdentifier = (!is_int($this->identifier) ? "\"" : "") . $this->identifier . (!is_int($this->identifier) ? "\"" : "");
        $this->databaseHandle->exec("DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND option_name NOT LIKE '%_file'");
        $this->updateFreshness();
        $execFlush = $this->databaseHandle->exec("SELECT row FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier");
        $returnArray = [];
        while ($queryResult = mysqli_fetch_object($execFlush)) {
            $returnArray[$queryResult->row] = "KILLME";
        }
        return $returnArray;
    }

    /**
     * after a beginFlushAttributes, deletes all attributes which are in the tobedeleted array
     *
     * @param array $tobedeleted array of database rows which are to be deleted
     */
    public function commitFlushAttributes($tobedeleted) {
        $quotedIdentifier = (!is_int($this->identifier) ? "\"" : "") . $this->identifier . (!is_int($this->identifier) ? "\"" : "");
        foreach (array_keys($tobedeleted) as $row) {
            $this->databaseHandle->exec("DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND row = $row");
            $this->updateFreshness();
        }
    }

    /**
     * deletes all attributes of this entity from the database
     */
    public function flushAttributes() {
        $this->commitFlushAttributes($this->beginFlushAttributes());
    }

    /**
     * Adds an attribute for the entity instance into the database. Multiple instances of the same attribute are supported.
     *
     * @param string $attrName Name of the attribute. This must be a well-known value from the profile_option_dict table in the DB.
     * @param mixed $attrValue Value of the attribute. Can be anything; will be stored in the DB as-is.
     */
    public function addAttribute($attrName, $attrValue) {
        $quotedIdentifier = (!is_int($this->identifier) ? "\"" : "") . $this->identifier . (!is_int($this->identifier) ? "\"" : "");
        $escapedAttrName = $this->databaseHandle->escapeValue($attrName);
        $escapedAttrValue = $this->databaseHandle->escapeValue($attrValue);
        $this->databaseHandle->exec("INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_value) VALUES("
                . $quotedIdentifier . ", '"
                . $escapedAttrName . "', '"
                . $escapedAttrValue
                . "')");
        $this->updateFreshness();
    }

    /**
     * files are base64-encoded and could be multi-language tagged. This function properly tears these file attributes apart
     * @param string $optionContent a string; either base64 string or an array with a language tag and base64
     * @return array an array with indexes lang and content. lang can be empty; content is base64-decoded
     */
    protected function decodeFileAttribute($optionContent) {
        // suppress E_NOTICE on the following... we are testing *if*
        // we have a serialized value - so not having one is fine and
        // shouldn't throw E_NOTICE
        if (@unserialize($optionContent) !== FALSE) { // multi-lang
            $tempContent = unserialize($optionContent);
            return ["lang" => $tempContent['lang'], "content" => base64_decode($tempContent['content'])];
        }
        // single lang, direct content
        return ["lang" => "", "content" => base64_decode($optionContent)];
    }

    /**
     * retrieve attributes from a database.
     * @param string $query sub-classes set the query to execute to get to the options
     * @param string $level the retrieved options get flagged with this "level" identifier
     * @return array the attributes in one array
     */
    protected function retrieveOptionsFromDatabase($query, $level) {
        $optioninstance = Options::instance();
        $tempAttributes = [];
        $attributeDbExec = $this->databaseHandle->exec($query);

        while ($attributeQuery = mysqli_fetch_object($attributeDbExec)) {
            // decode base64 for files (respecting multi-lang)
            $optinfo = $optioninstance->optionType($attributeQuery->option_name);
            $flag = $optinfo['flag'];

            if ($optinfo['type'] != "file") {
                $tempAttributes[] = ["name" => $attributeQuery->option_name, "value" => $attributeQuery->option_value, "level" => $level, "row" => $attributeQuery->row, "flag" => $flag];
            } else {
                $decodedAttribute = $this->decodeFileAttribute($attributeQuery->option_value);
                $tempAttributes[] = ["name" => $attributeQuery->option_name, "value" => ($decodedAttribute['lang'] == "" ? $decodedAttribute['content'] : serialize($decodedAttribute)), "level" => $level, "row" => $attributeQuery->row, "flag" => $flag];
            }
        }
        return $tempAttributes;
    }

    /**
     * Retrieves data from the underlying tables, for situations where instantiating the IdP or Profile object is inappropriate
     * 
     * @param string $table institution_option or profile_option
     * @param string $row rowindex
     * @return boolean
     */
    public static function fetchRawDataByIndex($table, $row) {
        // only for select tables!
        if ($table != "institution_option" && $table != "profile_option" && $table != "federation_option") {
            return FALSE;
        }
        if (!is_numeric($row)) {
            return FALSE;
        }

        $handle = DBConnection::handle("INST");
        $blobQuery = $handle->exec("SELECT option_value from $table WHERE row = $row");
        while ($returnedData = mysqli_fetch_object($blobQuery)) {
            $blob = $returnedData->option_value;
        }
        if (!isset($blob)) {
            return FALSE;
        }
        return $blob;
    }

    /**
     * Checks if a raw data pointer is public data (return value FALSE) or if 
     * yes who the authorised admins to view it are (return array of user IDs)
     * 
     * @param string $table which database table is this about
     * @param int $row row index of the table
     * @return mixed FALSE if the data is public, an array of owners of the data if it is NOT public
     */
    public static function isDataRestricted($table, $row) {
        if ($table != "institution_option" && $table != "profile_option" && $table != "federation_option" && $table != "user_options") {
            return []; // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
        }
        // we need to create our own DB handle as this is a static method
        $handle = DBConnection::handle("INST");
        switch ($table) {
            case "profile_option":
                $blobQuery = $handle->exec("SELECT profile_id from $table WHERE row = $row");
                while ($profileIdQuery = mysqli_fetch_object($blobQuery)) { // only one row
                    $blobprofile = $profileIdQuery->profile_id;
                }
                // is the profile in question public?
                if (!isset($blobprofile)) {
                    return []; // err on the side of caution: we did not find any data. It's a severe error, but not fatal. Nobody owns non-existent data.
                }
                $profile = ProfileFactory::instantiate($blobprofile);
                if ($profile->isShowtime() == TRUE) { // public data
                    return FALSE;
                }
                // okay, so it's NOT public. return the owner
                $inst = new IdP($profile->institution);
                return $inst->owner();

            case "institution_option":
                $blobQuery = $handle->exec("SELECT institution_id from $table WHERE row = $row");
                while ($instIdQuery = mysqli_fetch_object($blobQuery)) { // only one row
                    $blobinst = $instIdQuery->institution_id;
                }
                if (!isset($blobinst)) {
                    return []; // err on the side of caution: we did not find any data. It's a severe error, but not fatal. Nobody owns non-existent data.
                }
                $inst = new IdP($blobinst);
                // if at least one of the profiles belonging to the inst is public, the data is public
                if ($inst->isOneProfileShowtime()) { // public data
                    return FALSE;
                }
                // okay, so it's NOT public. return the owner
                return $inst->owner();
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
     * when options in the DB change, this can mean generated installers become stale. sub-classes must define whether this is the case for them
     */
    abstract public function updateFreshness();
}
