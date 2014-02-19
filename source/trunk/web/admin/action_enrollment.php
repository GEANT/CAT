<?php

/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("UserManagement.php");
require_once("CAT.php");
require_once("Federation.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/auth.inc.php");
authenticate();

$usermgmt = new UserManagement();
$mode = "TOKEN";

$checkval = $usermgmt->checkTokenValidity($_GET['token']);

if (Config::$CONSORTIUM['selfservice_registration'] !== NULL && $_GET['token'] == "SELF-REGISTER") {
    $mode = "SELFSERVICE";
    $federation = Config::$CONSORTIUM['selfservice_registration'];
    $checkval = "OK-NEW";
}

if (!isset($_GET['token']) || ( $checkval != "OK-NEW" && $checkval != "OK-EXISTING")) {
    pageheader(_("Error creating new IdP binding!"), "ADMIN-IDP");
    echo "<h1>"._("Error creating new IdP binding!")."</h1>";
    if ($checkval == "FAIL-ALREADYCONSUMED") {
        echo "<p>" . _("Sorry... this token has already been used to create an institution. If you got it from a mailing list, probably someone else used it before you.") . "</p>";
    } elseif ($checkval == "FAIL-EXPIRED") {
        echo "<p>" . _("Sorry... this token has expired. Invitation tokens are valid for 24 hours. Please ask your federation administrator for a new one.") . "</p>";
    } else {
        echo "<p>" . _("Sorry... you have come to the enrollment page without a valid token. Are you a nasty person? If not, you should go to <a href='overview_user.php'>your profile page</a> instead.") . "</p>";
    }
    footer();
    exit(1);
} else { // token is valid. Get meta-info and create inst
    // TODO get invitation level and mail, store it as property
    if ($mode == "SELFSERVICE") {
        $fed = new Federation($federation);
        $newidp = new IdP($fed->newIdP($_SESSION['user'], "FED", $mode));
        CAT::writeAudit($_SESSION['user'], "MOD", "IdP " . $newidp->identifier . " - $mode registration");
    } else {
        $newidp = $usermgmt->createIdPFromToken($_GET['token'], $_SESSION['user']);
        $usermgmt->invalidateToken($_GET['token']);
        CAT::writeAudit($_SESSION['user'], "MOD", "IdP " . $newidp->identifier . " - Token used and invalidated");
    };
}
if ($checkval == "OK-EXISTING")
    header("Location: overview_user.php");
else
    header("Location: edit_idp.php?inst_id=$newidp->identifier&wizard=true");
