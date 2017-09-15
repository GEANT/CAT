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
 * Front-end for the user GUI
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);

$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_user");
$loggerInstance = new \core\common\Logging();
$loggerInstance->debug(4, "\n---------------------- accountstatus.php START --------------------------\n");
$loggerInstance->debug(4, $operatingSystem);

$deco = new \web\lib\admin\PageDecoration();
$uiElements = new web\lib\admin\UIElements();

echo $deco->defaultPagePrelude(CONFIG['APPEARANCE']['productname_long'], FALSE);
echo "<link rel='stylesheet' media='screen' type='text/css' href='" . $skinObject->findResourceUrl("CSS", "cat-user.css") . "' />";
?>
</head>
<body>
    <div id="heading">
        <?php
        print '<img src="' . $skinObject->findResourceUrl("IMAGES", "consortium_logo.png") . '" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
        print '<div id="motd">' . ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp' ) . '</div>';
        print '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("Welcome to %s"), CONFIG['APPEARANCE']['productname']) . '</h1>
<h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . CONFIG['APPEARANCE']['productname_long'] . '</h2>';
        echo '<table id="lang_select"><tr><td>';
        echo _("View this page in");
        ?>
        <?php
        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
            echo "<a href='javascript:changeLang(\"$lang\")'>" . $value['display'] . "</a> ";
        }
        echo '</td><td style="text-align:right;padding-right:20px"><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $languageInstance->getLang() . '">' . _("Start page") . '</a></td></tr></table>';
        ?>
    </div> <!-- id="heading" -->
    <div id="user_info" style='min-height: 500px;'>    
        <?php
        if ($statusInfo['tokenstatus']['status'] == \core\ProfileSilverbullet::SB_TOKENSTATUS_VALID || $statusInfo['tokenstatus']['status'] == \core\ProfileSilverbullet::SB_TOKENSTATUS_EXPIRED || $statusInfo['tokenstatus']['status'] == \core\ProfileSilverbullet::SB_TOKENSTATUS_REDEEMED) {
            $loggerInstance->debug(4, "IDP ID = " . $statusInfo['idp']->identifier);
            //IdP and federatiopn logo, if present
            ?>
            <table style='float: right; right:30px; padding-top: 10px; border-spacing: 20px; max-width: 340px;'>
                <tr>
                    <td><img id='logo1' style='max-width: 150px; max-height:150px;' src='<?php echo $skinObject->findResourceUrl("BASE", "user/API.php"); ?>?action=sendLogo&api_version=2&idp=<?php echo $statusInfo['idp']->identifier; ?>' alt='IdP Logo'/></td>
                    <td><img id='logo2' style='max-width: 150px; max-height:150px;' src='<?php echo $skinObject->findResourceUrl("BASE", "user/API.php"); ?>?action=sendFedLogo&api_version=2&fed=<?php echo strtoupper($statusInfo['idp']->federation); ?>' alt='<?php echo sprintf(_("%s Logo"),$uiElements->nomenclature_fed);?>'/></td>
                </tr>
                <tr>
                    <td><?php echo $statusInfo['idp']->name; ?></td>
                    <td><?php echo sprintf(_("%s %s in %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation'], $statusInfo['fed']->name); ?></td>
                </tr>
            </table>
            <?php
        }
        ?>


        <div style='max-width: 700px;'>
            <span style="max-width: 700px;">
                <?php
                echo "<h1>" . sprintf(_("Your personal %s account status page"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</h1>";
                switch ($statusInfo['errorcode']) {
                    case "GENERATOR_CONSUMED":
                        echo $uiElements->boxError(_("You attempted to download an installer that was already downloaded before. Please request a new token from your administrator instead."), _("Attempt to re-use download link"), TRUE);
                        break;
                    case NULL:
                    default:
                }
                // if we know ANYTHING about this token, display info.
                if ($statusInfo['tokenstatus']['status'] != \core\ProfileSilverbullet::SB_TOKENSTATUS_INVALID) {
                    echo "<h2>" . _("We have the following information on file for you:") . "</h2>";
                    $profile = new \core\ProfileSilverbullet($statusInfo['profile']->identifier, NULL);
                    $userdata = $profile->userStatus($statusInfo['tokenstatus']['user']);
                    $allcerts = [];
                    foreach ($userdata as $index => $content) {
                        $allcerts = array_merge($allcerts, $content['cert_status']);
                    }
                    switch (count($allcerts)) {
                        case 0:
                            echo _("You are a new user without a history of eduroam credentials.");
                            break;
                        default:
                            echo "<table>";
                            $categories = [\core\ProfileSilverbullet::SB_CERTSTATUS_VALID, \core\ProfileSilverbullet::SB_CERTSTATUS_EXPIRED, \core\ProfileSilverbullet::SB_CERTSTATUS_REVOKED];
                            foreach ($categories as $category) {

                                switch ($category) {
                                    case \core\ProfileSilverbullet::SB_CERTSTATUS_VALID:
                                        $categoryText = _("Current login tokens");
                                        $color = "#000000";
                                        break;
                                    case \core\ProfileSilverbullet::SB_CERTSTATUS_EXPIRED:
                                        $categoryText = _("Previous login tokens");
                                        $color = "#999999";
                                        break;
                                    case \core\ProfileSilverbullet::SB_CERTSTATUS_REVOKED:
                                        $categoryText = _("Revoked login tokens");
                                        $color = "#ff0000";
                                        break;
                                    default:
                                        continue;
                                }
                                $categoryCount = 0;
                                $categoryText = "<tr style='color:$color;'><td colspan=4><h2>" . $categoryText;

                                $categoryText .= "</h2></td></tr>";
                                $categoryText .= "<tr style='color:$color;'><th>" . _("Pseudonym") . "</th><th>" . _("Device Type") . "</th><th>" . _("Serial Number") . "</th><th>" . _("Issue Date") . "</th><th>" . _("Expiry Date") . "</th></tr>";
                                foreach ($allcerts as $oneCredential) {
                                    if ($oneCredential['status'] == $category) {
                                        $categoryCount++;
                                        $categoryText .= "<tr style='color:$color;'>";
                                        $categoryText .= "<td>" . $oneCredential['name'] . "</td>";
                                        $categoryText .= "<td>" . $oneCredential['device'] . "</td>";
                                        $categoryText .= "<td>" . $oneCredential['serial'] . "</td>";
                                        $categoryText .= "<td>" . $oneCredential['issued'] . "</td>";
                                        $categoryText .= "<td>" . $oneCredential['expiry'] . "</td>";
                                        $categoryText .= "</tr>";
                                    }
                                }
                                if ($categoryCount > 0) {
                                    echo $categoryText;
                                }
                            }
                            echo "</table>";
                    }
                }
                // and then display additional information, based on status.
                switch ($statusInfo['tokenstatus']['status']) {
                    case \core\ProfileSilverbullet::SB_TOKENSTATUS_VALID: // treat both cases as equal
                    case \core\ProfileSilverbullet::SB_TOKENSTATUS_PARTIALLY_REDEEMED:
                        echo "<h2>" . sprintf(_("Your invitation token is valid for %d more device activations."), $statusInfo['tokenstatus']['activations_remaining']) . "</h2>";
                        if (!$statusInfo["OS"]) {
                            echo "<p>"._("Unfortunately, we are unable to determine your device's operating system. If you have made modifications on your device which prevent it from being recognised (e.g. custom 'User Agent' settings), please undo such modifications. You can come back to this page again; the invitation link has not been used up yet.") . "</p>";
                            break;
                        }

                        $dev = new \core\DeviceFactory($statusInfo['OS']['device']);
                        $dev->device->calculatePreferredEapType([new \core\common\EAP(\core\common\EAP::EAPTYPE_SILVERBULLET)]);
                        if ($dev->device->selectedEap == []) {
                            echo "<p>".sprintf(_("Unfortunately, the operating system your device uses (%s) is currently not supported for hosted end-user accounts. You can visit this page with a supported operating system later; the invitation link has not been used up yet."), $statusInfo['OS']['display']) . "</p>";
                            break;
                        }

                        echo "<p>".sprintf(_("You can now download a personalised  %s installation program."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
                        echo sprintf(_("The installation program is<br/><span style='font-size: 30px;'>strictly personal</span>, to be used<br/><span style='font-size: 30px;'>only on this device (%s)</span>, and it is<br/><span style='font-size: 30px;'>not permitted to share</span> this information with anyone."), $statusInfo['OS']['display']);
                        echo "<p style='color:red;'>" . _("When the system detects abuse such as sharing login data with others, all access rights for you will be revoked and you may be sanctioned by your local eduroam administrator.") . "</p>";
                        echo "<p>" . _("During the installation process, you will be asked for the following import PIN. This only happens once during the installation. You do not have to write down this PIN.") . "</p>";

                        $importPassword = \core\ProfileSilverbullet::random_str(4, "0123456789");
                        $profile = new \core\ProfileSilverbullet($statusInfo['profile']->identifier, NULL);

                        echo "<h2>" . sprintf(_("Import PIN: %s"), $importPassword) . "</h2>";
                        echo "<form action='../user/sb_download.php' method='POST'>";
                        echo "<input type='hidden' name='profile' value='" . $statusInfo['profile']->identifier . "'/>";
                        echo "<input type='hidden' name='idp' value='" . $statusInfo['profile']->institution . "'/>";
                        $_SESSION['individualtoken'] = $cleanToken;
                        $_SESSION['importpassword'] = $importPassword;
                        echo "<input type='hidden' name='device' value='" . $statusInfo['OS']['device'] . "'/>";
                        echo "<input type='hidden' name='generatedfor' value='silverbullet'/>";
                        echo "<button class='signin signin_large' id='user_button1' type='submit' style='height:80px;'><span id='user_button'>" . sprintf(_("Click here to download your %s installer!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</span></button>";
                        echo "</form>";
                        break;
                    case \core\ProfileSilverbullet::SB_TOKENSTATUS_EXPIRED:
                        echo "<h2>Invitation link expired</h2>";
                        echo "<p>" . sprintf(_("Unfortunately, the invitation link you just used is too old. The eduroam sign-up invitation was valid until %s. You cannot use this link any more. Please ask your administrator to issue you a new invitation link."), $statusInfo['tokenstatus']['expiry']) . "</p>";
                        echo "<p>Below is all the information about your account's other login details, if any.</p>";
// do NOT break, display full account info instead (this was a previously valid token after all)
                    case \core\ProfileSilverbullet::SB_TOKENSTATUS_REDEEMED:
                        // nothing to say really. User got the breakdown of certs above, and this link doesn't give him any new ones.
                        break;
                    case \core\ProfileSilverbullet::SB_TOKENSTATUS_INVALID:
                        echo "<h2>" . _("Account information not found") . "</h2>";
                        echo "<p>" . _("The invitation link you followed does not map to any invititation we have on file.") . "</p><p>" . _("You should use the exact link you got during sign-up to come here. Alternatively, if you have a valid eduroam login token already, you can visit this page and Accept the question about logging in with a client certificate (select a certificate with a name ending in '...hosted.eduroam.org').");
                }
                ?>
            </span>
        </div>
    </div>
    <div class='footer' id='footer' style="display:block;">
        <table style='width:100%'>
            <tr>
                <td style="padding-left:20px; text-align:left">
                    <?php
                    echo $Gui->CAT_COPYRIGHT;
                    ?>
                </td>
                <td style="padding-left:80px; text-align:right;">
                    <?php
                    echo $deco->attributionEurope();
                    ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
