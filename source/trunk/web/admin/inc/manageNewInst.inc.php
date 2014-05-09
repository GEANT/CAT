<?php
/*
 * ******************************************************************************
 * *  Copyright 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */
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

// if we have a pushed close button, submit attributes and send user back to the overview page

if ((isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_CLOSE))
    header("Location: ../overview_federation.php");

$cat = new CAT();
$cat->set_locale("web_admin");

header("Content-Type:text/html;charset=utf-8");

// new invitations are only permitted by federation operator himself
$user = new User($_SESSION['user']);
$mgmt = new UserManagement();
$is_fed_admin = $user->isFederationAdmin();

// if not, send the user away
if (!$is_fed_admin) {
    echo sprintf(_("You do not have the necessary privileges to register new IdPs."), Config::$CONSORTIUM['name']);
    exit(1);
}
// okay... we are indeed entitled to "do stuff"
$feds = $user->getAttributes("user:fedadmin");
?>
<h1>
    <?php printf(_("%s - Register New Institution"), Config::$APPEARANCE['productname']); ?>
</h1>
<?php
echo _("On this page, you can add new institutions to your federation. Please fill out the form below to send out an email invitation to the new institution's administrator.");
if (Config::$DB['enforce-external-sync']) {
    echo "<p>" . sprintf(_("You can either register a known IdP (as defined in the %s database) or create a totally new IdP."), Config::$CONSORTIUM['name']) . "</p>";
    echo "<p>" . sprintf(_("The latter one is typically for institutions which are yet in a testing phase and therefore don't appear in the %s database yet."), Config::$CONSORTIUM['name']) . "</p>";
    echo "<p>" . sprintf(_("Please keep in mind that any profiles of such new institutions will only be made available on the user download page after you have linked them to an entity in the %s database (but they are otherwise fully functional)."), Config::$CONSORTIUM['name']) . "</p>";
};
?>
<hr/>
<form name='sendinvite' action='inc/sendinvite.inc.php' method='post' accept-charset='UTF-8'>
    <table>
        <?php
        if (Config::$DB['enforce-external-sync']) {
            echo "<tr><td>
                <input type='radio' name='creation' value='existing'>" . _("Existing IdP:") . "</input>
                     </td>";

            echo "<td colspan='2'>
                <select id='externals' name='externals' onchange='document.sendinvite.creation[0].checked=true; document.sendinvite.mailaddr.value=this.options[this.selectedIndex].id;'>
                    <option value='FREETEXT'>" . _("--- select IdP here ---") . "</option>";

            foreach ($feds as $fed_value) {
                $thefed = new Federation(strtoupper($fed_value['value']));
                $temparray = array();
                $idparray = array();
                $contacts = array();
                $entities = $thefed->listUnmappedExternalEntities();
                // lets see if we have inst names in the current language
                foreach ($entities as $entity)
                    if (array_search($entity['ID'], $temparray) === FALSE && isset($entity['lang']) && $entity['lang'] == CAT::$lang_index) {
                        $idparray[$entity['ID']] = $entity['name'];
                        $contacts[$entity['ID']] = $entity['contactlist'];
                        $temparray[] = $entity['ID'];
                    }
                // now add the remaining in English language
                foreach ($entities as $entity)
                    if (array_search($entity['ID'], $temparray) === FALSE && isset($entity['lang']) && $entity['lang'] == "en") {
                        $idparray[$entity['ID']] = $entity['name'];
                        $contacts[$entity['ID']] = $entity['contactlist'];
                        $temparray[] = $entity['ID'];
                    }
                // if there are still entries remaining, pick any language we find
                foreach ($entities as $entity)
                    if (array_search($entity['ID'], $temparray) === FALSE) {
                        $idparray[$entity['ID']] = $entity['name'];
                        $contacts[$entity['ID']] = $entity['contactlist'];
                        $temparray[] = $entity['ID'];
                    }
            $current_locale = setlocale(LC_ALL,0);
            setlocale(LC_ALL,Config::$LANGUAGES[CAT::$lang_index]['locale']);
            asort($idparray,SORT_LOCALE_STRING);
            setlocale(LC_ALL,$current_locale);
            foreach ($idparray as $id => $v) {
                echo "<option id='".$contacts[$id]."' value='" . $id . "'>[" . $fed_value['value'] . "] " . $v . "</option>";
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
<?php echo _("Name"); ?><input type='text' size='40' id='name' name='name' onchange='document.sendinvite.creation[1].checked=true'/>
            </td>
            <td><?php echo _("Federation"); ?>
                <select id='country' name='country'>
                    <?php
                    foreach ($cat->printCountryList() as $iso_code => $country) {
                        foreach ($feds as $fed_value)
                            if (strtoupper($fed_value['value']) == strtoupper($iso_code))
                                echo "<option value='$iso_code'>$country</option>";
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
    <button type='submit' name='submitbutton' value='<?php echo BUTTON_SAVE;?>'><?php echo _("Send invitation"); ?></button>
</form>
<br/>
<form action='inc/manageNewInst.inc.php' method='post' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE;?>'><?php echo _("Close"); ?></button>
</form>
