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
require_once("Profile.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("common.inc.php");
require_once("input_validation.inc.php");
require_once("option_html.inc.php");
require_once("option_parse.inc.php");

require_once("devices/devices.php");

authenticate();

$Cat = new CAT();
$Cat->set_locale("web_admin");

header("Content-Type:text/html;charset=utf-8");
$my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);

$my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);

$device = NULL;
$device_key = NULL;
if (isset($_POST['device'])) {
    $device_key = valid_Device($_POST['device']);
    $devices = Devices::listDevices();
    if (isset($devices[$device_key])) {
        // we now know that $device_key is valid as well
        $device = $devices[$device_key];
    } else {
        // unknown device, i.e. malformed input. Goodbye.
        exit(1);
    }
}
$eaptype = NULL;
$eap_id = 0;
if (isset($_POST['eaptype'])) {
    $eaptype = unserialize(stripslashes($_POST['eaptype']));
    // is this an actual EAP type we know of?
    $eap_id = EAP::EAPMethodIdFromArray($eaptype);
    if ($eap_id === FALSE) // oh-oh, unexpected malformed input. Goodbye.
        exit(1);
}

// there is either one or the other. If both are set, something's fishy.

if ($device != NULL && $eaptype != NULL) {
    echo _("This page needs to be called either for EAP-Types OR for devices, not both simultaneously!");
    exit(1);
}

// if none are set, something's fishy, too.

if ($device == NULL && $eaptype == NULL) {
    echo _("This page needs to be called either for EAP-Types OR for devices, but none of the two were set!");
    exit(1);
}

// if we have a pushed button, submit attributes and send user back to the compat matrix

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_SAVE) {
    if ($eaptype == NULL) {
        $remaining_attribs = $my_profile->beginflushAttributes(0, $device_key);
        $killlist = processSubmittedFields($my_profile, $remaining_attribs, 0, $device_key, TRUE);
    }
    if ($device == NULL) {
        $remaining_attribs = $my_profile->beginflushAttributes($eap_id, 0);
        $killlist = processSubmittedFields($my_profile, $remaining_attribs, $eap_id, 0, TRUE);
    }
    $my_inst->commitFlushAttributes($killlist);
    CAT::writeAudit($_SESSION['user'], "MOD", "Profile " . $my_profile->identifier . " - device/EAP-Type settings changed");
    header("Location: ../overview_installers.php?inst_id=$my_inst->identifier&profile_id=$my_profile->identifier");
}

if ($device) {
    $attribs = $my_profile->getAttributes(0, 0, $device_key);
    $captiontext = sprintf(_("device <strong>%s</strong>"), $device['display']);
    $keyword = "device-specific";
    $param_name = "Device";
    $extrainput = "<input type='hidden' name='device' value='" . $device_key . "'/>";
} else {
    $attribs = $my_profile->getAttributes(0, $eaptype, 0);
    $captiontext = sprintf(_("EAP-Type <strong>%s</strong>"), display_name($eaptype));
    $keyword = "eap-specific";
    $param_name = "EapSpecific";
    $extrainput = "<input type='hidden' name='eaptype' value='" . addslashes(serialize(EAP::EAPMethodArrayFromId($eap_id))) . "'>";
}
?>
<p><?php echo _("Fine-tuning options for ") . $captiontext; ?></p>
<hr/>

<form action='inc/toggleRedirect.inc.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;profile_id=<?php echo $my_profile->identifier; ?>' method='post' accept-charset='UTF-8'><?php echo $extrainput; ?>
    <table id='expandable_<?php echo $keyword; ?>_options'>
        <?php
// see if we already have any attributes; if so, display these
        $interesting_attribs = array();

        foreach ($attribs as $attrib) {
            if ($attrib['level'] == "Method" && preg_match('/^' . $keyword . ':/', $attrib['name']))
                $interesting_attribs[] = $attrib;
        }
        // print_r($interesting_attribs);
        add_option($keyword, $interesting_attribs);
        ?>
    </table>
    <button type='button' class='newoption' onclick='<?php echo "add" . $param_name . "Options(\"\")"; ?>'><?php echo _("Add new option"); ?></button>
    <br/>
    <hr/>
    <button type='submit' name='submitbutton' id='submitbutton' value='<?php echo BUTTON_SAVE; ?>'><?php echo _("Save data"); ?></button>
</form>
