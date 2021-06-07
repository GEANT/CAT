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

/**
 * AJAX backend for the user GUI
 *
 * @package UserAPI
 */
require dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
$API = new \core\UserNetAPI();
$validator = new web\lib\common\InputValidation();
$loggerInstance = new \core\common\Logging();

const LISTOFACTIONS = [
    'listLanguages',
    'listCountries',
    'listIdentityProviders',
    'listAllIdentityProviders',
    'listIdentityProvidersWithProfiles',
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

// make sure this is a known action
$action = $validator->simpleInputFilter('action', 'safe_text');
if (!in_array($action, LISTOFACTIONS)) {
    throw new Exception("Unknown action used.");
}

$langR = $validator->simpleInputFilter('lang', 'safe_text');
$lang = $langR ? $validator->supportedLanguage($langR) : FALSE;
$deviceR = $validator->simpleInputFilter('device', 'safe_text');
$device = $deviceR ? $validator->existingDevice($deviceR) : FALSE;
$idpR = $validator->simpleInputFilter('idp', 'int');
$idp = $idpR ? $validator->existingIdP($idpR)->identifier : FALSE;
$profileR = $validator->simpleInputFilter('profile', 'int');
$profile = $profileR ? $validator->existingProfile($profileR)->identifier : FALSE;
$federationR = $validator->simpleInputFilter('federation', 'safe_text');
$federation = $federationR ? $validator->existingFederation($federationR)->tld : FALSE;
$disco = $validator->simpleInputFilter('disco', 'int');
$width = $validator->simpleInputFilter('width', 'int') ?? 0;
$height = $validator->simpleInputFilter('height', 'int') ?? 0;
$sort = $validator->simpleInputFilter('sort', 'int') ?? 0;
$generatedfor = $validator->simpleInputFilter('generatedfor', 'safe_text') ?? 'user';
$openRoaming = $validator->simpleInputFilter('openroaming', 'int') ?? 0;
$token = $validator->simpleInputFilter('token', 'safe_text');
$idR = $validator->simpleInputFilter('id', 'safe_text');
$id = $idR ? $idR : FALSE;
$loggerInstance->debug(4, "openRoaming:$openRoaming\n");
$loggerInstance->debug(4, $_REQUEST);
switch ($action) {
    case 'listLanguages':
        $API->jsonListLanguages();
        break;
    case 'listCountries':
        $API->jsonListCountries();
        break;
    case 'listIdentityProviders':
        if ($federation === FALSE) {
            $federation = $id ? $validator->existingFederation($id)->tld : FALSE;
        }
        if ($federation === FALSE) { // federation is a mandatory parameter!
            exit;
        }
        $API->jsonListIdentityProviders($federation);
        break;
    case 'listAllIdentityProviders':
        $API->jsonListIdentityProvidersForDisco();
        break;
        case 'listIdentityProvidersWithProfiles':
        $API->jsonListIdentityProvidersWithProfiles();
        break;
    case 'listProfiles': // needs $idp set - abort if not
        if ($idp === FALSE) {
            $idp = $id ? $validator->existingIdP($id)->identifier : FALSE;
        }
        if ($idp === FALSE) {
            exit;
        }
        // this was int-validated, so we can be sure it is an integer
        $API->jsonListProfiles($idp, (int) $sort);
        break;
    case 'listDevices':
        if ($profile === FALSE) {
            $profile = $id ? $validator->existingProfile($id)->identifier : FALSE;
        }
        if ($profile === FALSE) {
            exit;
        }
        $API->jsonListDevices($profile);
        break;
    case 'generateInstaller': // needs $device and $profile set
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->jsonGenerateInstaller($device, $profile, $openRoaming);
        break;
    case 'downloadInstaller': // needs $device and $profile set optional $generatedfor
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->downloadInstaller($device, $profile, $generatedfor, $openRoaming);
        break;
    case 'profileAttributes': // needs $profile set
        if ($profile === FALSE) {
            $profile = $id ? $validator->existingProfile($id)->identifier : FALSE;
        }
        if ($profile === FALSE) {
            exit;
        }
        $API->jsonProfileAttributes($profile);
        break;
    case 'sendLogo': // needs $idp and $disco set
        if ($idp === FALSE) {
            $idp = $id ? $validator->existingIdP($id)->identifier : FALSE;
        }
        if ($idp === FALSE) {
            exit;
        }
        if ($disco == 1) {
            $width = 120;
            $height = 40;
        }
        // those two were int-validated, cast to let SC know
        $API->sendLogo($idp, "idp", (int) $width, (int) $height);
        break;
    case 'sendFedLogo': // needs $federation
        if ($federation === FALSE) {
            if ($idp === FALSE) {
                exit;
            }
            $API->sendLogo($idp, "federation_from_idp", (int) $width, (int) $height);
        } else {
            $API->sendLogo($federation, "federation", (int) $width, (int) $height);
        }
        break;
    case 'deviceInfo': // needsdevice and profile set
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->deviceInfo($device, $profile);
        break;
    case 'locateUser':
        $API->jsonLocateUser();
        break;
    case 'detectOS':
        $API->jsonDetectOS();
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
        $API->jsonOrderIdentityProviders($federation, $coordinateArray);
        break;
    case 'getUserCerts':
        $API->jsonGetUserCerts($token);
        break;
}
