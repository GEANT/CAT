<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("auth.inc.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("CAT.php");
require_once("UserManagement.php");

require_once("common.inc.php");
require_once("input_validation.inc.php");

authenticate();

$Cat = new CAT();
$Cat->set_locale("web_admin");

header("Content-Type:text/html;charset=utf-8");

// if we have a pushed close button, submit attributes and send user back to the overview page
// if external DB sync is disabled globally, the user never gets to this page. If he came here *anyway* -> send him back immediately.
if ((isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_CLOSE ) || Config::$DB['enforce-external-sync'] == FALSE )
        header("Location: ../overview_federation.php");

// if not, must operate on a proper IdP
$my_inst = valid_IdP($_GET['inst_id']);
$user = new User($_SESSION['user']);
$mgmt = new UserManagement();

// DB link administrationis only permitted by federation operator himself
$is_fed_admin = $user->isFederationAdmin($my_inst->federation);

// if not, send the user away

if (!$is_fed_admin) {
    echo sprintf(_("You do not have the necessary privileges to manage the %s DB link state of this institution."), Config::$CONSORTIUM['name']);
    exit(1);
}

// okay... we are indeed entitled to "do stuff"
// make a link if the admin has submitted the required info

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_SAVE && isset($_POST['inst_link'])) {
    if ($_POST['inst_link'] != "other") {
        $my_inst->setExternalDBId(valid_string_db($_POST['inst_link']));
    } else if (isset($_POST['inst_link_other'])) {
        $my_inst->setExternalDBId(valid_string_db($_POST['inst_link_other']));
    }
    header("Location: ../overview_federation.php");
}
?>
<h1>
    <?php printf(_("%s Database Link Status for IdP '%s'"), Config::$CONSORTIUM['name'], $my_inst->name); ?>
</h1>
<hr/>
<p>
    <?php
    if ($my_inst->getExternalDBSyncState() == EXTERNAL_DB_SYNCSTATE_SYNCED) {

        printf(_("This institution is linked to the %s database."), Config::$CONSORTIUM['name']) . "</p>";
        echo "<p>" . sprintf(_("The following information about the IdP is stored in the %s DB and %s DB:"), Config::$APPEARANCE['productname'], Config::$CONSORTIUM['name']) . "</p>";
        echo "<table><tr><td>" . sprintf(_("Information in <strong>%s Database</strong>"), Config::$APPEARANCE['productname']) . "</td><td>" . sprintf(_("Information in <strong>%s Database</strong>"), Config::$CONSORTIUM['name']) . "</td></tr>";
        echo "<tr><td>";
        // left-hand side: CAT DB
        echo "<table>";
        $names = $my_inst->getAttributes("general:instname");

        foreach ($names as $name) {
            $thename = unserialize($name['value']);
            if ($thename['lang'] == "C")
                $language = "default/other";
            else
                $language = Config::$LANGUAGES[$thename['lang']]['display'];

            echo "<tr><td>" . sprintf(_("Institution Name (%s)"), $language) . "</td><td>" . $thename['content'] . "</td></tr>";
        }

        $admins = $my_inst->owner();

        foreach ($admins as $admin) {
            $user = new User($admin['ID']);
            $username = $user->getAttributes("user:realname");
            if (count($username) == 0)
                $username[0]['value'] = _("Unnamed User");
            echo "<tr><td>" . _("Administrator [invited as]") . "</td><td>" . $username[0]['value'] . " [" . $admin['MAIL'] . "]</td></tr>";
        }
        echo "</table>";
        // end of left-hand side
        echo "</td><td>";
        // right-hand side: external DB
        $extinfo = $my_inst->getExternalDBEntityDetails();
        echo "<table>";
        foreach ($extinfo['names'] as $lang => $name)
            echo "<tr><td>" . sprintf(_("Institution Name (%s)"), $lang) . "</td><td>$name</td>";
        foreach ($extinfo['admins'] as $number => $admin_details)
            echo "<tr><td>" . _("Administrator email") . "</td><td>" . $admin_details['email'] . "</td></tr>";
        echo "</table>";
        // end of right-hand side
        echo "</td></tr></table>";
    } else if ($my_inst->getExternalDBSyncState() == EXTERNAL_DB_SYNCSTATE_NOT_SYNCED) {
        $temparray = array();
        printf(_("This institution is not yet linked to the %s database."), Config::$CONSORTIUM['name']) . " ";
        echo "<strong>" . _("This means that its profiles are not made available on the user download page.") . "</strong> ";
        printf(_("You can link it to the %s database below."), Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name']);
        $candidates = $my_inst->getExternalDBSyncCandidates();
        echo "<br/><form name='form-link-inst' action='inc/manageDBLink.inc.php?inst_id=$my_inst->identifier' method='post' accept-charset='UTF-8'>";
        printf(_("Please select an entity from the %s DB which corresponds to this CAT institution."), Config::$CONSORTIUM['name']) . " ";
        if (count($candidates) > 0)
            printf(_("Particularly promising entries (names in CAT and %s DB are a 100%% match) are on top of the list."), Config::$CONSORTIUM['name']);
        echo "<table><tr><th>" . _("Link to this entity?") . "</th><th>" . _("Name of the institution") . "</th><th>" . _("Administrators") . "</th></tr>";
        foreach ($candidates as $candidate) {
            $info = Federation::getExternalDBEntityDetails($candidate);
            echo "<tr><td><input type='radio' name='inst_link' value='$candidate'>$candidate</input></td><td>";
            foreach ($info['names'] as $lang => $name)
                echo "[$lang] $name<br/>";
            echo "</td><td>";
            foreach ($info['admins'] as $number => $admin_details)
                echo "[E-Mail] " . $admin_details['email'] . "<br/>";
            echo "</td></tr>";
            $temparray[] = $candidate;
        }
        // we might have been wrong in our guess...
        $fed = new Federation(strtoupper($my_inst->federation));
        $unmappedentities = $fed->listUnmappedExternalEntities();
        // only display the "other" options if there is at least one
        $buffer = "";
        $idparray = array();

        // preferred lang first
        foreach ($unmappedentities as $entity)
            if (array_search($entity['ID'], $temparray) === FALSE && isset($entity['lang']) && $entity['lang'] == CAT::$lang_index) {
                $idparray[$entity['ID']] = $entity['name'];
                $temparray[] = $entity['ID'];
            }
        // English second
        foreach ($unmappedentities as $entity)
            if (array_search($entity['ID'], $temparray) === FALSE && isset($entity['lang']) && $entity['lang'] == "en") {
                  $idparray[$entity['ID']] = $entity['name'];
                $temparray[] = $entity['ID'];
            }
        // any other language last
        foreach ($unmappedentities as $entity)
            if (array_search($entity['ID'], $temparray) === FALSE && isset($entity['lang'])) {
                $idparray[$entity['ID']] = $entity['name'];
                $temparray[] = $entity['ID'];
            }
            $current_locale = setlocale(LC_ALL,0);
            setlocale(LC_ALL,Config::$LANGUAGES[CAT::$lang_index]['locale']);
            asort($idparray,SORT_LOCALE_STRING);
            setlocale(LC_ALL,$current_locale);
            foreach ($idparray as $id => $v) {
               $buffer .= "<option value='" . $id . "'>[ID " . $id . "] " . $v . "</option>";
            }

        if ($buffer != "") {
            echo "<tr><td><input type='radio' name='inst_link' id='radio-inst-other' value='other'>Other:</input></td>";
            echo "<td><select id='inst_link_other' name='inst_link_other' onchange='document.getElementById(\"radio-inst-other\").checked=true'>";
            echo $buffer;
            echo "</select></td></tr>";
        }
        echo "</table><button type='submit' name='submitbutton' value='".BUTTON_SAVE."'>" . _("Create Link") . "</button></form>";
    }
    ?>
</p>
<br/>
<?php
$pending_invites = $mgmt->listPendingInvitations($my_inst->identifier);
debug(4, "Displaying pending invitations for $my_inst->identifier.\n");
if (count($pending_invites) > 0) {
    echo "<strong>" . _("Pending invitations for this IdP") . "</strong>";
    echo "<table>";
    foreach ($pending_invites as $invitee)
        echo "<tr><td>$invitee</td></tr>";
    echo "</table>";
}
?>
<br/>
<hr/>
<form action='inc/manageDBLink.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE;?>' onclick='removeMsgbox(); return false'><?php echo _("Close"); ?></button>
</form>
