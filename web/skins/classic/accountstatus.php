<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
$loggerInstance->debug(4, print_r($operatingSystem, true));

defaultPagePrelude(CONFIG['APPEARANCE']['productname_long'], FALSE);
echo "<link rel='stylesheet' media='screen' type='text/css' href='".$skinObject->findResourceUrl("CSS",true)."cat-user.css' />";
?>
</head>
<body>
    <div id="heading">
        <?php
        print '<img src="'.$skinObject->findResourceUrl("IMAGES").'consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
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
    $cleanToken = FALSE;
    $tokenStatus = SB_TOKENSTATUS_INVALID; // default

    if (!$statusInfo['token']) {
        // user came to page without a token.
        // use client cert Apache Voodoo to find out the certificate serial number
        // of the user, then the token belonging to that cert, and then use the
        // token info for the normal status page display
        // $cleanToken = "123abc";
    }

    echo "<h1>Thanks for coming to the status page.</h1>";

    switch ($statusInfo['tokenstatus']['status']) {
        case SB_TOKENSTATUS_VALID:
            if (!$statusInfo["OS"]) {
                echo "<p>We would love to issue you a login credential, but this is not possible because we could not detect your operating system.</h1>";
                break;
            }

            echo "<p>Detected OS: " . $statusInfo['OS']['display'] . "</p>";

            $dev = new DeviceFactory($statusInfo['OS']['device']);
            $dev->device->calculatePreferredEapType([EAPTYPE_SILVERBULLET]);
            if ( $dev->device->selectedEap == [] ) {
                echo "<p>Sorry, we do not currently support individual login credentials for this type of device.</p>";
                break;
            }
            
            echo "<p>We will issue your login credential now.</p>";
            $importPassword = random_str(6);
            $profile = new ProfileSilverbullet($statusInfo['profile'], NULL);
            echo "<p>You will be prompted for an import password for your credential. This only happens ONCE. You do not have to write down this password. You can not re-use the installation program on a different device.</p>";
            echo "<h1>Import Password: $importPassword</h1>";
            echo "<form action='download.php' method='POST'>";
            echo "<input type='hidden' name='profile' value='" . $statusInfo['profile']->identifier . "'/>";
            echo "<input type='hidden' name='idp' value='" . $statusInfo['profile']->institution . "'/>";
            $_SESSION['individualtoken'] = $cleanToken;
            $_SESSION['importpassword'] = $importPassword;
            echo "<input type='hidden' name='device' value='" . $statusInfo['OS']['device'] . "'/>";
            echo "<input type='hidden' name='generatedfor' value='user'/>";
            echo "<button type='submit'>" . _("Click here to download your installer!") . "</button>";
            echo "</form>";
            echo "<pre>" . print_r($installer, TRUE) . "</pre>";

            break;
        case SB_TOKENSTATUS_REDEEMED:
            echo "<p>We have the following information on file for your login credential:</p>";
            echo "<table>";
            echo "<tr><td>Username</td><td>" . $statusInfo['tokenstatus']['cert_name'] . "</td></tr>";
            echo "<tr><td>Serial number</td><td>" . $statusInfo['tokenstatus']['cert_serial'] . "</td></tr>";
            echo "<tr><td>Expiry</td><td>" . $statusInfo['tokenstatus']['cert_expiry'] . "</td></tr>";
            break;
        case SB_TOKENSTATUS_EXPIRED:
            echo "<p>You have been given this URL to retrieve your login credential, but did not pick it up in time. It was valid until " . $statusInfo['tokenstatus']['expiry'] . ". You cannot use it any more. Please ask your administrator to issue you a new token.</p>";
            break;
        case SB_TOKENSTATUS_INVALID:
            echo "<p>Unfortunately, we know nothing about your account.</p><p>You should either use the exact link you got during sign-up to come here, or you should visit this page with the client certificate (you may need to click Accept to a strange question in your browser for that).";
    }
    ?>

    <div class='footer' id='footer'>
        <table style='width:100%'>
            <tr>
                <td style="padding-left:20px; text-align:left">
                    <?php
                    echo CONFIG['APPEARANCE']['productname'] . " - " . $Gui->CAT_VERSION_STRING;
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
