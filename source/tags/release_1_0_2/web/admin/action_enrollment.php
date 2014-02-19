<?php

/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
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

require_once("inc/auth.inc.php");

$usermgmt = new UserManagement();
$mode = "TOKEN";

$checkval = $usermgmt->checkTokenValidity($_GET['token']);

if (Config::$CONSORTIUM['selfservice_registration'] !== NULL && $_GET['token'] == "SELF-REGISTER") {
    $mode = "SELFSERVICE";
    $federation = Config::$CONSORTIUM['selfservice_registration'];
    $checkval = "OK-NEW";
}

foreach (Config::$CONSORTIUM['registration_API_keys'] as $key => $fed_name)
    if ($_POST['APIKEY'] == $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }

if (!isset($_GET['token']) || ( $checkval != "OK-NEW" && $checkval != "OK-EXISTING")) {
    include "inc/admin_header.php";
    defaultPagePrelude(_("Error creating new IdP binding!"));
    echo "</head><body>";
    productheader();
    if ($checkval == "FAIL-ALREADYCONSUMED") {
        echo "<p>" . _("Sorry... this token has already been used to create an institution. If you got it from a mailing list, probably someone else used it before you.") . "</p>";
    } elseif ($checkval == "FAIL-EXPIRED") {
        echo "<p>" . _("Sorry... this token has expired. Invitation tokens are valid for 24 hours. Please ask your federation administrator for a new one.") . "</p>";
    } else {
        echo "<p>" . _("Sorry... you have come to the enrollment page without a valid token. Are you a nasty person? If not, you should go to <a href='overview_user.php'>your profile page</a> instead.") . "</p>";
    }
    echo "</body></html>";
    exit(1);
} else { // token is valid. Get meta-info and create inst
    // TODO get invitation level and mail, store it as property
    if ($mode == "SELFSERVICE" || $mode == "API") {
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
