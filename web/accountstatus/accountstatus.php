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
 * Skin selection for user pages
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Core
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$cleanToken = FALSE;
$invitationObject = new core\SilverbulletInvitation("INVALID");
$profile = NULL;
$idp = NULL;
$fed = NULL;

$validator = new \web\lib\common\InputValidation();
$Gui = new \web\lib\user\Gui();

if (isset($_REQUEST['token'])) {
    $recoverToken = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    $cleanToken = $validator->token($recoverToken);
    if ($cleanToken) {
        // check status of this silverbullet token according to info in DB:
        // it can be VALID (exists and not redeemed, EXPIRED, REDEEMED or INVALID (non existent)
        $invitationObject = new core\SilverbulletInvitation($cleanToken);
    }
} elseif (isset($_SERVER['SSL_CLIENT_SAN_Email']) || isset($_SERVER['SSL_CLIENT_SAN_Email_0']) ) {
    // maybe the user authenticated with his client cert? Then pick any of his
    // tokens to go on
    $certname = $_SERVER['SSL_CLIENT_SAN_Email'] ?? $_SERVER['SSL_CLIENT_SAN_Email_0'];
    $certObject = new \core\SilverbulletCertificate($certname);
    $profile = new \core\ProfileSilverbullet($certObject->profileId);
    $allTokens = $profile->userStatus($certObject->userId);
    $invitationObject = $allTokens[0];
    $cleanToken = $invitationObject->invitationTokenString;
}

if ($invitationObject->invitationTokenStatus != \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID) { // determine skin to use based on NROs preference
    $profile = new \core\ProfileSilverbullet($invitationObject->profile, NULL);
    $idp = new \core\IdP($profile->institution);
    $fed = $validator->Federation(strtoupper($idp->federation));
    $fedskin = $fed->getAttributes("fed:desired_skin"); 
}
// ... unless overwritten by direct GET/POST parameter in the request
// ... with last resort being the default skin (first one in the configured skin list is the default)

$statusInfo = ["token" => $cleanToken,
    "invitation_object" => $invitationObject,
    "OS" => $Gui->operatingSystem,];

if ($profile !== NULL) {
    $attributes = $Gui->profileAttributes($profile->identifier);
    $statusInfo["profile"] = $profile;
    $statusInfo["attributes"] = $Gui->profileAttributes($profile->identifier);
    $statusInfo["profile_id"] = $invitationObject->profile;
};

$action = filter_input(INPUT_GET, 'action', FILTER_VALIDATE_INT);
$serialRaw = filter_input(INPUT_GET, 'serial', FILTER_DEFAULT);
$serial = $validator->hugeInteger($serialRaw);

if ($action !== NULL && $action !== FALSE && $action === \web\lib\common\FormElements::BUTTON_DELETE && $serial !== NULL && $serial !== FALSE) {
    if ($statusInfo['invitation_object']->invitationTokenStatus != \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID) {
        $userdata = $profile->userStatus($statusInfo['invitation_object']->userId);
        // if the requested serial belongs to the user, AND it is currently valid, revoke it
        $allcerts = [];
        foreach ($userdata as $content) {
            $allcerts = array_merge($allcerts, $content->associatedCertificates);
        }
        foreach ($allcerts as $onecert) {
            if ($onecert->serial == $serial && $onecert->revocationStatus == 'NOT_REVOKED') {
                print "//REVOKING\n";
                $certObject = new \core\SilverbulletCertificate($serial);
                $certObject->revokeCertificate();
                header("Location: accountstatus.php?token=" . $statusInfo['token']);
                exit;
            }
        }
    }
                    header("Location: accountstatus.php?token=" . $statusInfo['token']);
exit;
}

if ($idp !== NULL) {
    $logo = $idp->getAttributes('general:logo_file');
    $statusInfo["idp"] = $idp;
    $statusInfo["idp_id"] = $idp->identifier;
    $statusInfo["idp_logo"] = (count($logo) == 0 ? 0 : 1);
    $statusInfo["idp_name"] = $idp->name;
    $statusInfo["fed"] = new core\Federation($idp->federation);
}

const KNOWN_ERRORCODES = ["GENERATOR_CONSUMED"];
$errorcode = $_REQUEST['errorcode'] ?? "";
switch ($errorcode) {
    case KNOWN_ERRORCODES[0]:
        $statusInfo['errorcode'] = KNOWN_ERRORCODES[0];
        break;
    default:
        $statusInfo['errorcode'] = NULL;
}
$skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $fedskin[0] ?? CONFIG['APPEARANCE']['skins'][0]);
// and now, serve actual data
include("../skins/" . $skinObject->skin . "/accountstatus/accountstatus.php");
