<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("auth.inc.php");
require_once("common.inc.php");
require_once("input_validation.inc.php");

authenticate();

$languageInstance = new \core\Language();
$languageInstance->setTextDomain("web_admin");

header("Content-Type:text/html;charset=utf-8");

// where did the user come from? Save this...
// the user can come only from overview_user or overview_federation
// to prevent HTTP response slitting attacks, pick and rewrite the destination URL

if (isset($_SERVER['HTTP_REFERER']) && ($_SERVER['HTTP_REFERER'] != "") && preg_match("/overview_federation/", $_SERVER['HTTP_REFERER'])) {
    $dest = "../overview_federation.php";
} else { // not from fed adin page? destination is overview_user
    $dest = "../overview_user.php";
}

$my_inst = valid_IdP($_GET['inst_id']);
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

// either the operation is done by federation operator himself
$isFedAdmin = $user->isFederationAdmin($my_inst->federation);

// or an admin of the IdP with federation admin blessings
$is_admin_with_blessing = FALSE;
$owners = $my_inst->owner();
foreach ($owners as $oneowner) {
    if ($oneowner['ID'] == $_SESSION['user'] && $oneowner['LEVEL'] == "FED") {
        $is_admin_with_blessing = TRUE;
    }
}

// if none of the two, send the user away

if (!$isFedAdmin && !$is_admin_with_blessing) {
    echo _("You do not have the necessary privileges to alter administrators of this institution. In fact, you shouldn't have come this far!");
    exit(1);
}

// okay... we are indeed entitled to "do stuff"

if (isset($_POST['submitbutton'])) {
    if ($_POST['submitbutton'] == BUTTON_DELETE) {
        if (isset($_POST['admin_id'])) {
            $ownermgmt = new \core\UserManagement();
            $ownermgmt->removeAdminFromIdP($my_inst, $_POST['admin_id']);
            // if the user deleted himself, go back to overview page. Otherwise, just stay here and display the remaining owners
            // we don't decide about that here; it's done by JS magic in the calling button
            if ($_POST['admin_id'] == $_SESSION['user']) {
                header("Location: $dest");
                exit;
            }
        } else {
            echo "Fatal Error: asked to delete an administrator, but no administrator ID was given!";
            exit(1);
        }
    } elseif ($_POST['submitbutton'] == BUTTON_TAKECONTROL) {
        if ($isFedAdmin) {
            $ownermgmt = new \core\UserManagement();
            $ownermgmt->addAdminToIdp($my_inst, $_SESSION['user']);
        } else {
            echo "Fatal Error: you wanted to take control over an institution, but are not a federation operator!";
            exit(1);
        }
    }
}
?>

<h1>
    <?php printf(_("Administrators for IdP '%s'"), $my_inst->name); ?>
</h1>
<hr/>
<?php
if (isset($_GET['invitation'])) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    switch ($_GET['invitation']) {
        case "SUCCESS":
            echo UI_remark(_("The invitation email was sent successfully."), _("The invitation email was sent."));
            break;
        case "FAILURE":
            echo UI_error(_("The invitation email could not be sent!"), _("The invitation email could not be sent!"));
            break;
        default:
            throw new Exception("Error: unknown result code of invitation!?!");
    }
    echo "</table></div>";
}

if ($isFedAdmin) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo UI_remark(_("You are the federation administrator of this IdP. You can invite new administrators, who can in turn appoint further administrators on their own."), _("Federation Administrator"));
    echo "</table></div>";
}

if (!$isFedAdmin && $is_admin_with_blessing) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo UI_remark(_("You are an administrator of this IdP who was directly appointed by the federation administrator. You can appoint further administrators, but these can't in turn appoint any more administrators."), _("Directly Appointed IdP Administrator"));
    echo "</table></div>";
}
?>
<table>
    <?php
    foreach ($my_inst->owner() as $oneowner) {
        $ownerinfo = new \core\User($oneowner['ID']);
        $ownername = $ownerinfo->getAttributes("user:realname");
        if (count($ownername) > 0) {
            $prettyprint = $ownername[0]['value'];
        } else {
            $prettyprint = _("User without name");
        }
        echo "
            <tr>
              <td>
                 <strong>$prettyprint</strong>
                 <br/>";

        if ($oneowner['MAIL'] != "SELF-APPOINTED") {
            printf(_("(originally invited as %s)"), $oneowner['MAIL']);
        } else {
            echo _("(self-appointed)");
        }

        echo "</td>
              <td>
                <form action='inc/manageAdmins.inc.php?inst_id=" . $my_inst->identifier . "' method='post' " . ( $oneowner['ID'] != $_SESSION['user'] ? "onsubmit='popupRedirectWindow(this); return false;'" : "" ) . " accept-charset='UTF-8'>
                <input type='hidden' name='admin_id' value='" . $oneowner['ID'] . "'></input>
                <button type='submit' name='submitbutton' class='delete' value='" . BUTTON_DELETE . "'>" . _("Delete Administrator") . "</button>
                </form>
              </td>
            </tr>";
    }
    ?>
</table>

<br/>
<?php
$pending_invites = $mgmt->listPendingInvitations($my_inst->identifier);
$loggerInstance = new \core\Logging();
$loggerInstance->debug(4, "Displaying pending invitations for $my_inst->identifier.\n");
if (count($pending_invites) > 0) {
    echo "<strong>" . _("Pending invitations for this IdP") . "</strong>";
    echo "<table>";
    foreach ($pending_invites as $invitee) {
        echo "<tr><td>" . $invitee['mail'] . "</td></tr>";
    }
    echo "</table>";
}
?>
<br/>
<form action='inc/sendinvite.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
    <?php echo _("New administrator's email address(es) (comma-separated):"); ?><input type="text" name="mailaddr"/><button type='submit' name='submitbutton' value='<?php echo BUTTON_SAVE; ?>'><?php echo _("Invite new administrator"); ?></button>
</form>
<br/>
<?php
if ($isFedAdmin) {
    $is_admin_himself = FALSE;
    foreach ($owners as $oneowner) {
        if ($oneowner['ID'] == $_SESSION['user']) {
            $is_admin_himself = TRUE;
        }
    }

    if (!$is_admin_himself) {
        echo "<form action='inc/manageAdmins.inc.php?inst_id=$my_inst->identifier' method='post' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='" . BUTTON_TAKECONTROL . "'>" . _("Take control of this institution") . "</button>
</form>";
    }
}
?>
<hr/>
<form action='inc/manageAdmins.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE; ?>' onclick='removeMsgbox(); return false;'><?php echo _("Close"); ?></button>
</form>
