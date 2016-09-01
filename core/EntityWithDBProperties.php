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
abstract class EntityWithDBProperties {

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
        DBConnection::exec($this->databaseType, "DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND option_name NOT LIKE '%_file'");
        $this->updateFreshness();
        $execFlush = DBConnection::exec($this->databaseType, "SELECT row FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier");
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
            DBConnection::exec($this->databaseType, "DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $quotedIdentifier AND row = $row");
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
        $escapedAttrName = DBConnection::escapeValue($this->databaseType, $attrName);
        $escapedAttrValue = DBConnection::escapeValue($this->databaseType, $attrValue);
        DBConnection::exec($this->databaseType, "INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_value) VALUES("
                . $quotedIdentifier . ", '"
                . $escapedAttrName . "', '"
                . $escapedAttrValue
                . "')");
        $this->updateFreshness();
    }

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

    protected function retrieveOptionsFromDatabase($query, $level) {
        $optioninstance = Options::instance();
        $tempAttributes = [];
        $attributeDbExec = DBConnection::exec($this->databaseType, $query);

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
     * Checks if a raw data pointer is public data (return value FALSE) or if 
     * yes who the authorised admins to view it are (return array of user IDs)
     */
    public static function isDataRestricted($table, $row) {
        if ($table != "institution_option" && $table != "profile_option" && $table != "federation_option" && $table != "user_options") {
            return []; // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
        }
        switch ($table) {
            case "profile_option":
                $blobQuery = DBConnection::exec("INST", "SELECT profile_id from $table WHERE row = $row");
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
                $blobQuery = DBConnection::exec("INST", "SELECT institution_id from $table WHERE row = $row");
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

    abstract public function updateFreshness();
}