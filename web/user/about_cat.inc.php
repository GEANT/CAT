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
/// eduroam CAT, twice the consortium name eduroam, twice eduroam CAT
$out = sprintf(_("<span class='edu_cat'>%s</span> is built as a cooperation platform."), \config\Master::APPEARANCE['productname'])."<p>".
       sprintf(_("Local %s administrators enter their %s configuration details and based on them, <span class='edu_cat'>%s</span> builds customised installers for a number of popular platforms. ".
                 "An installer prepared for one organisation will not work for users of another one, therefore if your organisation is not on the list, you cannot use this system. ".
                 "Please contact your local administrators and try to influence them to add your %s configuration to <span class='edu_cat'>%s</span>."), 
        \config\Master::APPEARANCE['productname'], 
        \config\ConfAssistant::CONSORTIUM['display_name'], 
        \config\ConfAssistant::CONSORTIUM['display_name'], 
        \config\Master::APPEARANCE['productname'], 
        \config\Master::APPEARANCE['productname']);
$out .= "<p>" . sprintf(_("<span class='edu_cat'>%s</span> currently supports the following devices and EAP type combinations:"), \config\Master::APPEARANCE['productname']) . "</p>";
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
    $vendor = (new \web\lib\user\Skinjob())->findResourceUrl("IMAGES", "vendorlogo/" . $onedevice['group'] . ".png");
    $vendorImg = "";
    if ($vendor !== FALSE) {
        $vendorImg = "<img src='$vendor' alt='logo'>";
    }
    $out .= "<tr><td class='vendor'>$vendorImg</td><td>" . $onedevice['display'] . "</td>";
    $device_instance = new \core\DeviceFactory($index);
    foreach (\core\common\EAP::listKnownEAPTypes() as $oneeap) {
        $out .= "<td>";
        if (in_array($oneeap->getArrayRep(), $device_instance->device->supportedEapMethods)) {
            $check = $skinObject->findResourceUrl("IMAGES", "icons/Quetto/check-icon.png");
            if ($check !== FALSE) {
                $out .= "<img src='$check' alt='SUPPORTED'>";
            }
        } else {
            $not = $skinObject->findResourceUrl("IMAGES", "icons/Quetto/no-icon.png");
            if ($not !== FALSE) {
                $out .= "<img src='$not' alt='UNSUPPORTED'>";
            }
        }
        $out .= "</td>";
    }
    $out .= "</tr>";
}
$out .= "</table>";

$validator = new \web\lib\common\InputValidation();
$host = $validator->hostname($_SERVER['SERVER_NAME']);
if ($host === FALSE) {
    throw new Exception("We don't know our own hostname!");
}
$out .= sprintf(_("<p><span class='edu_cat'>%s</span> is publicly accessible. To enable its use behind captive portals (e.g. on a 'setup' SSID which only allows access to CAT for device configuration), the following hostnames need to be allowed for port TCP/443 in the portal:</p>"
                . "<b><u>REQUIRED</u></b>"
                . "<ul>"
                . "<li><b>%s</b> (the service itself)</li>"), \config\Master::APPEARANCE['productname'], $host);
if (!empty(\config\Master::APPEARANCE['webcert_CRLDP'])) {
    $out .= sprintf(ngettext("<li><b>%s</b> (the CRL Distribution Point for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the CRL Distribution Points for the site certificate), also TCP/80</li>", count(\config\Master::APPEARANCE['webcert_CRLDP'])), implode(", ", \config\Master::APPEARANCE['webcert_CRLDP']));
}
if (!empty(\config\Master::APPEARANCE['webcert_OCSP'])) {
    $out .= sprintf(ngettext("<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", count(\config\Master::APPEARANCE['webcert_OCSP'])), implode(", ", \config\Master::APPEARANCE['webcert_OCSP']));
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
                . "<li><b>*.gvt1.com</li>"
                . "</ul>"
        ));
