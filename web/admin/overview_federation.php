<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("UserManagement.php");
require_once("Federation.php");
require_once("RADIUSTests.php");
require_once("IdP.php");
require_once("User.php");

require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/input_validation.inc.php");
require_once("inc/common.inc.php");

$cat = defaultPagePrelude(sprintf(_("%s: Federation Management"), Config::$APPEARANCE['productname']));
$user = new User($_SESSION['user']);
?>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    productheader("FEDERATION", CAT::get_lang());
    ?>
    <h1>
        <?php echo _("Federation Overview"); ?>
    </h1>

    <div class="infobox">
        <h2><?php echo _("Your Personal Information"); ?></h2>
        <table>
            <?php echo infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo $_SESSION["user"]; ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <?php
    $mgmt = new UserManagement();

    if (!$user->isFederationAdmin()) {
        echo "<p>" . _("You are not a federation manager.") . "</p>";
        footer();
        exit(0);
    }

    $feds = $user->getAttributes("user:fedadmin");
    foreach ($feds as $onefed) {
        $thefed = new Federation(strtoupper($onefed['value']));
        ?>

        <div class='infobox'><h2>
                <?php echo sprintf(_("Federation Properties: %s"), strtoupper($thefed->name)); ?>
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
                            echo Federation::$federationList[strtoupper($thefed->name)];
                            ?></strong>
                    </td>
                </tr>
                <?php
                echo infoblock($thefed->getAttributes(), "fed", "FED");
                ?>
                <tr>
                    <td colspan='3' style='text-align:right;'><form action='edit_federation.php' method='POST'><input type="hidden" name='fed_id' value='<?php echo strtoupper($thefed->name); ?>'/><button type="submit">Edit</button></form></td>
                </tr>
            </table>
        </div>
        <div class='infobox'>
            <h2>
                <?php echo sprintf(_("Federation Statistics: %s"), strtoupper($thefed->name)); ?>
            </h2>
            <table>
                <!-- idp stats -->
                <tr>
                    <th style='text-align:left;'> <?php echo _("IdPs Total"); ?></th>
                    <th colspan='2'> <?php echo _("Public Download") ?></th>
                </tr>
                <tr>
                    <td> <?php echo count($thefed->listIdentityProviders(0)); ?></td>
                    <td colspan='2'> <?php echo count($thefed->listIdentityProviders(1)); ?>
                    </td>
                </tr>
                <tr>
                    <td colspan='3'><hr></td>
                </tr>    
                <!-- download stats -->
                <tr>
                    <th style='text-align:left;'> <?php echo _("Downloads"); ?></th>
                    <th style='text-align:left;'> <?php echo _("Admin"); ?></th>
                    <th style='text-align:left;'> <?php echo _("User"); ?></th>
                </tr>
                <?php echo Federation::downloadStats("table", $thefed->name); ?>
            </table>
        </div>
        <?php
    }

    if (isset($_POST['submitbutton']) &&
            $_POST['submitbutton'] == BUTTON_DELETE &&
            isset($_POST['invitation_id'])) {
        $mgmt->invalidateToken($_POST['invitation_id']);
    }

    if (isset($_GET['invitation'])) {
        echo "<div class='ca-summary' style='position:relative;'><table>";

        if ($_GET['invitation'] == "SUCCESS")
            echo UI_remark(_("The invitation email was sent successfully."), _("The invitation email was sent."));
        else if ($_GET['invitation'] == "FAILURE")
            echo UI_error(_("The invitation email could not be sent!"), _("The invitation email could not be sent!"));
        else
            echo UI_error(_("Error: unknown result code of invitation!?!"), _("Unknown result!"));

        echo "</table></div>";
    }
    if (Config::$CONSORTIUM['name'] == 'eduroam')
        $helptext = "<h3>" . sprintf(_("Need help? Refer to the <a href='%s'>Federation Operator manual</a>"),"https://wiki.geant.org/x/KQB_AQ")."</h3>";
    else
        $helptext = "";
    echo $helptext;

    ?>
    <table class='user_overview' style='border:0px;'>
        <tr>
            <th colspan="2"><?php echo _("Deployment Status"); ?></th>
            <th><?php echo _("Institution Name"); ?></th>

            <?php
            $feds = $user->getAttributes("user:fedadmin");
            $pending_invites = $mgmt->listPendingInvitations();

            if (Config::$DB['enforce-external-sync'])
                echo "<th>" . sprintf(_("%s Database Sync Status"), Config::$CONSORTIUM['name']) . "</th>";
            ?>
            <th><?php echo _("Administrator Management"); ?></th>
        </tr>
        <?php
        foreach ($feds as $onefed) {
            $thefed = new Federation(strtoupper($onefed['value']));
            echo "<tr><td colspan='8'><strong>" . sprintf(_("Your federation %s contains the following institutions: (<a href='%s'>Check their authentication server status</a>)"), '<span style="color:green">' . $thefed::$FederationList[$onefed['value']] . '</span>', "action_fedcheck.php?fed=" . $thefed->name) . "</strong></td></tr>";

            // extract only pending invitations for *this* fed
            $display_pendings = FALSE;
            foreach ($pending_invites as $oneinvite)
                if (strtoupper($oneinvite['country']) == strtoupper($thefed->name)) {
                    // echo "PENDINGS!";
                    $display_pendings = TRUE;
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
                echo ($idp_instance->isOneProfileConfigured() ? "C" : "" ) . " " . ($idp_instance->isOneProfileShowtime() ? "V" : "" );
                echo "</td>";
                // get the coarse status overview
                $status = $idp_instance->getAllProfileStatusOverview();
                echo "<td>";
                if ($status['dns'] == RETVAL_INVALID) {
                    echo UI_error(0, "DNS Error", true);
                } else {
                    echo UI_okay(0, "DNS OK", true);
                }
                if ($status['cert'] != L_OK && $status['cert'] != RETVAL_SKIPPED) {
                    echo UI_message($status['cert'], 0, "Cert Error", true);
                } else {
                    echo UI_okay(0, "Cert OK", true);
                }
                if ($status['reachability'] == RETVAL_INVALID) {
                    echo UI_error(0, "Reachability Error", true);
                } else {
                    echo UI_okay(0, "Reachability OK", true);
                }
                if ($status['TLS'] == RETVAL_INVALID) {
                    echo UI_error(0, "RADIUS/TLS Error", true);
                } else {
                    echo UI_okay(0, "RADIUS/TLS OK", true);
                }
                echo "</td>";
                // name
                echo "<td>
                         <input type='hidden' name='inst' value='" . $index . "'>" . $idp_instance->name . "
                      </td>";
                // external DB sync, if configured as being necessary
                if (Config::$DB['enforce-external-sync']) {
                    if ($idp_instance->getExternalDBSyncState() != EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING) {
                        echo "<td>";
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->name . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                                    <button type='submit'>" . _("Manage DB Link") . "</button> ";

                        if ($idp_instance->getExternalDBSyncState() != EXTERNAL_DB_SYNCSTATE_SYNCED) {
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
                _("Pending invitations in your federation:") . "
                               </strong>
                            </td>
                         </tr>";
                foreach ($pending_invites as $oneinvite)
                    if (strtoupper($oneinvite['country']) == strtoupper($thefed->name)) {
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
                                <button class='delete' type='submit' name='submitbutton' value='" . BUTTON_DELETE . "'>" . _("Revoke Invitation") . "</button>
                              </form>";
                        echo "      </td>
                                 </tr>";
                    }
            }
        };
        ?>
    </table>
    <hr/>
    <br/>
    <form method='post' action='inc/manageNewInst.inc.php' onsubmit='popupRedirectWindow(this);
            return false;' accept-charset='UTF-8'>
        <button type='submit' class='download'>
            <?php echo _("Register New Institution!"); ?>
        </button>
    </form>
    <br/>
    <?php
    footer();