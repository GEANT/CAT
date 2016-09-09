<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Federation.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("Logging.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_parse.inc.php");

require_once("inc/auth.inc.php");

pageheader(sprintf(_("%s: Federation Customisation (submission completed)"), Config::$APPEARANCE['productname']), "FEDERATION");
$my_fed = valid_Fed($_GET['fed_id'], $_SESSION['user']);
if (isset($_POST['submitbutton'])) {
    if (( $_POST['submitbutton'] == BUTTON_SAVE) && isset($_POST['option']) && isset($_POST['value'])) { // here we go
        $fed_name = $my_fed->name;
        echo "<h1>" . sprintf(_("Submitted attributes for federation '%s'"), $fed_name) . "</h1>";
        $remaining_attribs = $my_fed->beginflushAttributes();

        echo "<table>";
        $killlist = processSubmittedFields($my_fed, $_POST, $_FILES, $remaining_attribs);
        echo "</table>";
        $my_fed->commitFlushAttributes($killlist);

        $loggerInstance = new Logging();
        $loggerInstance->writeAudit($_SESSION['user'], "MOD", "FED " . $my_fed->name . " - attributes changed");

        // re-instantiate ourselves... profiles need fresh data

        $my_fed = valid_Fed($_GET['fed_id'], $_SESSION['user']);

        echo "<br/><form method='post' action='overview_federation.php' accept-charset='UTF-8'><button type='submit'>" . _("Continue to dashboard") . "</button></form>";
    }
}
footer();