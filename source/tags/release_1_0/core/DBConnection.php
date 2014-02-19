<?php

/* *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
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
     * This is the only usable function. It creates a database connection if it is not up yet, and returns a handle to the database connection on every call.
     * @return DBConnection the (only) instance of this class
     */
    private function handle($db) {
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
     * executes a query and triggers logging to the SQL audit log if it's not a SELECT
     * @param string $querystring the query to be executed
     * @return mixed the query result as mysqli_result object; or TRUE on non-return-value statements
     */
    public static function exec($db, $querystring) {
        // log exact query to debug log, if log level is at 5
        debug(5, "DB ATTEMPT: ".$querystring . "\n");
        
        $instance = DBConnection::handle($db);
        if ($instance->connection == FALSE) {
            echo "ERROR: Cannot send query to $db database (no connection)!";
            return FALSE;
        }
        $result = mysqli_query($instance->connection, $querystring);
        if ($result == FALSE) {
            echo "ERROR: Cannot execute query in $db database (query was '$querystring')!";
            return FALSE;
        }
        
        // log exact query to audit log, if it's not a SELECT
        if (preg_match("/^SELECT/i", $querystring) == 0)
            CAT::writeSQLAudit("[DB: " . strtoupper($db) . "] " . $querystring);
        return $result;
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
        $this->connection = mysqli_connect(Config::$DB[$DB]['host'], Config::$DB[$DB]['user'], Config::$DB[$DB]['pass'], Config::$DB[$DB]['db']) or die("Unable to connect to $DB database");
        if ($this->connection == FALSE)
            echo "ERROR: Unable to connect to $db database!";
    
    if ($db == "EXTERNAL" && Config::$CONSORTIUM['name']=="eduroam") // SW: sigh. Hack needed for UTF-8 brokenness. APPROVED
        mysqli_query($this->connection, "SET NAMES 'latin1'");
    }
}

?>
