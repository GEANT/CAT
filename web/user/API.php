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

/**
 * AJAX backend for the user GUI
 *
 * @package UserAPI
 */
include(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
$API = new \core\UserAPI();

// extract request parameters; action is mandatory
if (!isset($_REQUEST['action'])) {
    exit;
}

$action = $_REQUEST['action'];
$id = $_REQUEST['id'] ?? FALSE;
$device = $_REQUEST['device'] ?? FALSE;
$lang = $_REQUEST['lang'] ?? FALSE;
$idp = $_REQUEST['idp'] ?? FALSE;
$fed = $_REQUEST['fed'] ?? FALSE;
$profile = $_REQUEST['profile'] ?? FALSE;
$federation = $_REQUEST['federation'] ?? FALSE;
$disco = $_REQUEST['disco'] ?? FALSE;
$width = $_REQUEST['width'] ?? 0;
$height = $_REQUEST['height'] ?? 0;
$sort = $_REQUEST['sort'] ?? 0;
$location = $_REQUEST['location'] ?? 0;
$api_version = $_REQUEST['api_version'] ?? 1;
$generatedfor = $_REQUEST['generatedfor'] ?? 'user';

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
        if (!$federation) {
            $federation = $id;
        }
        $API->JSON_listIdentityProviders($federation);
        break;
    case 'listAllIdentityProviders':
        $API->JSON_listIdentityProvidersForDisco();
        break;
    case 'listProfiles': // needs $idp set - abort if not
        if (!$idp) {
            $idp = $id;
        }
        if ($idp === FALSE) {
            exit;
        }
        $API->JSON_listProfiles($idp, $sort);
        break;
    case 'listDevices':
        if (!$profile) {
            $profile = $id;
        }
        $API->JSON_listDevices($profile);
        break;
    case 'generateInstaller': // needs $id and $profile set
        if (!$device) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->JSON_generateInstaller($device, $profile);
        break;
    case 'downloadInstaller': // needs $id and $profile set optional $generatedfor
        if (!$device) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->downloadInstaller($device, $profile, $generatedfor);
        break;
    case 'profileAttributes': // needs $id set
        if (!$profile) {
            $profile = $id;
        }
        if ($profile === FALSE) {
            exit;
        }
        $API->JSON_profileAttributes($profile);
        break;
    case 'sendLogo': // needs $id and $disco set
        if (!$idp) {
            $idp = $id;
        }
        if ($idp === FALSE) {
            exit;
        }
        $validator = new \web\lib\common\InputValidation();
        $API->sendLogo($validator->IdP($idp), $disco, $width, $height);
        break;
    case 'sendFedLogo': // needs $id and $disco set
        if (!$fed) {
            $fed = $id;
        }
        if ($fed === FALSE) {
            exit;
        }
        $validator = new \web\lib\common\InputValidation();
        $API->sendFedLogo($validator->Federation($fed), $width, $height);
        break;        
    case 'deviceInfo': // needs $id and profile set
        if (!$device) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->deviceInfo($device, $profile);
        break;
    case 'locateUser':
        $API->JSON_locateUser();
        break;
    case 'detectOS':
        $API->JSON_detectOS();
        break;
    case 'orderIdentityProviders':
        if (!$federation) {
            $federation = $id;
        }
        $coordinateArray = NULL;
        if ($location) {
            $coordinateArrayRaw = explode(':', $location);
            $coordinateArray = ['lat' => $coordinateArrayRaw[0], 'lon' => $coordinateArrayRaw[1]];
        }
        $API->JSON_orderIdentityProviders($federation, $coordinateArray);
        break;
}
$loggerInstance = new \core\Logging();
$loggerInstance->debug(4, "UserAPI action: " . $action . ':' . $id . ':' . $lang . ':' . $profile . ':' . $disco . "\n");
