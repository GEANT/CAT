<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("inc/common.inc.php");

$auth = new web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

$auth->authenticate();

echo $deco->pageheader(sprintf(_("%s: Federation Customisation (submission completed)"), CONFIG['APPEARANCE']['productname']), "FEDERATION");
$my_fed = $validator->Federation($_GET['fed_id'], $_SESSION['user']);
if (isset($_POST['submitbutton'])) {
    if (( $_POST['submitbutton'] == BUTTON_SAVE) && isset($_POST['option']) && isset($_POST['value'])) { // here we go
        $fed_name = $my_fed->name;
        echo "<h1>" . sprintf(_("Submitted attributes for federation '%s'"), $fed_name) . "</h1>";
        $remaining_attribs = $my_fed->beginflushAttributes();

        echo "<table>";
        $killlist = $optionParser->processSubmittedFields($my_fed, $_POST, $_FILES, $remaining_attribs);
        echo "</table>";
        $my_fed->commitFlushAttributes($killlist);

        $loggerInstance = new \core\Logging();
        $loggerInstance->writeAudit($_SESSION['user'], "MOD", "FED " . $my_fed->name . " - attributes changed");

        // re-instantiate ourselves... profiles need fresh data

        $my_fed = $validator->Federation($_GET['fed_id'], $_SESSION['user']);

        echo "<br/><form method='post' action='overview_federation.php' accept-charset='UTF-8'><button type='submit'>" . _("Continue to dashboard") . "</button></form>";
    }
}
echo $deco->footer();
