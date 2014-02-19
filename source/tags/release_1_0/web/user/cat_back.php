<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php 
/**
 * AJAX backend for the user GUI
 *
 * @package UserGUI
 */
include(dirname(dirname(dirname(__FILE__)))."/config/_config.php");
include_once("GUI.php");
$Gui = new GUI();

// extract request parameters; action is mandatory
if(!isset($_REQUEST['action']))
   exit;

$action  = $_REQUEST['action'];
$id      = ( isset($_REQUEST['id'])      ? $_REQUEST['id']      : FALSE );
$lang    = ( isset($_REQUEST['lang'])    ? $_REQUEST['lang']    : FALSE );
$profile = ( isset($_REQUEST['profile']) ? $_REQUEST['profile'] : FALSE );
$disco   = ( isset($_REQUEST['disco'])   ? $_REQUEST['disco']   : FALSE );
    
debug(4,"cat_back action: ".$action.':'.$id.':'.$lang.':'.$profile.':'.$disco."\n");

switch ($action) {
    case 'listAllIdentityProviders':
        $Gui->JSON_listIdentityProvidersForDisco();
        break;
    case 'listProfiles': // needs $id set - abort if not
        if ($id === FALSE) exit;
        $Gui->JSON_listProfiles($id);
        break;
    case 'generateInstaller': // needs $id and $profile set
        if ($id === FALSE || $profile === FALSE) exit;
        $Gui->JSON_generateInstaller($id, $profile);
        break;
    case 'profileAttributes': // needs $id set
        if ($id === FALSE) exit;
        $Gui->JSON_profileAttributes($id);
        break;
    case 'sendLogo': // needs $id and $disco set
        if ($id === FALSE) exit;
        $Gui->sendLogo($id, $disco);
    case 'deviceInfo': // needs $id and profile set
        if ($id === FALSE || $profile === FALSE) exit;
        $Gui->deviceInfo($id, $profile);
        break;
    case 'locateUser':
        $Gui->JSON_locateUser();
        break;
    case 'ssss':
        $Gui->orderIdentityProviders(array());
        break;
}

?>
