<?php
error_reporting(E_ALL | E_STRICT);
$Gui->defaultPagePrelude();
?>
<!-- JQuery -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery.js"); ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery-migrate.js"); ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery-ui.js"); ?>"></script>
<!-- JQuery -->
<script type="text/javascript">
    var recognisedOS = '';
    var downloadMessage;
    var noDisco = 1;
    var sbPage = 1;
<?php
$profile_list_size = 1;
include_once(dirname(__DIR__) . '/Divs.php');
$divs = new Divs($Gui);
$visibility = 'sb';
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, $operatingSystem);
$uiElements = new web\lib\admin\UIElements();
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
}
include(dirname(__DIR__) . '/user/js/cat_js.php');
?>
    var lang = "<?php echo($Gui->langObject->getLang()) ?>";
</script>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS", "cat-user.css"); ?>" />
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS", "accountstatus.css", "accountstatus"); ?>" />

</head>
<body>
    <div id="wrap">
        <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="">

            <?php echo $divs->div_heading($visibility); ?>
            <div id="info_overlay"> <!-- device info -->
                <div id="info_window"></div>
                <img id="info_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/button_cancel.png") ?>" ALT="Close"/>
            </div>
            <div id="main_menu_info" style="display:none"> <!-- stuff triggered form main menu -->
                <img id="main_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/button_cancel.png") ?>" ALT="Close"/>
                <div id="main_menu_content"></div>
            </div>
            <div id="main_body">
                <div id="user_page">
                    <?php echo $divs->div_institution(FALSE); ?>
                    <div id="user_info"></div> <!-- this will be filled with the profile contact information -->
                    <div id="sb_info">
                        <?php
                        switch ($statusInfo['errorcode']) {
                            case "GENERATOR_CONSUMED":
                                echo $uiElements->boxError(_("You attempted to download an installer that was already downloaded before. Please request a new token from your administrator instead."), _("Attempt to re-use download link"), TRUE) . "<p>";
                                break;
                            case NULL:
                            default:
                        }
                        // if we know ANYTHING about this token, display info.
                        if ($statusInfo['invitation_object']->invitationTokenStatus != \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID) {
                            //                echo "<h3>" . _("We have the following information on file for you:") . "</h3>";
                            $profile = new \core\ProfileSilverbullet($statusInfo['profile']->identifier, NULL);
                            $allcerts = $Gui->getUserCerts($statusInfo['token']);
                            if (count($allcerts) == 0) {
                                echo _("You are a new user without a history of eduroam credentials.");
                            } else {
                                $stats = array_count_values(array_column($allcerts, 'status'));
                                $numValid = $stats[\core\SilverbulletCertificate::CERTSTATUS_VALID] ?? 0;
                                $numExpired = $stats[\core\SilverbulletCertificate::CERTSTATUS_EXPIRED] ?? 0;
                                $numRevoked = $stats[\core\SilverbulletCertificate::CERTSTATUS_REVOKED] ?? 0;
                                echo sprintf(ngettext("You have <strong>%d</strong> currently valid %s credential.", "You have <strong>%d</strong> currently valid %s credentials.", $numValid), $numValid, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
                                $noGoodCerts = $numRevoked + $numExpired;
                                if ($noGoodCerts > 0) {
                                    echo " ";
                                    echo sprintf(ngettext("<strong>%d</strong> of your credentials is not valid any more.", "<strong>%d</strong> of your credentials are not valid any more.", $noGoodCerts), $noGoodCerts);
                                }
                                echo " <span id='detailtext'>" . _("I want to see the details.") . "</span>";
                                echo "<table id='cert_details'></table>";
                            }
                        }
                        // and then display additional information, based on status.
                        switch ($statusInfo['invitation_object']->invitationTokenStatus) {
                            case \core\SilverbulletInvitation::SB_TOKENSTATUS_VALID: // treat both cases as equal
                            case \core\SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED:
                                if ($statusInfo['invitation_object']->activationsTotal > 1) { // only show this extra info in the non-trivial case.
                                    echo "<h3>" . sprintf(_("Your invitation token is valid for %d more device activations (%d have already been used)."), $statusInfo['invitation_object']->activationsRemaining, $statusInfo['invitation_object']->activationsTotal - $statusInfo['invitation_object']->activationsRemaining) . "</h3>";
                                }
                                if (!$statusInfo["OS"]) {
                                    echo "<p>" . _("Unfortunately, we are unable to determine your device's operating system. If you have made modifications on your device which prevent it from being recognised (e.g. custom 'User Agent' settings), please undo such modifications. You can come back to this page again; the invitation link has not been used up yet.") . "</p>";
                                    break;
                                }

                                $dev = new \core\DeviceFactory($statusInfo['OS']['device']);
                                $dev->device->calculatePreferredEapType([new \core\common\EAP(\core\common\EAP::EAPTYPE_SILVERBULLET)]);
                                if ($dev->device->selectedEap == []) {
                                    echo "<p>" . sprintf(_("Unfortunately, the operating system your device uses (%s) is currently not supported for hosted end-user accounts. You can visit this page with a supported operating system later; the invitation link has not been used up yet."), $statusInfo['OS']['display']) . "</p>";
                                    break;
                                }

                                echo "<div id='sb_download_message'><p>" . sprintf(_("You can now download a personalised  %s installation program."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
//                       echo sprintf(_("The installation program is <span class='emph'>strictly personal</span>, to be used <span class='emph'>only on this device (%s)</span>, and it is <span class='emph'>not permitted to share</span> this information with anyone."), $statusInfo['OS']['display']);
                                echo sprintf(_("The installation program is <span class='emph'>strictly personal</span>, to be used <span class='emph'>only on this device (%s)</span>, and it is <span class='emph'>not permitted to share</span> this information with anyone."), $statusInfo['OS']['display']);
                                echo "<p style='color:red;'>" . _("When the system detects abuse such as sharing login data with others, all access rights for you will be revoked and you may be sanctioned by your local eduroam administrator.") . "</p>";
                                echo "<p>" . _("During the installation process, you will be asked for the following import PIN. This only happens once during the installation. You do not have to write down this PIN.") . "</p></div>";

                                $importPassword = \core\common\Entity::randomString(4, "0123456789");
                                $profile = new \core\ProfileSilverbullet($statusInfo['profile']->identifier, NULL);

                                echo "<h2>" . sprintf(_("Import PIN: %s"), $importPassword) . "</h2>";
                                $_SESSION['individualtoken'] = $cleanToken;
                                $_SESSION['importpassword'] = $importPassword;
                                echo "<input type='hidden' name='device' value='" . $statusInfo['OS']['device'] . "'/>";
                                echo "<input type='hidden' name='generatedfor' value='silverbullet'/>";
                                echo "<button class='large_button' id='user_button_sb' style='height:80px;'><span id='user_buttonnnn'>" . sprintf(_("Click here to download your %s installer!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</span></button>";
                                break;
                            case \core\SilverbulletInvitation::SB_TOKENSTATUS_EXPIRED:
                                echo "<h2>Invitation link expired</h2>";
                                echo "<p>" . sprintf(_("Unfortunately, the invitation link you just used is too old. The eduroam sign-up invitation was valid until %s. You cannot use this link any more. Please ask your administrator to issue you a new invitation link."), $statusInfo['invitation_object']->expiry) . "</p>";
                                echo "<p>Below is all the information about your account's other login details, if any.</p>";
// do NOT break, display full account info instead (this was a previously valid token after all)
                            case \core\SilverbulletInvitation::SB_TOKENSTATUS_REDEEMED:
                                // nothing to say really. User got the breakdown of certs above, and this link doesn't give him any new ones.
                                break;
                            case \core\SilverbulletInvitation::SB_TOKENSTATUS_INVALID:
                                echo "<h2>" . _("Account information not found") . "</h2>";
                                echo "<p>" . _("The invitation link you followed does not map to any invititation we have on file.") . "</p><p>" . _("You should use the exact link you got during sign-up to come here. Alternatively, if you have a valid eduroam login token already, you can visit this page and Accept the question about logging in with a client certificate (select a certificate with a name ending in '...hosted.eduroam.org').");
                        }
                        if (isset($statusInfo['profile_id']) && isset($statusInfo['idp_id'])) {
                            echo "<input type='hidden' name='profile' id='profile_id' value='" . $statusInfo['profile_id'] . "'/>";
                            echo "<input type='hidden' id='inst_id' name='idp' value='" . $statusInfo['idp_id'] . "'/>";
                        }
                        ?>
                    </div>

                    <input type="hidden" name="inst_name" id="inst_name"/>
                    <input type="hidden" name="lang" id="lang"/>
                </div>
            </div>
        </form>
    </div>
    <?php
    echo $divs->div_footer();
    if (isset($statusInfo['profile_id']) && isset($statusInfo['idp_id'])) {
    $attributes = $statusInfo['attributes'];
    $supportInfo = '';
    if (!empty($attributes['local_url'])) {
        $supportInfo .= '<tr><td>' . ("WWW:") . '</td><td><a href="' . $attributes['local_url'] . '" target="_blank">' . $attributes['local_url'] . '</a></td></tr>';
    }
    if (!empty($attributes['local_email'])) {
        $supportInfo .= '<tr><td>' . ("email:") . '</td><td><a href="' . $attributes['local_email'] . '" target="_blank">' . $attributes['local_email'] . '</a></td></tr>';
    }
    if (!empty($attributes['local_phone'])) {
        $supportInfo .= '<tr><td>' . ("tel:") . '</td><td><a href="' . $attributes['local_phone'] . '" target="_blank">' . $attributes['local_phone'] . '</a></td></tr>';
    }
    if ($supportInfo != '') {
        $supportInfo = "<table><tr><th colspan='2'>" . sprintf(_("If you encounter problems, then you can obtain direct assistance from your %s at:"), $cat->nomenclature_inst) . "</th></tr>$supportInfo</table>";
    } else {
        $supportInfo = "<table><tr><th colspan='2'>" . sprintf(_("If you encounter problems you should ask for help at your %s"), $cat->nomenclature_inst) . "</th></tr></table>";
    }
    ?>
    <script>
        function loadIdpData() {
            var idpName = "<?php echo $statusInfo['idp_name']; ?>";
            var logo = <?php echo $statusInfo['idp_logo']; ?>;
            var idpId = <?php echo $statusInfo['idp_id']; ?>;
            $("#inst_name").val(idpName);
            $("#inst_name_span").html(idpName);
            $(".inst_name").text(idpName);
            $("#inst_extra_text").html("<?php escaped_echo(sprintf(_("Your personal %s account status page"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'])); ?>");
            if (logo) {
                $("#idp_logo").attr("src", "<?php echo $skinObject->findResourceUrl("BASE", "user/API.php"); ?>?action=sendLogo&api_version=2&idp=" + idpId);
                $("#idp_logo").show();
            }
            $("#user_info").html("<?php escaped_echo($supportInfo); ?>");
            $("#user_info").show();


            //$("#user_page").show();
            //$("#institution_name").show();
        }

        $("#user_button_sb").click(function (event) {
            event.preventDefault();
            $("#cat_form").attr('action', '<?php echo $Gui->skinObject->findResourceUrl("BASE", "user/sb_download.php"); ?>');
            $("#cat_form").submit();
        });

        $("#detailtext").click(function (event) {
            token = "<?php echo $statusInfo['token']; ?>";
            $.post('<?php echo $skinObject->findResourceUrl("BASE", "user/API.php"); ?>', {action: 'getUserCerts', api_version: 2, token: token}, function (data) {
                var validCerts = new Array();
                var revokedCerts = new Array();
                var expiredCerts = new Array();
                var allArray = new Array();
                var statusCount = new Array();

                allArray[<?php echo \core\SilverbulletCertificate::CERTSTATUS_VALID; ?>] = {color: "#000000", categoryText: "<?php escaped_echo(_("Current login tokens")) ?>", rows: validCerts};
                allArray[<?php echo \core\SilverbulletCertificate::CERTSTATUS_EXPIRED; ?>] = {color: "#999999", categoryText: "<?php escaped_echo(_("Previous login tokens")) ?>", rows: expiredCerts};
                allArray[<?php echo \core\SilverbulletCertificate::CERTSTATUS_REVOKED; ?>] = {color: "#ff0000", categoryText: "<?php escaped_echo(_("Revoked login tokens")) ?>", rows: revokedCerts};
                var headerLine = "<tr><th><?php escaped_echo(_("Serial Number")); ?></th><th><?php escaped_echo(_("Pseudonym")); ?></th><th><?php escaped_echo(_("Device Type")); ?></th><th><?php escaped_echo(_("Issue Date")); ?></th><th><?php escaped_echo(_("Expiry Date")); ?></th></tr>";
                $.each(allArray, function (index, value) {
                    if (value !== undefined) {
                        value.rows.push('<tr style="color:' + value.color + ';"><th class="th1" colspan="5">' + value.categoryText + '</th></tr>');
                        value.rows.push(headerLine);
                        statusCount[index] = 0;
                    }
                });
                j = $.parseJSON(data);
                if (!j.status) {
                    alert("<?php escaped_echo(_("invalid token")); ?>");
                }
                j = j.data;
                $.each(j, function (index, value) {
                    statusCount[value.status]++;
                    allArray[value.status].rows.push('<tr style="color:' + allArray[value.status].color + ';"><td>' + value.serial + '</td><td>' + value.name + '</td><td>' + value.device + '</td><td>' + value.issued + '</td><td>' + value.expiry + '</td>');
                });
                $.each(allArray, function (index, value) {
                    if (value !== undefined && value.rows.length > 2) {
                        $.each(value.rows, function (i, line) {
                            if (i > 1) {
                                if (index === <?php echo \core\SilverbulletCertificate::CERTSTATUS_VALID; ?>)
                                    line = line + '<td class="revoke"><a href="" TITLE="revoke certificate">revoke</a></td></tr>';
                                else
                                    line = line + '</tr>';
                            }
                            // alert(line);
                            $("#cert_details").append(line);
                        });
                    }
                });
                // alert("V:"+statusCount[1]+"; E:"+statusCount[2]+"; R"+statusCount[3])
            });
            $("#cert_details").show();
            $(this).html("<?php escaped_echo(_("The details are displayed below.")); ?>");
        });

        $("#cert_details").on("click", "td.revoke>a", function (event) {
            event.preventDefault();
            if (confirm("<?php escaped_echo(_("really revoke this certificate?")); ?>"))
                alert("deleting - not yet implemented");
        })
        loadIdpData();
    </script> 
    <?php
    }
    ?>
</body>
