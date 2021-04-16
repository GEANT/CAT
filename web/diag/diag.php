<?php
/*
 * *****************************************************************************
 * Contributions to is this work were made on behalf of the GÉANT project, a 
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
$admin = filter_input(INPUT_GET, 'admin', FILTER_VALIDATE_INT);
$sp = filter_input(INPUT_GET, 'sp', FILTER_VALIDATE_INT);
$givenRealm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
$auth = new \web\lib\admin\Authentication();
$isauth = 0;
if ($auth->isAuthenticated()) {
    $isauth = 1;
}
if ($admin == 1 && !$isauth) { 
    if ($_SERVER['QUERY_STRING']) {
        $q_el = explode('&', $_SERVER['QUERY_STRING']);
        if (($idx = array_search("admin=1", $q_el)) !== NULL) {
            unset($q_el[$idx]);
            $q_r = preg_replace("/\?.*/", "", $_SERVER['REQUEST_URI']);
            if (count($q_el)) {
                $q_r = $q_r . '?' . implode('&', $q_el);
            }
            $_SERVER['REQUEST_URI'] = $q_r;
        }
    }
    $_SESSION['admin_diag_auth'] = 1;
    $auth->authenticate();
}
if (isset($_SESSION['admin_diag_auth'])) {
   $admin =  1;
   unset($_SESSION['admin_diag_auth']);
}
$Gui = new \web\lib\user\Gui();
$skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $_SESSION['skin'] ?? $fedskin[0] ?? \config\Master::APPEARANCE['skins'][0]);
require "../skins/" . $skinObject->skin . "/diag/diag.php";


