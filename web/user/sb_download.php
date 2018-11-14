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
$API = new \core\UserAPI();
$loggerInstance = new \core\common\Logging();
$validator = new \web\lib\common\InputValidation();

$profileId = filter_input(INPUT_GET, 'profile', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'profile', FILTER_VALIDATE_INT) ?? -1;
$instId = filter_input(INPUT_GET, 'idp', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'idp', FILTER_VALIDATE_INT) ?? -1;
$device =  filter_input(INPUT_GET, 'device', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'device', FILTER_SANITIZE_STRING) ?? "INVALID";
$generatedFor = $_REQUEST['generatedfor'] ?? 'user';

const VALID_GENERATOR_TARGETS = ['admin', 'user', 'silverbullet'];

if (!in_array($generatedFor, VALID_GENERATOR_TARGETS)) {
    $errorText = "Invalid downloads triggered (not a category in VALID_GENERATOR_TARGETS???)";
    $loggerInstance->debug(2, $errorText);
    print($errorText);
    throw new Exception($errorText);
}

$loggerInstance->debug(4, "download: profile:$profileId; inst:$instId; device:$device\n");

$cleanToken = NULL;
$password = NULL;

if (isset($_SESSION['individualtoken']) && isset($_SESSION['importpassword'])) {
    $cleanToken = $validator->token($_SESSION['individualtoken']);
    // TODO validate that token actually exists and is unused
    $password = $validator->string($_SESSION['importpassword']);
}

// first block will test if the user input was valid.

$profile = $validator->Profile($profileId);

if (!$profile->institution || $profile->institution !== $instId) {
    header("HTTP/1.0 404 Not Found");
    return;
}

// now we generate the installer
try {
    $cleanDevice = $validator->Device($device); // throws an Exception if unknown
    $API->downloadInstaller($cleanDevice, $profile->identifier, $generatedFor, $cleanToken, $password);
} catch (\Exception $e) {
    $skinObject = new \web\lib\user\Skinjob();
    // find our account status page, and bail out if this doesn't work
    $accountPageUrl = $skinObject->findResourceUrl("BASE", "accountstatus/accountstatus.php");
    if ($accountPageUrl === FALSE) {
        throw new Exception("Unable to find our accountstatus.php page.");
    }
    header("Location: ../accountstatus/accountstatus.php?token=" . $cleanToken . "&errorcode=GENERATOR_CONSUMED");
    throw $e;
}
