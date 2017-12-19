<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/*
 * Class autoloader invocation, should be included prior to any other code at the entry points to the application
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/core/phpqrcode.php");
const QRCODE_PIXELS_PER_SYMBOL = 12;

$auth = new \web\lib\admin\Authentication();
$auth->authenticate();

$uiElements = new \web\lib\admin\UIElements();
$validator = new web\lib\common\InputValidation();
$deco = new \web\lib\admin\PageDecoration();
$loggerInstance = new core\common\Logging();

$inst = $validator->IdP(filter_input(INPUT_GET, 'inst_id'));
// this page may have been called for the first time, when the profile does not
// actually exist in the DB yet. If so, we will need to create it first.
if (!isset($_REQUEST['profile_id'])) {
    // someone might want to trick himself into this page by sending an inst_id but
    // not having permission for silverbullet. Sanity check that the fed in question
    // does allow SB and that the IdP doesn't have any non-SB profiles
    if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT_SILVERBULLET'] != "LOCAL") {
        throw new Exception("We were told to create a new SB profile, but this deployment is not configured for SB!");
    }

    $inst = $validator->IdP(filter_input(INPUT_GET, 'inst_id'));
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
        throw new Exception("We were told to create a new SB profile, but this " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation'] . " does not allow SB at all!");
    }
    // okay, new SB profiles are allowed. 
    // but is there a support:email attribute on inst level?
    if (count($inst->getAttributes("support:email")) == 0) {
        // user shouldn't have gotten that far; tricked his way in. No need to be verbose.
        throw new Exception("Attempt to create a new SB profile, but the inst does not have a support:email attribute!");
    }
    // Create one.
    $newProfile = $inst->newProfile("SILVERBULLET");
    // and modify the REQUEST_URI to add the new profile ID
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] . "&profile_id=" . $newProfile->identifier;
    $_GET['profile_id'] = $newProfile->identifier;
    $profile = $newProfile;
} else {
    $profile = $validator->Profile(filter_input(INPUT_GET, "profile_id"));
}
// at this point, we should really have a SB profile in our hands, not a RADIUS one
if (!($profile instanceof \core\ProfileSilverbullet)) {
    throw new Exception("Despite utmost care to get a SB profile, we got a RADIUS profile?!");
}

assert($profile instanceof \core\ProfileSilverbullet);

$displaySendStatus = "NOSTIPULATION";

$formtext = "<form enctype='multipart/form-data' action='edit_silverbullet.php?inst_id=$inst->identifier&profile_id=$profile->identifier' method='post' accept-charset='UTF-8'>";

if (isset($_POST['command'])) {
    switch ($_POST['command']) {
        case \web\lib\common\FormElements::BUTTON_TERMSOFUSE:
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
                $properName = $validator->User($_POST['username']);
                $properDate = new DateTime($_POST['userexpiry'] . " 00:00:00");
                $profile->addUser($properName, $properDate);
            }
            if (isset($_FILES['newusers']) && $_FILES['newusers']['size'] > 0) {
                $content = fopen($_FILES['newusers']['tmp_name'], "r");
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
                    $properName = $validator->User($elements[0]);
                    $properDate = new DateTime($elements[1] . " 00:00:00");
                    $number = $elements[2] ?? 1;

                    $newId = $profile->addUser($properName, $properDate);
                    $profile->createInvitation($newId, $validator->integer($number));
                }
            }
            break;
        case \web\lib\common\FormElements::BUTTON_CHANGEUSEREXPIRY:
            if (isset($_POST['userexpiry']) && isset($_POST['userid'])) {
                $properId = $validator->User($_POST['userid']);
                $properDate = new DateTime($_POST['userexpiry'] . " 00:00:00");
                $profile->setUserExpiryDate($properId, $properDate);
            }
            break;
        case \web\lib\common\FormElements::BUTTON_REVOKEINVITATION:
            if (isset($_POST['invitationid'])) {
                $filteredId = $validator->token(filter_input(INPUT_POST, 'invitationid'));
                $profile->revokeInvitation($filteredId);
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_REVOKECREDENTIAL:
            if (isset($_POST['certSerial'])) {
                $certSerial = $validator->integer(filter_input(INPUT_POST, 'certSerial'));
                $profile->revokeCertificate($certSerial);
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_DEACTIVATEUSER:
            if (isset($_POST['userid'])) {
                $properId = $validator->User(filter_input(INPUT_POST, 'userid'));
                $profile->deactivateUser($properId);
                sleep(1); // make sure the expiry timestamps of invitations and certs are at least one second in the past
            }
            break;
        case \web\lib\common\FormElements::BUTTON_NEWINVITATION:
            if (isset($_POST['userid']) && isset($_POST['invitationsquantity'])) {
                $properId = $validator->User($_POST['userid']);
                $number = $validator->integer($_POST['invitationsquantity']);
                $profile->createInvitation($properId, $number);
            }
            break;
        case \web\lib\common\FormElements::BUTTON_ACKUSERELIGIBILITY:
            if (isset($_POST['acknowledge']) && $_POST['acknowledge'] == 'true') {
                $profile->refreshEligibility();
            }
            break;
        case \web\lib\common\FormElements::BUTTON_SENDINVITATIONMAILBYCAT:
            if (!isset($_POST['address']) || !isset($_POST['token'])) {
                break;
            }
            $mail = \core\common\OutsideComm::mailHandle();
            $invitationToken = \core\ProfileSilverbullet::generateTokenLink($_POST['token']);
            $properEmail = $validator->email(filter_input(INPUT_POST, 'address'));
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
                        echo "<input type='hidden' name='token' value='" . $validator->token(filter_input(INPUT_POST, 'token')) . "'</>";
                        echo "<input type='hidden' name='insecureconfirm' value='CONFIRM'/>";
                        echo "<button type='submit'>" . _("Send anyway.") . "</button>";
                        echo "</form>";
                        echo $deco->footer();
                        exit;
                    }
                // otherwise (insecure confirmed), intentional fall through to send the mail
                case \core\common\OutsideComm::MAILDOMAIN_STARTTLS:
                    $bytestream = $uiElements->pngInjectConsortiumLogo(\QRcode::png($invitationToken, FALSE, QR_ECLEVEL_Q, QRCODE_PIXELS_PER_SYMBOL), QRCODE_PIXELS_PER_SYMBOL);
                    $mail->FromName = sprintf(_("%s Invitation System"), CONFIG['APPEARANCE']['productname']);
                    $mail->Subject = $profile->invitationMailSubject();
                    $mail->Body = $profile->invitationMailBody($invitationToken);
                    $mail->addStringAttachment($bytestream, "qr-code-invitation.png", "base64", "image/png");
                    $mail->addAddress($properEmail);
                    if ($mail->send()) {
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
            if (!isset($_POST['smsnumber']) || !isset($_POST['token'])) {
                break;
            }
            $number = str_replace(' ', '', str_replace(".", "", str_replace("+", "", $_POST['smsnumber'])));
            if (!is_numeric($number)) {
                break;
            }
            $tokenlink = \core\ProfileSilverbullet::generateTokenLink($validator->token($_POST['token']));
            $sent = core\common\OutsideComm::sendSMS($number, "Your eduroam access is ready! Click here to continue: $tokenlink (on Android, install the app 'eduroam CAT' before that!)");
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

echo $deco->defaultPagePrelude(_(sprintf(_('Managing %s users'), $uiElements->nomenclature_inst)));
?>
<script src='js/option_expand.js' type='text/javascript'></script>
<script src='../external/jquery/jquery.js' type='text/javascript'></script>
<script src='../external/jquery/jquery-ui.js' type='text/javascript'></script>
<script src='../external/jquery/jquery-migrate.js' type='text/javascript'></script>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
<?php // https://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript ?>
<script type='text/javascript'>
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
            $("#tabs").tabs();
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
    <div class='infobox'>
        <h2><?php echo sprintf(_('Current %s users'), $uiElements->nomenclature_inst); ?></h2>
        <table>
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
    <div>
        <?php
        switch ($displaySendStatus) {
            case "NOSTIPULATION":
                break;
            case "EMAIL-SENT":
                echo $uiElements->boxOkay(_("The e-mail was sent successfully."), _("E-mail OK."), TRUE);
                break;
            case "EMAIL-NOTSENT":
                echo $uiElements->boxError(_("The e-mail was NOT sent."), _("E-mail not OK."), TRUE);
                break;
            case "SMS-SENT":
                echo $uiElements->boxOkay(_("The SMS was sent successfully."), _("SMS OK."), TRUE);
                break;
            case "SMS-NOTSENT":
                echo $uiElements->boxOkay(_("The SMS was NOT sent."), _("SMS not OK."), TRUE);
                break;
            case "SMS-FRAGMENT":
                echo $uiElements->boxWarning(_("Only a fragment of the SMS was sent. You should re-send it."), _("SMS Fragment."), TRUE);
                break;
        }
        ?>
    </div>
    <div class="sb-editable-block">
        <fieldset>
            <legend>
                <strong><?php echo sprintf(_('Manage %s users'), $uiElements->nomenclature_inst); ?></strong>
            </legend>
            <!-- table with actual user details ... -->
            <table cellpadding="5" style="max-width:1920px;">
                <tr class="sb-title-row">
                    <td><?php echo _("User"); ?></td>
                    <td><?php echo _("Token/Certificate details"); ?></td>
                    <td><?php echo _("User/Token Expiry"); ?></td>
                    <td><?php echo _("Actions"); ?></td>
                </tr>
                <?php
                natsort($allUsers);
                $internalUserCount = 0;
                foreach ($allUsers as $oneUserId => $oneUserName) {
                    $userStatus = $profile->userStatus($oneUserId);
                    $allCerts = [];
                    $validCerts = [];
                    $tokensWithoutCerts = [];
                    foreach ($userStatus as $oneToken) {
                        if (count($oneToken['cert_status']) == 0 || $oneToken['status'] == core\ProfileSilverbullet::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
                            $tokensWithoutCerts[] = $oneToken;
                        }
                        if (count($oneToken['cert_status']) > 0) {
                            $allCerts = array_merge($allCerts, $oneToken['cert_status']);
                        }
                    }

                    // show all info about the user
                    ?>
                    <tr class='sb-user-row'>
                        <td><?php echo $oneUserName; ?></td>
                        <td>
                            <!-- list of certificates for the user-->
                            <?php
                            foreach ($allCerts as $oneCert) {
                                switch ($oneCert['status']) {
                                    case core\ProfileSilverbullet::SB_CERTSTATUS_REVOKED:
                                        $style = "style:'background-color:#F0C0C0;' ";
                                        $buttonStyle = "style:'height:22px; margin-top:7px; text-align:center;'";
                                        $buttonText = _("REVOKED");
                                        break;
                                    case core\ProfileSilverbullet::SB_CERTSTATUS_EXPIRED:
                                        $style = "style:'background-color:lightgrey;'";
                                        $buttonStyle = "style:'height:22px; margin-top:7px; text-align:center;'";
                                        $buttonText = _("EXPIRED");
                                        break;
                                    default:
                                        $validCerts[] = $oneCert;
                                        $style = "";
                                        $buttonStyle = "";
                                        $buttonText = "";
                                }
                                ?>

                                <div class="sb-certificate-summary ca-summary">
                                    <div class="sb-certificate-details" <?php echo $style; ?> ><?php echo _("Device:") . " " . $oneCert['device']; ?>
                                        <br><?php echo _("Serial Number:") . "&nbsp;" . dechex($oneCert['serial']); ?>
                                        <br><?php echo _("CN:") . "&nbsp;" . explode('@', $oneCert['name'])[0] . "@…"; ?>
                                        <br><?php echo _("Expiry:") . "&nbsp;" . $oneCert['expiry']; ?>
                                        <br><?php echo _("Issued:") . "&nbsp;" . $oneCert['issued']; ?>
                                    </div>
                                    <div style="text-align:right;padding-top: 5px; <?php echo $buttonStyle; ?>">
                                        <?php
                                        if ($buttonText == "") {
                                            echo "$formtext"
                                            . "<input type='hidden' name='certSerial' value='" . $oneCert['serial'] . "'/>"
                                            . "<button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_REVOKECREDENTIAL . "' class='delete'>" . _("Revoke") . "</button>"
                                            . "</form>";
                                        } else {
                                            echo $buttonText;
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </td>

                        <?php
                        $tokenHtmlBuffer = "";
                        $hasOnePendingInvite = FALSE;
                        foreach ($tokensWithoutCerts as $tokenWithoutCert) {
                            switch ($tokenWithoutCert['status']) {
                                case core\ProfileSilverbullet::SB_TOKENSTATUS_VALID:
                                case core\ProfileSilverbullet::SB_TOKENSTATUS_PARTIALLY_REDEEMED:
                                    $hasOnePendingInvite = TRUE;
                                    $tokenHtmlBuffer .= "<tr class='sb-certificate-row'><td></td>";
                                    $link = \core\ProfileSilverbullet::generateTokenLink($tokenWithoutCert['value']);
                                    $jsEncodedBody = str_replace('\n', '%0D%0A', str_replace('"', '', json_encode($profile->invitationMailBody($link))));
                                    $tokenHtmlBuffer .= "<td>
                                
                                    The invitation token <input type='text' readonly='readonly' color='grey' size='60' value='$link' name='token' class='identifiedtokenarea-" . $tokenWithoutCert['db_id'] . "'>(…)<br/> is ready for sending! Choose how to send it:
                                    <table>
                                    <tr><td style='vertical-align:bottom;'>E-Mail:</td><td>
                                    $formtext
                                <input type='hidden' value='" . $tokenWithoutCert['value'] . "' name='token'><br/>
                                <input type='text' name='address' id='address'/>
                                <button type='button' id='sb-compose-email-client' onclick='window.location=\"mailto:\"+document.getElementById(\"address\").value+\"?subject=" . $profile->invitationMailSubject() . "&body=$jsEncodedBody\"; return false;'>" . _("Local mail client") . "</button>
                                <button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_SENDINVITATIONMAILBYCAT . "'>Send with CAT</button>
                                    </form>
                                    </td></tr>
                                    <tr><td style='vertical-align:bottom;'>SMS:</td><td>
                                    $formtext
                                    <input type='hidden' value='" . $tokenWithoutCert['value'] . "' name='token'><br/>
                                    <input type='text' name='smsnumber' id='smsnumber'/>
				<button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_SENDINVITATIONSMS . "'>" . _("Send in SMS...") . "</button>
                                    </form>
				</td></tr>
                                    <tr><td style='vertical-align:bottom;'>Manual:</td><td>
				<button type='button' class='clipboardButton' onclick='clipboardCopy(" . $tokenWithoutCert['db_id'] . ");'>" . _("Copy to Clipboard") . "</button>
                                    <form style='display:inline-block;' method='post' action='inc/displayQRcode.inc.php' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                    <input type='hidden' value='" . $tokenWithoutCert['value'] . "' name='token'><br/>
                                      <button type='submit'>" . _("Display QR code") . "</button>
                                  </form>
                                        </td></tr>
                                        
                                </table>
                                </form>
                                </td>";
                                    $tokenHtmlBuffer .= "<td>" . _("Expiry Date:") . " " . $tokenWithoutCert['expiry'] . "<br>" . _("Activations remaining:") . " " . sprintf(_("%d of %d"), $tokenWithoutCert['activations_remaining'], $tokenWithoutCert['activations_total']) . "</td>";
                                    $tokenHtmlBuffer .= "<td>"
                                            . $formtext
                                            . "<input type='hidden' name='invitationid' value='" . $tokenWithoutCert['db_id'] . "'/>"
                                            . "<button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_REVOKEINVITATION . "' class='delete'>Revoke</button></form>"
                                            . "</td></tr>";
                                    break;
                                case core\ProfileSilverbullet::SB_TOKENSTATUS_EXPIRED:
                                case core\ProfileSilverbullet::SB_TOKENSTATUS_REDEEMED:
                                    break;
                                default: // ??? INVALID - not possible
                                    $tokenHtmlBuffer .= "<td>INTERNAL ERROR - token state is INVALID?</td>";
                                    $tokenHtmlBuffer .= "<td></td>";
                                    $tokenHtmlBuffer .= "<td></td>";
                            }

                            $internalUserCount++;
                        }
                        ?>

                        <td>
                            <?php echo $formtext; ?>
                            <div class="sb-date-container">
                                <input type="text" maxlength="10" id="sb-date-picker-1" class="sb-date-picker" name="userexpiry" value="<?php echo $profile->getUserExpiryDate($oneUserId); ?>">
                                <button class="sb-date-button" type="button">▼</button>
                            </div>
                            <input type="hidden" name="userid" value="<?php echo $oneUserId; ?>"/>
                            <button type="submit" id="updateexpiry" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_CHANGEUSEREXPIRY ?>">Update</button>
                            </form>
                        </td>
                        <td>
                            <div class="sb-user-buttons">
                                <?php
                                if ($hasOnePendingInvite || count($validCerts) > 0) {
                                    echo $formtext . "
                                    <input type='hidden' name='userid' value='$oneUserId'/>
                                    <button type='submit' id='userdel' name='command' value='" . \web\lib\common\FormElements::BUTTON_DEACTIVATEUSER . "' class='delete'>" . _("Deactivate User") . "</button>
                                </form>";
                                }
                                $expiryDate = $profile->getUserExpiryDate($oneUserId);
                                if (new DateTime() < new DateTime($expiryDate)) {
                                    echo $formtext;
                                    ?>
                                    <input type='hidden' name='userid' value='<?php echo $oneUserId ?>'/>
                                    <button type='submit' id='userinvite' name='command' value='<?php echo \web\lib\common\FormElements::BUTTON_NEWINVITATION ?>'><?php echo _("New Credential"); ?></button>

                                    <label>
                                        <?php echo _("Activations:"); ?>
                                        <input type="text" name="invitationsquantity" value="1" maxlength="3" style="width: 30px;"/>
                                    </label>
                                    </form>
                                    <?php
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <!-- one tr for each invitation -->
                    <?php
                    echo $tokenHtmlBuffer;
                }
                ?>

            </table>
            <!-- ... ends here -->
            <div style="padding: 20px;">
                <?php
                if (count($allUsers) > 0) {
                    $acknowledgeText = sprintf(_('You need to acknowledge that the created accounts are still valid within the next %s days.'
                                    . ' If all accounts shown as active above are indeed still valid, please check the box below and push "Save".'
                                    . ' If any of the accounts are stale, please deactivate them by pushing the corresponding button before doing this.'), CONFIG_CONFASSISTANT['SILVERBULLET']['gracetime'] ?? core\ProfileSilverbullet::SB_ACKNOWLEDGEMENT_REQUIRED_DAYS);

                    echo $formtext . "<div style='padding-bottom: 20px;'>"
                    . "
                    <p>$acknowledgeText</p>
                    <input type='checkbox' name='acknowledge' value='true'>
                    <label>" . _("I have verified that all configured users are still eligible for eduroam.") . "</label>
                </div>
                <button type='submit' name='command' value='" . \web\lib\common\FormElements::BUTTON_ACKUSERELIGIBILITY . "'>Save</button></form>";
                }
                ?>
            </div>
        </fieldset>
    </div>
    <!--Add new user and user import forms -->
    <div id="tabs" active="0">
        <ul>
            <li>	
                <a href="#tabs-1"><?php echo _("Add new user"); ?></a>
            </li>
            <li>
                <a href="#tabs-2"><?php echo _("Import users from CSV file"); ?></a>
            </li>            
        </ul>
        <!--adding manual -->
        <div id="tabs-1">
            <?php echo $formtext; ?>
            <div class="sb-add-new-user">
                <label for="username"><?php echo _("Please enter a username of your choice and user expiry date to create a new user:"); ?></label>
                <div style="margin: 5px 0px 10px 0px;">
                    <input type="text" name="username">
                    <div class="sb-date-container">
                        <input type="text" maxlength="10" id="sb-date-picker-5" class="sb-date-picker" name="userexpiry" value="yyyy-MM-dd"/>
                        <button class="sb-date-button" type="button">▼</button>
                    </div>                
                </div>
                <button type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_ADDUSER ?>"><?php echo _("Add new user"); ?></button>
            </div>
            </form>
        </div>
        <!--CSV -->
        <div id="tabs-2">
            <div>
                <?php echo $formtext; ?>
                <div class="sb-add-new-user">
                    <p><?php echo _("Comma separated values should be provided in CSV file: username, expiration date 'yyyy-mm-dd', number of tokens (optional):"); ?></p>
                    <div style="margin: 5px 0px 10px 0px;">
                        <input type="file" name="newusers">
                    </div>
                    <button type="submit" name="command" value="newusers" ><?php echo _("Import users"); ?></button>
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
                        <img class="sb-popup-message-redirect" src="../resources/images/icons/button_cancel.png" alt="cancel">
                        <h1><?php echo sprintf(_("%s - Terms of Use"), core\ProfileSilverbullet::PRODUCTNAME); ?></h1>
                        <div class="containerbox" style="position: relative;">
                            <hr>
                            <?php echo $profile->termsAndConditions; ?>
                            <hr>
                            <?php echo $formtext; ?>
                            <div style="position: relative; padding-bottom: 5px;">
                                <input type="checkbox" name="agreement" value="true"> <label><?php echo _("I have read and agree to the terms."); ?></label>
                            </div>
                            <button type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_TERMSOFUSE ?>"><?php echo _("Continue"); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    echo $deco->footer();
    