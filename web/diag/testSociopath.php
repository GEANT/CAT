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

$sociopath = new \core\diag\Sociopath();

// with this input, and no questions asked yet, we should get a question related to INFRA_DEVICE:

echo "<pre>";
echo "Testing condition set ";
print_r($workingwith);
echo " with no question yet.\n";
$QJSON = $sociopath->questionOracle();
$QPHP = json_decode($QJSON, TRUE);
print_r($QPHP);
/* echo "Assuming this question gets a Yes, the next guess state is ";
$sociopath->revaluate($QPHP["NUMBER"], TRUE);
print_r(json_decode($sociopath->getCurrentGuessState(), TRUE));
echo "</pre>"; */

echo "Assuming this question gets a No, the next guess state is ";
$sociopath->revaluate($QPHP["NUMBER"], FALSE);
print_r(json_decode($sociopath->getCurrentGuessState(), TRUE));
echo "And now, let's see what the verdict text to display would be: (consists of the basic AREA text plus a lecture on what the user did wrong.\n\n";
echo wordwrap($sociopath->verdictText(\core\diag\AbstractTest::INFRA_DEVICE));
echo "</pre>";

