<?php

/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once(Config::$AUTHENTICATION['ssp-path-to-autoloader']);

require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");

$state = SimpleSAML_Auth_State::loadState((string) $_REQUEST['LogoutState'], 'MyLogoutState');
$ls = $state['saml:sp:LogoutStatus']; /* Only works for SAML SP */
if ($ls['Code'] === 'urn:oasis:names:tc:SAML:2.0:status:Success' && !isset($ls['SubCode'])) {
    /* Successful logout. */
    $url = $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/logout_check.php"));
    if ($_SERVER['HTTPS'] == "on")
        $url = "https://" . $url;
    else
        $url = "http://" . $url;

    header("Location: $url");
} else {
    /* Logout failed. Tell the user to close the browser. */
    pageheader(_("Incomplete Logout"), "ADMIN", FALSE);
    echo "<p>" . _("We were unable to log you out of all your sessions. To be completely sure that you are logged out, you need to close your web browser.") . "</p>";
    footer();
}
?>