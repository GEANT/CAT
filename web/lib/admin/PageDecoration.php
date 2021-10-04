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

namespace web\lib\admin;

class PageDecoration extends \core\common\Entity {

    /**
     * an instance of the InputValidation class
     * 
     * @var \web\lib\common\InputValidation
     */
    private $validator;
    
    /**
     * an instance of the UIElements class
     * 
     * @var \web\lib\admin\UIElements
     */
    private $ui;
    
    /**
     * an instance of the Language class
     * 
     * @var \core\common\Language
     */
    private $langObject;
    
    /**
     * construct the PageDecoration object
     */
    public function __construct() {
        parent::__construct();
        $this->langObject = new \core\common\Language();
        $this->langObject->setTextDomain("web_admin");
        $this->validator = new \web\lib\common\InputValidation();
        $this->ui = new UIElements();
    }

    /**
     * Our (very modest and light) sidebar. authenticated admins get more options, like logout
     * 
     * @param boolean $advancedControls display the admin-side advanced controls?
     * @return string
     */
    private function sidebar($advancedControls) {
        \core\common\Entity::intoThePotatoes();
        $retval = "<div class='sidebar'><p>";

        if ($advancedControls) {
            $retval .= "<strong>" . _("You are:") . "</strong> ".$_SESSION['name']
            ."<br/>
              <br/>
              <a href='" . \core\CAT::getRootUrlPath() . "/admin/overview_user.php'>" . _("Go to your Profile page") . "</a> 
              <a href='" . \core\CAT::getRootUrlPath() . "/admin/inc/logout.php'>" . _("Logout") . "</a> ";
        }
        $retval .= "<a href='" . \core\CAT::getRootUrlPath() . "/'>" . _("Start page") . "</a>
            </p>
        </div> <!-- sidebar -->";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * constructs a <div> called 'header' for use on the top of the page
     * 
     * @param string $cap1     caption to display in this div
     * @param string $language current language (this one gets pre-set in the lang selector drop-down
     * @return string
     */
    private function headerDiv($cap1, $language) {
        \core\common\Entity::intoThePotatoes();
        $place = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $retval = "<div class='header'>
            <div id='header_toprow'>
                <div id='header_captions' style='display:inline-block; float:left; min-width:400px;'>
                    <h1>$cap1</h1>
                </div><!--header_captions-->
                <div id='langselection' style='padding-top:20px; padding-left:10px;'>
                    <form action='$place' method='GET' accept-charset='UTF-8'>" . _("View this page in") . "&nbsp;
                        <select id='lang' name='lang' onchange='this.form.submit()'>";

        foreach (\config\Master::LANGUAGES as $lang => $getValue) {
            $retval .= "<option value='$lang' " . (strtoupper($language) == strtoupper($lang) ? "selected" : "" ) . " >" . $getValue['display'] . "</option> ";
        }
        $retval .= "</select>";

        foreach ($_GET as $getVar => $getValue) {
            $filterValue = filter_input(INPUT_GET, $getVar, FILTER_SANITIZE_STRING);
            if ($getVar != "lang" && $getValue != "") {
                $retval .= "<input type='hidden' name='" . htmlspecialchars($getVar) . "' value='" . htmlspecialchars($filterValue) . "'>";
            }
        }
        $retval .= "</form>
                </div><!--langselection-->";
        $logoUrl = \core\CAT::getRootUrlPath() . "/resources/images/consortium_logo.png";
        $retval .= "<div class='consortium_logo'>
                    <img id='test_locate' src='$logoUrl' alt='Consortium Logo'>
                </div> <!-- consortium_logo -->
            </div><!--header_toprow-->
        </div> <!-- header -->";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * This starts HTML in a default way. Most pages would call this.
     * Exception: if you need to add extra code in <head> or modify the <body> tag
     * (e.g. onload) then you should call defaultPagePrelude, close head, open body,
     * and then call productheader.
     * 
     * @param string  $pagetitle    Title of the page to display
     * @param string  $area         the area in which this page is (displays some matching <h1>s)
     * @param boolean $authRequired is authentication required on this page?
     * @return string
     */
    public function pageheader($pagetitle, $area, $authRequired = TRUE) {
        $retval = "";
        $retval .= $this->defaultPagePrelude($pagetitle, $authRequired);
        $retval .= "</head></body>";
        $retval .= $this->productheader($area);
        return $retval;
    }

    /**
     * the entire top of the page (<body> part)
     * 
     * @param string $area the area we are in
     * @return string
     */
    public function productheader($area) {
        \core\common\Entity::intoThePotatoes();
        $language = $this->languageInstance->getLang();
        // this <div is closing in footer, keep it in PHP for Netbeans syntax
        // highlighting to work
        $retval = "<div class='maincontent'>";
        // default values which match almost every case; override where needed
        $cap1 = \config\Master::APPEARANCE['productname_long'];
        $advancedControls = TRUE;
        switch ($area) {
            case "ADMIN-PARTICIPANT":
                $cap2 = sprintf(_("Administrator Interface - %s"), $this->ui->nomenclatureParticipant);
                break;
            case "ADMIN-IDP":
                $cap2 = sprintf(_("Administrator Interface - %s"), $this->ui->nomenclatureIdP);
                break;
            case "ADMIN-SP":
                $cap2 = sprintf(_("Administrator Interface - %s"), $this->ui->nomenclatureHotspot);
                break;
            case "ADMIN-IDP-USERS":
                $cap2 = sprintf(_("Administrator Interface - %s User Management"), \core\ProfileSilverbullet::PRODUCTNAME);
                break;
            case "ADMIN":
                $cap2 = _("Administrator Interface");
                break;
            case "USERMGMT":
                $cap2 = _("Management of User Details");
                break;
            case "FEDERATION":
                $cap2 = sprintf(_("Administrator Interface - %s Management"), $this->ui->nomenclatureFed);
                break;
            case "USER":
                $cap1 = sprintf(_("Welcome to %s"), \config\Master::APPEARANCE['productname']);
                $cap2 = \config\Master::APPEARANCE['productname_long'];
                $advancedControls = FALSE;
                break;
            case "SUPERADMIN":
                $cap2 = _("CIC");
                break;
            case "DIAG":
                $cap2 = _("Diagnostics");
                break;
            default:
                $cap2 = "It is an error if you ever see this string.";
                $advancedControls = FALSE;
        }

        $retval .= $this->headerDiv($cap1, $language);
        // content from here on will SCROLL instead of being fixed at the top
        $retval .= "<div class='pagecontent'>"; // closes in footer again
        $retval .= "<div class='trick'>"; // closes in footer again
        $retval .= "<div id='secondrow'>
            <div id='secondarycaptions' style='display:inline-block; float:left'>
                <h2 style='padding-left:10px;'>$cap2</h2>
            </div><!--secondarycaptions-->";

        if (isset(\config\Master::APPEARANCE['MOTD']) && \config\Master::APPEARANCE['MOTD'] != "") {
            $retval .= "<div id='header_MOTD' style='display:inline-block; padding-left:20px;vertical-align:top;'>
              <p class='MOTD'>" . \config\Master::APPEARANCE['MOTD'] . "</p>
              </div><!--header_MOTD-->";
        }
        $retval .= $this->sidebar($advancedControls);
        $retval .= "</div><!--secondrow--><div id='thirdrow'>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * 
     * @param string  $pagetitle    Title of the page to display
     * @param boolean $authRequired does the user need to be autenticated to access this page?
     * @return string
     */
    public function defaultPagePrelude($pagetitle, $authRequired = TRUE) {
        if ($authRequired === TRUE) {
            $auth = new \web\lib\admin\Authentication();
            $auth->authenticate();
        }
        $ourlocale = $this->langObject->getLang();
        header("Content-Type:text/html;charset=utf-8");
        $retval = "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='$ourlocale'>
          <head lang='$ourlocale'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
        $cssUrl = \core\CAT::getRootUrlPath() . "/resources/css/cat.css.php";
        $retval .= "<link rel='stylesheet' type='text/css' href='$cssUrl' />";
        $retval .= "<title>" . htmlspecialchars($pagetitle) . "</title>";
        return $retval;
    }

    /**
     * HTML code for the EU attribution
     * 
     * @return string HTML code with GEANT Org and EU attribution as required for FP7 / H2020 projects
     */
    public function attributionEurope() {
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") {// SW: APPROVED
            // we may need to jump up one dir if we are either in admin/ or accountstatus/
            // (accountstatus courtesy of my good mood. It's userspace not admin space so
            // it shouldn't be using this function any more.)
            $logoBase = \core\CAT::getRootUrlPath() . "/resources/images";
            return "<span id='logos' style='position:fixed; left:50%;'><img src='$logoBase/dante.png' alt='DANTE' style='height:23px;width:47px'/>
              <img src='$logoBase/eu.png' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
              <span id='eu_text' style='text-align:right;'><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top;'>European Commission Communications Networks, Content and Technology</a></span>";
        }
        return "&nbsp";
    }

    /**
     * displays the admin area footer
     * 
     * @return string
     */
    public function footer() {
        $cat = new \core\CAT();
        \core\common\Entity::intoThePotatoes();
        $retval = "</div><!-- thirdrow --></div><!-- trick -->
          </div><!-- pagecontent -->
        <div class='footer'>
            <table style='width:100%'>
                <tr>
                    <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>
                        " . $cat->catCopyright . "</td>";
        if (!empty(\config\Master::APPEARANCE['privacy_notice_url'])) {
            $retval .= "<td><a href='".\config\Master::APPEARANCE['privacy_notice_url']."'>" . sprintf(_("%s Privacy Notice"),\config\ConfAssistant::CONSORTIUM['display_name']) . "</a></td>";
        }
        $retval .= "            <td style='padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;'>";

        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $retval .= $this->attributionEurope();
        } else {
            $retval .= "&nbsp;";
        }
        $retval .= "
                    </td>
                </tr>
            </table>
        </div><!-- footer -->
        </div><!-- maincontent -->
        </body>
        </html>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

}
