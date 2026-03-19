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

require_once dirname(dirname(__DIR__))."/config/_config.php";

\core\CAT::sessionStart();
$auth = new \web\lib\admin\Authentication();
$validator = new \web\lib\common\InputValidation();
$auth->authenticate();
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("core");
$uiElements = new \web\lib\admin\UIElements();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

if (isset($_GET['profile_id'])) {
    $profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
} else {
    throw new Exception("Missing profile id");
}
if ($editMode === 'nouser') {
    throw new Exception("Unauthorised user");
}

$res = $profile->openroamingReadinessTest();
$html = "<table class='or_table'>";
foreach ($res as $entry) {
    $levelString = \core\AbstractProfile::OVERALL_OPENROAMING_INDEX[$entry['level']];
    $iconData = $uiElements->iconData($levelString);
    $orStateIcon = $uiElements->catIcon(($iconData));
    $html .= "<tr><td>".$orStateIcon."</td><td>".$entry['explanation']."</td></tr>";
}
$html .= '</table>';
$out = json_encode(['state'=>$levelString, 'html'=>$html]);
header("Content-type: application/json; utf-8");
print($out);


