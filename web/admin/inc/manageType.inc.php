<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */
?>
<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$languageInstance = new \core\common\Language();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();
$languageInstance->setTextDomain("web_admin");
$loggerInstance = new \core\common\Logging();


header("Content-Type:text/html;charset=utf-8");

$validator = new \web\lib\common\InputValidation();

// where did the user come from? Save this...
// the user can come only from overview_user or overview_federation
// to prevent HTTP response slitting attacks, pick and rewrite the destination URL

if (isset($_SERVER['HTTP_REFERER']) && ($_SERVER['HTTP_REFERER'] != "") && preg_match("/overview_federation/", $_SERVER['HTTP_REFERER'])) {
    $dest = "../overview_federation.php";
} else { // not from fed admin page? destination is overview_user
    $dest = "../overview_user.php";
}

$my_inst = $validator->existingIdP($_GET['inst_id']);
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

// either the operation is done by federation operator himself
$isFedAdmin = $user->isFederationAdmin($my_inst->federation);
// or an admin of the IdP with federation admin blessings
$is_admin_with_blessing = $my_inst->isPrimaryOwner($_SESSION['user']);

if (isset($_SESSION['entitledIdPs']) && in_array($my_inst->identifier, $_SESSION['entitledIdPs'])) {
        $is_admin_with_blessing = true;
}

if (isset($_SESSION['resyncedIdPs']) && in_array($my_inst->identifier, $_SESSION['resyncedIdPs'])) {
    $is_admin_with_blessing = true;
}   

// if none of the two, send the user away
if (!$isFedAdmin && !$is_admin_with_blessing) {
    echo sprintf(_("You do not have the necessary privileges to alter administrators of this %s. In fact, you shouldn't have come this far!"), $uiElements->nomenclatureParticipant);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"


if (isset($_POST['submitbutton'])) {
    $newType = $_POST['inst_type'];
    if (!in_array($newType, [\core\IdP::TYPE_IDPSP, \core\IdP::TYPE_IDP, \core\IdP::TYPE_SP])) {
        header("Location: ../overview_federation.php");
        exit;        
    }
    
    $my_inst->updateType($newType);
    header("Location: ../overview_federation.php");
    exit;
}

?>
<h1>
    <?php printf(_("IdP type for '%s'"), $my_inst->name); ?>
</h1>
<hr/>

<?php
printf(_("Current type: %s"), $my_inst->type);
$radios = "";
$checked = [];

$checked[$my_inst->type] = "checked ";

echo "<br/><form name='form-link-inst' action='inc/manageType.inc.php?inst_id=$my_inst->identifier' method='post' accept-charset='UTF-8'>";

foreach ([\core\IdP::TYPE_IDPSP, \core\IdP::TYPE_IDP, \core\IdP::TYPE_SP] as $type) {
    $radios .= "<input type='radio' name='inst_type' value='$type' ".(isset($checked[$type]) ? $checked[$type] : "").">".$type;
}





echo "<p>";
printf(_("New type %s"), $radios);
echo "<p>";
echo "<button type='submit' name='submitbutton' id='submit' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save") . "</button></form>";

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
?>


<br/>
<hr/>
<form action='inc/manageType.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>' onclick='removeMsgbox(<?php echo $my_inst->identifier; ?>); return false;'><?php echo _("Close"); ?></button>
</form>
