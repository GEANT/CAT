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
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$deco = new \web\lib\admin\PageDecoration();
$uiElements = new web\lib\admin\UIElements();
$validator = new \web\lib\common\InputValidation();

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Management"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclature_fed));
$user = new \core\User($_SESSION['user']);
require_once("inc/click_button_js.php");
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    echo $deco->productheader("FEDERATION");
    $readonly = CONFIG['DB']['INST']['readonly'];
    ?>
    <h1>
        <?php echo sprintf(_("%s Overview"), $uiElements->nomenclature_fed); ?>
    </h1>

    <div class="infobox">
        <h2><?php echo _("Your Personal Information"); ?></h2>
        <table>
            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo str_replace('\'', '\x27', str_replace('"', '\x22', $_SESSION["user"])); ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <?php
    $mgmt = new \core\UserManagement();

    if (!$user->isFederationAdmin()) {
        echo "<p>" . sprintf(_("You are not a %s manager."), $uiElements->nomenclature_fed) . "</p>";
        echo $deco->footer();
        exit(0);
    }

    $feds = $user->getAttributes("user:fedadmin");
    foreach ($feds as $onefed) {
        $thefed = new \core\Federation(strtoupper($onefed['value']));
        ?>

        <div class='infobox'><h2>
                <?php echo sprintf(_("%s Properties: %s"), $uiElements->nomenclature_fed, $thefed->name); ?>
            </h2>
            <table>
                <!-- fed properties -->
                <tr>
                    <td>
                        <?php echo "" . _("Country") ?>
                    </td>
                    <td>
                    </td>
                    <td>
                        <strong><?php
                            echo $thefed->name;
                            ?></strong>
                    </td>
                </tr>
                <?php
                echo $uiElements->infoblock($thefed->getAttributes(), "fed", "FED");
                if ($readonly === FALSE) {
                    ?>
                    <tr>
                        <td colspan='3' style='text-align:right;'><form action='edit_federation.php' method='POST'><input type="hidden" name='fed_id' value='<?php echo strtoupper($thefed->tld); ?>'/><button type="submit">Edit</button></form></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>
        <div class='infobox'>
            <h2>
                <?php echo sprintf(_("%s Statistics: %s"), $uiElements->nomenclature_fed, $thefed->name); ?>
            </h2>
            <table>
                <!-- idp stats -->
                <tr>
                    <th style='text-align:left;'> <?php echo _("IdPs Total"); ?></th>
                    <th colspan='3'> <?php echo _("Public Download") ?></th>
                </tr>
                <tr>
                    <td> <?php echo count($thefed->listIdentityProviders(0)); ?></td>
                    <td colspan='3'> <?php echo count($thefed->listIdentityProviders(1)); ?>
                    </td>
                </tr>
                <tr>
                    <td colspan='4'><hr></td>
                </tr>    
                <!-- download stats -->
                <tr>
                    <th style='text-align:left;'> <?php echo _("Downloads"); ?></th>
                    <th style='text-align:left;'> <?php echo _("Admin"); ?></th>
                    <th style='text-align:left;'> <?php echo \core\ProfileSilverbullet::PRODUCTNAME ?></th>
                    <th style='text-align:left;'> <?php echo _("User"); ?></th>
                </tr>
                <?php echo $thefed->downloadStats("table"); ?>
            </table>
        </div>
        <?php
    }

    if (isset($_POST['submitbutton']) &&
            $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_DELETE &&
            isset($_POST['invitation_id'])) {
        $mgmt->invalidateToken(filter_input(INPUT_POST, 'invitation_id', FILTER_SANITIZE_STRING));
    }

    if (isset($_GET['invitation'])) {
        echo "<div class='ca-summary' style='position:relative;'><table>";
        $counter = $validator->integer($_GET['successcount']);
        if ($counter === FALSE) {
            $counter = 1;
        }
        switch ($_GET['invitation']) {
            case "SUCCESS":
                $cryptText = "";
                switch ($_GET['transportsecurity']) {
                    case "ENCRYPTED":
                        $cryptText = ngettext("It was sent with transport security (encryption).", "They were sent with transport security (encryption).", $counter);
                        break;
                    case "CLEAR":
                        $cryptText = ngettext("It was sent in clear text (no encryption).", "They were sent in clear text (no encryption).", $counter);
                        break;
                    case "PARTIAL":
                        $cryptText = _("A subset of the mails were sent with transport encryption, the rest in clear text.");
                        break;
                    default:
                        throw new Exception("Error: unknown encryption status of invitation!?!");
                }
                echo $uiElements->boxRemark(ngettext("The invitation email was sent successfully.", "All invitation emails were sent successfully.", $counter) . " " . $cryptText, _("Sent successfully."));
                break;
            case "FAILURE":
                echo $uiElements->boxError(_("No invitation email could be sent!"), _("Sending failure!"));
                break;
            case "PARTIAL":
                $cryptText = "";
                switch ($_GET['transportsecurity']) {
                    case "ENCRYPTED":
                        $cryptText = ngettext("The successful one was sent with transport security (encryption).", "The successful ones were sent with transport security (encryption).", $counter);
                        break;
                    case "CLEAR":
                        $cryptText = ngettext("The successful one was sent in clear text (no encryption).", "The successful ones were sent in clear text (no encryption).", $counter);
                        break;
                    case "PARTIAL":
                        $cryptText = _("A subset of the successfully sent mails were sent with transport encryption, the rest in clear text.");
                        break;
                    default:
                        throw new Exception("Error: unknown encryption status of invitation!?!");
                }
                echo $uiElements->boxWarning(sprintf(_("Some invitation emails were sent successfully (%s in total), the others failed."), $counter) . " " . $cryptText, _("Partial success."));
                break;
            case "INVALIDSYNTAX":
                echo $uiElements->boxError(_("The invitation email address was malformed, no invitation was sent!"), _("The invitation email address was malformed, no invitation was sent!"));
                break;
            default:
                echo $uiElements->boxError(_("Error: unknown result code of invitation!?!"), _("Unknown result!"));
        }
        echo "</table></div>";
    }
    if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == 'eduroam') {
        $helptext = "<h3>" . sprintf(_("Need help? Refer to the <a href='%s'>%s manual</a>"), "https://wiki.geant.org/x/KQB_AQ", $uiElements->nomenclature_fed) . "</h3>";
    } else {
        $helptext = "";
    }
    echo $helptext;
    ?>
    <table class='user_overview' style='border:0px;'>
        <tr>
            <th><?php echo _("Deployment Status"); ?></th>
            <th><?php echo sprintf(_("%s Name"), $uiElements->nomenclature_inst); ?></th>

            <?php
            $pending_invites = $mgmt->listPendingInvitations();

            if (CONFIG['DB']['enforce-external-sync']) {
                echo "<th>" . sprintf(_("%s Database Sync Status"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</th>";
            }
            ?>
            <th>
                <?php
                if ($readonly === FALSE) {
                    echo _("Administrator Management");
                }
                ?>
            </th>
        </tr>
        <?php
        foreach ($feds as $onefed) {
            $thefed = new \core\Federation(strtoupper($onefed['value']));
            /// nomenclature for 'federation', federation name, nomenclature for 'inst'
            echo "<tr><td colspan='8'><strong>" . sprintf(_("The following %s are in your %s %s:"), $uiElements->nomenclature_inst, $uiElements->nomenclature_fed, '<span style="color:green">' . $thefed->name . '</span>') . "</strong></td></tr>";

            // extract only pending invitations for *this* fed
            $display_pendings = FALSE;
            foreach ($pending_invites as $oneinvite) {
                if (strtoupper($oneinvite['country']) == strtoupper($thefed->tld)) {
                    // echo "PENDINGS!";
                    $display_pendings = TRUE;
                }
            }
            $idps = $thefed->listIdentityProviders();

            $my_idps = [];
            foreach ($idps as $index => $idp) {
                $my_idps[$idp['entityID']] = strtolower($idp['title']);
            }
            asort($my_idps);

            foreach ($my_idps as $index => $my_idp) {
                $idp_instance = $idps[$index]['instance'];
                // new row, with one IdP inside
                echo "<tr>";
                // deployment status; need to dive into profiles for this
                // show happy eyeballs if at least one profile is configured/showtime                    
                echo "<td>";
                echo ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_CONFIGURED ? "C" : "" ) . " " . ($idp_instance->maxProfileStatus() >= \core\IdP::PROFILES_SHOWTIME ? "V" : "" );
                echo "</td>";
                // name
                echo "<td>
                         <input type='hidden' name='inst' value='" . $index . "'>" . $idp_instance->name . "
                      </td>";
                // external DB sync, if configured as being necessary
                if (CONFIG['DB']['enforce-external-sync']) {
                    echo "<td style='display: ruby;'>";
                    if ($readonly === FALSE) {
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                    <button type='submit'>" . _("Manage DB Link") . "</button></form>&nbsp;&nbsp;";
                    }
                    switch ($idp_instance->getExternalDBSyncState()) {
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING:
                            break;
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_SYNCED:
                            echo "<div class='acceptable'>" . _("Linked") . "</div>";
                            break;
                        case \core\IdP::EXTERNAL_DB_SYNCSTATE_NOT_SYNCED:

                            echo "<div class='notacceptable'>" . _("NOT linked") . "</div>";


                            break;
                    }

                    echo "</td>";
                }

                // admin management
                echo "<td>";
                if ($readonly === FALSE) {
                    echo "<div style='white-space: nowrap;'>
                                  <form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $index . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                      <button type='submit'>" .
                    _("Add/Remove Administrators") . "
                                      </button>
                                  </form>
                                </div>";
                }
                echo "</td>";
                // end of entry
                echo "</tr>";
            }
            if ($display_pendings) {
                echo "<tr>
                            <td colspan='2'>
                               <strong>" .
                sprintf(_("Pending invitations in the %s:"), $uiElements->nomenclature_fed) . "
                               </strong>
                            </td>
                         </tr>";
                foreach ($pending_invites as $oneinvite) {
                    if (strtoupper($oneinvite['country']) == strtoupper($thefed->tld)) {
                        echo "<tr>
                                    <td>" .
                        $oneinvite['name'] . "
                                    </td>
                                    <td>" .
                        $oneinvite['mail'] . "
                                    </td>
                                    <td colspan=2>";
                        if ($readonly === FALSE) {
                            echo "<form method='post' action='overview_federation.php' accept-charset='UTF-8'>
                                <input type='hidden' name='invitation_id' value='" . $oneinvite['token'] . "'/>
                                <button class='delete' type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Revoke Invitation") . "</button>
                              </form>";
                        }
                        echo "      </td>
                                 </tr>";
                    }
                }
            }
        }
        ?>
    </table>
    <?php
    if ($readonly === FALSE) {
        ?>
        <hr/>
        <br/>
        <form method='post' action='inc/manageNewInst.inc.php' onsubmit='popupRedirectWindow(this);
                return false;' accept-charset='UTF-8'>
            <button type='submit' class='download'>
                <?php echo sprintf(_("Register a new %s!"), $uiElements->nomenclature_inst); ?>
            </button>
        </form>
        <br/>
        <?php
    }
    echo $deco->footer();
    