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

$auth = new \web\lib\admin\Authentication();
$languageInstance = new \core\common\Language();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();
$languageInstance->setTextDomain("web_admin");


header("Content-Type:text/html;charset=utf-8");

$validator = new \web\lib\common\InputValidation();

// where did the user come from? Save this...
// the user can come only from overview_user or overview_federation
// to prevent HTTP response slitting attacks, pick and rewrite the destination URL

if (isset($_SERVER['HTTP_REFERER']) && ($_SERVER['HTTP_REFERER'] != "") && preg_match("/overview_federation/", $_SERVER['HTTP_REFERER'])) {
    $dest = "../overview_federation.php";
} else { // not from fed adin page? destination is overview_user
    $dest = "../overview_user.php";
}

$my_inst = $validator->IdP($_GET['inst_id']);
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
    echo sprintf(_("You do not have the necessary privileges to alter administrators of this %s. In fact, you shouldn't have come this far!"), $uiElements->nomenclature_inst);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"

if (isset($_POST['submitbutton'])) {
    if ($_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_DELETE) {
        if (isset($_POST['admin_id'])) {
            $ownermgmt = new \core\UserManagement();
            $ownermgmt->removeAdminFromIdP($my_inst, filter_input(INPUT_POST, 'admin_id', FILTER_SANITIZE_STRING));
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
    } elseif ($_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_TAKECONTROL) {
        if ($isFedAdmin) {
            $ownermgmt = new \core\UserManagement();
            $ownermgmt->addAdminToIdp($my_inst, $_SESSION['user']);
        } else {
            echo "Fatal Error: you wanted to take control over an ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'].", but are not a ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation']." operator!";
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
            throw new Exception("Error: unknown result code of invitation!?!");
    }
    echo "</table></div>";
}

if ($isFedAdmin) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo $uiElements->boxRemark(sprintf(_("You are the %s administrator of this %s. You can invite new administrators, who can in turn appoint further administrators on their own."),$uiElements->nomenclature_fed, $uiElements->nomenclature_inst), sprintf(_("%s Administrator"),$uiElements->nomenclature_fed));
    echo "</table></div>";
}

if (!$isFedAdmin && $is_admin_with_blessing) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo $uiElements->boxRemark(sprintf(_("You are an administrator of this %s, and were directly appointed by the %s administrator. You can appoint further administrators, but these can't in turn appoint any more administrators."),$uiElements->nomenclature_inst ,$uiElements->nomenclature_fed), _("Directly Appointed IdP Administrator"));
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
                <button type='submit' name='submitbutton' class='delete' value='" . web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Delete Administrator") . "</button>
                </form>
              </td>
            </tr>";
    }
    ?>
</table>

<br/>
<?php
$pending_invites = $mgmt->listPendingInvitations($my_inst->identifier);
$loggerInstance = new \core\common\Logging();
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
    <?php echo _("New administrator's email address(es) (comma-separated):"); ?><input type="text" name="mailaddr"/><button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Invite new administrator"); ?></button>
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
    <button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_TAKECONTROL . "'>" . sprintf(_("Take control of this %s"), $uiElements->nomenclature_inst) . "</button>
</form>";
    }
}
?>
<hr/>
<form action='inc/manageAdmins.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>' onclick='removeMsgbox(); return false;'><?php echo _("Close"); ?></button>
</form>
