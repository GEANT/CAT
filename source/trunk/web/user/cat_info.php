<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
include(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("UserAPI.php");
require_once("EAP.php");
require_once(dirname(dirname(__FILE__)) . "/admin/inc/input_validation.inc.php");
require_once(dirname(dirname(__FILE__)) . "/admin/inc/common.inc.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/devices/devices.php");

$API = new UserAPI();
$API->set_locale("web_user");

$page = $_REQUEST['page'];

switch ($page) {
    case 'consortium':
        $out = '<script type="text/javascript">document.location.href="' . Config::$CONSORTIUM['homepage'] . '"</script>';
        break;
    case 'about_consortium':
        if (Config::$CONSORTIUM['name'] == "eduroam")
            $out = sprintf(_("eduroam is a global WiFi roaming consortium which gives members of education and research access to the internet <i>for free</i> on all eduroam hotspots on the planet. There are several million eduroam users already, enjoying free internet access on more than 6.000 hotspots! Visit <a href='http://www.eduroam.org'>the eduroam homepage</a> for more details."));
        else
            $out = "";
        break;
    case 'about':
        $out = sprintf(_("<span class='edu_cat'>%s</span> is built as a cooperation platform.<p>Local %s administrators enter their %s configuration details and based on them, <span class='edu_cat'>%s</span> builds customised installers for a number of popular platforms. An installer prepared for one institution will not work for users of another one, therefore if your institution is not on the list, you cannot use this system. Please contact your local administrators and try to influence them to add your institution configuration to <span class='edu_cat'>%s</span>."), Config::$APPEARANCE['productname'], Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name'], Config::$APPEARANCE['productname'], Config::$APPEARANCE['productname']);
        $out .= "<p>" . sprintf(_("<span class='edu_cat'>%s</span> currently supports the following devices and EAP type combinations:"), Config::$APPEARANCE['productname']) . "</p>";
        $out .= "<table><tr><th>Device Group</th><th>Device</th>";
        foreach (EAP::listKnownEAPTypes() as $oneeap) {
            $out .= "<th style='min-width: 80px;'>" . display_name($oneeap) . "</th>";
        }
        $out .= "</tr>";
        foreach (Devices::listDevices() as $index => $onedevice) {
            $out .= "<tr><td><img src='resources/images/vendorlogo/" . $onedevice['group'] . ".png' alt='logo'></td><td>" . $onedevice['display'] . "</td>";
            $device_instance = new DeviceFactory($index);
            foreach (EAP::listKnownEAPTypes() as $oneeap) {
                $out .= "<td>";
                if (in_array($oneeap, $device_instance->device->supportedEapMethods))
                    $out .= "<img src='resources/images/icons/Quetto/check-icon.png' alt='SUPPORTED'>";
                else
                    $out .= "<img src='resources/images/icons/Quetto/no-icon.png' alt='UNSUPPOERTED'>";
                $out .= "</td>";
            }
            $out .= "</tr>";
        }
        $out .= "</table>";
        
        $out .= sprintf(_("<p><span class='edu_cat'>%s</span> is publicly accessible. To enable its use behind captive portals (e.g. on a 'setup' SSID which only allows access to CAT for device configuration), the following hostnames need to be allowed for port TCP/443 in the portal:</p>"
                                . "<b><u>REQUIRED</u></b>"
                                . "<ul>"
                                . "<li><b>%s</b> (the service itself)</li>"), Config::$APPEARANCE['productname'], valid_host($_SERVER['HTTP_HOST']));
        if (!empty(Config::$APPEARANCE['webcert_CRLDP']))
            $out .= sprintf(ngettext("<li><b>%s</b> (the CRL Distribution Point for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the CRL Distribution Points for the site certificate), also TCP/80</li>", count(Config::$APPEARANCE['webcert_CRLDP'])), implode(", ", Config::$APPEARANCE['webcert_CRLDP']));
        if (!empty(Config::$APPEARANCE['webcert_OCSP']))
            $out .= sprintf(ngettext("<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", "<li><b>%s</b> (the OCSP Responder for the site certificate), also TCP/80</li>", count(Config::$APPEARANCE['webcert_OCSP'])), implode(", ", Config::$APPEARANCE['webcert_OCSP']));
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
                        . "<li><b>www.google-analystics.com</b></li>"
                        . "<li><b>wallet.google.com</b></li>"
                        . "<li><b>plus.google.com</b></li>"
                        . "<li><b>checkout.google.com</b></li>"
                        . "</ul>"
        ));
        break;
    case 'tou':
        print ('no_title');
        include('tou.php');
        return;
    case 'develop':
        $out = sprintf(_("The most important need is adding new installer modules, which will configure particular devices.  CAT is making this easy for you. If you know how to create an automatic installer then fitting it into CAT should be a piece of cake. You should start by contacting us at <a href='mailto:%s'>%s</a>, but please also take a look at <a href='%s'>CAT documentation</a>."), Config::$APPEARANCE['support-contact']['developer-mail'], Config::$APPEARANCE['support-contact']['developer-mail'], 'doc/');
        break;
    case 'report':
        $out = sprintf(_("Please send a problem report to <a href='%s'>%s</a>. Some screen dumps are very welcome."), Config::$APPEARANCE['support-contact']['url'], Config::$APPEARANCE['support-contact']['display']);
        if (!empty(Config::$APPEARANCE['abuse-mail'])) {
            $out .= sprintf(_("<br/><br/>If you are a copyright holder and believe that content on this website infringes on your copyright, or find any other inappropriate content, please notify us at <a href='mailto:%s'>%s</a>."), Config::$APPEARANCE['abuse-mail'], Config::$APPEARANCE['abuse-mail']);
        }
        break;
    case 'faq':
        print ('no_title');
        include('faq.php');
        return;
    case 'admin' :
        $out = "";
        require_once(Config::$AUTHENTICATION['ssp-path-to-autoloader']);

        $as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);
        if ($as->isAuthenticated())
            $out.='<script type="text/javascript">goAdmin()</script>';
        else {
            if (Config::$CONSORTIUM['selfservice_registration'] === NULL)
                $out .= sprintf(_("You must have received an invitation from your national %s operator before being able to manage your institution. If that is the case, please continue and log in."), Config::$CONSORTIUM['name']);
            else
                $out .= _("Please authenticate yourself and login");
            $out .= "<p><button onclick='goAdmin(); return(false);'>" . _("Login") . "</button>";
        }
        break;
    default:
        break;
}
print $out;
?>


