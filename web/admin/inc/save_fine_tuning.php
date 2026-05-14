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
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();
$loggerInstance = new \core\common\Logging();

$auth->authenticate();
[$my_inst, $editMode] = $validator->existingIdPInt($_POST['inst_id'], $_SESSION['user']);
$my_profile = $validator->existingProfile($_POST['profile_id'], $my_inst->identifier);

if ($editMode === 'readonly') {
    exit;
}

$optionType = $_POST['optiontype'];

if ($optionType !== 'device-specific' && $optionType !== 'eap-specific') {
    throw new Exception("optiontype does not have the expected value!");
}

if ($optionType === 'device-specific') {
    $posted_device = $_POST['optionvalue'];
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

if ($optionType === 'eap-specific') {
    $posted_eaptype = $_POST['optionvalue'];
    if (!is_numeric($posted_eaptype)) {
        throw new Exception("POSTed EAP type value is not an integer!");
    }
    // conversion routine throws an exception if the EAP type id is not known
    $eaptype = new \core\common\EAP((int) $posted_eaptype);    
}

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_SAVE) {
    if ($optionType === 'device-specific') {
        $remaining_attribs = $my_profile->beginFlushMethodLevelAttributes(0, $device_key);
        $optionParser->processSubmittedFields($my_profile, $_POST, $_FILES, 0, $device_key);
    }
    if ($optionType === 'eap-specific') {
        $remaining_attribs = $my_profile->beginFlushMethodLevelAttributes($eaptype->getIntegerRep(), NULL);
        $optionParser->processSubmittedFields($my_profile, $_POST, $_FILES, $eaptype->getIntegerRep(), NULL);
    }
    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $my_profile->identifier . " - device/EAP-Type settings changed");
    header("Location: ../overview_installers.php?inst_id=$my_inst->identifier&profile_id=$my_profile->identifier");
    exit;
}
