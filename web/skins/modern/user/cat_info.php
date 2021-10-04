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
require_once dirname(dirname(dirname((dirname(dirname(__FILE__)))))) . "/config/_config.php";

$Gui = new \web\lib\user\Gui();

$Gui->languageInstance->setTextDomain("web_user");

$page = $_REQUEST['page'];
$subpage = $_REQUEST['subpage'];
switch ($page) {
    case 'about':
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . "/user/about_cat.inc.php";
        $out = "<div class='padding'>$out</div>";
        break;
    case 'tou':
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . "/user/tou.inc.php";
        $out = "no_title<div>
           <h1>
         " . $Tou['title'] . "
    </h1>
<div id='tou_1'>" . $Tou['subtitle'] .
                $Tou['short'] . "
</div>
<div id='all_tou_link'><a href='javascript:showTOU()'>Click here to see the full terms</a></div>
<div id='tou_2' style='display:none; padding-top:20px'>" .
                $Tou['full'] . "
</div>
</div>
";
        break;
    case 'help':
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . "/user/faq.inc.php";
        switch ($subpage) {
            case 'contact':
            case 'idp_not_listed':
            case 'device_not_listed':
            case 'what_is_eduroam':
                $out = "no_title<div><h1>" . _("Help") . "</h1>";
                foreach ($Faq as $faqItem) {
                    if (!empty($faqItem['id']) && $faqItem['id'] == $subpage) {
                        $out .= "<div><h3>" . $faqItem['title'] . "</h3>\n";
                        $out .= "" . $faqItem['text'] . "</div>\n";
                    }
                }
                $out .= "</div>";
                break;
            case 'faq':
                $out = "no_title<div><h1>" . _("Frequently Asked Questions") . "</h1>";
                foreach ($Faq as $faqItem) {
                    $out .= "<div><h3>" . $faqItem['title'] . "</h3>\n";
                    $out .= "" . $faqItem['text'] . "</div>\n";
                }
                $out .= "</div>";
                break;
            default:
                break;
        }
        break;
    case 'manage':
        switch ($subpage) {
            case 'admin':
                $out = "";
                $auth = new \web\lib\admin\Authentication();
                if ($auth->isAuthenticated()) {
                    $out .= '<script type="text/javascript">goAdmin()</script>';
                } else {
                    if (\config\ConfAssistant::CONSORTIUM['selfservice_registration'] === NULL) {
                        $out .= sprintf(_("You must have received an invitation from your %s %s before being able to manage your %s. If that is the case, please continue and log in."), \config\ConfAssistant::CONSORTIUM['display_name'], core\common\Entity::$nomenclature_fed, core\common\Entity::$nomenclature_participant);
                    } else {
                        $out .= _("Please authenticate yourself and login");
                    }
                    $rn = uniqid();
                    $_SESSION['remindIdP'] = $rn;
                    $out .= "<input type='hidden' id='remindIdPs' value='$rn'>";
                    $out .= "<p><button type='button' onclick='goAdmin(); return(false);'>" . _("Login") . "</button>";
                    $out .= "<br/><br/><p>" . _("Did you forget with which Identity Provider you logged in to the system? We can try to find out if you specify the email address with which you were invited to the system in the box below. This may not work if you were invited from a third-party website via the AdminAPI.") . "</p>";
                    $out .= "<input id='remindIdP' type='text'/><button onclick='remindIdPF(); return false;'>" . _("Get IdP Reminder") . "</button>";
                    $out .= "<div id='remindIdPd'><span id='remindIdPh'></span><ul id='remindIdPl'></ul></div>";
                    $out = "<div  class='padding'>$out</div>";
                }
                break;
            case 'develop':
                include_once dirname(dirname(dirname(dirname(__FILE__)))) . "/user/devel.inc.php";
                $out = "<div class='padding'>$out</div>";
                break;
        }
        break;
    default:
        break;
}

print $out;
