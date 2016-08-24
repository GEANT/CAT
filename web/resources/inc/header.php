<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/admin/inc/input_validation.inc.php");

/**
 * This starts HTML in a default way. Most pages would call this.
 * Exception: if you need to add extra code in <head> or modify the <body> tag
 * (e.g. onload) then you should call defaultPagePrelude, close head, open body,
 * and then call productheader.
 * 
 * @param type $pagetitle
 * @param type $area
 * @param type $auth_required
 */
function pageheader($pagetitle, $area, $auth_required = TRUE) {
    $cat = defaultPagePrelude($pagetitle, $auth_required);
    echo "</head></body>";
    productheader($area, CAT::get_lang());
    return $cat;
}

function defaultPagePrelude($pagetitle, $auth_required = TRUE) {
    if ($auth_required == TRUE) {
        require_once(dirname(dirname(dirname(__FILE__))) . "/admin/inc/auth.inc.php");
        authenticate();
    }
    $Cat = new CAT();
    $Cat->set_locale("web_admin");
    $ourlocale = CAT::get_lang();
    header("Content-Type:text/html;charset=utf-8");
    echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='$ourlocale'>
          <head lang='$ourlocale'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
    $css_url = valid_host($_SERVER['HTTP_HOST']);
    if ($css_url === FALSE)
        exit(1);
    // we need to construct the right path to the consortium logo; we are either
    // in the admin area or on the main index.php ...
    if (strpos($_SERVER['PHP_SELF'], "admin/") !== FALSE) {
        $css_url .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . "/resources/css/cat.css.php";
    } else if (strpos($_SERVER['PHP_SELF'], "diag/") !== FALSE) {
        $css_url .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/diag/")) . "/resources/css/cat.css.php";
    } else {
        $css_url .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/resources/css/cat.css.php";
    }
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
        $css_url = "https://" . $css_url;
    else
        $css_url = "http://" . $css_url;
    echo "<link rel='stylesheet' type='text/css' href='$css_url' />";
    echo "<title>".htmlspecialchars($pagetitle)."</title>";

    return $Cat;
}

function productheader($area, $language) {
    echo "<div class='maincontent'>";
// echo "You are here: ".$_SERVER['REQUEST_URI'];
    $place = parse_url($_SERVER['REQUEST_URI']);

// this code does not do anything?!
// 
//    if (isset($place['query'])) {
//        preg_match('/(.*)&lang=.*/', '&' . $place['query'], $result);
//
//        if (array_key_exists(1, $result))
//            $short = substr($result[1], 1);
//        else
//            $short = $place['query'];
//    } else {
//        $short = "";
//    }

    echo "<div class='header'>";
    $cap1 = "";
    $cap2 = "";
    $advanced_controls = FALSE;
    switch ($area) {
        case "ADMIN-IDP":
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = _("Administrator Interface - Identity Provider");
            $advanced_controls = TRUE;
            break;
        case "ADMIN":
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = _("Administrator Interface");
            $advanced_controls = TRUE;
            break;
        case "USERMGMT":
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = _("Management of User Details");
            $advanced_controls = TRUE;
            break;
        case "FEDERATION":
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = _("Administrator Interface - Federation Management");
            $advanced_controls = TRUE;
            break;
        case "USER":
            $cap1 = sprintf(_("Welcome to %s"), Config::$APPEARANCE['productname']);
            $cap2 = Config::$APPEARANCE['productname_long'];
            // '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("Welcome to %s"), Config::$APPEARANCE['productname']) . '</h1>
            //  <h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . Config::$APPEARANCE['productname_long'] . '</h2>';
            break;
        case "SUPERADMIN":
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = _("CIC");
            $advanced_controls = TRUE;
            break;
        default:
            $cap1 = Config::$APPEARANCE['productname_long'];
            $cap2 = "It is an error if you ever see this string.";
    }
    echo "<div id='header_toprow'>";
    echo "<div id='header_captions' style='display:inline-block; float:left; min-width:400px;'>";
    echo "<h1>$cap1</h1>";
    echo "</div><!--header_captions-->";
    echo "<div id='langselection' style='padding-top:20px; padding-left:10px;'>";
    echo "<form action='" . $place['path'] . "' method='GET' accept-charset='UTF-8'>" . _("View this page in") . " ";
    echo "<select id='lang' name='lang' onchange='this.form.submit()'>";
    foreach (Config::$LANGUAGES as $lang => $value) {
        echo "<option value='$lang' ".(strtoupper($language) == strtoupper($lang) ? "selected" : "" )." >" . $value['display'] . "</option> ";
    }
    echo "</select>";
    foreach ($_GET as $var => $value) {
        if ($var != "lang" && $value != "")
            echo "<input type='hidden' name='".htmlspecialchars($var)."' value='".htmlspecialchars($value)."'>";
    }
    echo "</form>";
    echo "</div><!--langselection-->";
    $logo_url = valid_host($_SERVER['HTTP_HOST']);
    if ($logo_url === FALSE)
        exit(1);
    // we need to construct the right path to the consortium logo; we are either
    // in the admin area or on the main index.php ...
    if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE)
        $logo_url .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/resources/images/consortium_logo.png";
    else
        $logo_url .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . "/resources/images/consortium_logo.png";
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
        $logo_url = "https://" . $logo_url;
    else
        $logo_url = "http://" . $logo_url;
    echo "<div class='consortium_logo'>
    <img id='test_locate' src='$logo_url' alt='Consortium Logo'>
</div> <!-- consortium_logo -->";

    echo "</div><!--header_toprow-->";
    echo "</div> <!-- header -->";
    // content from here on will SCROLL instead of being fixed at the top
    echo "<div class='pagecontent'>
          <div class='trick'>";
    echo "<div id='secondrow' style='border-bottom:5px solid ". Config::$APPEARANCE['colour1']."; min-height:100px;'>";
    echo "<div id='secondarycaptions' style='display:inline-block; float:left'>";
    echo "<h2>$cap2</h2>";
    echo "</div><!--secondarycaptions-->";

    if (isset(Config::$APPEARANCE['MOTD']) && Config::$APPEARANCE['MOTD'] != "") 
        echo "<div id='header_MOTD' style='display:inline-block; padding-left:20px;vertical-align:top;'>
              <p class='MOTD'>" . Config::$APPEARANCE['MOTD'] . "</p>
              </div><!--header_MOTD-->";

    // echo $_SERVER['PHP_SELF'];
    echo "<div class='sidebar'><p>";
    if ($advanced_controls) {
        echo "<strong>" . _("You are:") . "</strong> "
        . (isset($_SESSION['name']) ? $_SESSION['name'] : _("Unnamed User")) . "
              <br/>
              <br/>
              <a href='overview_user.php'>" . _("Go to your Profile page") . "</a> 
              <a href='inc/logout.php'>" . _("Logout") . "</a> ";
    }
    if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE)
        echo "<a href='" . dirname($_SERVER['SCRIPT_NAME']) . "/'>" . _("Start page") . "</a>";
    else
        echo "<a href='../'>" . _("Start page") . "</a>";
    echo "</p>
</div> <!-- sidebar --></div><!--secondrow-->";
}