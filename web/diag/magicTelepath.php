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
$realm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
$visited = filter_input(INPUT_GET,'visited', FILTER_SANITIZE_STRING);
$nro = filter_input(INPUT_GET,'nro', FILTER_SANITIZE_STRING);
\core\CAT::sessionStart();
$languageObject = new core\common\Language();
$languageObject->setTextDomain("diagonstics");
$telepath = new \core\diag\Telepath($realm, $nro, $visited);
$telepathArray = $telepath->magic();

$returnArray = array();
if (empty($telepathArray)) {
    $returnArray['status'] = 0;
} else {
    $returnArray['status'] = 1;
    $returnArray['realm'] = $realm;
    $returnArray['suspects'] = $telepathArray;
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
