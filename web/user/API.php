<?php
/***********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php 
/**
 * AJAX backend for the user GUI
 *
 * @package UserAPI
 */
include(dirname(dirname(dirname(__FILE__)))."/config/_config.php");
include_once("UserAPI.php");
include_once("Logging.php");
$API = new UserAPI();

// extract request parameters; action is mandatory
if(!isset($_REQUEST['action']))
   exit;

$action  = $_REQUEST['action'];
$id      = ( isset($_REQUEST['id'])      ? $_REQUEST['id']      : FALSE );
$device  = ( isset($_REQUEST['device'])  ? $_REQUEST['device']  : FALSE );
$lang    = ( isset($_REQUEST['lang'])    ? $_REQUEST['lang']    : FALSE );
$idp     = ( isset($_REQUEST['idp'])     ? $_REQUEST['idp']     : FALSE );
$profile = ( isset($_REQUEST['profile']) ? $_REQUEST['profile'] : FALSE );
$federation = ( isset($_REQUEST['federation']) ? $_REQUEST['federation'] : FALSE );
$disco   = ( isset($_REQUEST['disco'])   ? $_REQUEST['disco']   : FALSE );
$width   = ( isset($_REQUEST['width'])   ? $_REQUEST['width']   : 0 );
$height   = ( isset($_REQUEST['height'])   ? $_REQUEST['height']   : 0 );
$sort    = ( isset($_REQUEST['sort'])    ? $_REQUEST['sort']    : 0 );
$location    = ( isset($_REQUEST['location'])    ? $_REQUEST['location']    : 0 );
$api_version = ( isset($_REQUEST['api_version']) ? $_REQUEST['api_version'] : 1 );
$generatedfor = ( isset($_REQUEST['generatedfor']) ? $_REQUEST['generatedfor'] : 'user' );

/* in order to provide bacwards compatibility, both $id and new named arguments are supported.
   Support for $id will be removed in the futute
 */
    
$API->version = $api_version;

switch ($action) {
    case 'listLanguages':
        $API->JSON_listLanguages();
        break;
    case 'listCountries':
        $API->JSON_listCountries();
        break;
    case 'listIdentityProviders':
        if(! $federation)
           $federation = $id;
        $API->JSON_listIdentityProviders($federation);
        break;
    case 'listAllIdentityProviders':
        $API->JSON_listIdentityProvidersForDisco();
        break;
    case 'listProfiles': // needs $idp set - abort if not
        if(! $idp) 
           $idp = $id;
        if ($idp === FALSE) exit;
        $API->JSON_listProfiles($idp,$sort);
        break;
    case 'listDevices':
        if(! $profile)
           $profile = $id;
        $API->JSON_listDevices($profile);
        break;
    case 'generateInstaller': // needs $id and $profile set
        if(! $device)
            $device = $id;
        if ($device === FALSE || $profile === FALSE) exit;
        $API->JSON_generateInstaller($device, $profile);
        break;
    case 'downloadInstaller': // needs $id and $profile set optional $generatedfor
        if(! $device)
            $device = $id;
        if ($device === FALSE || $profile === FALSE) exit;
        $API->downloadInstaller($device, $profile,$generatedfor);
        break;
    case 'profileAttributes': // needs $id set
        if(! $profile)
           $profile = $id;
        if ($profile === FALSE) exit;
        $API->JSON_profileAttributes($profile);
        break;
    case 'sendLogo': // needs $id and $disco set
        if(! $idp)
           $idp = $id;
        if ($idp === FALSE) exit;
        $API->sendLogo($idp, $disco,$width,$height);
        break;
    case 'deviceInfo': // needs $id and profile set
        if(! $device)
            $device = $id;
        if ($id === FALSE || $profile === FALSE) exit;
        $API->deviceInfo($device, $profile);
        break;
    case 'locateUser':
        $API->JSON_locateUser();
        break;
    case 'detectOS':
        $API->JSON_detectOS();
        break;
    case 'orderIdentityProviders':
        if(! $federation)
           $federation = $id;
         if($location)  {
            $A=explode(':',$location);
            $L = ['lat'=>$A[0],'lon'=>$A[1]];
         } else
            $L = NULL;
        $API->JSON_orderIdentityProviders($federation,$L);
        break;
}
$loggerInstance = new Logging();
$loggerInstance->debug(4,"UserAPI action: ".$action.':'.$id.':'.$lang.':'.$profile.':'.$disco."\n");