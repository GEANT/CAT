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

/**
 * This page parses federation properties and enters them into the database.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();
$uiElements = new \web\lib\admin\UIElements();

$auth->authenticate();

/// first productname (eduroam CAT), then nomenclature for 'federation'
echo $deco->pageheader(sprintf(_("%s: %s Customisation (submission completed)"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureFed), "FEDERATION");
$my_fed = $validator->existingFederation($_GET['fed_id'], $_SESSION['user']);
if (isset($_POST['submitbutton'])) {
    $submitGiven = filter_input(INPUT_POST, 'submitbutton', FILTER_SANITIZE_STRING);
} else {
    $submitGiven = NULL;
}

if ($submitGiven == web\lib\common\FormElements::BUTTON_SAVE) { // here we go
    $fed_name = $my_fed->name;
    echo "<h1>" . sprintf(_("Submitted attributes for %s '%s'"), $uiElements->nomenclatureFed, $fed_name) . "</h1>";
    echo "<table>";
    echo $optionParser->processSubmittedFields($my_fed, $_POST, $_FILES);
    echo "</table>";

    $loggerInstance = new \core\common\Logging();
    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "FED " . $my_fed->name . " - attributes changed");

    // re-instantiate ourselves... profiles need fresh data

    $my_fed = $validator->existingFederation($_GET['fed_id'], $_SESSION['user']);

    echo "<br/><form method='post' action='overview_federation.php' accept-charset='UTF-8'><button type='submit'>" . _("Continue to dashboard") . "</button></form>";
}
echo $deco->footer();
