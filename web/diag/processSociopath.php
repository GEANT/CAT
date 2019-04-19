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

require_once dirname(dirname(__DIR__)) . "/config/_config.php";
\core\CAT::sessionStart();
$loggerInstance = new \core\common\Logging();
$loggerInstance->debug(4, "Sociopath test\n");

$answer = filter_input(INPUT_GET, 'answer', FILTER_VALIDATE_INT);
$sociopath = new \core\diag\Sociopath();
if ($answer > 0) {
    if (isset($_SESSION['LAST_QUESTION'])) {
        $QNUM = $_SESSION['LAST_QUESTION'];
    } else {
        return NULL;
    }
    $loggerInstance->debug(4, $_SESSION['EVIDENCE']['QUESTIONSASKED']);
    $loggerInstance->debug(4, "\nAnswer question " . $QNUM . "\n");
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
$loggerInstance->debug(4, "QPHP\n");
$loggerInstance->debug(4, $QPHP);
if ($QPHP['NEXTEXISTS']) {
    $_SESSION['LAST_QUESTION'] = $QPHP['NUMBER'];
    echo $QJSON;
} else {
    /*$logopath = new \core\diag\Logopath();
    if ($logopath->isEndUserContactUseful()) {
        $loggerInstance->debug(4, "Sociopath End User contact useful");
    } else {
        $loggerInstance->debug(4, "Sociopath End User contact NOT useful");
    }*/
    unset($_SESSION['LAST_QUESTION']);
    echo $sociopath->getCurrentGuessState();
}
