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
$answer = filter_input(INPUT_GET, 'answer', FILTER_SANITIZE_NUMBER_INT);
$sociopath = new \core\diag\Sociopath();
if ($answer > 0) {
    $QJSON = $_SESSION['QJSON'];
    $loggerInstance->debug(4, $QJSON);
    $QPHP = json_decode($QJSON, TRUE);
    switch ($answer) {
        case 1:
            $loggerInstance->debug(4, "Revaluate with FALSE");
            $sociopath->revaluate($QPHP["NUMBER"], FALSE);
            break;
        case 2:
            $loggerInstance->debug(4, "Revaluate with TRUE");
            $sociopath->revaluate($QPHP["NUMBER"], TRUE);
            break;
        case 3:
            $loggerInstance->debug(4, "Revaluate with NULL");
            $sociopath->revaluate($QPHP["NUMBER"], NULL);
            break;
    }
}
$QJSON = $sociopath->questionOracle();
$_SESSION['QJSON'] = $QJSON;
$QPHP = json_decode($QJSON, TRUE);
if ($QPHP['NEXTEXISTS']) {
    echo $QJSON;
} else {
    echo $sociopath->getCurrentGuessState();
}
