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
 * Skin selection for user pages
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Core
 */
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

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
} elseif (isset($_SERVER['SSL_CLIENT_SAN_Email']) || isset($_SERVER['SSL_CLIENT_SAN_Email_0'])) {
    // maybe the user authenticated with his client cert? Then pick any of his
    // tokens to go on
    $certname = $_SERVER['SSL_CLIENT_SAN_Email'] ?? $_SERVER['SSL_CLIENT_SAN_Email_0'];
    if (preg_match("R$", $_SERVER['SSL_CLIENT_I_DN'])) {
        $certObject = new \core\SilverbulletCertificate($certname, devices\Devices::SUPPORT_EMBEDDED_RSA);
    } else if (preg_match("E$", $_SERVER['SSL_CLIENT_I_DN'])) {
        $certObject = new \core\SilverbulletCertificate($certname, devices\Devices::SUPPORT_EMBEDDED_ECDSA);
    } else {
        throw new Exception("We got an accepted certificate authentication, but can't find the certificate in the database!");
    }
    $profile = new \core\ProfileSilverbullet($certObject->profileId);
    $allTokens = $profile->userStatus($certObject->userId);
    $invitationObject = $allTokens[0];
    $cleanToken = $invitationObject->invitationTokenString;
}

if ($invitationObject->invitationTokenStatus != \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID) { // determine skin to use based on NROs preference
    $profile = new \core\ProfileSilverbullet($invitationObject->profile, NULL);
    $idp = new \core\IdP($profile->institution);
    $fed = $validator->existingFederation(strtoupper($idp->federation));
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
}

$action = filter_input(INPUT_GET, 'action', FILTER_VALIDATE_INT);
$caAndSerial = filter_input(INPUT_GET, 'serial', FILTER_SANITIZE_STRING);

if ($action !== NULL && $action !== FALSE && $action === \web\lib\common\FormElements::BUTTON_DELETE && $caAndSerial !== NULL && $caAndSerial !== FALSE) {
    $tuple = explode(':',$caAndSerial);
    $ca_type = $tuple[0];
    $serial = $tuple[1];
    if ($statusInfo['invitation_object']->invitationTokenStatus != \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID) {
        $userdata = $profile->userStatus($statusInfo['invitation_object']->userId);
        // if the requested serial belongs to the user, AND it is currently valid, revoke it
        $allcerts = [];
        foreach ($userdata as $content) {
            $allcerts = array_merge($allcerts, $content->associatedCertificates);
        }
        foreach ($allcerts as $onecert) {
            if ($onecert->serial == $serial && $onecert->ca_type == $ca_type && $onecert->revocationStatus == 'NOT_REVOKED') {
                print "//REVOKING\n";
                $certObject = new \core\SilverbulletCertificate($serial, $ca_type);
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
$skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $_SESSION['skin'] ?? $fedskin[0] ?? \config\Master::APPEARANCE['skins'][0]);

// and now, serve actual data
require "../skins/" . $skinObject->skin . "/accountstatus/accountstatus.php";
