<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

namespace core\common;
use \Exception;

require_once(dirname(dirname(__DIR__))."/config/_config.php");

class Logging {

    /**
     * We don't have a lot to do here, but at least make sure that the logdir 
     * is specified and exists.
     */
    public function __construct() {
        if (!isset(CONFIG['PATHS']['logdir'])) {
            throw new Exception("No logdir was specified in the configuration. We cannot continue without one!");
        }
    }

    /**
     * 
     * @param string $filename the name of the log file, relative (path to logdir gets prepended)
     * @param string $message what to write into the file
     */
    private function writeToFile($filename, $message) {
        file_put_contents(CONFIG['PATHS']['logdir'] . "/$filename", sprintf("%-015s", microtime(TRUE)) . $message, FILE_APPEND);
    }

    /**
     *
     * write debug messages to the log, if the debug level is high enough
     *
     * @param int $level the debug level of the message that is to be logged
     * @param mixed $stuff the stuff to be logged (via print_r)
     * @return void
     */
    public function debug($level, $stuff) {
        if (CONFIG['DEBUG_LEVEL'] < $level) {
            return;
        }
        
        $output = " ($level) ";
        if ($level > 3) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $orig_file = $backtrace[1]['file'] ?? "no file";
            $file = str_replace(ROOT, "", $orig_file);
            $function = $backtrace[1]['function'] ?? "no function";
            $line = $backtrace[1]['line'] ?? "no line";
            $output .= " [$file / $function / $line] ";
        }
        if (is_string($stuff)) {
            $output .= $stuff;
        } else {
            $output .= var_export($stuff, TRUE);
        }
        $this->writeToFile("debug.log", $output);

        return;
    }

    /**
     * Writes an audit log entry to the audit log file. These audits are semantic logs; they don't record every single modification
     * in the database, but provide a logical "who did what" overview. The exact modification SQL statements are logged
     * automatically with writeSQLAudit() instead. The log file path is configurable in _config.php.
     * 
     * @param string $user persistent identifier of the user who triggered the action
     * @param string $category type of modification, from the fixed vocabulary: "NEW", "OWN", "MOD", "DEL"
     * @param string $message message to log into the audit log
     * @return boolean TRUE if successful. Will terminate script execution on failure. 
     */
    public function writeAudit($user, $category, $message) {
        switch ($category) {
            case "NEW": // created a new object
            case "OWN": // ownership changes
            case "MOD": // modified existing object
            case "DEL": // deleted an object
                ob_start();
                echo " ($category) ";
                echo " " . $user . ": ";
                if (is_string($message)) {
                    echo $message ."\n";
                } else {
                    echo var_export($message);
                }
                
                $output = ob_get_clean();

                $this->writeToFile("audit-activity.log", $output);
                return TRUE;
            default:
                throw new Exception("Unable to write to AUDIT file (unknown category, requested data was $user, $category, $message!");
        }
    }

    /**
     * Write an audit log entry to the SQL log file. Every SQL statement which is not a simple SELECT one will be written
     * to the log file. The log file path is configurable in _config.php.
     * 
     * @param string $query the SQL query to be logged
     */
    public function writeSQLAudit($query) {
        // clean up text to be in one line, with no extra spaces
        // also clean up non UTF-8 to sanitise possibly malicious inputs
        $logTextStep1 = preg_replace("/[\n\r]/", "", $query);
        $logTextStep2 = preg_replace("/ +/", " ", $logTextStep1);
        $logTextStep3 = iconv("UTF-8", "UTF-8//TRANSLIT", $logTextStep2);
        
        ob_start();
        
        print(" " . $logTextStep3 . "\n");
        $output = ob_get_clean();

        $this->writeToFile("audit-SQL.log", $output);
    }

}
