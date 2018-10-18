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

$device = filter_input(INPUT_GET, 'device', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'device', FILTER_SANITIZE_STRING) ?? "INVALID";
$generatedFor = $_REQUEST['generatedfor'] ?? 'user';

const VALID_GENERATOR_TARGETS = ['admin', 'user', 'silverbullet'];

if (!in_array($generatedFor, VALID_GENERATOR_TARGETS)) {
    $errorText = "Invalid downloads triggered (not a category in VALID_GENERATOR_TARGETS???)";
    $loggerInstance->debug(2, $errorText);
    print($errorText);
    throw new Exception($errorText);
}

$rawToken = $_POST['individualtoken'] ?? $_SESSION['individualtoken'] ?? NULL;
$password = $_POST['importpassword'] ?? $_SESSION['importpassword'] ?? NULL;

if ($rawToken === NULL || $password === NULL) {
    throw new Exception("We cannot continue without import password and token value.");
}
// also, syntax checks on token and password
$cleanToken = $validator->token($rawToken);
$cleanPassword = $validator->string($password);

// check actual token validity and associated profile / IdP
$inviteObject = new core\SilverbulletInvitation($cleanToken);
$profileId = $inviteObject->profile;
if ($profileId == 0) { // invalid invitation
    header("HTTP/1.0 404 Not Found");
    return;
}
$profile = $validator->Profile($profileId);

$loggerInstance->debug(4, "download: profile:$profile->identifier; inst:$profile->institution; device:$device\n");

// now we generate the installer
try {
    $cleanDevice = $validator->Device($device); // throws an Exception if unknown
    $API->downloadInstaller($cleanDevice, $profile->identifier, $generatedFor, $cleanToken, $cleanPassword);
} catch (\Exception $e) {
    $skinObject = new \web\lib\user\Skinjob();
    // find our account status page, and bail out if this doesn't work
    $accountPageUrl = $skinObject->findResourceUrl("BASE", "accountstatus/accountstatus.php");
    if ($accountPageUrl === FALSE) {
        throw new Exception("Unable to find our accountstatus.php page.");
    }
    header("Location: ../accountstatus/accountstatus.php?token=" . $cleanToken . "&errorcode=GENERATOR_CONSUMED");
}