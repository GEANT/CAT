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
 * This file contains some convenience functions around option handling.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 *
 * @package Developer
 */
/**
 * necessary includes
 */

namespace core;

use \Exception;

/**
 * The Options class contains convenience functions around option handling. It is implemented as a singleton to prevent
 * excessive DB requests; its content never changes during a script run.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class Options
{

    /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $databaseType = "INST";

    /**
     * The (only) instance of this class
     * 
     * @var Options
     */
    private static $instance;

    /**
     * Our access to logging facilities
     * 
     * @var \core\common\Logging
     */
    private $loggerInstance;

    /**
     * This private variable contains the list of all known options and their properties (i.e. flags).
     * 
     * @var array all known options
     */
    private $typeDb;

    const TYPECODE_STRING = "string";
    const TYPECODE_ENUM_OPENROAMING = "enum_openroaming";
    const TYPECODE_INTEGER = "integer";
    const TYPECODE_TEXT = "text";
    const TYPECODE_BOOLEAN = "boolean";
    const TYPECODE_FILE = "file";
    const TYPECODE_COORDINATES = "coordinates";

    public const LEVEL_METHOD = "Method";
    public const LEVEL_PROFILE = "Profile";
    public const LEVEL_IDP = "IdP";
    public const LEVEL_FED = "FED";
    public const LEVEL_USER = "User";

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
     * 
     * @return void
     */
    public function __clone()
    {
        trigger_error('Cloning not allowed for singleton classes.', E_USER_ERROR);
    }

    /**
     *  Option class constructor; retrieves information about the known options from the database.
     */
    private function __construct()
    {
        $this->typeDb = [];
        $this->loggerInstance = new \core\common\Logging();
        $this->loggerInstance->debug(3, "--- BEGIN constructing Options instance ---\n");
        $handle = DBConnection::handle(self::$databaseType);
        $options = $handle->exec("SELECT name,type,flag from profile_option_dict ORDER BY name");
        // SELECT -> resource, not boolean
        while ($optionDataQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $options)) {
            $this->typeDb[$optionDataQuery->name] = ["type" => $optionDataQuery->type, "flag" => $optionDataQuery->flag];
        }
        $this->typeDb["general:logo_url"] = ["type" => "string", "flag" => NULL];
        $this->typeDb["eap:ca_url"] = ["type" => "string", "flag" => NULL];
        $this->typeDb["internal:country"] = ["type" => "string", "flag" => NULL];
        $this->typeDb["internal:profile_count"] = ["type" => "integer", "flag" => NULL];
        $this->typeDb["internal:checkuser_outer"] = ["type" => "boolean", "flag" => NULL];
        $this->typeDb["internal:checkuser_value"] = ["type" => "string", "flag" => NULL];
        $this->typeDb["internal:verify_userinput_suffix"] = ["type" => "boolean", "flag" => NULL];
        $this->typeDb["internal:hint_userinput_suffix"] = ["type" => "boolean", "flag" => NULL];
        $this->typeDb["internal:realm"] = ["type" => "string", "flag" => NULL];
        $this->typeDb["internal:use_anon_outer"] = ["type" => "boolean", "flag" => NULL];
        $this->typeDb["internal:anon_local_value"] = ["type" => "string", "flag" => NULL];
        $this->loggerInstance->debug(3, "--- END constructing Options instance ---\n");
    }

    /**
     * This function lists all known options. If called with the optional parameter $className, only options of that class are
     * returned, otherwise the full set of all known attributes.
     * 
     * @assert ("user") == Array("user:email","user:fedadmin","user:realname")
     * 
     * @param string $className optionally specifies the class of options to be listed (class is the part of the option name before the : sign)
     * @return array of options
     */
    public function availableOptions($className = 0)
    {
        $tempArray = [];
        $this->loggerInstance->debug(3, "CLASSNAME IS $className\n");

        foreach (array_keys($this->typeDb) as $name) {
            if ($className === 0) {
                $tempArray[] = $name;
            } elseif (preg_match('/^' . $className . ':/', $name) > 0) {
                $tempArray[] = $name;
            }
        }
        $returnArray = $tempArray;
        // remove silverbullet-specific options if this deployment is not SB
        foreach ($tempArray as $key => $val) {
            if (( \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] != 'LOCAL') && (preg_match('/^fed:silverbullet/', $val) > 0)) {
                unset($returnArray[$key]);
            }
            if (( \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] != 'LOCAL') && (preg_match('/^fed:minted_ca_file/', $val) > 0)) {
                unset($returnArray[$key]);
            }
        }
        return $returnArray;
    }

    /** This function returns the properties of a given attribute name. This currently means it returns its type and its flag field ("ML").
     *
     * @assert ("general:instname") == Array("type"=>"string", "flag"=>"ML")
     * @assert ("profile:production") == Array("type"=>"boolean", "flag"=>NULL)
     * 
     * @param string $optionname Name of the option whose properties are to be retrieved.
     * @return array properties of the attribute
     * @throws Exception
     */
    public function optionType($optionname)
    {
        if (isset($this->typeDb[$optionname])) {
            return $this->typeDb[$optionname];
        }
        throw new Exception("Metadata about an option was requested, but the option name does not exist in the system: " . htmlentities($optionname));
    }

    /**
     * This function is mostly useless. It takes an (unvetted) string, sees if
     * it is a valid option name, and then returns the array key of the typeDb
     * instead of the unvetted string. This makes Scrutinizer happy.
     * 
     * @param string $unvettedName the input name
     * @return string the name echoed back, but from trusted source
     * @throws Exception
     */
    public function assertValidOptionName($unvettedName)
    {
        $listOfOptions = array_keys($this->typeDb);
        foreach ($listOfOptions as $name) {
            if ($name == $unvettedName) {
                return $name;
            }
        }
        throw new Exception("Unknown option name encountered.");
    }
}