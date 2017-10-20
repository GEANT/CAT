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
$uiElements = new \web\lib\admin\UIElements();
$auth->authenticate();

// if we have a pushed close button, submit attributes and send user back to the overview page

if ((isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_CLOSE)) {
    header("Location: ../overview_federation.php");
    exit;
}

$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");

header("Content-Type:text/html;charset=utf-8");

// new invitations are only permitted by federation operator himself
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();
$isFedAdmin = $user->isFederationAdmin();

// if not, send the user away
if (!$isFedAdmin) {
    echo sprintf(_("You do not have the necessary privileges to register new %ss."), $uiElements->nomenclature_inst);
    exit(1);
}
// okay... we are indeed entitled to "do stuff"
$feds = $user->getAttributes("user:fedadmin");
?>
<h1>
    <?php printf(_("%s - Register new %s"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclature_inst); ?>
</h1>
<?php
echo sprintf(_("On this page, you can add a new %s to your %s. Please fill out the form below to send out an email invitation to the new %s administrator."), $uiElements->nomenclature_inst, $uiElements->nomenclature_fed, $uiElements->nomenclature_inst);
if (CONFIG['DB']['enforce-external-sync']) {
    echo "<p>" . sprintf(_("You can either register a known %s (as defined in the %s database) or create a totally new %s."), $uiElements->nomenclature_inst, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_inst) . "</p>";
    echo "<p>" . sprintf(_("The latter one is typically for an %s which is yet in a testing phase and therefore doesn't appear in the %s database yet."), $uiElements->nomenclature_inst, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</p>";
    echo "<p>" . sprintf(_("Please keep in mind that any profiles of such a new %s will only be made available on the user download page after you have linked it to an entity in the %s database (but they are otherwise fully functional)."), $uiElements->nomenclature_inst, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</p>";
}
?>
<hr/>
<form name='sendinvite' action='inc/sendinvite.inc.php' method='post' accept-charset='UTF-8'>
    <table>
        <?php
        if (CONFIG['DB']['enforce-external-sync']) {
            echo "<tr><td>
                <input type='radio' name='creation' value='existing'>" . _("Existing IdP:") . "</input>
                     </td>";

            echo "<td colspan='2'>
                <select id='externals' name='externals' onchange='document.sendinvite.creation[0].checked=true; document.sendinvite.mailaddr.value=this.options[this.selectedIndex].id;'>
                    <option value='FREETEXT'>" . _("--- select IdP here ---") . "</option>";

            foreach ($feds as $fed_value) {
                $thefed = new \core\Federation(strtoupper($fed_value['value']));
                $temparray = [];
                $contacts = [];
                $entities = $thefed->listExternalEntities(TRUE);

                foreach ($entities as $v) {
                    echo "<option id='" . $v['contactlist'] . "' value='" . $v['ID'] . "'>[" . $fed_value['value'] . "] " . $v['name'] . "</option>";
                }
            }

            echo "</select></td></tr>";
        }
        ?>
        <tr>
            <td>
                <input type='radio' name='creation' value='new'><?php echo _("New IdP"); ?></input>
            </td>
            <td>
                <?php echo _("Name"); ?><input type='text' size='40' id='name' name='name' onchange='document.sendinvite.creation[1].checked = true'/>
            </td>
            <td><?php echo $uiElements->nomenclature_fed; ?>
                <select id='country' name='country'>
                    <?php
                    $cat = new \core\CAT();
                    foreach ($cat->printCountryList() as $iso_code => $country) {
                        foreach ($feds as $fed_value) {
                            if (strtoupper($fed_value['value']) == strtoupper($iso_code)) {
                                echo "<option value='$iso_code'>$country</option>";
                            }
                        }
                    }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    <hr/>
    <table>    
        <tr>
            <td><?php echo _("Administrator's E-Mail:"); ?></td>
            <td><input type='text' size='40' id='mailaddr' name='mailaddr'/></td>
        </tr>
    </table>
    <hr/>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Send invitation"); ?></button>
</form>
<br/>
<form action='inc/manageNewInst.inc.php' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_CLOSE; ?>'><?php echo _("Close"); ?></button>
</form>
