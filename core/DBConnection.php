<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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

require_once(dirname(__DIR__) . "/config/_config.php");

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
    public static function handle($database) {
        $theDb = strtoupper($database);
        switch ($theDb) {
            case "INST":
            case "USER":
            case "EXTERNAL":
            case "FRONTEND":
                if (!isset(self::${"instance" . $theDb})) {
                    $class = __CLASS__;
                    self::${"instance" . $theDb} = new $class($database);
                    DBConnection::${"instance" . $theDb}->databaseInstance = $theDb;
                }
                return self::${"instance" . $theDb};
            default:
                throw new Exception("This type of database (" . strtoupper($database) . ") is not known!");
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
    public function exec($querystring, $types = NULL, &...$arguments) {
        // log exact query to debug log, if log level is at 5
        $this->loggerInstance->debug(5, "DB ATTEMPT: " . $querystring . "\n");
        if ($types != NULL) {
            $this->loggerInstance->debug(5, "Argument type sequence: $types, parameters are: " . print_r($arguments, true));
        }

        if ($this->connection->connect_error) {
            $this->loggerInstance->debug(1, "ERROR: Cannot send query to $this->databaseInstance database (no connection, error number" . $this->connection->connect_errno . ")!");
            return FALSE;
        }
        if ($types == NULL) {
            $result = $this->connection->query($querystring);
        } else {
            // fancy! prepared statement with dedicated argument list
            if (strlen($types) != count($arguments)) {
                throw new Exception("DB: Prepared Statement: Number of arguments and the type list length differ!");
            }
            $statementObject = $this->connection->stmt_init();
            if ($statementObject === FALSE) {
                throw new Exception("DB: Unable to initialise prepared Statement!");
            }
            $prepResult = $statementObject->prepare($querystring);
            if ($prepResult === FALSE) {
                throw new Exception("DB: Unable to prepare statement! Statement was --> $querystring <--, error was --> ". $statementObject->error ." <--.");
            }

            // we have a variable number of arguments packed into the ... array
            // but the function needs to be called exactly once, with a series of
            // individual arguments, not an array. The voodoo solution is to call
            // it via call_user_func_array()

            $localArray = $arguments;
            array_unshift($localArray, $types);
            $retval = call_user_func_array([$statementObject, "bind_param"], $localArray);
            if ($retval === FALSE) {
                throw new Exception("DB: Unuable to bind parameters to prepared statement! Argument array was --> ". var_export($localArray, TRUE) ." <--. Error was --> ". $statementObject->error ." <--");
            }
            $result = $statementObject->execute();
            if ($result === FALSE) {
                throw new Exception("DB: Unuable to execute prepared statement! Error was --> ". $statementObject->error ." <--");
            }
            $selectResult = $statementObject->get_result();
            if ($selectResult !== FALSE) {
                $result = $selectResult;
            }

            $statementObject->close();
        }

        if ($result === FALSE && $this->connection->errno) {
            $this->loggerInstance->debug(1, "ERROR: Cannot execute query in $this->databaseInstance database - (hopefully escaped) query was '$querystring'!");
            return FALSE;
        }

        // log exact query to audit log, if it's not a SELECT
        if (preg_match("/^SELECT/i", $querystring) == 0) {
            $this->loggerInstance->writeSQLAudit("[DB: " . strtoupper($this->databaseInstance) . "] " . $querystring);
            if ($types != NULL) {
                $this->loggerInstance->writeSQLAudit("Argument type sequence: $types, parameters are: " . print_r($arguments, true));
            }
        }
        return $result;
    }

    /**
     * Retrieves the last auto-id of an INSERT. Needs to be called immediately after the corresponding exec() call
     * @return int the last autoincrement-ID
     */
    public function lastID() {
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
     * Class constructor. Cannot be called directly; use handle()
     */
    private function __construct($database) {
        $this->loggerInstance = new \core\common\Logging();
        $databaseCapitalised = strtoupper($database);
        $this->connection = new \mysqli(CONFIG['DB'][$databaseCapitalised]['host'], CONFIG['DB'][$databaseCapitalised]['user'], CONFIG['DB'][$databaseCapitalised]['pass'], CONFIG['DB'][$databaseCapitalised]['db']);
        if ($this->connection->connect_error) {
            throw new Exception("ERROR: Unable to connect to $database database! This is a fatal error, giving up (error number " . $this->connection->connect_errno . ").");
        }

        if ($databaseCapitalised == "EXTERNAL" && CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") {
            $this->connection->query("SET NAMES 'latin1'");
        }
    }

}
