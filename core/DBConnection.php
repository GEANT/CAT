<?php

/* * ********************************************************************************
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
    private static function handle($database) {
        switch (strtoupper($database)) {
            case "INST":
                if (!isset(self::$instanceInst)) {
                    $class = __CLASS__;
                    self::$instanceInst = new $class($database);
                }
                return self::$instanceInst;
            case "USER":
                if (!isset(self::$instanceUser)) {
                    $class = __CLASS__;
                    self::$instanceUser = new $class($database);
                }
                return self::$instanceUser;
            case "EXTERNAL":
                if (!isset(self::$instanceExternal)) {
                    $class = __CLASS__;
                    self::$instanceExternal = new $class($database);
                }
                return self::$instanceExternal;
            default:
                throw new Exception("This type of database (".strtoupper($database).") is not known!");
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
     * @param string $database The database to do escapting for
     * @param string $value The value to escape
     * @return string
     */
    public static function escapeValue($database, $value) {
        $handle = DBConnection::handle($database);
        debug(5, "Escaping $value for DB $database to get a safe query value.\n");
        $escaped = mysqli_real_escape_string($handle->connection, $value);
        debug(5, "This is the result: $escaped .\n");
        return $escaped;
    }

    /**
     * executes a query and triggers logging to the SQL audit log if it's not a SELECT
     * @param string $querystring the query to be executed
     * @return mixed the query result as mysqli_result object; or TRUE on non-return-value statements
     */
    public static function exec($database, $querystring) {
        // log exact query to debug log, if log level is at 5
        debug(5, "DB ATTEMPT: " . $querystring . "\n");

        $instance = DBConnection::handle($database);
        if ($instance->connection == FALSE) {
            debug(1, "ERROR: Cannot send query to $database database (no connection)!");
            return FALSE;
        }

        $result = mysqli_query($instance->connection, $querystring);
        if ($result == FALSE) {
            debug(1, "ERROR: Cannot execute query in $database database - (hopefully escaped) query was '$querystring'!");
            return FALSE;
        }

        // log exact query to audit log, if it's not a SELECT
        if (preg_match("/^SELECT/i", $querystring) == 0) {
            CAT::writeSQLAudit("[DB: " . strtoupper($database) . "] " . $querystring);
        }
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
        if ($table != "institution_option" && $table != "profile_option" && $table != "federation_option") {
            return FALSE;
        }
        $blobQuery = DBConnection::exec("INST", "SELECT option_value from $table WHERE row = $row");
        while ($returnedData = mysqli_fetch_object($blobQuery)) {
            $blob = $returnedData->option_value;
        }
        if (!isset($blob)) {
            return FALSE;
        }
        return $blob;
    }

    /**
     * Retrieves the last auto-id of an INSERT. Needs to be called immediately after the corresponding exec() call
     * @return int the last autoincrement-ID
     */
    public static function lastID($database) {
        $instance = DBConnection::handle($database);
        return mysqli_insert_id($instance->connection);
    }

    /**
     * Holds the singleton instance reference
     * 
     * @var DBConnection 
     */
    private static $instanceUser;
    private static $instanceInst;
    private static $instanceExternal;

    /**
     * The connection to the DB server
     * 
     * @var mysqli
     */
    private $connection;

    /**
     * Class constructor. Cannot be called directly; use handle()
     */
    private function __construct($database) {
        $databaseCapitalised = strtoupper($database);
        $this->connection = mysqli_connect(Config::$DB[$databaseCapitalised]['host'], Config::$DB[$databaseCapitalised]['user'], Config::$DB[$databaseCapitalised]['pass'], Config::$DB[$databaseCapitalised]['db']);
        if ($this->connection == FALSE) {
            throw new Exception("ERROR: Unable to connect to $database database! This is a fatal error, giving up.");
        }

        if ($databaseCapitalised == "EXTERNAL" && Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") {
            mysqli_query($this->connection, "SET NAMES 'latin1'");
        }
    }

}