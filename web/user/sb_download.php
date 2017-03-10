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
 * Download for 
 * This file is obsolete and left for backwards compatibility reasons only
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */
include(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once('../admin/inc/input_validation.inc.php');
$API = new \core\UserAPI();
$loggerInstance = new \core\Logging();

$profileId = $_REQUEST['profile'] ?? FALSE;
$instId = $_REQUEST['idp'] ?? FALSE;
$device = $_REQUEST['device'] ?? FALSE;
$generatedFor = $_REQUEST['generatedfor'] ?? 'user';

const VALID_GENERATOR_TARGETS = ['admin', 'user', 'silverbullet'];

if (!in_array($generatedFor, VALID_GENERATOR_TARGETS)) {
    $errorText = "Invalid downloads triggered (not a category in VALID_GENERATOR_TARGETS???)";
    $loggerInstance->debug(2, $errorText);
    print($errorText);
    throw new Exception($errorText);
}

$loggerInstance->debug(4, "download: profile:$profile_id; inst:$instId; device:$device\n");

$cleanToken = NULL;
$password = NULL;

if (isset($_SESSION['individualtoken']) && isset($_SESSION['importpassword'])) {
    $cleanToken = valid_token($_SESSION['individualtoken']);
    // TODO validate that token actually exists and is unused
    $password = valid_string_db($_SESSION['importpassword']);
}

// first block will test if the user input was valid.

$p = \core\ProfileFactory::instantiate($profileId);

if (!$p->institution || $p->institution !== $instId) {
    header("HTTP/1.0 404 Not Found");
    return;
}

// now we generate the installer
try {
    $API->downloadInstaller($device, $profileId, $generatedFor, $cleanToken, $password);
} catch (\Exception $e) {
    $skinObject = new \web\lib\user\Skinjob("");
    header("Location: " . $skinObject->findResourceUrl("BASE") . "/accountstatus.php?token=" . $cleanToken . "&errorcode=GENERATOR_CONSUMED");
}
