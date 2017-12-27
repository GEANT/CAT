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
    if (!isset($_SESSION['EVIDENCE']['QUESTIONSASKED'])) {
        $QNUM = 1;
    } else {
        $QNUM = count($_SESSION['EVIDENCE']['QUESTIONSASKED']) + 1;
    }
    $loggerInstance->debug(4, $_SESSION['EVIDENCE']);
    $loggerInstance->debug(4, "Answer question ".$QNUM."\n");
    switch ($answer) {
        case 1:
            $loggerInstance->debug(4, "Revaluate with FALSE");
            $sociopath->revaluate($QNUM, FALSE);
            break;
        case 2:
            $loggerInstance->debug(4, "Revaluate with TRUE");
            $sociopath->revaluate($QNUM, TRUE);
            break;
        case 3:
            $loggerInstance->debug(4, "Revaluate with NULL");
            $sociopath->revaluate($QNUM, NULL);
            break;
    }
}
$QJSON = $sociopath->questionOracle();
$QPHP = json_decode($QJSON, TRUE);
if ($QPHP['NEXTEXISTS']) {
    echo $QJSON;
} else {
    $logopath = new \core\diag\Logopath();
    if ($logopath->isEndUserContactUseful()) {
        $loggerInstance->debug(4, "Sociopath End User contact useful");
    } else {
        $loggerInstance->debug(4, "Sociopath End User contact NOT useful");
    }
    echo $sociopath->getCurrentGuessState();
}
