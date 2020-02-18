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

namespace core\common;

use \Exception;

class Logging
{

    /**
     * We don't have a lot to do here, but at least make sure that the logdir 
     * is specified and exists.
     * 
     * @throws Exception
     */
    public function __construct()
    {
        if (!isset(\config\Master::PATHS['logdir'])) {
            throw new Exception("No logdir was specified in the configuration. We cannot continue without one!");
        }
    }

    /**
     * writes a message to file
     * 
     * @param string $filename the name of the log file, relative (path to logdir gets prepended)
     * @param string $message  what to write into the file
     * @return void
     */
    private function writeToFile($filename, $message)
    {
        file_put_contents(\config\Master::PATHS['logdir'] . "/$filename", sprintf("%-015s", microtime(TRUE)) . $message, FILE_APPEND);
    }

    /**
     * write debug messages to the log, if the debug level is high enough
     *
     * @param int    $level  the debug level of the message that is to be logged
     * @param mixed  $stuff  the stuff to be logged (via print_r)
     * @param string $prefix prefix to the message, optional
     * @param string $suffix suffix to the message, optional
     * @return void
     */
    public function debug($level, $stuff, $prefix = '', $suffix = '')
    {
        if (\config\Master::DEBUG_LEVEL < $level) {
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
        $output = $prefix . $output . $suffix;
        $this->writeToFile("debug.log", $output);

        return;
    }

    /**
     * Writes an audit log entry to the audit log file. These audits are semantic logs; they don't record every single modification
     * in the database, but provide a logical "who did what" overview. The exact modification SQL statements are logged
     * automatically with writeSQLAudit() instead. The log file path is configurable in _config.php.
     * 
     * @param string $user     persistent identifier of the user who triggered the action
     * @param string $category type of modification, from the fixed vocabulary: "NEW", "OWN", "MOD", "DEL"
     * @param string $message  message to log into the audit log
     * @return boolean TRUE if successful. Will terminate script execution on failure. 
     * @throws Exception
     */
    public function writeAudit($user, $category, $message)
    {
        switch ($category) {
            case "NEW": // created a new object
            case "OWN": // ownership changes
            case "MOD": // modified existing object
            case "DEL": // deleted an object
                ob_start();
                echo " ($category)  $user : $message\n";
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
     * @return void
     */
    public function writeSQLAudit($query)
    {
        // clean up text to be in one line, with no extra spaces
        // also clean up non UTF-8 to sanitise possibly malicious inputs
        $logTextStep1 = preg_replace("/[\n\r]/", "", $query);
        $logTextStep2 = preg_replace("/ +/", " ", $logTextStep1);
        $logTextStep3 = iconv("UTF-8", "UTF-8//IGNORE", $logTextStep2);
        $this->writeToFile("audit-SQL.log", " " . $logTextStep3 . "\n");
    }
}