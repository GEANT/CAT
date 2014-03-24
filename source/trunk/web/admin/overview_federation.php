<?php
/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("UserManagement.php");
require_once("Federation.php");
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
    productheader("FEDERATION", $cat->lang_index);
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
    ?>
    <table class='user_overview' style='border:0px;'>
        <tr>
            <th><?php echo _("Deployment Status"); ?></th>
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
            echo "<tr><td colspan='4'><strong>" . sprintf(_("Your federation %s contains the following institutions:"), '<span style="color:green">' . $thefed::$FederationList[$onefed['value']] . '</span>') . "</strong></td></tr>";

            // extract only pending invitations for *this* fed
            $display_pendings = FALSE;
            foreach ($pending_invites as $oneinvite)
                if (strtoupper($oneinvite['country']) == strtoupper($thefed->identifier)) {
                    // echo "PENDINGS!";
                    $display_pendings = TRUE;
                }

            $idps = $thefed->listIdentityProviders();

            $my_idps = array();
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
                echo "<td>" . ($idp_instance->isOneProfileConfigured() ? "C" : "" ) . ($idp_instance->isOneProfileShowtime() ? "V" : "" ) . "</td>";
                // name
                echo "<td>
                         <input type='hidden' name='inst' value='" . $index . "'>" . $idp_instance->name . "
                      </td>";
                // external DB sync, if configured as being necessary
                if (Config::$DB['enforce-external-sync']) {
                    if ($idp_instance->getExternalDBSyncState() != EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING) {
                        echo "<td>";
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
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
                    if (strtoupper($oneinvite['country']) == strtoupper($thefed->identifier)) {
                        echo "<tr>
                                    <td>" .
                        $oneinvite['name'] . "
                                    </td>
                                    <td>" .
                        $oneinvite['mail'] . "
                                    </td>
                                    <td>";
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
    ?>

