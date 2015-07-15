<?php

/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the DBConnection singleton.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 */

require_once('Helper.php');
require_once('Profile.php');
require_once('IdP.php');

/**
 * This class is a singleton for establishing a connection to the database
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class DBConnection {

    /**
     * This is the actual constructor for the singleton. It creates a database connection if it is not up yet, and returns a handle to the database connection on every call.
     * @return DBConnection the (only) instance of this class
     */
    private static function handle($db) {
        switch (strtoupper($db)) {
            case "INST":
                if (!isset(self::$instance_inst)) {
                    $c = __CLASS__;
                    self::$instance_inst = new $c($db);
                }
                return self::$instance_inst;
                break;
            case "USER":
                if (!isset(self::$instance_user)) {
                    $c = __CLASS__;
                    self::$instance_user = new $c($db);
                }
                return self::$instance_user;
                break;
            case "EXTERNAL":
                if (!isset(self::$instance_external)) {
                    $c = __CLASS__;
                    self::$instance_external = new $c($db);
                }
                return self::$instance_external;
                break;
            default:
                return FALSE;
        }
    }

    /**
     * Implemented for safety reasons only. Cloning is forbidden and will tell the user so.
     */
    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }
    /**
     * 
     * @param string $db The database to do escapting for
     * @param string $value The value to escape
     * @return string
     */
    public static function escape_value($db, $value) {
        $handle = DBConnection::handle($db);
        debug(5,"Escaping $value for DB $db to get a safe query value.\n");
        $escaped = mysqli_real_escape_string($handle->connection, $value);
        debug(5,"This is the result: $escaped .\n");
        return $escaped;
    }
    
    /**
     * executes a query and triggers logging to the SQL audit log if it's not a SELECT
     * @param string $querystring the query to be executed
     * @return mixed the query result as mysqli_result object; or TRUE on non-return-value statements
     */
    public static function exec($db, $querystring) {
        // log exact query to debug log, if log level is at 5
        debug(5, "DB ATTEMPT: ".$querystring . "\n");
        
        $instance = DBConnection::handle($db);
        if ($instance->connection == FALSE) {
            debug(1,"ERROR: Cannot send query to $db database (no connection)!");
            return FALSE;
        }
        
        $result = mysqli_query($instance->connection, $querystring);
        if ($result == FALSE) {
            debug(1,"ERROR: Cannot execute query in $db database - (hopefully escaped) query was '$querystring'!");
            return FALSE;
        }
        
        // log exact query to audit log, if it's not a SELECT
        if (preg_match("/^SELECT/i", $querystring) == 0)
            CAT::writeSQLAudit("[DB: " . strtoupper($db) . "] " . $querystring);
        return $result;
    }

    /**
     * Retrieves data from the underlying tables, for situations where instantiating the IdP or Profile object is inappropriate
     * 
     * @param type $table institution_option or profile_option
     * @param type $row rowindex
     * @return boolean
     */
    public static function fetchRawDataByIndex($table, $row) {
        // only for select tables!
        if ($table != "institution_option" && $table != "profile_option")
            return FALSE;
    $blob_query = DBConnection::exec("INST", "SELECT option_value from $table WHERE row = $row");
    while ($a = mysqli_fetch_object($blob_query))
        $blob = $a->option_value;

    if (!isset($blob))
        return FALSE;
    return $blob;
}

    /**
     * Checks if a raw data pointer is public data (return value FALSE) or if 
     * yes who the authorised admins to view it are (return array of user IDs)
     */
    public static function isDataRestricted($table, $row) {
            if ($table != "institution_option" && $table != "profile_option")
            return array(); // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
            switch ($table) {
            case "profile_option":            
                $blob_query = DBConnection::exec("INST", "SELECT profile_id from $table WHERE row = $row");
                while ($a = mysqli_fetch_object($blob_query)) // only one row
                    $blobprofile = $a->profile_id;
                // is the profile in question public?
                $profile = new Profile($blobprofile);
                if ($profile->getShowtime() == TRUE) { // public data
                    return FALSE;
                } else {
                    $inst = new IdP($profile->institution);
                    return $inst->owner();
                }
                break;
            case "institution_option":
                $blob_query = DBConnection::exec("INST", "SELECT institution_id from $table WHERE row = $row");
                while ($a = mysqli_fetch_object($blob_query)) // only one row
                    $blobinst = $a->institution_id;
                $inst = new IdP($blobinst);
                // if at least one of the profiles belonging to the inst is public, the data is public
                if ($inst->isOneProfileShowtime()) { // public data
                    return FALSE;
                } else {
                    return $inst->owner();
                }
                break;
            default:
            return array(); // better safe than sorry: that's an error, so assume nobody is authorised to act on that data
            }
            
    }

    /**
     * Retrieves the last auto-id of an INSERT. Needs to be called immediately after the corresponding exec() call
     * @return int the last autoincrement-ID
     */
    public static function lastID($db) {
        $instance = DBConnection::handle($db);
        return mysqli_insert_id($instance->connection);
    }

    /**
     * Holds the singleton instance reference
     * 
     * @var DBConnection 
     */
    private static $instance_user;
    private static $instance_inst;
    private static $instance_external;

    /**
     * The connection to the DB server
     * 
     * @var mysqli
     */
    private $connection;

    /**
     * Class constructor. Cannot be called directly; use handle()
     */
    private function __construct($db) {
        $DB = strtoupper($db);
        $this->connection = mysqli_connect(Config::$DB[$DB]['host'], Config::$DB[$DB]['user'], Config::$DB[$DB]['pass'], Config::$DB[$DB]['db']) or die("ERROR: Unable to connect to $DB database! This is a fatal error, giving up.");
        if ($this->connection == FALSE) {
            echo "ERROR: Unable to connect to $db database! This is a fatal error, giving up.";
            exit(1);
        }
    
    if ($db == "EXTERNAL" && Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team")
        mysqli_query($this->connection, "SET NAMES 'latin1'");
    }
}

?>
