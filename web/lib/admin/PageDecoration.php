<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\admin;

class PageDecoration {

    private $validator;
    private $ui;
    
    public function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
        $this->ui = new UIElements();
    }
    /**
     * Our (very modest and light) sidebar. authenticated admins get more options, like logout
     * @param boolean $advancedControls
     */
    private function sidebar($advancedControls) {
        $retval = "<div class='sidebar'><p>";

        if ($advancedControls) {
            $retval .= "<strong>" . _("You are:") . "</strong> "
                    . (isset($_SESSION['name']) ? $_SESSION['name'] : _("Unnamed User")) . "
              <br/>
              <br/>
              <a href='overview_user.php'>" . _("Go to your Profile page") . "</a> 
              <a href='inc/logout.php'>" . _("Logout") . "</a> ";
        }
        $startPageUrl = "../";
        if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE && strpos($_SERVER['PHP_SELF'], "diag/") === FALSE) {
            $startPageUrl = dirname($_SERVER['SCRIPT_NAME']) . "/";
        }

        $retval .= "<a href='" . $startPageUrl . "'>" . _("Start page") . "</a>
            </p>
        </div> <!-- sidebar -->";
        return $retval;
    }

    /**
     * constructs a <div> called 'header' for use on the top of the page
     * @param string $cap1 caption to display in this div
     * @param string $language current language (this one gets pre-set in the lang selector drop-down
     */
    private function headerDiv($cap1, $language) {

        $place = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $retval = "<div class='header'>
            <div id='header_toprow'>
                <div id='header_captions' style='display:inline-block; float:left; min-width:400px;'>
                    <h1>$cap1</h1>
                </div><!--header_captions-->
                <div id='langselection' style='padding-top:20px; padding-left:10px;'>
                    <form action='$place' method='GET' accept-charset='UTF-8'>" . _("View this page in") . "&nbsp;
                        <select id='lang' name='lang' onchange='this.form.submit()'>";

        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
            $retval .= "<option value='$lang' " . (strtoupper($language) == strtoupper($lang) ? "selected" : "" ) . " >" . $value['display'] . "</option> ";
        }
        $retval .= "</select>";

        foreach ($_GET as $var => $value) {
            if ($var != "lang" && $value != "") {
                $retval .= "<input type='hidden' name='" . htmlspecialchars($var) . "' value='" . htmlspecialchars($value) . "'>";
            }
        }
        $retval .= "</form>
                </div><!--langselection-->";

        $logoUrl = "//" . $this->validator->hostname($_SERVER['SERVER_NAME']) . substr($_SERVER['PHP_SELF'], 0, (strrpos($_SERVER['PHP_SELF'], "admin/") !== FALSE ? strrpos($_SERVER['PHP_SELF'], "admin/") : strrpos($_SERVER['PHP_SELF'], "/")))."/resources/images/consortium_logo.png";        
        $retval .= "<div class='consortium_logo'>
                    <img id='test_locate' src='$logoUrl' alt='Consortium Logo'>
                </div> <!-- consortium_logo -->
            </div><!--header_toprow-->
        </div> <!-- header -->";
        return $retval;
    }

    /**
     * This starts HTML in a default way. Most pages would call this.
     * Exception: if you need to add extra code in <head> or modify the <body> tag
     * (e.g. onload) then you should call defaultPagePrelude, close head, open body,
     * and then call productheader.
     * 
     * @param string $pagetitle Title of the page to display
     * @param string $area the area in which this page is (displays some matching <h1>s)
     * @param boolean $authRequired
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
     */
    public function productheader($area) {
        $langObject = new \core\common\Language();
        $language = $langObject->getLang();
        // this <div is closing in footer, keep it in PHP for Netbeans syntax
        // highlighting to work
        $retval = "<div class='maincontent'>";

        switch ($area) {
            case "ADMIN-IDP":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = sprintf(_("Administrator Interface - Identity Provider"),$this->ui->nomenclature_inst);
                $advancedControls = TRUE;
                break;
            case "ADMIN-IDP-USERS":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = sprintf(_("Administrator Interface - %s User Management"), \core\ProfileSilverbullet::PRODUCTNAME);
                $advancedControls = TRUE;
                break;
            case "ADMIN":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = _("Administrator Interface");
                $advancedControls = TRUE;
                break;
            case "USERMGMT":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = _("Management of User Details");
                $advancedControls = TRUE;
                break;
            case "FEDERATION":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = sprintf(_("Administrator Interface - %s Management"),$this->ui->nomenclature_fed);
                $advancedControls = TRUE;
                break;
            case "USER":
                $cap1 = sprintf(_("Welcome to %s"), CONFIG['APPEARANCE']['productname']);
                $cap2 = CONFIG['APPEARANCE']['productname_long'];
                $advancedControls = FALSE;
                break;
            case "SUPERADMIN":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = _("CIC");
                $advancedControls = TRUE;
                break;
            case "DIAG":
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = _("Diagnostics");
                $advancedControls = TRUE;
                break;
            default:
                $cap1 = CONFIG['APPEARANCE']['productname_long'];
                $cap2 = "It is an error if you ever see this string.";
                $advancedControls = FALSE;
        }


        $retval .= $this->headerDiv($cap1, $language);
        // content from here on will SCROLL instead of being fixed at the top
        $retval .= "<div class='pagecontent'>"; // closes in footer again
        $retval .= "<div class='trick'>"; // closes in footer again
        $retval .= "<div id='secondrow' style='border-bottom:5px solid ".CONFIG['APPEARANCE']['colour1']."; min-height:100px;'>
            <div id='secondarycaptions' style='display:inline-block; float:left'>
                <h2>$cap2</h2>
            </div><!--secondarycaptions-->";

        if (isset(CONFIG['APPEARANCE']['MOTD']) && CONFIG['APPEARANCE']['MOTD'] != "") {
            $retval .= "<div id='header_MOTD' style='display:inline-block; padding-left:20px;vertical-align:top;'>
              <p class='MOTD'>" . CONFIG['APPEARANCE']['MOTD'] . "</p>
              </div><!--header_MOTD-->";
        }
        $retval .= $this->sidebar($advancedControls);
        $retval .= "</div><!--secondrow-->";
        return $retval;
    }

    /**
     * 
     * @param string $pagetitle Title of the page to display
     * @param boolean $authRequired does the user need to be autenticated to access this page?
     */
    public function defaultPagePrelude($pagetitle, $authRequired = TRUE) {
        if ($authRequired === TRUE) {
            $auth = new \web\lib\admin\Authentication();
            $auth->authenticate();
        }
        $langObject = new \core\common\Language();
        $langObject->setTextDomain("web_admin");
        $ourlocale = $langObject->getLang();
        header("Content-Type:text/html;charset=utf-8");
        $retval = "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='$ourlocale'>
          <head lang='$ourlocale'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";

        if (strrpos($_SERVER['PHP_SELF'], "admin/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "admin/");
        } elseif (strrpos($_SERVER['PHP_SELF'], "accountstatus/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "accountstatus/");
        } elseif (strrpos($_SERVER['PHP_SELF'], "diag/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "diag/");
        }
            else {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "/");
        }

        $cssUrl = "//" . $this->validator->hostname($_SERVER['SERVER_NAME']) . substr($_SERVER['PHP_SELF'], 0, $cutoffPosition )."/resources/css/cat.css.php";
        
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
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") {// SW: APPROVED
        // we may need to jump up one dir if we are either in admin/ or accountstatus/
        // (accountstatus courtesy of my good mood. It's userspace not admin space so
        // it shouldn't be using this function any more.)
        
        if (strrpos($_SERVER['PHP_SELF'], "admin/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "admin/");
        } elseif (strrpos($_SERVER['PHP_SELF'], "accountstatus/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "accountstatus/");
        } elseif (strrpos($_SERVER['PHP_SELF'], "diag/")) {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "diag/");
        } else {
            $cutoffPosition = strrpos($_SERVER['PHP_SELF'], "/");
        }
        
        $logoBase = "//" . $this->validator->hostname($_SERVER['SERVER_NAME']) . substr($_SERVER['PHP_SELF'], 0, $cutoffPosition)."/resources/images";

        return "<span id='logos' style='position:fixed; left:50%;'><img src='$logoBase/dante.png' alt='DANTE' style='height:23px;width:47px'/>
              <img src='$logoBase/eu.png' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
              <span id='eu_text' style='text-align:right;'><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top;'>European Commission Communications Networks, Content and Technology</a></span>";
        }
        return "&nbsp";
    }

    /**
     * displays the admin area footer
     */
    public function footer() {
        $cat = new \core\CAT();
        $retval = "</div><!-- trick -->
          </div><!-- pagecontent -->
        <div class='footer'>
            <hr />
            <table style='width:100%'>
                <tr>
                    <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>
                        " . $cat->CAT_COPYRIGHT . "</td>
                    <td style='padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;'>";

        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
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
        return $retval;
    }

}
