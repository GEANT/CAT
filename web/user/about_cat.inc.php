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

/**
 * Back-end supplying information for the main_menu_content window
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 *
 * This handles the popups from the main menu. The page argument is saved in the $page variable and used
 * to select the proper handler. If the contents is read form a file which supplies its own title
 * then you need to preappend the returned data with the 'no_title' sting, this will cause
 * the receiving end to strip this marker and not add the title by itself.
 *
 */
$cat = new core\CAT();
$skinObject = new \web\lib\user\Skinjob("classic");

$out = sprintf(_("<span class='edu_cat'>%s</span> is built as a cooperation platform.<p>Local %s administrators enter their %s configuration details and based on them, <span class='edu_cat'>%s</span> builds customised installers for a number of popular platforms. An installer prepared for one %s will not work for users of another one, therefore if your %s is not on the list, you cannot use this system. Please contact your local administrators and try to influence them to add your %s configuration to <span class='edu_cat'>%s</span>."), CONFIG['APPEARANCE']['productname'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG['APPEARANCE']['productname'], $cat->nomenclature_inst, $cat->nomenclature_inst, $cat->nomenclature_inst, CONFIG['APPEARANCE']['productname']);
$out .= "<p>" . sprintf(_("<span class='edu_cat'>%s</span> currently supports the following devices and EAP type combinations:"), CONFIG['APPEARANCE']['productname']) . "</p>";
$out .= "<table><tr><th>" . _("Device Group") . "</th><th>" . _("Device") . "</th>";
foreach (\core\common\EAP::listKnownEAPTypes() as $oneeap) {
    $out .= "<th style='min-width: 80px;'>" . $oneeap->getPrintableRep() . "</th>";
}
$out .= "</tr>";
foreach (\devices\Devices::listDevices() as $index => $onedevice) {
    if (isset($onedevice['options'])) {
        if ((isset($onedevice['options']['hidden']) && ($onedevice['options']['hidden'] == 1)) || (isset($onedevice['options']['redirect']) && ($onedevice['options']['redirect'] == 1))) {
            continue;
        }
    }
    $out .= "<tr><td class='vendor'><img src='" . (new \web\lib\user\Skinjob())->findResourceUrl("IMAGES", "vendorlogo/" . $onedevice['group'] . ".png") . "' alt='logo'></td><td>" . $onedevice['display'] . "</td>";
    $device_instance = new \core\DeviceFactory($index);
    foreach (\core\common\EAP::listKnownEAPTypes() as $oneeap) {
        $out .= "<td>";
        if (in_array($oneeap->getArrayRep(), $device_instance->device->supportedEapMethods)) {
            $out .= "<img src='" . $skinObject->findResourceUrl("IMAGES", "icons/Quetto/check-icon.png") . "' alt='SUPPORTED'>";
        } else {
            $out .= "<img src='" . $skinObject->findResourceUrl("IMAGES", "icons/Quetto/no-icon.png") . "' alt='UNSUPPORTED'>";
        }
        $out .= "</td>";
    }
    $out .= "</tr>";
}
$out .= "</table>";

$validator = new \web\lib\common\InputValidation();
$out .= sprintf(_("<p><span class='edu_cat'>%s</span> is publicly accessible. To enable its use behind captive portals (e.g. on a 'setup' SSID which only allows access to CAT for device configuration), the following hostnames need to be allowed for port TCP/443 in the portal:</p>"
                . "<b><u>REQUIRED</u></b>"
                . "<ul>"
                . "<li><b>%s</b> (the service itself)</li>"), CONFIG['APPEARANCE']['productname'], $validator->hostname($_SERVER['SERVER_NAME']));
if (!empty(CONFIG['APPEARANCE']['webcert_CRLDP'])) {
    $out .= sprintf(ngettext("<li><b>%s</b> (the CRL Distribution Point for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the CRL Distribution Points for the site certificate), also TCP/80</li>", count(CONFIG['APPEARANCE']['webcert_CRLDP'])), implode(", ", CONFIG['APPEARANCE']['webcert_CRLDP']));
}
if (!empty(CONFIG['APPEARANCE']['webcert_OCSP'])) {
    $out .= sprintf(ngettext("<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", count(CONFIG['APPEARANCE']['webcert_OCSP'])), implode(", ", CONFIG['APPEARANCE']['webcert_OCSP']));
}
$out .= sprintf(_("<li><b>android.l.google.com</b> (Google Play access for Android App)</li>"
                . "<li><b>android.clients.google.com</b> (Google Play access for Android App)</li>"
                . "<li><b>play.google.com</b> (Google Play access for Android App)</li>"
                . "<li><b>ggpht.com</b> (Google Play access for Android App)</li>"
                . "</ul>"
                . "<b><u>RECOMMENDED</u></b> for full Google Play functionality (otherwise, Play Store will look broken to users and/or some non-vital functionality will not be available)"
                . "<ul>"
                . "<li><b>photos-ugc.l.google.com</b></li>"
                . "<li><b>googleusercontent.com</b></li>"
                . "<li><b>ajax.googleapis.com</b></li>"
                . "<li><b>play.google-apis.com</b></li>"
                . "<li><b>googleapis.l.google.com</b></li>"
                . "<li><b>apis.google.com</b></li>"
                . "<li><b>gstatic.com</b></li>"
                . "<li><b>www.google-analytics.com</b></li>"
                . "<li><b>wallet.google.com</b></li>"
                . "<li><b>plus.google.com</b></li>"
                . "<li><b>checkout.google.com</b></li>"
                . "</ul>"
        ));
