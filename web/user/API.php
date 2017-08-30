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
$validator = new web\lib\common\InputValidation();

// extract request parameters; action is mandatory
if (!isset($_REQUEST['action'])) {
    exit;
}
$action = $_REQUEST['action'];

const LISTOFACTIONS = [
    'listLanguages',
    'listCountries',
    'listIdentityProviders',
    'listAllIdentityProviders',
    'listProfiles', // needs $idp set - abort if not
    'listDevices',
    'generateInstaller', // needs $device and $profile set
    'downloadInstaller', // needs $device and $profile set optional $generatedfor
    'profileAttributes', // needs $profile set
    'sendLogo', // needs $idp and $disco set
    'sendFedLogo', // needs $federation
    'deviceInfo', // needs $device and profile set
    'locateUser',
    'detectOS',
    'orderIdentityProviders',
];

// make sure this is a known action
$action = LISTOFACTIONS[array_search($_REQUEST['action'], LISTOFACTIONS)];
if ($action === FALSE) {
    exit;
}

$idp = FALSE;
$lang = FALSE;
$profile = FALSE;
$federation = FALSE;
$disco = FALSE;
$device = FALSE;
if (isset($_REQUEST['lang'])) {
    $lang = $validator->supportedLanguage($_REQUEST['lang']);
}
if (isset($_REQUEST['device'])) {
    $device = $validator->Device($_REQUEST['device']);
}
if (isset($_REQUEST['idp'])) {
    $idp = $validator->IdP($_REQUEST['idp'])->identifier;
}
if (isset($_REQUEST['profile'])) {
    $profile = $validator->Profile($_REQUEST['profile'])->identifier;
}
if (isset($_REQUEST['federation'])) {
    $federation = $validator->Federation(strtoupper($_REQUEST['federation']))->identifier;
}
if (isset($_REQUEST['disco'])){
    $disco    = (int)$_REQUEST['disco'];
}

$width    = (int)($_REQUEST['width'] ?? 0);
$height   = (int)($_REQUEST['height'] ?? 0);
$sort     = (int)($_REQUEST['sort'] ?? 0);
$location = $_REQUEST['location'] ?? 0;
$generatedfor = $_REQUEST['generatedfor'] ?? 'user';

$API->version = 2;

switch ($action) {
    case 'listLanguages':
        $API->JSON_listLanguages();
        break;
    case 'listCountries':
        $API->JSON_listCountries();
        break;
    case 'listIdentityProviders':
        $API->JSON_listIdentityProviders($federation);
        break;
    case 'listAllIdentityProviders':
        $API->JSON_listIdentityProvidersForDisco();
        break;
    case 'listProfiles': // needs $idp set - abort if not
        if ($idp === FALSE) {
            exit;
        }
        $API->JSON_listProfiles($idp, $sort);
        break;
    case 'listDevices':
        $API->JSON_listDevices($profile);
        break;
    case 'generateInstaller': // needs $device and $profile set
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->JSON_generateInstaller($device, $profile);
        break;
    case 'downloadInstaller': // needs $device and $profile set optional $generatedfor
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->downloadInstaller($device, $profile, $generatedfor);
        break;
    case 'profileAttributes': // needs $profile set
        if ($profile === FALSE) {
            exit;
        }
        $API->JSON_profileAttributes($profile);
        break;
    case 'sendLogo': // needs $idp and $disco set
        if ($idp === FALSE) {
            exit;
        }
        if ($disco == 1) {
            $width = 120;
            $height = 40;
        }
        $API->sendLogo($idp, "idp", $width, $height);
        break;
    case 'sendFedLogo': // needs $federation
        if ($federation === FALSE) {
            exit;
        }
        $API->sendLogo($federation, "federation", $width, $height);
        break;        
    case 'deviceInfo': // needsdevice and profile set
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
        $coordinateArray = NULL;
        if ($location) {
            $coordinateArrayRaw = explode(':', $location);
            $coordinateArray = ['lat' => $coordinateArrayRaw[0], 'lon' => $coordinateArrayRaw[1]];
        }
        $API->JSON_orderIdentityProviders($federation, $coordinateArray);
        break;
}
//$loggerInstance = new \core\common\Logging();
//$loggerInstance->debug(4, "UserAPI action: " . $action . ':' . $id . ':' . $lang . ':' . $profile . ':' . $disco . "\n");
