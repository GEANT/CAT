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
class EntityWithDBProperties {

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
     * Virtual: sub-classes should define themselves what it means to become
     * stale
     */
    protected function updateFreshness() {
        
    }

    /**
     * This function returns the count of specific attributes in an IdP
     * This function will not retreive the values attributes (particularly important for large blobs),
     * it is mainly intended as a test for an attribute existance.
     *
     * @param string $optionName name of the attribute whose existence in the IdP is to be checked
     * @return int attribute count
     */
    public function isAttributeAvailable($optionName) {
        $quotedIdentifier = (!is_int($identifier) ? "\"" : "") . $this->identifier . (!is_int($identifier) ? "\"" : "");
        $escapedOptionName = DBConnection::escape_value($this->databaseType, $optionName);
        $result = DBConnection::exec($this->databaseType, "SELECT row FROM $this->entityOptionTable 
              WHERE $this->entityIdColumn = $quotedIdentifier AND option_name = '$escapedOptionName'");
        return(mysqli_num_rows($result));
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
        } else {
            return $this->attributes;
        }
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
        $escapedAttrName = DBConnection::escape_value($this->databaseType, $attrName);
        $escapedAttrValue = DBConnection::escape_value($this->databaseType, $attrValue);
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
        } else { // single lang, direct content
            return ["lang" => "", "content" => base64_decode($optionContent)];
        }
    }

    protected function retrieveOptionsFromDatabase($query, $level) {
        $optioninstance = Options::instance();
        $this->attributes = [];
        $attributeDbExec = DBConnection::exec($this->databaseType, $query);

        while ($attributeQuery = mysqli_fetch_object($attributeDbExec)) {
            // decode base64 for files (respecting multi-lang)
            $optinfo = $optioninstance->optionType($attributeQuery->option_name);
            $flag = $optinfo['flag'];

            if ($optinfo['type'] != "file") {
                $this->attributes[] = ["name" => $attributeQuery->option_name, "value" => $attributeQuery->option_value, "level" => $level, "row" => $attributeQuery->row, "flag" => $flag];
            } else {
                $decodedAttribute = $this->decodeFileAttribute($attributeQuery->option_value);
                $this->attributes[] = ["name" => $attributeQuery->option_name, "value" => ($decodedAttribute['lang'] == "" ? $decodedAttribute['content'] : serialize($decodedAttribute)), "level" => $level, "row" => $attributeQuery->row, "flag" => $flag];
            }
        }
    }

}
