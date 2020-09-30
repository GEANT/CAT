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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
require_once \config\Master::AUTHENTICATION['ssp-path-to-autoloader'];

$deco = new \web\lib\admin\PageDecoration();

try {
    $state = SimpleSAML\Auth\State::loadState((string) $_REQUEST['LogoutState'], 'MyLogoutState');
    $ls = $state['saml:sp:LogoutStatus']; /* Only works for SAML SP */
} catch (Exception $except) {
    $ls = ['Code' => 'NOSTATE'];
}

if ($ls['Code'] === 'urn:oasis:names:tc:SAML:2.0:status:Success' && !isset($ls['SubCode'])) {
    /* Successful logout. */
    $url = "https://www.eduroam.org"; // this is the fallback if constructing our own base URL subsequently does not work
    $cutoff = strrpos($_SERVER['PHP_SELF'], "/admin/logout_check.php");
    if ($cutoff !== FALSE) {    
        $substring = substr($_SERVER['PHP_SELF'], 0, $cutoff);
        if ($substring !== FALSE) {
            $url = "//" . htmlspecialchars($_SERVER['SERVER_NAME']) . $substring;
        }
    }
    header("Location: $url");
} else {
    /* Logout failed. Tell the user to close the browser. */
    echo $deco->pageheader(_("Incomplete Logout"), "ADMIN", FALSE);
    echo "<p>" . _("We were unable to log you out of all your sessions. To be completely sure that you are logged out, you need to close your web browser.") . "</p>";
    echo $deco->footer();
}
