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
$auth->authenticate();

$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");

header("Content-Type:text/html;charset=utf-8");

$validator = new \web\lib\common\InputValidation();
$uiElements = new \web\lib\admin\UIElements();

// if we have a pushed close button, submit attributes and send user back to the overview page
// if external DB sync is disabled globally, the user never gets to this page. If he came here *anyway* -> send him back immediately.
if ((isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_CLOSE ) || CONFIG['DB']['enforce-external-sync'] == FALSE) {
    header("Location: ../overview_federation.php");
    exit;
}

// if not, must operate on a proper IdP
$my_inst = $validator->IdP($_GET['inst_id']);
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

// DB link administrationis only permitted by federation operator himself
$isFedAdmin = $user->isFederationAdmin($my_inst->federation);

// if not, send the user away

if (!$isFedAdmin) {
    echo sprintf(_("You do not have the necessary privileges to manage the %s DB link state of this %s."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_inst);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"
// make a link if the admin has submitted the required info

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_SAVE) {
    // someone clever pushed the button without selecting an inst?
    if (!isset($_POST['inst_link'])) {
        header("Location: ../overview_federation.php");
        exit;
    }
    // okay, he did sumbit an inst. It's either a (string) handle from a promising 
    // candidate, or "other" as selected from the drop-down list
    if ($_POST['inst_link'] != "other") {
        $my_inst->setExternalDBId($validator->string(filter_input(INPUT_POST, 'inst_link', FILTER_SANITIZE_STRING)));
    } elseif (isset($_POST['inst_link_other'])) {
        $my_inst->setExternalDBId($validator->string(filter_input(INPUT_POST, 'inst_link_other', FILTER_SANITIZE_STRING)));
    }
    header("Location: ../overview_federation.php");
    exit;
}
?>
<h1>
    <?php printf(_("%s Database Link Status for IdP '%s'"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $my_inst->name); ?>
</h1>
<hr/>
<p>
    <?php
    $cat = new \core\CAT();
    if ($my_inst->getExternalDBSyncState() == \core\IdP::EXTERNAL_DB_SYNCSTATE_SYNCED) {

        printf(_("This %s is linked to the %s database."), $uiElements->nomenclature_inst, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</p>";
        echo "<p>" . sprintf(_("The following information about the IdP is stored in the %s DB and %s DB:"), CONFIG['APPEARANCE']['productname'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</p>";
        echo "<table><tr><td>" . sprintf(_("Information in <strong>%s Database</strong>"), CONFIG['APPEARANCE']['productname']) . "</td><td>" . sprintf(_("Information in <strong>%s Database</strong>"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</td></tr>";
        echo "<tr><td>";
        // left-hand side: CAT DB
        echo "<table>";
        $names = $my_inst->getAttributes("general:instname");

        foreach ($names as $name) {
            if ($name['lang'] == "C") {
                $language = "default/other";
            } else {
                $language = CONFIG['LANGUAGES'][$name['lang']]['display'] ?? "(unsupported language)";
            }
            echo "<tr><td>" . sprintf(_("Name of %s (%s)"), $uiElements->nomenclature_inst, $language) . "</td><td>" . $name['value'] . "</td></tr>";
        }

        $admins = $my_inst->owner();

        foreach ($admins as $admin) {
            $user = new \core\User($admin['ID']);
            $username = $user->getAttributes("user:realname");
            if (count($username) == 0) {
                $username[0]['value'] = _("Unnamed User");
            }
            echo "<tr><td>" . _("Administrator [invited as]") . "</td><td>" . $username[0]['value'] . " [" . $admin['MAIL'] . "]</td></tr>";
        }
        echo "</table>";
        // end of left-hand side
        echo "</td><td>";
        // right-hand side: external DB
        $externalid = $my_inst->getExternalDBId();
        if (!$externalid) { // we are in SYNCED state so this cannot happen
            throw new Exception("We are in SYNCSTATE_SYNCED but still there is no external DB Id available for the ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution']."!");
        }

        $extinfo = $cat->getExternalDBEntityDetails($externalid);

        echo "<table>";
        foreach ($extinfo['names'] as $lang => $name) {
            echo "<tr><td>" . sprintf(_("Name of %s (%s)"), $uiElements->nomenclature_inst, $lang) . "</td><td>$name</td>";
        }
        foreach ($extinfo['admins'] as $number => $admin_details) {
            echo "<tr><td>" . _("Administrator email") . "</td><td>" . $admin_details['email'] . "</td></tr>";
        }
        echo "</table>";
        // end of right-hand side
        echo "</td></tr></table>";
    } else if ($my_inst->getExternalDBSyncState() == \core\IdP::EXTERNAL_DB_SYNCSTATE_NOT_SYNCED) {
        $temparray = [];
        printf(_("This %s is not yet linked to the %s database."), $uiElements->nomenclature_inst, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . " ";
        echo "<strong>" . _("This means that its profiles are not made available on the user download page.") . "</strong> ";
        printf(_("You can link it to the %s database below."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $candidates = $my_inst->getExternalDBSyncCandidates();
        echo "<br/><form name='form-link-inst' action='inc/manageDBLink.inc.php?inst_id=$my_inst->identifier' method='post' accept-charset='UTF-8'>";
        printf(_("Please select an entity from the %s DB which corresponds to this CAT %s."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_inst) . " ";
        if ($candidates !== FALSE) {
            printf(_("Particularly promising entries (names in CAT and %s DB are a 100%% match) are on top of the list."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        }
        echo "<table>";
        echo "<tr><th>" . _("Link to this entity?") . "</th><th>" . sprintf(_("Name of the %s"), $uiElements->nomenclature_inst) . "</th><th>" . _("Administrators") . "</th></tr>";
        if ($candidates !== FALSE) {
            foreach ($candidates as $candidate) {
                $info = $cat->getExternalDBEntityDetails($candidate);
                echo "<tr><td><input type='radio' name='inst_link' value='$candidate' onclick='document.getElementById(\"submit\").disabled = false;'>$candidate</input></td><td>";
                foreach ($info['names'] as $lang => $name) {
                    echo "[$lang] $name<br/>";
                }
                echo "</td><td>";
                foreach ($info['admins'] as $number => $admin_details) {
                    echo "[E-Mail] " . $admin_details['email'] . "<br/>";
                }
                echo "</td></tr>";
                $temparray[] = $candidate;
            }
        }
        // we might have been wrong in our guess...
        $fed = new \core\Federation(strtoupper($my_inst->federation));
        $unmappedentities = $fed->listExternalEntities(TRUE);
        // only display the "other" options if there is at least one
        $buffer = "";

        foreach ($unmappedentities as $v) {
            $buffer .= "<option value='" . $v['ID'] . "'>[ID " . $v['ID'] . "] " . $v['name'] . "</option>";
        }

        if ($buffer != "") {
            echo "<tr><td><input type='radio' name='inst_link' id='radio-inst-other' value='other'>Other:</input></td>";
            echo "<td><select id='inst_link_other' name='inst_link_other' onchange='document.getElementById(\"radio-inst-other\").checked=true; document.getElementById(\"submit\").disabled = false;'>";
            echo $buffer;
            echo "</select></td></tr>";
        }
        // issue a big red warning if there are no link candidates at all in the federation
        if (empty($buffer) && empty($candidates)) {
            echo "<tr><td style='color:#ff0000' colspan='2'>". sprintf(_('There is no single unmapped %s in the external database for this %s!'), $uiElements->nomenclature_inst, $uiElements->nomenclature_fed)."</td></tr>";
        }
        echo "</table><button type='submit' name='submitbutton' id='submit' value='" . web\lib\common\FormElements::BUTTON_SAVE . "' disabled >" . _("Create Link") . "</button></form>";
    }
    ?>
</p>
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
<hr/>
<form action='inc/manageDBLink.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>' onclick='removeMsgbox();
            return false'><?php echo _("Close"); ?></button>
</form>
