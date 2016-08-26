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
 * @param type $authRequired
 */
function pageheader($pagetitle, $area, $authRequired = TRUE) {
    $cat = defaultPagePrelude($pagetitle, $authRequired);
    echo "</head></body>";
    productheader($area, CAT::get_lang());
    return $cat;
}

function defaultPagePrelude($pagetitle, $auth_required = TRUE) {
    if ($auth_required == TRUE) {
        require_once(dirname(dirname(dirname(__FILE__))) . "/admin/inc/auth.inc.php");
        authenticate();
    }
    $cat = new CAT();
    $cat->set_locale("web_admin");
    $ourlocale = CAT::get_lang();
    header("Content-Type:text/html;charset=utf-8");
    echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='<?php echo $ourlocale;?>'>
          <head lang='<?php echo $ourlocale;?>'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";

    $cssUrl = valid_host($_SERVER['HTTP_HOST']);
    if ($cssUrl === FALSE)
        exit(1);
    // we need to construct the right path to the consortium logo; we are either
    // in the admin area or on the main index.php ...
    if (strpos($_SERVER['PHP_SELF'], "admin/") !== FALSE) {
        $cssUrl .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . "/resources/css/cat.css.php";
    } else if (strpos($_SERVER['PHP_SELF'], "diag/") !== FALSE) {
        $cssUrl .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/diag/")) . "/resources/css/cat.css.php";
    } else {
        $cssUrl .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/resources/css/cat.css.php";
    }

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
        $cssUrl = "https://" . $cssUrl;
    else
        $cssUrl = "http://" . $cssUrl;
    echo "<link rel='stylesheet' type='text/css' href='$cssUrl' />";
    echo "<title>" . htmlspecialchars($pagetitle) . "</title>";

    return $cat;
}

function headerDiv($area) {
    ?>
    <div class='header'>
        <?php
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
                $advanced_controls = FALSE;
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
        ?>
        <div id='header_toprow'>
            <div id='header_captions' style='display:inline-block; float:left; min-width:400px;'>
                <h1><?php echo $cap1; ?></h1>
            </div><!--header_captions-->
            <div id='langselection' style='padding-top:20px; padding-left:10px;'>
                <form action='<?php echo $place['path']; ?>' method='GET' accept-charset='UTF-8'><?php echo _("View this page in"); ?>&nbsp;
                    <select id='lang' name='lang' onchange='this.form.submit()'>
                        <?php
                        foreach (Config::$LANGUAGES as $lang => $value) {
                            echo "<option value='$lang' " . (strtoupper($language) == strtoupper($lang) ? "selected" : "" ) . " >" . $value['display'] . "</option> ";
                        }
                        ?>
                    </select>
                    <?php
                    foreach ($_GET as $var => $value) {
                        if ($var != "lang" && $value != "")
                            echo "<input type='hidden' name='" . htmlspecialchars($var) . "' value='" . htmlspecialchars($value) . "'>";
                    }
                    ?>
                </form>
            </div><!--langselection-->
            <?php
            $logoUrl = valid_host($_SERVER['HTTP_HOST']);
            if ($logoUrl === FALSE)
                exit(1);
            // we need to construct the right path to the consortium logo; we are either
            // in the admin area or on the main index.php ...
            if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE)
                $logoUrl .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/resources/images/consortium_logo.png";
            else
                $logoUrl .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . "/resources/images/consortium_logo.png";
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
                $logoUrl = "https://" . $logoUrl;
            else
                $logoUrl = "http://" . $logoUrl;
            ?>
            <div class='consortium_logo'>
                <img id='test_locate' src='$logoUrl' alt='Consortium Logo'>
            </div> <!-- consortium_logo -->

        </div><!--header_toprow-->
    </div> <!-- header -->
    <?php
}

function productheader($area, $language) {
    $place = parse_url($_SERVER['REQUEST_URI']);
    // this <div is closing in footer, keep it in PHP for Netbeans syntax
    // highlighting to work
    echo "<div class='maincontent'>";
    echo headerDiv($area);
    // content from here on will SCROLL instead of being fixed at the top
    echo "<div class='pagecontent'>"; // closes in footer again
    echo "<div class='trick'>"; // closes in footer again
    ?>
    <div id='secondrow' style='border-bottom:5px solid <?php echo Config::$APPEARANCE['colour1']; ?>; min-height:100px;'>
        <div id='secondarycaptions' style='display:inline-block; float:left'>
            <h2><?php echo $cap2; ?></h2>
        </div><!--secondarycaptions-->";
        <?php
        if (isset(Config::$APPEARANCE['MOTD']) && Config::$APPEARANCE['MOTD'] != "")
            echo "<div id='header_MOTD' style='display:inline-block; padding-left:20px;vertical-align:top;'>
              <p class='MOTD'>" . Config::$APPEARANCE['MOTD'] . "</p>
              </div><!--header_MOTD-->";
        ?>
        <div class='sidebar'><p>
                <?php
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
                ?>
            </p>
        </div> <!-- sidebar -->
    </div><!--secondrow-->
    <?php
}
