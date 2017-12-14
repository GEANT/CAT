<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
require_once(dirname(dirname(__DIR__)) . "/config/_config.php");
$realm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
$visited = filter_input(INPUT_GET,'visited', FILTER_SANITIZE_STRING);
if (session_status != PHP_SESSION_ACTIVE) {
    session_start();
}
$telepath = new \core\diag\Telepath($realm, $visited);
$telepathArray = $telepath->magic();

$returnArray = array();
if (empty($telepathArray)) {
    $returnArray['status'] = 0;
} else {
    $returnArray['status'] = 1;
    $returnArray['realm'] = $realm;
    $returnArray['suspects'] = $telepathArray;
    $returnArray['print_r'] = print_r(telepathArray, true);
}
$loggerInstance = new \core\common\Logging();
$loggerInstance->debug(4, "magic Telepath returns:");
$loggerInstance->debug(4, $returnArray);
$json = json_encode($returnArray);
if ($json) {
    echo $json;
} else {
    echo(json_encode(array('status' => 0)));
}
