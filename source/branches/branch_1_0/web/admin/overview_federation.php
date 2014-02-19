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

require_once("inc/admin_header.php");
require_once("inc/input_validation.inc.php");
require_once("inc/common.inc.php");

$cat = defaultPagePrelude(sprintf(_("%s: Federation Management"), Config::$APPEARANCE['productname']));
$user = new User($_SESSION['user']);
?>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    productheader();
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
        include "inc/admin_footer.php";
        exit(0);
    }

    $feds = $user->getAttributes("user:fedadmin");

    foreach ($feds as $onefed) {
        $thefed = new Federation(strtoupper($onefed['value']));

        echo "<div class='infobox'><h2>";
        echo sprintf(_("Federation Statistics: %s"), strtoupper($thefed->identifier));
        echo "</h2>";
        echo "<table>";
        // idp stats
        echo "<tr><th style='text-align:left;'>"._("IdPs Total")."</th><th colspan='2'>"._("Public Download")."</th></tr>";
        echo "<tr><td>".count($thefed->listIdentityProviders(0))."</td><td colspan='2'>".count($thefed->listIdentityProviders(1))."</td></tr>";
        // download stats
        echo "<tr><th style='text-align:left;'>"._("Downloads")."</th><th style='text-align:left;'>"._("Admin")."</th><th style='text-align:left;'>"._("User")."</th></tr>";
        echo Federation::downloadStats($thefed->identifier, TRUE);
        echo "</table>";
        echo "</div>";
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
        $helptext = "<h3>" . sprintf(_("Need help? Refer to the <a href='%s'>Federation Operator manual</a>"),"https://confluence.terena.org/x/NACvAg")."</h3>";
    else
        $helptext = "";
    echo $helptext;
    ?>
    <table class='user_overview' style='border:0px;'>
        <tr>
            <th><?php echo _("Institution Name"); ?></th>

            <?php
            $pending_invites = $mgmt->listPendingInvitations();
            if (Config::$DB['enforce-external-sync'])
                echo "<th>" . sprintf(_("%s Database Sync Status"), Config::$CONSORTIUM['name']) . "</th>";
            ?>
            <th><?php echo _("Administrator Management"); ?></th>
        </tr>
        <?php
        foreach ($feds as $onefed) {
            $thefed = new Federation(strtoupper($onefed['value']));
            echo "<tr><td colspan='3'><strong>" . sprintf(_("Your federation %s contains the following institutions:"), '<span style="color:green">' . $thefed::$FederationList[$onefed['value']] . '</span>') . "</strong></td><td></td></tr>";
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
                echo "<tr>
                            <td>
                               <input type='hidden' name='inst' value='" . $index . "'>" . $idp_instance->name . "
                            </td>";
                if (Config::$DB['enforce-external-sync']) {
                    if ($idp_instance->getExternalDBSyncState() != EXTERNAL_DB_SYNCSTATE_NOTSUBJECTTOSYNCING) {
                        echo "<td>";
                        echo "<form method='post' action='inc/manageDBLink.inc.php?inst_id=" . $idp_instance->identifier . "' onsubmit='popupRedirectWindow(this); return false;'>
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

                echo "<td>
                               <div style='white-space: nowrap;'>
                                  <form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $index . "' onsubmit='popupRedirectWindow(this); return false;'>
                                      <button type='submit'>" .
                _("Add/Remove Administrators") . "
                                      </button>
                                  </form>
                                </div>
                             </td>
                          </tr>";
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
                        echo "<form method='post' action='overview_federation.php'>
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
                            return false;'>
        <button type='submit' class='download'>
            <?php echo _("Register New Institution!"); ?>
        </button>
    </td>
</tr>
</table>
</form>
<br/>
<?php
include "inc/admin_footer.php";
?>

