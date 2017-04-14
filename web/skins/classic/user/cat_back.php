<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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

$action = $validator->string($_REQUEST['action']);
$id = $_REQUEST['id'] ?? FALSE;
$lang = FALSE;
if (isset($_REQUEST['lang'])) {
    $lang = $validator->supportedLanguage($langRaw);
}
$profile = FALSE;
if (isset($_REQUEST['profile'])) {
    $profile = $validator->Profile($_REQUEST['profile'])->identifier;
}
$disco = FALSE;
if (isset($_REQUEST['disco'])) {
    $disco = (int)$_REQUEST['disco'];
}
$sort = $_REQUEST['sort'] ?? FALSE;
$generatedfor = $_REQUEST['generatedfor'] ?? 'user';

$loggerInstance = new \core\Logging();
$loggerInstance->debug(4, "cat_back action: " . $action . ':' . $id . ':' . $lang . ':' . $profile . ':' . $disco . "\n");

switch ($action) {
    case 'listLanguages':
        $API->JSON_listLanguages();
        break;
    case 'listCountries':
        $API->JSON_listCountries();
        break;
    case 'listIdentityProviders':
        $API->JSON_listIdentityProviders($id);
        break;
    case 'listAllIdentityProviders':
        $API->JSON_listIdentityProvidersForDisco();
        break;
    case 'listProfiles': // needs $id set - abort if not
        if ($id === FALSE) {
            exit;
        }
        $API->JSON_listProfiles($id, $sort);
        break;
    case 'listDevices':
        $API->JSON_listDevices($id);
        break;
    case 'generateInstaller': // needs $id and $profile set
        if ($id === FALSE || $profile === FALSE) {
            exit;
        }
        $API->JSON_generateInstaller($id, $profile);
        break;
    case 'downloadInstaller': // needs $id and $profile set optional $generatedfor
        if ($id === FALSE || $profile === FALSE) {
            exit;
        }
        $API->downloadInstaller($id, $profile, $generatedfor);
        break;
    case 'profileAttributes': // needs $id set
        if ($id === FALSE) {
            exit;
        }
        $API->JSON_profileAttributes($id);
        break;
    case 'sendLogo': // needs $id and $disco set
        if ($id === FALSE) {
            exit;
        }
        // refers to an *IdP* ID, so let's make sure this is really an IdP
        $validator = new \web\lib\common\InputValidation();
        $API->sendLogo($validator->IdP($id), $disco);
    case 'deviceInfo': // needs $id and profile set
        if ($id === FALSE || $profile === FALSE) {
            exit;
        }
        $API->deviceInfo($id, $profile);
        break;
    case 'locateUser':
        $API->JSON_locateUser();
        break;
    case 'ssss':
        $API->orderIdentityProviders([]);
        break;
}