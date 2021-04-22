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

require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$loggerInstance = new \core\common\Logging();
$optionParser = new \web\lib\admin\OptionParser();
$validator = new \web\lib\common\InputValidation();
$languageInstance = new \core\common\Language();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();
$languageInstance->setTextDomain("web_admin");

header("Content-Type:text/html;charset=utf-8");
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);

$my_profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);

if (!$my_profile instanceof \core\ProfileRADIUS) {
    throw new Exception("Redirect options can only be set for RADIUS profiles!");
}

$device = NULL;
$device_key = NULL;
$posted_device = $_POST['device'] ?? FALSE;
if ($posted_device) {
    $device_key = $validator->existingDevice($posted_device);
    $devices = \devices\Devices::listDevices();
    if (isset($devices[$device_key])) {
        // we now know that $device_key is valid as well
        $device = $devices[$device_key];
    } else {
        // unknown device, i.e. malformed input. Goodbye.
        throw new Exception("Tried to change device-level attributes, but the device is not known!");
    }
}
$eaptype = NULL;
$posted_eaptype = $_POST['eaptype'] ?? FALSE;
if ($posted_eaptype) {
    if (!is_numeric($posted_eaptype)) {
        throw new Exception("POSTed EAP type value is not an integer!");
    }
    // conversion routine throws an exception if the EAP type id is not known
    $eaptype = new \core\common\EAP((int) $posted_eaptype);
}

// there is either one or the other. If both are set, something's fishy.
if ($device != NULL && $eaptype !== NULL) {
    throw new Exception("This page needs to be called either for EAP-Types OR for devices, not both simultaneously!");
}

// if none are set, something's fishy, too.
if ($device == NULL && $eaptype === NULL) {
    throw new Exception("This page needs to be called either for EAP-Types OR for devices, but none of the two were set!");
}

// if we have a pushed button, submit attributes and send user back to the compat matrix

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_SAVE) {
    if ($eaptype === NULL) {
        $remaining_attribs = $my_profile->beginFlushMethodLevelAttributes(0, $device_key);
        $optionParser->processSubmittedFields($my_profile, $_POST, $_FILES, 0, $device_key);
    }
    if ($device === NULL) {
        $remaining_attribs = $my_profile->beginFlushMethodLevelAttributes($eaptype->getIntegerRep(), NULL);
        $optionParser->processSubmittedFields($my_profile, $_POST, $_FILES, $eaptype->getIntegerRep(), NULL);
    }
    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $my_profile->identifier . " - device/EAP-Type settings changed");
    header("Location: ../overview_installers.php?inst_id=$my_inst->identifier&profile_id=$my_profile->identifier");
    exit;
}

$attribs = [];
if ($device !== NULL) {
    foreach ($my_profile->getAttributes() as $attrib) {
        if (isset($attrib['device']) && $attrib['device'] == $device_key) {
            $attribs[] = $attrib;
        }
    }
    $captiontext = sprintf(_("device <strong>%s</strong>"), $device['display']);
    $keyword = "device-specific";
    $extrainput = "<input type='hidden' name='device' value='" . $device_key . "'/>";
} elseif ($eaptype !== NULL) {
    foreach ($my_profile->getAttributes() as $attrib) {
        if (isset($attrib['eapmethod']) && $attrib['eapmethod'] == $eaptype->getArrayRep()) {
            $attribs[] = $attrib;
        }
    }

    $captiontext = sprintf(_("EAP-Type <strong>%s</strong>"), $eaptype->getPrintableRep());
    $keyword = "eap-specific";
    $extrainput = "<input type='hidden' name='eaptype' value='" . $eaptype->getIntegerRep() . "'>";
} else {
    throw new Exception("previous type checks make it impossible to reach this code path.");
}
?>
<p><?php echo _("Fine-tuning options for ") . $captiontext; ?></p>
<hr/>

<form action='inc/toggleRedirect.inc.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;profile_id=<?php echo $my_profile->identifier; ?>' method='post' accept-charset='UTF-8'><?php echo $extrainput; ?>
    <?php
// see if we already have any attributes; if so, display these
    $interesting_attribs = [];

    foreach ($attribs as $attrib) {
        if ($attrib['level'] == \core\Options::LEVEL_METHOD && preg_match('/^' . $keyword . ':/', $attrib['name'])) {
            $interesting_attribs[] = $attrib;
        }
    }
    $optionDisplay = new \web\lib\admin\OptionDisplay($interesting_attribs, \core\Options::LEVEL_METHOD);
    echo $optionDisplay->prefilledOptionTable($keyword, $my_inst->federation);
    if (\config\Master::DB['INST']['readonly'] === FALSE) {
        ?>
        <button type='button' class='newoption' onclick='getXML("<?php echo $keyword;?>", "<?php echo $my_inst->federation;?>")'><?php echo _("Add new option"); ?></button>
        <br/>
        <hr/>
        <button type='submit' name='submitbutton' id='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Save data"); ?></button>
        <?php
    }
    ?>
</form>
