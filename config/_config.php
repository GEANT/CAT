<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once ("autoloader.php");
require_once(__DIR__ . "/../packageRoot.php");

/* This code block compares the template config against the actual one to find
 * out which of the values are MISSING, which are still at DEFAULT and which
 * have been CHANGED.

function recursiveConfCheck($template, $real, $level = 0) {
    $result = [];

    $stateMissingSet = 0;
    $stateDefaultSet = 0;
    $stateChangedSet = 0;
    $stateSubLevelsDiffer = 0;

    foreach ($template as $key => $value) {
        if (!isset($real[$key])) {
            $result[$key] = "MISSING";
            $stateMissingSet = 1;
        } elseif (is_array($value)) {
            $result[$key] = recursiveConfCheck($value, $real[$key], $level + 1);
            switch ($result[$key]) {
                case "MISSING":
                    $stateMissingSet = 1;
                    break;
                case "DEFAULT":
                    $stateDefaultSet = 1;
                    break;
                case "CHANGED":
                    $stateChangedSet = 1;
                    break;
                default:
                    $stateSubLevelsDiffer = 1;
            }
        } elseif ($value === $real[$key]) {
            $result[$key] = "DEFAULT";
            $stateDefaultSet = 1;
        } else {
            $result[$key] = "CHANGED";
            $stateChangedSet = 1;
        }
    }
    // group together a config layer if all settings are same
    if ($stateChangedSet + $stateDefaultSet + $stateMissingSet + $stateSubLevelsDiffer > 1) {
        return $result;
    }
    if ($stateMissingSet == 1) {
        return "MISSING";
    }
    if ($stateChangedSet == 1) {
        return "CHANGED";
    }
    if ($stateDefaultSet == 1) {
        return "DEFAULT";
    }
}

// first, fetch and store the /template/ config so that we can find missing
// bits in the actual config. Since this is a const, we need to first load
// the template, alter the const name, save it, and include it

$templateConfig = file_get_contents(ROOT . "/config/config-master-template.php");
$newTemplateConfig = preg_replace("/const CONFIG/", "const TEMPLATE_CONFIG", $templateConfig);
file_put_contents(ROOT . "/var/tmp/temp-master.php", $newTemplateConfig);
include(ROOT . "/var/tmp/temp-master.php");
unlink(ROOT . "/var/tmp/temp-master.php");
*/
// this is the actual config


include(ROOT . "/config/config-master.php");

/* as a test for the config comparison, run this, display in browser and exit 

echo "<pre>";
print_r(TEMPLATE_CONFIG);
print_r(CONFIG);
print_r(recursiveConfCheck(TEMPLATE_CONFIG, CONFIG));
echo "</pre>";
exit;

*/

/* load sub-configs if we are dealing with those in this installation */

if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT'] == 'LOCAL') {
    include(ROOT . "/config/config-confassistant.php");
}

if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] == 'LOCAL') {
    include(ROOT . "/config/config-diagnostics.php");
}

