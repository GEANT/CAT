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
require_once(dirname(dirname(dirname((dirname(dirname(__FILE__)))))) . "/config/_config.php");

$Gui = new \web\lib\user\Gui();

$Gui->langObject->setTextDomain("web_user");

$page = $_REQUEST['page'];
$subpage= $_REQUEST['subpage'];
switch ($page) {
    case 'about' :
       require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/user/about_cat.inc.php");
       $out = "<div>$out</div>";
       break;
    case 'tou':
        require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/user/tou.inc.php");
        $out = "no_title<div>
           <h1>
         " . $Tou['title'] . "
    </h1>
<div id='tou_1'> " .
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
        require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/user/faq.inc.php");
        switch ($subpage) {
            case 'contact' :
            case 'idp_not_listed' :
            case 'device_not_listed' :
            case 'what_is_eduroam' :
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
    case 'manage' :
        switch ($subpage) {
            case 'admin' :
                $out = "";
                require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);
        
                $as = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
                if ($as->isAuthenticated()) {
                    $out .= '<script type="text/javascript">goAdmin()</script>';
                } else {
                    if (CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'] === NULL) {
                        $out .= sprintf(_("You must have received an invitation from your %s %s before being able to manage your %s. If that is the case, please continue and log in."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $Gui->nomenclature_fed, $Gui->nomenclature_inst);
                    } else {
                        $out .= _("Please authenticate yourself and login");
                    }
                    $out .= "<p><button onclick='goAdmin(); return(false);'>" . _("Login") . "</button>";
               }
               break;
       }
       break;
    default:
        break;
}
print $out;
