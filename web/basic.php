<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/**
 * Skin selection for user pages
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Core
 */

require_once(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("Skinjob.php");
require_once("IdP.php");
require_once("admin/inc/input_validation.inc.php");

if (isset($_REQUEST['idp'])) { // determine skin to use based on NROs preference
    $idp = valid_IdP($_REQUEST['idp']);
    $fed = valid_Fed($idp->federation);
    $fedskin = $fed->getAttributes("fed:desired_skin");
}
// ... unless overwritten by direct GET/POST parameter in the request
// ... with last resort being the default skin (first one in the configured skin list is the default)

$skinObject = new Skinjob( $_REQUEST['skin'] ?? $fedskin[0] ?? CONFIG['APPEARANCE']['skins'][0]);

// and now, serve actual data
include("skins/".$skinObject->skin."/basic.php");