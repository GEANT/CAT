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

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$auth = new \web\lib\admin\Authentication();
$loggerInstance = new \core\common\Logging();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_DELETE && isset($_GET['inst_id'])) {
    $auth->authenticate();
    $my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
    $instId = $my_inst->identifier;
    // delete the IdP and send user to enrollment
    $my_inst->destroy();
    $loggerInstance->writeAudit($_SESSION['user'], "DEL", "IdP " . $instId);
    header("Location: overview_user.php");
    exit;
}

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_FLUSH_AND_RESTART && isset($_GET['inst_id'])) {
    $auth->authenticate();
    $my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
    $instId = $my_inst->identifier;
    //
    $profiles = $my_inst->listProfiles();
    foreach ($profiles as $profile) {
        $profile->destroy();
    }
    // flush all IdP attributes and send user to creation wizard
    $my_inst->flushAttributes();
    $loggerInstance->writeAudit($_SESSION['user'], "DEL", "IdP starting over" . $instId);
    header("Location: edit_idp.php?inst_id=$instId&wizard=true");
    exit;
}


echo $deco->pageheader(sprintf(_("%s: IdP enrollment wizard (step 2 completed)"), CONFIG['APPEARANCE']['productname']), "ADMIN-IDP");
$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);

if ((!isset($_POST['submitbutton'])) || (!isset($_POST['option'])) || (!isset($_POST['value']))) {
    // this page doesn't make sense without POST values
    echo $deco->footer();
    exit(0);
}

if ($_POST['submitbutton'] != web\lib\common\FormElements::BUTTON_SAVE && $_POST['submitbutton'] != web\lib\common\FormElements::BUTTON_CONTINUE) {
    // unexpected button value
    echo $deco->footer();
    exit(0);
}

$inst_name = $my_inst->name;
echo "<h1>" . sprintf(_("Submitted attributes for IdP '%s'"), $inst_name) . "</h1>";
echo "<table>";
echo $optionParser->processSubmittedFields($my_inst, $_POST, $_FILES);
echo "</table>";

// delete cached logo, if present
$logofile = dirname(dirname(__FILE__)) . "/downloads/logos/" . $my_inst->identifier . ".png";
if (is_file($logofile)) {
    unlink($logofile);
}

$loggerInstance->writeAudit($_SESSION['user'], "MOD", "IdP " . $my_inst->identifier . " - attributes changed");

// re-instantiate ourselves... profiles need fresh data

$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);

// check if we have any SSID at all.

$ssids = [];

if (isset(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) && count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0) {
    foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'] as $ssidname) {
        $ssids[] = $ssidname . " " . (isset(CONFIG_CONFASSISTANT['CONSORTIUM']['tkipsupport']) && CONFIG_CONFASSISTANT['CONSORTIUM']['tkipsupport'] === TRUE ? _("(WPA2/AES and WPA/TKIP)") : _("(WPA2/AES)") );
    }
}

$custom_ssids_wpa2 = $my_inst->getAttributes("media:SSID");
$custom_ssids_wpa = $my_inst->getAttributes("media:SSID_with_legacy");
$wired_support = $my_inst->getAttributes("media:wired");

if (count($custom_ssids_wpa) > 0) {
    foreach ($custom_ssids_wpa as $ssidname) {
        $ssids[] = $ssidname['value'] . " " . _("(WPA2/AES and WPA/TKIP)");
    }
}

if (count($custom_ssids_wpa2) > 0) {
    foreach ($custom_ssids_wpa2 as $ssidname) {
        $ssids[] = $ssidname['value'] . " " . _("(WPA2/AES)");
    }
}

echo "<table>";
$uiElements = new web\lib\admin\UIElements();
if (count($ssids) > 0) {
    $printedlist = "";
    foreach ($ssids as $names) {
        $printedlist = $printedlist . "$names ";
    }
    echo $uiElements->boxOkay(sprintf(_("Your installers will configure the following SSIDs: <strong>%s</strong>"), $printedlist), _("SSIDs configured"));
}
if (count($wired_support) > 0) {
    echo $uiElements->boxOkay(sprintf(_("Your installers will configure wired interfaces."), $printedlist), _("Wired configured"));
}
if (count($ssids) == 0 && count($wired_support) == 0) {
    echo $uiElements->boxWarning(_("We cannot generate installers because neither wireless SSIDs nor wired interfaces have been selected as a target!"));
}
echo "</table>";

foreach ($my_inst->listProfiles() as $index => $profile) {
    $profile->prepShowtime();
}
if ($_POST['submitbutton'] == web\lib\common\FormElements::BUTTON_SAVE) {// not in initial wizard mode, just allow to go back to overview page
    echo "<br/><form method='post' action='overview_idp.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'><button type='submit'>" . _("Continue to dashboard") . "</button></form>";
} else { // does federation want us to offer Silver Bullet?
    // if so, show both buttons; if not, just the normal EAP profile button
    $myfed = new \core\Federation($my_inst->federation);
    $allow_sb = $myfed->getAttributes("fed:silverbullet");
    if (count($allow_sb) > 0) {
        echo "<br/><form method='post' action='edit_silverbullet.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'><button type='submit'>" . sprintf(_("Continue to %s properties"), \core\ProfileSilverbullet::PRODUCTNAME) . "</button></form>";
    }
    echo "<br/><form method='post' action='edit_profile.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'><button type='submit'>" . _("Continue to RADIUS/EAP profile definition") . "</button></form>";
}
echo $deco->footer();
