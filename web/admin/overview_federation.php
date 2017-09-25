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

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Management"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclature_fed));
$user = new \core\User($_SESSION['user']);
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    echo $deco->productheader("FEDERATION");
    ?>
    <h1>
        <?php echo sprintf(_("%s Overview"),$uiElements->nomenclature_fed); ?>
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
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo str_replace('\'','\x27',str_replace('"','\x22', $_SESSION["user"])); ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <?php
    $mgmt = new \core\UserManagement();

    if (!$user->isFederationAdmin()) {
        echo "<p>" . sprintf(_("You are not a %s manager."),$uiElements->nomenclature_fed) . "</p>";
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
                ?>
                <tr>
                    <td colspan='3' style='text-align:right;'><form action='edit_federation.php' method='POST'><input type="hidden" name='fed_id' value='<?php echo strtoupper($thefed->identifier); ?>'/><button type="submit">Edit</button></form></td>
                </tr>
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
        switch ($_GET['invitation']) {
            case "SUCCESS":
                $cryptText = "";
                switch ($_GET['transportsecurity']) {
                    case "ENCRYPTED":
                        $cryptText = _("and <b>encrypted</b> to the mail domain");
                        break;
                    case "CLEAR":
                        $cryptText = _("but <b>in clear text</b> to the mail domain");
                        break;
                    default:
                        throw new Exception("Error: unknown encryption status of invitation!?!");
                }
                echo $uiElements->boxRemark(sprintf(_("The invitation email was sent successfully %s."), $cryptText), _("The invitation email was sent."));
                break;
            case "FAILURE":
                echo $uiElements->boxError(_("The invitation email could not be sent!"), _("The invitation email could not be sent!"));
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
            <th><?php echo sprintf(_("Name of %s"), $uiElements->nomenclature_inst); ?></th>

            <?php
            $pending_invites = $mgmt->listPendingInvitations();

            if (CONFIG['DB']['enforce-external-sync']) {
                echo "<th>" . sprintf(_("%s Database Sync Status"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</th>";
            }
            ?>
            <th><?php echo _("Administrator Management"); ?></th>
        </tr>
        <?php
        foreach ($feds as $onefed) {
            $thefed = new \core\Federation(strtoupper($onefed['value']));
            echo "<tr><td colspan='8'><strong>" . sprintf(_("Your %s %s contains the following %s list:"), $uiElements->nomenclature_fed, '<span style="color:green">' . $thefed->name . '</span>', $uiElements->nomenclature_inst) . "</strong></td></tr>";

            // extract only pending invitations for *this* fed
            $display_pendings = FALSE;
            foreach ($pending_invites as $oneinvite) {
                if (strtoupper($oneinvite['country']) == strtoupper($thefed->identifier)) {
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
                    if ($idp_instance->getExternalDBSyncState() != \core\IdP::EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING) {
                        echo "<td>";
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                    <button type='submit'>" . _("Manage DB Link") . "</button> ";

                        if ($idp_instance->getExternalDBSyncState() != \core\IdP::EXTERNAL_DB_SYNCSTATE_SYNCED) {
                            echo "<div class='notacceptable'>" . _("NOT linked") . "</div>";
                        } else {
                            echo "<div class='acceptable'>" . _("Linked") . "</div>";
                        }
                        echo "</form>";
                        echo "</td>";
                    }
                }
                // admin management
                echo "<td>
                               <div style='white-space: nowrap;'>
                                  <form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $index . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                      <button type='submit'>" .
                _("Add/Remove Administrators") . "
                                      </button>
                                  </form>
                                </div>
                             </td>";
                // end of entry
                echo "</tr>";
            }
            if ($display_pendings) {
                echo "<tr>
                            <td colspan='2'>
                               <strong>" .
                sprintf(_("Pending invitations in your %s:"),$uiElements->nomenclature_fed) . "
                               </strong>
                            </td>
                         </tr>";
                foreach ($pending_invites as $oneinvite) {
                    if (strtoupper($oneinvite['country']) == strtoupper($thefed->identifier)) {
                        echo "<tr>
                                    <td>" .
                        $oneinvite['name'] . "
                                    </td>
                                    <td>" .
                        $oneinvite['mail'] . "
                                    </td>
                                    <td colspan=2>";
                        echo "<form method='post' action='overview_federation.php' accept-charset='UTF-8'>
                                <input type='hidden' name='invitation_id' value='" . $oneinvite['token'] . "'/>
                                <button class='delete' type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Revoke Invitation") . "</button>
                              </form>";
                        echo "      </td>
                                 </tr>";
                    }
                }
            }
        }
        ?>
    </table>
    <hr/>
    <br/>
    <form method='post' action='inc/manageNewInst.inc.php' onsubmit='popupRedirectWindow(this);
            return false;' accept-charset='UTF-8'>
        <button type='submit' class='download'>
            <?php echo sprintf(_("Register new %s!"), $uiElements->nomenclature_inst); ?>
        </button>
    </form>
    <br/>
    <?php
    echo $deco->footer();
    