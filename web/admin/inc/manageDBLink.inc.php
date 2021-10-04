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
$auth->authenticate();

$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");

header("Content-Type:text/html;charset=utf-8");

$validator = new \web\lib\common\InputValidation();
$uiElements = new \web\lib\admin\UIElements();

// if we have a pushed close button, submit attributes and send user back to the overview page
// if external DB sync is disabled globally, the user never gets to this page. If he came here *anyway* -> send him back immediately.
if ((isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_CLOSE ) || \config\Master::DB['enforce-external-sync'] == FALSE) {
    header("Location: ../overview_federation.php");
    exit;
}

// if not, must operate on a proper IdP
$my_inst = $validator->existingIdP($_GET['inst_id']);
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

// DB link administrationis only permitted by federation operator himself
$isFedAdmin = $user->isFederationAdmin($my_inst->federation);

// if not, send the user away

if (!$isFedAdmin) {
    echo sprintf(_("You do not have the necessary privileges to manage the %s DB link state of this %s."), \config\ConfAssistant::CONSORTIUM['display_name'], $uiElements->nomenclatureParticipant);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"
// make a link if the admin has submitted the required info

if (isset($_POST['submitbutton'])) {
    switch ($_POST['submitbutton']) {
        case web\lib\common\FormElements::BUTTON_SAVE:
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
            break;
        case web\lib\common\FormElements::BUTTON_DELETE:
            $my_inst->removeExternalDBId();
            break;
        default:
    }
    header("Location: ../overview_federation.php");
    exit;
}
?>
<h1>
    <?php printf(_("%s Database Link Status for IdP '%s'"), \config\ConfAssistant::CONSORTIUM['display_name'], $my_inst->name); ?>
</h1>
<hr/>
<p>
    <?php
    $cat = new \core\CAT();
    switch ($my_inst->getExternalDBSyncState()) {
        case \core\IdP::EXTERNAL_DB_SYNCSTATE_SYNCED:
            printf(_("This %s is linked to the %s database."), $uiElements->nomenclatureParticipant, \config\ConfAssistant::CONSORTIUM['display_name']) . "</p>";
            echo "<p>" . sprintf(_("The following information about the IdP is stored in the %s DB and %s DB:"), \config\Master::APPEARANCE['productname'], \config\ConfAssistant::CONSORTIUM['display_name']) . "</p>";
            echo "<table><tr><td>" . sprintf(_("Information in <strong>%s Database</strong>"), \config\Master::APPEARANCE['productname']) . "</td><td>" . sprintf(_("Information in <strong>%s Database</strong>"), \config\ConfAssistant::CONSORTIUM['display_name']) . "</td></tr>";
            echo "<tr><td>";
            // left-hand side: CAT DB
            echo "<table>";
            $names = $my_inst->getAttributes("general:instname");

            foreach ($names as $name) {
                if ($name['lang'] == "C") {
                    $language = "default/other";
                } else {
                    $language = \config\Master::LANGUAGES[$name['lang']]['display'] ?? "(unsupported language)";
                }
                echo "<tr><td>" . sprintf(_("%s Name (%s)"), $uiElements->nomenclatureParticipant, $language) . "</td><td>" . $name['value'] . "</td></tr>";
            }

            $admins = $my_inst->listOwners();

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
            if (is_bool($externalid)) { // we are in SYNCED state so this cannot happen
                throw new Exception("We are in SYNCSTATE_SYNCED but still there is no external DB Id available for the " . $uiElements->nomenclatureParticipant . "!");
            }

            $extinfo = $cat->getExternalDBEntityDetails($externalid);

            echo "<table>";
            foreach ($extinfo['names'] as $lang => $name) {
                echo "<tr><td>" . sprintf(_("%s Name (%s)"), $uiElements->nomenclatureParticipant, $lang) . "</td><td>$name</td>";
            }
            foreach ($extinfo['admins'] as $number => $admin_details) {
                echo "<tr><td>" . _("Administrator email") . "</td><td>" . $admin_details['email'] . "</td></tr>";
            }
            echo "</table>";
            // end of right-hand side
            echo "</td></tr></table>";
            echo "<p>" . _("If this mapping is not correct any more, you can remove the link:") . " ";
            echo "<form name='form-unlink-inst' action='inc/manageDBLink.inc.php?inst_id=$my_inst->identifier' method='post' accept-charset='UTF-8'>";
            echo "<button type='submit' class='delete' name='submitbutton' id='submit' value='" . web\lib\common\FormElements::BUTTON_DELETE . "'>" . _("Unlink") . "</button></form>";
            break;
        case \core\IdP::EXTERNAL_DB_SYNCSTATE_NOT_SYNCED:
            $temparray = [];
            printf(_("This %s is not yet linked to the %s database."), $uiElements->nomenclatureParticipant, \config\ConfAssistant::CONSORTIUM['display_name']) . " ";
            echo "<strong>" . _("This means that its profiles are not made available on the user download page.") . "</strong> ";
            printf(_("You can link it to the %s database below."), \config\ConfAssistant::CONSORTIUM['display_name']);
            $candidates = $my_inst->getExternalDBSyncCandidates($my_inst->type);
            echo "<br/><form name='form-link-inst' action='inc/manageDBLink.inc.php?inst_id=$my_inst->identifier' method='post' accept-charset='UTF-8'>";
            printf(_("Please select an entity from the %s DB which corresponds to this CAT %s."), \config\ConfAssistant::CONSORTIUM['display_name'], $uiElements->nomenclatureParticipant) . " ";
            if (count($candidates) > 0) {
                printf(_("Particularly promising entries (names in CAT and %s DB are a 100%% match) are on top of the list."), \config\ConfAssistant::CONSORTIUM['display_name']);
            }
            echo "<table>";
            echo "<tr><th>" . _("Link to this entity?") . "</th><th>" . sprintf(_("%s Name"), $uiElements->nomenclatureParticipant) . "</th><th>" . _("Administrators") . "</th></tr>";

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
            // we might have been wrong in our guess...
            $fed = new \core\Federation(strtoupper($my_inst->federation));
            $unmappedentities = $fed->listExternalEntities(TRUE, $my_inst->type);
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
                echo "<tr><td style='color:#ff0000' colspan='2'>" . sprintf(_('There is no single unmapped %s in the external database for this %s!'), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureFed) . "</td></tr>";
            }
            echo "</table><button type='submit' name='submitbutton' id='submit' value='" . web\lib\common\FormElements::BUTTON_SAVE . "' disabled >" . _("Create Link") . "</button></form>";
            break;
        default:
    }
    ?>
</p>
<hr/>
<form action='inc/manageDBLink.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>' onclick='removeMsgbox();
            return false'><?php echo _("Close"); ?></button>
</form>
