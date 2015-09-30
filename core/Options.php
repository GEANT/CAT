<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php

/**
 * This file contains some convenience functions around option handling.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 *
 * @package Developer
 */

/**
 * necessary includes
 */
require_once('Helper.php');

/**
 * The Options class contains convenience functions around option handling. It is implemented as a singleton to prevent
 * excessive DB requests; its content never changes during a script run.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 *
 * @package Developer
 */
class Options {
    
   /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $DB_TYPE = "INST";
    
    /**
     * The (only) instance of this class
     * 
     * @var Options
     */
    private static $instance;
    
    /**
     * This private variable contains the list of all known options and their properties (i.e. flags).
     * 
     * @var array all known options
     */
    private $type_db;
    
    /**
     * Returns the handle to the (only) instance of this class.
     * 
     * @return Options
     */
    public static function instance()
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }
    
    /**
     * Prevent cloning - this is a singleton.
     */
    public function __clone()
    {
        trigger_error('Cloning not allowed for singleton classes.', E_USER_ERROR);
    }
    
    /**
     *  Option class constructor; retrieves information about the known options from the database.
     */
    private function __construct() {
        $this->type_db = [];
        debug(3,"--- BEGIN constructing Options instance ---\n");
        $options = DBConnection::exec(Options::$DB_TYPE, "SELECT name,type,flag from profile_option_dict ORDER BY name");
        while($a = mysqli_fetch_object($options))
            $this->type_db[$a->name]       =  ["type" => $a->type, "flag" => $a->flag];
        $this->type_db["general:logo_url"] =  ["type" => "string", "flag" => NULL];
        $this->type_db["eap:ca_url"]       =  ["type" => "string", "flag" => NULL];
        $this->type_db["internal:country"] =  ["type" => "string", "flag" => NULL];

        debug(3,"--- END constructing Options instance ---\n");
    }
    
    /**
     * This function lists all known options. If called with the optional parameter $class_name, only options of that class are
     * returned, otherwise the full set of all known attributes.
     * 
     * @assert ("user") == Array("user:email","user:fedadmin","user:realname")
     * 
     * @param string $class_name optionally specifies the class of options to be listed (class is the part of the option name before the : sign)
     * @return array of options
     */
    public function availableOptions($class_name = 0) {
        $temporary_array = [];
        debug(3,"CLASSNAME IS $class_name\n");
        
        foreach (array_keys($this->type_db) as $name) {
            if ($class_name === 0) {
                $temporary_array[] = $name;
            } else {
                if ( preg_match('/^'.$class_name.':/', $name) > 0 ) {
                        $temporary_array[] = $name;
                }
            }
        }

        return $temporary_array;
    }

    /** This function returns the properties of a given attribute name. This currently means it returns its type and its flag field ("ML").
     *
     * @assert ("general:instname") == Array("type"=>"string", "flag"=>"ML")
     * @assert ("profile:production") == Array("type"=>"boolean", "flag"=>NULL)
     * 
     * @param string $optionname Name of the option whose properties are to be retrieved.
     * @return array properties of the attribute
     */
    public function optionType($optionname) {        
        return $this->type_db[$optionname];
    }

}
?>
