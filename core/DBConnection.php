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
 * This file contains the DBConnection singleton.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package Developer
 */

namespace core;

use \Exception;

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
class DBConnection
{

    /**
     * This is the actual constructor for the singleton. It creates a database connection if it is not up yet, and returns a handle to the database connection on every call.
     * 
     * @param string $database the database type to open
     * @return DBConnection|array the (only) instance of this class; or all instances of a DB cluster (only for RADIUS auth servers right now)
     * @throws Exception
     */
    public static function handle($database)
    {
        $theDb = strtoupper($database);
        switch ($theDb) {
            case "INST":
            case "USER":
            case "EXTERNAL":
            case "FRONTEND":
            case "DIAGNOSTICS":
                if (!isset(self::${"instance" . $theDb})) {
                    $class = __CLASS__;
                    self::${"instance" . $theDb} = new $class($database);
                    DBConnection::${"instance" . $theDb}->databaseInstance = $theDb;
                }
                return self::${"instance" . $theDb};
            case "RADIUS":
                if (!isset(self::${"instance" . $theDb})) {
                    $class = __CLASS__;
                    foreach (\config\ConfAssistant::DB as $name => $oneRadiusAuthDb) {
                        $theInstance = new $class($name);
                        self::${"instance" . $theDb}[] = $theInstance;
                        $theInstance->databaseInstance = $theDb;
                    }
                }
                return self::${"instance" . $theDb};
            default:
                throw new Exception("This type of database (" . strtoupper($database) . ") is not known!");
        }
    }

    /**
     * Implemented for safety reasons only. Cloning is forbidden and will tell the user so.
     *
     * @return void
     */
    public function __clone()
    {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    /**
     * tells the caller if the database is to be accessed read-only
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * executes a query and triggers logging to the SQL audit log if it's not a SELECT
     * @param string $querystring  the query to be executed
     * @param string $types        for prepared statements, the type list of parameters
     * @param mixed  ...$arguments for prepared statements, the parameters
     * @return mixed the query result as mysqli_result object; or TRUE on non-return-value statements
     * @throws Exception
     */
    public function exec($querystring, $types = NULL, &...$arguments)
    {
        // log exact query to audit log, if it's not a SELECT
        $isMoreThanSelect = FALSE;
        if (preg_match("/^(SELECT\ |SET\ )/i", $querystring) == 0 && preg_match("/^DESC/i", $querystring) == 0) {
            $isMoreThanSelect = TRUE;
            if ($this->readOnly) { // let's not do this.
                throw new Exception("This is a read-only DB connection, but this is statement is not a SELECT!");
            }
        }
        // log exact query to debug log, if log level is at 5
        $this->loggerInstance->debug(5, "DB ATTEMPT: " . $querystring . "\n");
        if ($types !== NULL) {
            $this->loggerInstance->debug(5, "Argument type sequence: $types, parameters are: " . /** @scrutinizer ignore-type */ print_r($arguments, true));
        }

        if ($this->connection->connect_error) {
            throw new Exception("ERROR: Cannot send query to $this->databaseInstance database (no connection, error number" . $this->connection->connect_error . ")!");
        }
        if ($types === NULL) {
            $result = $this->connection->query($querystring);
            if ($result === FALSE) {
                throw new Exception("DB: Unable to execute simple statement! Error was --> " . $this->connection->error . " <--");
            }
        } else {
            // fancy! prepared statement with dedicated argument list
            if (strlen($types) != count($arguments)) {
                throw new Exception("DB: Prepared Statement: Number of arguments and the type list length differ!");
            }
            if (isset($this->preparedStatements[$querystring])) {
                $statementObject = $this->preparedStatements[$querystring];
            } else {
                $statementObject = $this->connection->stmt_init();
                if ($statementObject === FALSE) {
                    throw new Exception("DB: Unable to initialise prepared Statement!");
                }
                $prepResult = $statementObject->prepare($querystring);
                if ($prepResult === FALSE) {
                    throw new Exception("DB: Unable to prepare statement! Statement was --> $querystring <--, error was --> " . $statementObject->error . " <--.");
                }
                $this->preparedStatements[$querystring] = $statementObject;
            }
            // we have a variable number of arguments packed into the ... array
            // but the function needs to be called exactly once, with a series of
            // individual arguments, not an array. The voodoo solution is to call
            // it via call_user_func_array()

            $localArray = $arguments;
            array_unshift($localArray, $types);
            $retval = call_user_func_array([$statementObject, "bind_param"], $localArray);
            if ($retval === FALSE) {
                throw new Exception("DB: Unable to bind parameters to prepared statement! Argument array was --> " . var_export($localArray, TRUE) . " <--. Error was --> " . $statementObject->error . " <--");
            }
            $result = $statementObject->execute();
            if ($result === FALSE) {
                throw new Exception("DB: Unable to execute prepared statement! Error was --> " . $statementObject->error . " <--");
            }
            $selectResult = $statementObject->get_result();
            if ($selectResult !== FALSE) {
                $result = $selectResult;
            }
        }

        // all cases where $result could be FALSE have been caught earlier
        if ($this->connection->errno) {
            throw new Exception("ERROR: Cannot execute query in $this->databaseInstance database - (hopefully escaped) query was '$querystring', errno was " . $this->connection->errno . "!");
        }


        if ($isMoreThanSelect) {
            $this->loggerInstance->writeSQLAudit("[DB: " . strtoupper($this->databaseInstance) . "] " . $querystring);
            if ($types !== NULL) {
                $this->loggerInstance->writeSQLAudit("Argument type sequence: $types, parameters are: " . /** @scrutinizer ignore-type */ print_r($arguments, true));
            }
        }
        return $result;
    }

    /**
     * Retrieves the last auto-id of an INSERT. Needs to be called immediately after the corresponding exec() call
     * @return int the last autoincrement-ID
     */
    public function lastID()
    {
        if (is_string($this->connection->insert_id)) {
            throw new \Exception("The row ID is allegedly larger than PHP_INT_MAX. This is unbelievable.");
        }
        return $this->connection->insert_id;
    }

    /**
     * Holds the singleton instance reference to USER database
     * 
     * @var DBConnection 
     */
    private static $instanceUSER;

    /**
     * Holds the singleton instance reference to INST database
     * 
     * @var DBConnection 
     */
    private static $instanceINST;

    /**
     * Holds the singleton instance reference to EXTERNAL database
     * 
     * @var DBConnection 
     */
    private static $instanceEXTERNAL;

    /**
     * Holds the singleton instance reference to FRONTEND database
     * 
     * @var DBConnection 
     */
    private static $instanceFRONTEND;

    /**
     * Holds the singleton instance reference to DIAGNOSTICS database
     * 
     * @var DBConnection 
     */
    private static $instanceDIAGNOSTICS;

    /**
     * Holds an ARRAY of all RADIUS server instances for Silverbullet
     * 
     * @var array<DBConnection>
     */
    private static $instanceRADIUS;

    /**
     * after instantiation, keep state of which DB *this one* talks to
     * 
     * @var string which database does this instance talk to
     */
    private $databaseInstance;

    /**
     * The connection to the DB server
     * 
     * @var \mysqli
     */
    private $connection;

    /**
     * @var \core\common\Logging
     */
    private $loggerInstance;

    /**
     * Keeps state whether we are a readonly DB instance
     * @var boolean
     */
    private $readOnly;

    /**
     * Class constructor. Cannot be called directly; use handle()
     * 
     * @param string $database the database to open
     * @throws Exception
     */
    private function __construct($database)
    {
        $this->loggerInstance = new \core\common\Logging();
        $databaseCapitalised = strtoupper($database);
        if (isset(\config\Master::DB[$databaseCapitalised])) {
            $this->connection = new \mysqli(\config\Master::DB[$databaseCapitalised]['host'], \config\Master::DB[$databaseCapitalised]['user'], \config\Master::DB[$databaseCapitalised]['pass'], \config\Master::DB[$databaseCapitalised]['db']);
            if ($this->connection->connect_error) {
                throw new Exception("ERROR: Unable to connect to $database database! This is a fatal error, giving up (error number " . $this->connection->connect_errno . ").");
            }
            $this->readOnly = \config\Master::DB[$databaseCapitalised]['readonly'];
        } else { // one of the RADIUS DBs
            $this->connection = new \mysqli(\config\ConfAssistant::DB[$databaseCapitalised]['host'], \config\ConfAssistant::DB[$databaseCapitalised]['user'], \config\ConfAssistant::DB[$databaseCapitalised]['pass'], \config\ConfAssistant::DB[$databaseCapitalised]['db']);
            if ($this->connection->connect_error) {
                throw new Exception("ERROR: Unable to connect to $database database! This is a fatal error, giving up (error number " . $this->connection->connect_errno . ").");
            }
            $this->readOnly = \config\ConfAssistant::DB[$databaseCapitalised]['readonly'];
        }
        if ($databaseCapitalised == "EXTERNAL" && \config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") {
        // it does not matter for internal time calculations with TIMESTAMPs but
        // sometimes we operate on date/time strings. Since MySQL returns those
        // in "local timezone" but doesn't tell what timezone that is, the result
        // is ambiguous. Resolve the ambiguity by telling MySQL to always operate
        // in UTC time.
        $this->connection->query("SET SESSION time_zone='+00:00'");
        $this->connection->query("SET NAMES 'latin1'");
        }
    }

    /**
     * keeps all previously prepared statements in memory so we can reuse them
     * later
     * 
     * @var array
     */
    private $preparedStatements = [];
}