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
$loggerInstance = new \core\common\Logging();
$loggerInstance->debug(4, "Sociopath test\n");
session_start();
$loggerInstance->debug(4, $_SESSION);
$workingwith = filter_input(INPUT_GET, 'workingwith', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$answer = filter_input(INPUT_GET, 'answer', FILTER_SANITIZE_STRING);
$loggerInstance->debug(4, $workingwith);
$loggerInstance->debug(4, "answer $answer\n");
$sociopath = new \core\diag\Sociopath();
if (!$workingwith) {
    $QJSON = $_SESSION['QJSON'];
    $QPHP = json_decode($QJSON, TRUE);
    $yes = FALSE;
    if ($answer === 2) {
        $yes = TRUE;
    }
    $sociopath->revaluate($QPHP["NUMBER"], $yes);
} else {
    error_log('MGW workingwith '.serialize($workingwith));
}
$QJSON = $sociopath->questionOracle();
$_SESSION['QJSON'] = $QJSON;
$loggerInstance->debug(4, json_decode($QJSON));
//$returnArray = array();
$loggerInstance->debug(4, $workingwith);
$loggerInstance->debug(4, $sociopath->getCurrentGuessState());
$QPHP = json_decode($QJSON, TRUE);
if ($QPHP['NEXTEXISTS']) {
    error_log('QJSON '.serialize($QJSON));
    echo $QJSON;
} else {
    error_log('Verdict '.serialize($sociopath->getCurrentGuessState()));
    echo $sociopath->getCurrentGuessState();
}
