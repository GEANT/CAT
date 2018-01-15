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
$API = new \core\UserNetAPI();
$validator = new web\lib\common\InputValidation();
$loggerInstance = new \core\common\Logging();

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
    'getUserCerts',
];

function getRequest($varName, $filter) {
    $safeText = ["options"=>["regexp"=>"/^[\w\d-]+$/"]];
    switch ($filter) {
        case 'safe_text':
            $out = filter_input(INPUT_GET, $varName, FILTER_VALIDATE_REGEXP, $safeText) ?? filter_input(INPUT_POST, $varName, FILTER_VALIDATE_REGEXP, $safeText);
            break;
        case 'int':
            $out = filter_input(INPUT_GET, $varName, FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, $varName, FILTER_VALIDATE_INT);
            break;
        default:
            $out = NULL;
            break;
    }
    return $out;
}

// make sure this is a known action
$action = getRequest('action', 'safe_text');
if (!in_array($action, LISTOFACTIONS)) {
    throw new Exception("Unknown action used.");
}

$langR = getRequest('lang', 'safe_text');
$lang = $langR ? $validator->supportedLanguage($langR) : FALSE;
$deviceR = getRequest('device', 'safe_text');
$device = $deviceR ? $validator->Device($deviceR) : FALSE;
$idpR = getRequest('idp', 'int');
$idp = $idpR ? $validator->IdP($idpR)->identifier : FALSE;
$profileR = getRequest('profile', 'int');
$profile = $profileR ? $validator->Profile($profileR)->identifier : FALSE;
$federationR = getRequest('federation', 'safe_text');
$federation = $federationR ? $validator->Federation($deviceR)->tld : FALSE;
$disco = getRequest('disco', 'int');
$width = getRequest('width', 'int') ?? 0;
$height = getRequest('height', 'int') ?? 0;
$sort = getRequest('sort', 'int') ?? 0;
$generatedfor = getRequest('generatedfor', 'safe_text') ?? 'user';
$token = getRequest('token', 'safe_text');

switch ($action) {
    case 'listLanguages':
        $API->JSON_listLanguages();
        break;
    case 'listCountries':
        $API->JSON_listCountries();
        break;
    case 'listIdentityProviders':
        if ($federation === FALSE) { // federation is a mandatory parameter!
            exit;
        }
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
        if ($profile === FALSE) {
            exit;
        }
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
        if ($federation === FALSE) { // is this parameter mandatory? The entire API call is not mentioned in UserAPI.md documentation currently
            $federation = "";
        }
        $API->JSON_orderIdentityProviders($federation, $coordinateArray);
        break;
    case 'getUserCerts':
        $API->JSON_getUserCerts($token);
        break;
}

$loggerInstance->debug(4, "UserAPI action: " . $action . ':' . $lang !== FALSE ? $lang : '' . ':' . $profile . ':' . $device . "\n");
