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

/*
 * Class autoloader invocation, should be included prior to any other code at the entry points to the application
 */
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$auth->authenticate();
$uiElements = new \web\lib\admin\UIElements();
$validator = new web\lib\common\InputValidation();
$deco = new \web\lib\admin\PageDecoration();
$loggerInstance = new core\common\Logging();

$inst = $validator->existingIdP(filter_input(INPUT_GET, 'inst_id'));

// this page may have been called for the first time, when the profile does not
// actually exist in the DB yet. If so, we will need to create it first.
if (!isset($_REQUEST['profile_id'])) {
    // someone might want to trick himself into this page by sending an inst_id but
    // not having permission for silverbullet. Sanity check that the fed in question
    // does allow SB and that the IdP doesn't have any non-SB profiles
    if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] != "LOCAL") {
        throw new Exception("We were told to create a new SB profile, but this deployment is not configured for SB!");
    }

    $inst = $validator->existingIdP(filter_input(INPUT_GET, 'inst_id'));
    if ($inst->profileCount() > 0) {
        foreach ($inst->listProfiles() as $oneProfile) {
            $profileEapMethod = $oneProfile->getEapMethodsInOrderOfPreference()[0];
            if ($profileEapMethod->getIntegerRep() == core\common\EAP::INTEGER_SILVERBULLET) {
                throw new Exception("We were told to create a new SB profile, but the inst in question already has at least one SB profile!");
            }
        }
    }
    $fed = new \core\Federation($inst->federation);
    $allowSb = $fed->getAttributes("fed:silverbullet");
    if (count($allowSb) == 0) {
        throw new Exception("We were told to create a new SB profile, but this " . \config\ConfAssistant::CONSORTIUM['nomenclature_federation'] . " does not allow SB at all!");
    }
    // okay, new SB profiles are allowed. 
    // but is there a support:email attribute on inst level?
    if (count($inst->getAttributes("support:email")) == 0) {
        // user shouldn't have gotten that far; tricked his way in. No need to be verbose.
        throw new Exception("Attempt to create a new SB profile, but the inst does not have a support:email attribute!");
    }
    // Create one.
    $newProfile = $inst->newProfile(core\AbstractProfile::PROFILETYPE_SILVERBULLET);
    // and modify the REQUEST_URI to add the new profile ID
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] . "&profile_id=" . $newProfile->identifier;
    $_GET['profile_id'] = $newProfile->identifier;
    $profile = $newProfile;
} else {
    $profile = $validator->existingProfile(filter_input(INPUT_GET, "profile_id"));
}
// at this point, we should really have a SB profile in our hands, not a RADIUS one
if (!($profile instanceof \core\ProfileSilverbullet)) {
    throw new Exception("Despite utmost care to get a SB profile, we got a RADIUS profile?!");
}

assert($profile instanceof \core\ProfileSilverbullet);

$displaySendStatus = "NOSTIPULATION";

$formtext = "<form enctype='multipart/form-data' action='edit_silverbullet.php?inst_id=$inst->identifier&amp;profile_id=$profile->identifier' method='post' accept-charset='UTF-8'>";

$invitationObject = NULL;
if (isset($_POST['token'])) {
    $invitationObject = new core\SilverbulletInvitation($validator->token(filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING)));
}

if (isset($_POST['command'])) {
    switch ($_POST['command']) {
        case \web\lib\common\FormElements::BUTTON_CLOSE:
            header("Location: overview_org.php?inst_id=" . $inst->identifier);
            break;
        case \web\lib\common\FormElements::BUTTON_TERMSOFUSE_ACCEPTED:
            if (isset($_POST['agreement']) && $_POST['agreement'] == 'true') {
                $profile->addAttribute("hiddenprofile:tou_accepted", NULL, 1);
                // re-instantiate profile with the new info
                $profile = core\ProfileFactory::instantiate($profile->identifier, $inst);
                // at this point, we should really have a SB profile in our hands, not a RADIUS one
                if (!($profile instanceof \core\ProfileSilverbullet)) {
                    throw new Exception("Despite utmost care to get a SB profile, we got a RADIUS profile?!");
                }
            }
            break;
        case \web\lib\common\FormElements::BUTTON_ADDUSER:
            if (isset($_POST['username']) && isset($_POST['userexpiry'])) {
                $properName = $validator->syntaxConformUser($_POST['username']);
                try {
                    $properDate = new DateTime($_POST['userexpiry']);
                } catch (Exception $e) {
                    // it's okay if this fails. Just bogus input from the user
                    // just don't do anything
                    break;
                }
                $profile->addUser($properName, $properDate);
            }
            if (isset($_FILES['newusers']) && $_FILES['newusers']['size'] > 0) {
                $content = fopen($_FILES['newusers']['tmp_name'], "r");
                if ($content === FALSE) {
                    // seems we can't work with this file for some reason. Ignore.
                    continue;
                }
                $oneLine = TRUE;
                while ($oneLine !== FALSE) {
                    $oneLine = fgets($content);
                    if ($oneLine === FALSE) {
                        break;
                    }
                    $elements = explode(',', $oneLine);
                    // our format is: username, expiry, numberOfActivations (optional)
                    if (count($elements) < 2) {
                        break;
                    }
                    $properName = $validator->syntaxConformUser($elements[0]);
                    $properDate = new DateTime($elements[1] . " 00:00:00");
                    $numberOfActivations = $elements[2] ?? 5;
                    $number = $validator->integer($numberOfActivations);
                    if ($number === FALSE) { // invalid input received, default to sane
                        $number = 5;
                    }

                    $newId = $profile->addUser($properName, $properDate);
                    core\SilverbulletInvitation::createInvitation($profile->identifier, $newId, $number);
                }
                fclose($content);
            }
            break;
        case \web\lib\common\FormElements::BUTTON_CHANGEUSEREXPIRY:
            if (isset($_POST['userexpiry']) && isset($_POST['userid'])) {
                $properId = $validator->integer($_POST['userid']);
                if ($properId === FALSE) { // not a real user ID
                    continue;
                }
                try {
                    $properDate = new DateTime($_POST['userexpiry']);
                } catch (Exception $e) {
                    // do nothing, just ignore the bogus request
                    break;
                }
                $profile->setUserExpiryDate($properId, $properDate);
            }
            break;
        case \web\lib\common\FormElements::BUTTON_REVOKEINVITATION:
            if (isset($_POST['invitationtoken'])) {
                $filteredToken = $validator->token(filter_input(INPUT_POST, 'invitationtoken'));
                $invitationObject = new core\SilverbulletInvitation($filteredToken);
                $invitationObject->revokeInvitation();
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_REVOKECREDENTIAL:
            if (isset($_POST['certSerial']) && isset($_POST['certAlgo'])) {
                $certSerial = $validator->integer(filter_input(INPUT_POST, 'certSerial', FILTER_SANITIZE_STRING));
                if ($certSerial === FALSE) {
                    continue;
                }
                $certAlgo = $validator->string($_POST['certAlgo']);
                if ($certAlgo != devices\Devices::SUPPORT_EMBEDDED_RSA && $certAlgo != devices\Devices::SUPPORT_EMBEDDED_ECDSA) {
                    continue;
                }
                $certObject = new \core\SilverbulletCertificate($certSerial, $certAlgo);
                $certObject->revokeCertificate();
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_DEACTIVATEUSER:
            if (isset($_POST['userid'])) {
                $properId = $validator->integer(filter_input(INPUT_POST, 'userid'));
                if ($properId === FALSE) { // bogus user ID, ignore
                    continue;
                }
                $profile->deactivateUser($properId);
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_DELETE:
            if (isset($_POST['userid'])) {
                $properId = $validator->integer(filter_input(INPUT_POST, 'userid'));
                if ($properId === FALSE) { // bogus user ID, ignore
                    continue;
                }
                $profile->deleteUser($properId);
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;    
        case \web\lib\common\FormElements::BUTTON_NEWINVITATION:
            if (isset($_POST['userid']) && isset($_POST['invitationsquantity'])) {
                $properId = $validator->integer($_POST['userid']);
                $number = $validator->integer($_POST['invitationsquantity']);
                if ($properId === FALSE || $number === FALSE) { // bogus inputs, ignore
                    continue;
                }
                core\SilverbulletInvitation::createInvitation($profile->identifier, $properId, $number);
            }
            break;
        case \web\lib\common\FormElements::BUTTON_ACKUSERELIGIBILITY:
            if (isset($_POST['acknowledge']) && $_POST['acknowledge'] == 'true') {
                $profile->refreshEligibility();
            }
            break;
        case \web\lib\common\FormElements::BUTTON_SENDINVITATIONMAILBYCAT:
            if (!isset($_POST['address']) || $invitationObject === NULL) {
                break;
            }
            $properEmail = $validator->email(filter_input(INPUT_POST, 'address'));
            if (is_bool($properEmail)) {
                $domainStatus = \core\common\OutsideComm::MAILDOMAIN_INVALID;
                $displaySendStatus = "EMAIL-NOTSENT";
                break;
            }
            $domainStatus = \core\common\OutsideComm::mailAddressValidSecure($properEmail);
            // send mail if all is good, otherwise UI a warning and confirmation
            switch ($domainStatus) {
                case \core\common\OutsideComm::MAILDOMAIN_NO_STARTTLS:
                    // warn and ask for confirmation unless already confirmed
                    if (!isset($_POST['insecureconfirm']) || $_POST['insecureconfirm'] != "CONFIRM") {
                        echo $deco->pageheader(_("Insecure mail domain!"), "ADMIN-IDP-USERS");
                        echo "<p>" . sprintf(_("The mail domain of the mail address <strong>%s</strong> is not secure: some or all of the mail servers are not accepting encrypted connections (no consistent support for STARTTLS)."), $properEmail) . "</p>";
                        echo "<p>" . _("The invitation would need to be sent in cleartext across the internet, and can possibly be read and abused by anyone in transit.") . "</p>";
                        echo "<p>" . _("Do you want the system to send this mail anyway?") . "</p>";
                        echo $formtext;
                        echo "<button type='submit' class='delete'>" . _("DO NOT SEND") . "</button>";
                        echo "</form>";
                        echo $formtext;
                        echo "<input type='hidden' name='command' value='" . \web\lib\common\FormElements::BUTTON_SENDINVITATIONMAILBYCAT . "'</>";
                        echo "<input type='hidden' name='address' value='$properEmail'</>";
                        echo "<input type='hidden' name='token' value='" . $invitationObject->invitationTokenString . "'</>";
                        echo "<input type='hidden' name='insecureconfirm' value='CONFIRM'/>";
                        echo "<button type='submit'>" . _("Send anyway.") . "</button>";
                        echo "</form>";
                        echo $deco->footer();
                        exit;
                    }
                // otherwise (insecure confirmed), intentional fall through to send the mail
                case \core\common\OutsideComm::MAILDOMAIN_STARTTLS:
                    $result = $invitationObject->sendByMail($properEmail);
                    if ($result["SENT"]) {
                        $displaySendStatus = "EMAIL-SENT";
                    } else {
                        $displaySendStatus = "EMAIL-NOTSENT";
                    }
                    break;
                default:
                    $displaySendStatus = "EMAIL-NOTSENT";
            }
            break;
        case \web\lib\common\FormElements::BUTTON_SENDINVITATIONSMS:
            if (!isset($_POST['smsnumber']) || $invitationObject === NULL) {
                break;
            }

            $number = $validator->sms($_POST['smsnumber']);
            if (is_bool($number)) {
                break;
            }
            $sent = $invitationObject->sendBySms($number);
            switch ($sent) {
                case core\common\OutsideComm::SMS_SENT:
                    $displaySendStatus = "SMS-SENT";
                    break;
                case core\common\OutsideComm::SMS_NOTSENT:
                    $displaySendStatus = "SMS-NOTSENT";
                    break;
                case core\common\OutsideComm::SMS_FRAGEMENTSLOST:
                    $displaySendStatus = "SMS-FRAGMENT";
                    break;
                default:
            }

            break;
        default:
            throw new Exception("Unknown button action in Silverbullet!");
    }
}

$allUsers = $profile->listAllUsers();
$activeUsers = $profile->listActiveUsers();


echo $deco->defaultPagePrelude(sprintf(_('Managing %s users'), \core\ProfileSilverbullet::PRODUCTNAME ));

?>
<script src='js/option_expand.js' type='text/javascript'></script>
<script src='../external/jquery/jquery.js' type='text/javascript'></script>
<script src='../external/jquery/jquery-ui.js' type='text/javascript'></script>
<script src='../external/jquery/jquery-migrate.js' type='text/javascript'></script>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>

<?php // https://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript ?>
<script>
    function clipboardCopy(user) {
    var copyTextArea = document.querySelector('.identifiedtokenarea-' + user);
    copyTextArea.select();
    try {
    var successful = document.execCommand('copy');
    var msg = successful ? 'successful' : 'unsuccessful';
    console.log('Copying text command was ' + msg);
    } catch (err) {
    console.log('Unable to copy to clipboard.');
    }
    }
    $(document).ready(function () {
    $(function () {
    $(".tabbed").tabs();
    });
    });
</script>
<link rel='stylesheet' type='text/css' href='../external/jquery/jquery-ui.css' />
<link rel='stylesheet' type='text/css' href='css/silverbullet.css' />
</head>

<body>
    <?php
    echo $deco->productHeader("ADMIN-IDP-USERS");
    ?>
    <img src='../resources/images/icons/loading51.gif' id='spin' alt='loading...' style='position:absolute;left: 50%; top: 50%; transform: translate(-100px, -50px); display:none; z-index: 100;'>
    <?php echo $uiElements->instLevelInfoBoxes($inst); ?>
    <div class='infobox'>
        <h2><?php $tablecaption = sprintf(_('Current %s users'), \core\ProfileSilverbullet::PRODUCTNAME); echo $tablecaption;?></h2>
        <table>
            <caption><?php echo $tablecaption;?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value");?></th>
            </tr>

            <tr>
                <td><strong><?php echo _("Assigned Realm"); ?></strong></td><td><?php echo $profile->realm; ?></td>
            </tr>
            <tr>
                <td><strong><?php echo _("Total number of active users allowed"); ?></strong></td><td><?php echo $profile->getAttributes("internal:silverbullet_maxusers")[0]['value']; ?></td>
            </tr>
            <tr>
                <td><strong><?php echo _("Number of active users"); ?></strong></td><td><?php echo count($activeUsers); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo _("Number of inactive users"); ?></strong></td><td><?php echo count($allUsers) - count($activeUsers); ?></td>
            </tr>
        </table>
    </div>
    <?php
    $boundaryPre = "<div class='ca-summary'><table>";
    $boundaryPost = "</table></div>";
    switch ($displaySendStatus) {
        case "NOSTIPULATION":
            break;
        case "EMAIL-SENT":
            echo $boundaryPre . $uiElements->boxOkay(_("The e-mail was sent successfully."), _("E-mail OK."), FALSE) . $boundaryPost;
            break;
        case "EMAIL-NOTSENT":
            echo $boundaryPre . $uiElements->boxError(_("The e-mail was NOT sent."), _("E-mail not OK."), FALSE) . $boundaryPost;
            break;
        case "SMS-SENT":
            echo $boundaryPre . $uiElements->boxOkay(_("The SMS was sent successfully."), _("SMS OK."), FALSE) . $boundaryPost;
            break;
        case "SMS-NOTSENT":
            echo $boundaryPre . $uiElements->boxOkay(_("The SMS was NOT sent."), _("SMS not OK."), FALSE) . $boundaryPost;
            break;
        case "SMS-FRAGMENT":
            echo $boundaryPre . $uiElements->boxWarning(_("Only a fragment of the SMS was sent. You should re-send it."), _("SMS Fragment."), FALSE) . $boundaryPost;
            break;
    }
    ?>
    <div class="sb-editable-block">
        <fieldset>
            <legend>
                <strong><?php echo sprintf(_('Manage %s users'), \core\ProfileSilverbullet::PRODUCTNAME); ?></strong>
            </legend>
            <!-- table with actual user details ... -->
            <?php
            $bufferCurrentUsers = "<table class='sb-user-table' style='max-width:1920px;'>
                <tr class='sb-title-row'>
                    <td>" . _("User") . "</td>
                    <td>" . _("Token/Certificate details") . "</td>
                    <td>" . _("User/Token Expiry") . "</td>
                    <td>" . _("Actions") . "</td>
                </tr>";
            $bufferPreviousUsers = "<table class='sb-user-table' style='max-width:1920px;'>
                <tr class='sb-title-row'>
                    <td>" . _("User") . "</td>
                    <td>" . _("Certificate details") . "</td>
                    <td>" . _("User Expiry") . "</td>
                    <td>" . _("Actions") . "</td>
                </tr>";

            natsort($allUsers);
            $internalUserCount = 0;
            foreach ($allUsers as $oneUserId => $oneUserName) {
                $expiryDate = $profile->getUserExpiryDate($oneUserId);
                if (isset($activeUsers[$oneUserId]) || (new DateTime() < new DateTime($expiryDate))) {
                    $outputBuffer = "bufferCurrentUsers";
                } else {
                    $outputBuffer = "bufferPreviousUsers";
                }
                $userStatus = $profile->userStatus($oneUserId);
                $allCerts = [];
                $validCerts = [];
                $tokensWithoutCerts = [];
                foreach ($userStatus as $oneInvitationObject) {
                    if (count($oneInvitationObject->associatedCertificates) == 0 || $oneInvitationObject->invitationTokenStatus == core\SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
                        $tokensWithoutCerts[] = $oneInvitationObject;
                    }
                    $allCerts = array_merge($allCerts, $oneInvitationObject->associatedCertificates);
                }

                // show all info about the user
                ${$outputBuffer} .= "<tr class='sb-user-row'>
                    <td>$oneUserName</td>
                    <td>";
                // list of certificates for the user
                // we need to translate the device id to readable device name
                $textActiveCerts = "";
                $textRevokedCerts = "";
                $textExpiredCerts = "";
                $countRevoked = 0;
                $countExpired = 0;


                foreach ($allCerts as $oneCert) {
                    switch ($oneCert->status) {
                        case core\SilverbulletCertificate::CERTSTATUS_REVOKED:
                            $style = "style='background-color:#F0C0C0;' ";
                            $buttonStyle = "height:22px; margin-top:7px; text-align:center;";
                            $buttonText = _("REVOKED");
                            break;
                        case core\SilverbulletCertificate::CERTSTATUS_EXPIRED:
                            $style = "style='background-color:lightgrey;'";
                            $buttonStyle = "height:22px; margin-top:7px; text-align:center;";
                            $buttonText = _("EXPIRED");
                            break;
                        default:
                            $validCerts[] = $oneCert;
                            $style = "";
                            $buttonStyle = "";
                            $buttonText = "";
                    }
                    $display = empty(devices\Devices::listDevices()[$oneCert->device]['display']) ? $oneCert->device : devices\Devices::listDevices()[$oneCert->device]['display'];

                    $bufferText = "<div class='sb-certificate-summary ca-summary' $style>
                                    <div class='sb-certificate-details'>" . _("Device:") . " " . $display .
                            "<br>" . _("Serial Number:") . "&nbsp;" . dechex($oneCert->serial) .
                            "<br>" . _("CN:") . "&nbsp;" . explode('@', $oneCert->username)[0] . "@…" .
                            "<br>" . _("Expiry:") . "&nbsp;" . $oneCert->expiry .
                            "<br>" . _("Issued:") . "&nbsp;" . $oneCert->issued .
                            "</div>" .
                            "<div style='text-align:right;padding-top: 5px; $buttonStyle'>";

                    if ($buttonText == "") {
                        $bufferText .= $formtext
                                . "<input type='hidden' name='certSerial' value='" . $oneCert->serial . "'/>"
                                . "<input type='hidden' name='certAlgo' value='" . $oneCert->ca_type . "'/>"
                                . "<button type='submit' "
                                . "name='command' "
                                . "value='" . \web\lib\common\FormElements::BUTTON_REVOKECREDENTIAL . "' "
                                . "class='delete' "
                                . "onclick='return confirm(\"" . sprintf(_("The device in question will stop functioning with %s. The revocation cannot be undone. Are you sure you want to do this?"), \config\ConfAssistant::CONSORTIUM['display_name']) . "\")'>"
                                . _("Revoke")
                                . "</button>"
                                . "</form>";
                    } else {
                        $bufferText .= $buttonText;
                    }
                    $bufferText .= "</div></div>";

                    // add to the respective category
                    switch ($oneCert->status) {
                        case core\SilverbulletCertificate::CERTSTATUS_REVOKED:
                            $textRevokedCerts .= $bufferText;
                            $countRevoked += 1;
                            break;
                        case core\SilverbulletCertificate::CERTSTATUS_EXPIRED:
                            $textExpiredCerts .= $bufferText;
                            $countExpired += 1;
                            break;
                        default:
                            $textActiveCerts .= $bufferText;
                    }
                }
                // wrap the revoked and expired certs in a div that is hidden by default
                if ($textRevokedCerts !== "") {
                    $textRevokedCerts = "<span style='text-decoration: underline;' id='$oneUserId-revoked-heading' onclick='document.getElementById(\"$oneUserId-revoked-certs\").style.display = \"block\"; document.getElementById(\"$oneUserId-revoked-heading\").style.display = \"none\";'>" . sprintf(ngettext("(show %d revoked certificate)", "(show %d revoked certificates)", $countRevoked), $countRevoked) . "</span><div id='$oneUserId-revoked-certs' style='display:none;'>" . $textRevokedCerts . "</div>";
                }
                if ($textExpiredCerts !== "") {
                    $textExpiredCerts = "<span style='text-decoration: underline;' id='$oneUserId-expired-heading' onclick='document.getElementById(\"$oneUserId-expired-certs\").style.display = \"block\"; document.getElementById(\"$oneUserId-expired-heading\").style.display = \"none\";'>" . sprintf(ngettext("(show %d expired certificate)", "(show %d expired certificates)", $countExpired), $countExpired) . "</span><div id='$oneUserId-expired-certs' style='display:none;'>" . $textExpiredCerts . "</div>";
                }
                // and push out the HTML
                ${$outputBuffer} .= $textActiveCerts . "<br/>" . $textExpiredCerts . " " . $textRevokedCerts . "</td>";
                $tokenHtmlBuffer = "";
                $hasOnePendingInvite = FALSE;
                foreach ($tokensWithoutCerts as $invitationObject) {
                    switch ($invitationObject->invitationTokenStatus) {
                        case core\SilverbulletInvitation::SB_TOKENSTATUS_VALID:
                        case core\SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED:
                            $hasOnePendingInvite = TRUE;
                            $tokenHtmlBuffer .= "<tr class='sb-certificate-row'><td></td>";
                            $jsEncodedBody = str_replace('\n', '%0D%0A', str_replace('"', '', json_encode($invitationObject->invitationMailBody())));
                            $tokenHtmlBuffer .= "<td>";
                            $tokenHtmlBuffer .= sprintf(_("The invitation token %s is ready for sending! Choose how to send it:"), "<input type='text' readonly='readonly' style='background-color:lightgrey;' size='60' value='" . $invitationObject->link() . "' name='token' class='identifiedtokenarea-" . $invitationObject->identifier . "'>(…)<br/>");
                            $tokenHtmlBuffer .= "<table>
                                    <tr><td style='vertical-align:bottom;'>" . _("E-Mail:") . "</td><td>
                                    $formtext
                                <input type='hidden' value='" . $invitationObject->invitationTokenString . "' name='token'><br/>
                                <input type='text' name='address' id='address-$invitationObject->identifier'/>
                                <button type='button' onclick='window.location=\"mailto:\"+document.getElementById(\"address-$invitationObject->identifier\").value+\"?subject=" . $invitationObject->invitationMailSubject() . "&amp;body=$jsEncodedBody\"; return false;'>" . _("Local mail client") . "</button>
                                <button type='submit' name='command' onclick='document.getElementById(\"spin\").style.display =\"block\"' value='" . \web\lib\common\FormElements::BUTTON_SENDINVITATIONMAILBYCAT . "'>" . _("Send with CAT") . "</button>
                                    </form>
                                    </td></tr>
                                    <tr><td style='vertical-align:bottom;'>" . _("SMS:") . "</td><td>
                                    $formtext
                                    <input type='hidden' value='" . $invitationObject->invitationTokenString . "' name='token'><br/>
                                    <input type='text' name='smsnumber' />
				<button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_SENDINVITATIONSMS . "'>" . _("Send in SMS...") . "</button>
                                    </form>
				</td></tr>
                                    <tr><td style='vertical-align:bottom;'>" . _("Manual:") . "</td><td>
				<button type='button' class='clipboardButton' onclick='clipboardCopy(" . $invitationObject->identifier . ");'>" . _("Copy to Clipboard") . "</button>
                                    <form style='display:inline-block;' method='post' action='inc/displayQRcode.inc.php' onsubmit='popupQRWindow(this); return false;' accept-charset='UTF-8'>
                                    <input type='hidden' value='" . $invitationObject->invitationTokenString . "' name='token'><br/>
                                      <button type='submit'>" . _("Display QR code") . "</button>
                                  </form>
                                        </td></tr>
                                        
                                </table>
                                </td>";
                            $tokenHtmlBuffer .= "<td>" . _("Expiry Date:") . " " . $invitationObject->expiry . " UTC<br>" . _("Activations remaining:") . " " . sprintf(_("%d of %d"), $invitationObject->activationsRemaining, $invitationObject->activationsTotal) . "</td>";
                            $tokenHtmlBuffer .= "<td>"
                                    . $formtext
                                    . "<input type='hidden' name='invitationtoken' value='" . $invitationObject->invitationTokenString . "'/>"
                                    . "<button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_REVOKEINVITATION . "' class='delete'>" . _("Revoke") . "</button></form>"
                                    . "</td></tr>";
                            break;
                        case core\SilverbulletInvitation::SB_TOKENSTATUS_EXPIRED:
                        case core\SilverbulletInvitation::SB_TOKENSTATUS_REDEEMED:
                            break;
                        default: // ??? INVALID - not possible
                            $tokenHtmlBuffer .= "<td>INTERNAL ERROR - token state is INVALID?</td>";
                            $tokenHtmlBuffer .= "<td></td>";
                            $tokenHtmlBuffer .= "<td></td>";
                    }

                    $internalUserCount++;
                }
                ${$outputBuffer} .= "<td>$formtext
                    <div class='sb-date-container' style='min-width: 200px;'>
                        <span><input type='text' maxlength='19' class='sb-date-picker' name='userexpiry' value='" . $profile->getUserExpiryDate($oneUserId) . "'>&nbsp;(UTC)</span>
                    </div>
                    <input type='hidden' name='userid' value='$oneUserId'/>
                    <button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_CHANGEUSEREXPIRY . "'>" . _("Update") . "</button>
                    </form>
                </td>
                <td>
                    <div class='sb-user-buttons'>";

                if ($hasOnePendingInvite || count($validCerts) > 0) {
                    $deletionText = sprintf(_("All of the currently active devices will stop functioning with %s. This cannot be undone. While the user can be re-activated later, they will then need to be re-provisioned with new invitation tokens. Are you sure you want to do this?"), \config\ConfAssistant::CONSORTIUM['display_name']);
                    ${$outputBuffer} .= $formtext . "
                                    <input type='hidden' name='userid' value='$oneUserId'/>
                                    <button type='submit' "
                            . "name='command' "
                            . "value='" . \web\lib\common\FormElements::BUTTON_DEACTIVATEUSER . "' "
                            . "class='delete' "
                            . ( count($validCerts) > 0 ? "onclick='return confirm(\"" . $deletionText . "\")' " : "" )
                            . ">"
                            . _("Deactivate User")
                            . "</button>
                                </form>";
                }
                ${$outputBuffer} .= "<form method='post' action='inc/userStats.inc.php?inst_id=" . $profile->institution . "&amp;profile_id=" . $profile->identifier . "&amp;user_id=$oneUserId' onsubmit='popupStatsWindow(this); return false;' accept-charset='UTF-8'>
                    <button type='submit'>" . _("Show Authentication Records") . "</button>
                </form>";
                if (new DateTime() < new DateTime($expiryDate)) { // current user, allow sending new token
                    ${$outputBuffer} .= $formtext . "
                    <input type='hidden' name='userid' value='$oneUserId'/>
                    <button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_NEWINVITATION . "'>" . _("New Invitation") . "</button>
                    <label>" . _("Activations:") . "
                        <input type='text' name='invitationsquantity' value='5' maxlength='3' style='width: 30px;'/>
                    </label>
                    </form>";
                } elseif (count($profile->getUserAuthRecords($oneUserId)) == 0) { // previous user; if there are NO authentication records, allow full deletion - otherwise, need to keep user trace for abuse handling
                    ${$outputBuffer} .= $formtext . "
                    <input type='hidden' name='userid' value='$oneUserId'/>
                    <button type='submit' class='delete' name='command' value='" . \web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Delete User") . "</button>
                    </form>";
                }
                ${$outputBuffer} .= "</div>
    </td>
    </tr>
    <!-- one tr for each invitation -->
    $tokenHtmlBuffer";
            }
            $bufferCurrentUsers .= "</table>";
            $bufferPreviousUsers .= "</table>";
            ?>
            <!-- ... ends here -->
            <div class="tabbed" id="listusers">
                <ul>
                    <li>
                        <a href="#tabs-1"><?php echo _("Current Users"); ?></a>
                    </li>
                    <li>
                        <a href="#tabs-2"><?php echo _("Previous Users"); ?></a>
                    </li>
                </ul>
                <div id="tabs-1"><?php echo $bufferCurrentUsers; ?></div>
                <div id="tabs-2"><?php echo $bufferPreviousUsers; ?></div>
            </div>
            <div style="padding: 20px;">
                <?php
                if (count($allUsers) > 0 && false) { // false because this restriction is currently not in effect and thus no UI is needed for it.
                    $acknowledgeText = sprintf(_('You need to acknowledge that the created accounts are still valid within the next %s days.'
                                    . ' If all accounts shown as active above are indeed still valid, please check the box below and push "Save".'
                                    . ' If any of the accounts are stale, please deactivate them by pushing the corresponding button before doing this.'), \config\ConfAssistant::SILVERBULLET['gracetime'] ?? core\ProfileSilverbullet::SB_ACKNOWLEDGEMENT_REQUIRED_DAYS);

                    echo $formtext . "<div style='padding-bottom: 20px;'>"
                    . "
                    <p>$acknowledgeText</p>
                    <input type='checkbox' name='acknowledge' value='true'>
                    <label>" . sprintf(_("I have verified that all configured users are still eligible for %s."),\config\ConfAssistant::CONSORTIUM['display_name']) . "</label>
                </div>
                <button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_ACKUSERELIGIBILITY . "'>" . _("Save") . "</button></form>";
                }
                ?>
            </div>
        </fieldset>
    </div>
    <!--Add new user and user import forms -->
    <div class="tabbed" id="tabs">
        <ul>
            <li>	
                <a href="#tabs-3"><?php echo _("Add new user"); ?></a>
            </li>
            <li>
                <a href="#tabs-4"><?php echo _("Import users from CSV file"); ?></a>
            </li>            
        </ul>
        <!--adding manual -->
        <div id="tabs-3">
            <?php echo $formtext; ?>
            <div class="sb-add-new-user">
                <label for="username"><?php echo _("Please enter a username of your choice and user expiry date to create a new user:"); ?></label>
                <span style="margin: 5px 0px 10px 0px;">
                    <input type="text" name="username" id="username">
                    <input type="text" maxlength="19" class="sb-date-picker" name="userexpiry" value="yyyy-MM-dd HH:MM:SS"/>(UTC)
                </span>
                <button type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_ADDUSER ?>"><?php echo _("Add new user"); ?></button>
            </div>
            </form>
        </div>
        <!--CSV -->
        <div id="tabs-4">
            <div>
                <?php echo $formtext; ?>
                <div class="sb-add-new-user">
                    <p><?php echo _("Comma separated values should be provided in CSV file: username, expiration date 'yyyy-mm-dd', number of tokens (optional):"); ?></p>
                    <div style="margin: 5px 0px 10px 0px;">
                        <input type="file" name="newusers">
                    </div>
                    <button type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_ADDUSER ?>"><?php echo _("Import users"); ?></button>
                </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    if (count($profile->getAttributes("hiddenprofile:tou_accepted")) == 0) {
        //Appending terms of use popup
        ?>

        <div id="sb-popup-message" >
            <div id="overlay"></div>
            <div id="msgbox">
                <div style="top: 100px;">
                    <div class="graybox">
                        <h1><?php echo sprintf(_("%s - Terms of Use"), core\ProfileSilverbullet::PRODUCTNAME); ?></h1>
                        <div class="containerbox" style="position: relative;">
                            <hr>
                            <?php echo $profile->termsAndConditions; ?>
                            <hr>
                            <?php echo $formtext; ?>
                            <div style="position: relative; padding-bottom: 5px;">
                                <input type="checkbox" name="agreement" value="true"> <label><?php echo _("I have read and agree to the terms."); ?></label>
                            </div>
                            <button type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_TERMSOFUSE_ACCEPTED ?>"><?php echo _("Continue"); ?></button>
                            <button class="delete" type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_CLOSE ?>"><?php echo _("Abort"); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <form action="overview_org.php?inst_id=<?php echo $inst->identifier; ?>" method="POST">
        <p>
            <button type='submit' name='submitbutton' value="nomatter"><?php echo sprintf(_("Back to %s page"), $uiElements->nomenclatureIdP); ?></button>
        </p>
    </form>
    <?php
    echo $deco->footer();
    