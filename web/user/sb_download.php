<?php
/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
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
?>
<?php

/**
 * Download silverbullet installers
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */
require dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
$API = new \core\UserAPI();
$loggerInstance = new \core\common\Logging();
$validator = new \web\lib\common\InputValidation();

$device = filter_input(INPUT_GET, 'device', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'device', FILTER_SANITIZE_STRING) ?? "INVALID";
$generatedFor = $_REQUEST['generatedfor'] ?? 'user';
$openRoaming = 0;

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
$profile = $validator->existingProfile($profileId);

$loggerInstance->debug(4, "download: profile:$profile->identifier; inst:$profile->institution; device:$device\n");

// now we generate the installer
try {
    $cleanDevice = $validator->existingDevice($device); // throws an Exception if unknown
    $API->downloadInstaller($cleanDevice, $profile->identifier, $generatedFor, $openRoaming, $cleanToken, $cleanPassword);
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
