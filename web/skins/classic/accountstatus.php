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
require_once("UserAPI.php");
require_once("resources/inc/header.php");
require_once("resources/inc/footer.php");
require_once("web/admin/inc/input_validation.inc.php");
require_once("Logging.php");
require_once("Language.php");
require_once("Helper.php");
require_once("ProfileSilverbullet.php");


$languageInstance = new Language();
$languageInstance->setTextDomain("web_user");
$loggerInstance = new Logging();
$loggerInstance->debug(4, "\n---------------------- accountstatus.php START --------------------------\n");
$loggerInstance->debug(4, $operatingSystem, true);




defaultPagePrelude(CONFIG['APPEARANCE']['productname_long'], FALSE);
echo "<link rel='stylesheet' media='screen' type='text/css' href='" . $skinObject->findResourceUrl("CSS", true) . "cat-user.css' />";
?>
</head>
<body>
    <div id="heading">
        <?php
        print '<img src="' . $skinObject->findResourceUrl("IMAGES") . 'consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
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
    <?php
    if (!$statusInfo['token']) {
        // user came to page without a token.
        // use client cert Apache Voodoo to find out the certificate serial number
        // of the user, then the token belonging to that cert, and then use the
        // token info for the normal status page display
        // $cleanToken = "123abc";
    }
    ?>
    <div id="user_info">
        <?php
        echo "<h1>" . sprintf(_("Your personal %s account status page"), CONFIG['CONSORTIUM']['name']) . "</h1>";

        switch ($statusInfo['tokenstatus']['status']) {
            case SB_TOKENSTATUS_VALID:
                echo "<p>" . _("Your invitation token is valid.") . " ";
                if (!$statusInfo["OS"]) {
                    echo _("Unfortunately, we are unable to determine your device's operating system. If you have made modifications on your device which prevent it from being recognised (e.g. custom 'User Agent' settings), please undo such modifications. You can come back to this page again; the invitation link has not been used up yet.") . "</p>";
                    break;
                }

                $dev = new DeviceFactory($statusInfo['OS']['device']);
                $dev->device->calculatePreferredEapType([EAPTYPE_SILVERBULLET]);
                if ($dev->device->selectedEap == []) {
                    echo sprintf(_("Unfortunately, the operating system your device uses (%s) is currently not supported for hosted end-user accounts. You can visit this page with a supported operating system later; the invitation link has not been used up yet."), $statusInfo['OS']['display']) . "</p>";
                    break;
                }

                echo sprintf(_("You can now create an installation program with personalised %s login information."), CONFIG['CONSORTIUM']['name']) . "</p>";
                echo "<p>" . sprintf(_("The installation program is <b>strictly personal</b>, to be used <b>only on the device</b> you are currently using (%s), and it is <b>not permitted to share</b> this information with anyone. When the system detects abuse such as sharing login data with others, all access rights for you will be revoked and you may be sanctioned by your local eduroam administrator."), $statusInfo['OS']['display']) . "</p>";
                echo "<p>" . _("During the installation process, you will be asked for the following import password. This only happens once during the installation. You do not have to write down this password.") . "</p>";

                $importPassword = random_str(6);
                $profile = new ProfileSilverbullet($statusInfo['profile']->identifier, NULL);

                echo "<h2>" . sprintf(_("Import Password: %s"), $importPassword) . "</h2>";
                echo "<form action='user/sb_download.php' method='POST'>";
                echo "<input type='hidden' name='profile' value='" . $statusInfo['profile']->identifier . "'/>";
                echo "<input type='hidden' name='idp' value='" . $statusInfo['profile']->institution . "'/>";
                $_SESSION['individualtoken'] = $cleanToken;
                $_SESSION['importpassword'] = $importPassword;
                echo "<input type='hidden' name='device' value='" . $statusInfo['OS']['device'] . "'/>";
                echo "<input type='hidden' name='generatedfor' value='user'/>";
                echo "<button class='signin signin_large' id='user_button1' type='submit' style='height:80px;'><span id='user_button'>" . sprintf(_("Click here to download your %s installer!"), CONFIG['CONSORTIUM']['name']) . "</span></button>";
                echo "</form>";
                echo "<pre>" . print_r($installer, TRUE) . "</pre>";

                break;
            case SB_TOKENSTATUS_EXPIRED:
                echo "<h2>Invitation link expired</h2>";
                echo "<p>".sprintf(_("Unfortunately, the invitation link you just used is too old. The eduroam sign-up invitation was valid until %s. You cannot use this link any more. Please ask your administrator to issue you a new invitation link."),$statusInfo['tokenstatus']['expiry'])."</p>";
                echo "<p>Below is all the information about your account's other login details, if any.</p>";
                // do NOT break, display full account info instead (this was a previously valid token after all)
            case SB_TOKENSTATUS_REDEEMED:
                echo "<h2>" . _("We have the following information on file for you:") . "</h2>";
                $profile = new ProfileSilverbullet($statusInfo['profile']->identifier, NULL);
                $userdata = $profile->userStatus($statusInfo['tokenstatus']['user']);
                echo "<table>";
                $categories = [SB_CERTSTATUS_VALID, SB_CERTSTATUS_EXPIRED, SB_CERTSTATUS_REVOKED];
                foreach ($categories as $category) {
                    
                    switch ($category) {
                        case SB_CERTSTATUS_VALID:
                            $categoryText = _("Current login tokens");
                            $color = "#000000";
                            break;
                        case SB_CERTSTATUS_EXPIRED:
                            $categoryText = _("Previous login tokens");
                            $color = "#999999";
                            break;
                        case SB_CERTSTATUS_REVOKED:
                            $categoryText = _("Revoked login tokens");
                            $color = "#ff0000";
                            break;
                        default:
                            continue;
                    }
                    $categoryCount = 0;
                    $categoryText = "<tr style='color:$color;'><td colspan=3><h2>".$categoryText;
                    
                    $categoryText .= "</h2></td></tr>";
                    $categoryText .= "<tr style='color:$color;'><th>" . _("Pseudonym") . "</th><th>" . _("Serial Number") . "</th><th>" . _("Expiry Date") . "</th></tr>";
                    foreach ($userdata as $oneCredential) {
                        if ($oneCredential['cert_status'] == $category) {
                            $categoryCount++;
                            $categoryText .= "<tr style='color:$color;'>";
                            $categoryText .= "<td>" . $oneCredential['cert_name'] . "</td>";
                            $categoryText .= "<td>" . $oneCredential['cert_serial'] . "</td>";
                            $categoryText .= "<td>" . $oneCredential['cert_expiry'] . "</td>";
                            $categoryText .= "</tr>";
                        }
                    }
                    if ($categoryCount > 0) {
                        echo $categoryText;
                    }
                }
                echo "</table>";
                break;
            case SB_TOKENSTATUS_INVALID:
                echo "<h2>"._("Account information not found")."</h2>";
                echo "<p>"._("The invitation link you followed does not map to any invititation we have on file.")."</p><p>"._("You should use the exact link you got during sign-up to come here. Alternatively, if you have a valid eduroam login token already, you can visit this page and Accept the question about logging in with a client certificate (select a certificate with a name ending in '...hosted.eduroam.org').");
        }
        ?>
    </div>
    <div style="height:200px;"></div>
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
                    if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                        echo attributionEurope();
                    } else {
                        echo "&nbsp;";
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
