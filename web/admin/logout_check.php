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
require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);

$deco = new \web\lib\admin\PageDecoration();

$state = SimpleSAML_Auth_State::loadState((string) $_REQUEST['LogoutState'], 'MyLogoutState');
$ls = $state['saml:sp:LogoutStatus']; /* Only works for SAML SP */
if ($ls['Code'] === 'urn:oasis:names:tc:SAML:2.0:status:Success' && !isset($ls['SubCode'])) {
    /* Successful logout. */
    $url = "//" . htmlspecialchars($_SERVER['SERVER_NAME']) . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/logout_check.php"));
    header("Location: $url");
} else {
    /* Logout failed. Tell the user to close the browser. */
    echo $deco->pageheader(_("Incomplete Logout"), "ADMIN", FALSE);
    echo "<p>" . _("We were unable to log you out of all your sessions. To be completely sure that you are logged out, you need to close your web browser.") . "</p>";
    echo $deco->footer();
}