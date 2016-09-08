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
require_once('Logging.php');

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
class DBConnection extends Entity {

    /**
     * This is the actual constructor for the singleton. It creates a database connection if it is not up yet, and returns a handle to the database connection on every call.
     * @return DBConnection the (only) instance of this class
     */
    public static function handle($database) {
        switch (strtoupper($database)) {
            case "INST":
                if (!isset(self::$instanceInst)) {
                    $class = __CLASS__;
                    self::$instanceInst = new $class($database);
                    DBConnection::$instanceInst->databaseInstance = strtoupper($database);
                }
                return self::$instanceInst;
            case "USER":
                if (!isset(self::$instanceUser)) {
                    $class = __CLASS__;
                    self::$instanceUser = new $class($database);
                    DBConnection::$instanceUser->databaseInstance = strtoupper($database);
                }
                return self::$instanceUser;
            case "EXTERNAL":
                if (!isset(self::$instanceExternal)) {
                    $class = __CLASS__;
                    self::$instanceExternal = new $class($database);
                    DBConnection::$instanceExternal->databaseInstance = strtoupper($database);
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
     * @param string $value The value to escape
     * @return string
     */
    public function escapeValue($value) {
        $this->loggerInstance->debug(5, "Escaping $value for DB $this->databaseInstance to get a safe query value.\n");
        $escaped = mysqli_real_escape_string($this->connection, $value);
        $this->loggerInstance->debug(5, "This is the result: $escaped .\n");
        return $escaped;
    }

    /**
     * executes a query and triggers logging to the SQL audit log if it's not a SELECT
     * @param string $querystring the query to be executed
     * @return mixed the query result as mysqli_result object; or TRUE on non-return-value statements
     */
    public function exec($querystring) {
        // log exact query to debug log, if log level is at 5
        $this->loggerInstance->debug(5, "DB ATTEMPT: " . $querystring . "\n");

        if ($this->connection == FALSE) {
            $this->loggerInstance->debug(1, "ERROR: Cannot send query to $this->databaseInstance database (no connection)!");
            return FALSE;
        }

        $result = mysqli_query($this->connection, $querystring);
        if ($result == FALSE) {
            $this->loggerInstance->debug(1, "ERROR: Cannot execute query in $this->databaseInstance database - (hopefully escaped) query was '$querystring'!");
            return FALSE;
        }

        // log exact query to audit log, if it's not a SELECT
        if (preg_match("/^SELECT/i", $querystring) == 0) {
            CAT::writeSQLAudit("[DB: " . strtoupper($this->databaseInstance) . "] " . $querystring);
        }
        return $result;
    }

    /**
     * Retrieves the last auto-id of an INSERT. Needs to be called immediately after the corresponding exec() call
     * @return int the last autoincrement-ID
     */
    public function lastID() {
        return mysqli_insert_id($this->connection);
    }

    /**
     * Holds the singleton instance reference to USER database
     * 
     * @var DBConnection 
     */
    private static $instanceUser;
    
    /**
     * Holds the singleton instance reference to INST database
     * 
     * @var DBConnection 
     */
    private static $instanceInst;
    
    /**
     * Holds the singleton instance reference to EXTERNAL database
     * 
     * @var DBConnection 
     */
    private static $instanceExternal;
    
    /**
     * after instantiation, keep state of which DB *this one* talks to
     * 
     * @var string which database does this instance talk to
     */
    private $databaseInstance;
        
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
        parent::__construct();
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