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
$is_admin_with_blessing = $my_inst->isPrimaryOwner($_SESSION['user']);
// if none of the two, send the user away
if (!$isFedAdmin && !$is_admin_with_blessing) {
    echo sprintf(_("You do not have the necessary privileges to alter administrators of this %s. In fact, you shouldn't have come this far!"), $uiElements->nomenclature_inst);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"

if (isset($_POST['submitbutton'])) {
    switch ($_POST['submitbutton']) {
        case web\lib\common\FormElements::BUTTON_DELETE:
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
            break;
        case web\lib\common\FormElements::BUTTON_TAKECONTROL:
            if ($isFedAdmin) {
                $ownermgmt = new \core\UserManagement();
                $ownermgmt->addAdminToIdp($my_inst, $_SESSION['user']);
            } else {
                echo "Fatal Error: you wanted to take control over an " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'] . ", but are not a " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation'] . " operator!";
                exit(1);
            }
            break;
        default:
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

if ($isFedAdmin) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo $uiElements->boxRemark(sprintf(_("You are the %s administrator. You can invite new administrators, who can in turn appoint further administrators on their own."), $uiElements->nomenclature_fed), sprintf(_("%s Administrator"), $uiElements->nomenclature_fed));
    echo "</table></div>";
}

if (!$isFedAdmin && $is_admin_with_blessing) {
    echo "<div class='ca-summary' style='position:relative;'><table>";
    echo $uiElements->boxRemark(sprintf(_("You are an administrator who was directly appointed by the %s administrator. You can appoint further administrators, but these can't in turn appoint any more administrators."), $uiElements->nomenclature_fed), _("Directly Appointed Administrator"));
    echo "</table></div>";
}
?>
<table>
    <?php
    $owners = $my_inst->listOwners();
    foreach ($owners as $oneowner) {
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
<img src='../resources/images/icons/loading51.gif' id='spin' style='position:absolute;left: 50%; top: 50%; transform: translate(-100px, -50px); display:none;'>
<form action='inc/sendinvite.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
    <?php echo _("New administrator's email address(es) (comma-separated):"); ?><input type="text" name="mailaddr"/><button type='submit' name='submitbutton' onclick='document.getElementById("spin").style.display = "block"' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Invite new administrator"); ?></button>
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
