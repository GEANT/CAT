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
 * constructs a URL to the main resources (CSS and LOGO)
 * 
 * @param string $resourcetype which type of resource do we need a URL for? CSS or LOGO?
 * @return string the URL to the resource
 * @throws Exception if something went wrong during the URL construction
 */

function findResourceUrl($resourcetype) {
    switch ($resourcetype) {
        case "CSS":
            $path = "/resources/css/cat.css.php";
            break;
        case "LOGO":
            $path = "/resources/images/consortium_logo.png";
            break;
        default:
            throw new Exception("findResourceUrl: unknown type of resource requested");
    }
    $url = "//" . valid_host($_SERVER['HTTP_HOST']); // omitting http or https means "on same protocol"
    if ($url === FALSE) {
        throw new Exception("We don't know our own hostname?!");
    }
    // we need to construct the right path to the file; we are either
    // in the admin area or on the main index.php ...
    if (strpos($_SERVER['PHP_SELF'], "admin/") !== FALSE) {
        return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . $path;
    }
    if (strpos($_SERVER['PHP_SELF'], "diag/") !== FALSE) {
        return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/diag/")) . $path;
    }
    return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . $path;
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
 * @return \CAT an instance of the CAT object (useful for later lang change operations etc.)
 */
function pageheader($pagetitle, $area, $authRequired = TRUE) {
    $cat = defaultPagePrelude($pagetitle, $authRequired);
    echo "</head></body>";
    productheader($area, CAT::get_lang());
    return $cat;
}

/**
 * 
 * @param string $pagetitle Title of the page to display
 * @param boolean $authRequired does the user need to be autenticated to access this page?
 * @return \CAT an instance of the CAT object (useful for later lang change operations etc.)
 */
function defaultPagePrelude($pagetitle, $authRequired = TRUE) {
    if ($authRequired === TRUE) {
        require_once(dirname(dirname(dirname(__FILE__))) . "/admin/inc/auth.inc.php");
        authenticate();
    }
    $cat = new CAT();
    $cat->set_locale("web_admin");
    $ourlocale = CAT::get_lang();
    header("Content-Type:text/html;charset=utf-8");
    echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='" . $ourlocale . "'>
          <head lang='" . $ourlocale . "'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";

    $cssUrl = findResourceUrl("CSS");

    echo "<link rel='stylesheet' type='text/css' href='$cssUrl' />";
    echo "<title>" . htmlspecialchars($pagetitle) . "</title>";

    return $cat;
}

/**
 * constructs a <div> called 'header' for use on the top of the page
 * @param string $cap1 caption to display in this div
 * @param string $language current language (this one gets pre-set in the lang selector drop-down
 */
function headerDiv($cap1, $language) {
    $place = parse_url($_SERVER['REQUEST_URI']);
    ?>
    <div class='header'>
        <div id='header_toprow'>
            <div id='header_captions' style='display:inline-block; float:left; min-width:400px;'>
                <h1><?php echo $cap1; ?></h1>
            </div><!--header_captions-->
            <div id='langselection' style='padding-top:20px; padding-left:10px;'>
                <form action='<?php echo $place['path']; ?>' method='GET' accept-charset='UTF-8'><?php echo _("View this page in"); ?>&nbsp;
                    <select id='lang' name='lang' onchange='this.form.submit()'>
                        <?php
                        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
                            echo "<option value='$lang' " . (strtoupper($language) == strtoupper($lang) ? "selected" : "" ) . " >" . $value['display'] . "</option> ";
                        }
                        ?>
                    </select>
                    <?php
                    foreach ($_GET as $var => $value) {
                        if ($var != "lang" && $value != "") {
                            echo "<input type='hidden' name='" . htmlspecialchars($var) . "' value='" . htmlspecialchars($value) . "'>";
                        }
                    }
                    ?>
                </form>
            </div><!--langselection-->
            <?php
            $logoUrl = findResourceUrl("LOGO");
            ?>
            <div class='consortium_logo'>
                <img id='test_locate' src='<?php echo $logoUrl; ?>' alt='Consortium Logo'>
            </div> <!-- consortium_logo -->

        </div><!--header_toprow-->
    </div> <!-- header -->
    <?php
}

/**
 * Our (very modest and light) sidebar. authenticated admins get more options, like logout
 * @param boolean $advancedControls
 */
function sidebar($advancedControls) {
    ?>
    <div class='sidebar'><p>
            <?php
            if ($advancedControls) {
                echo "<strong>" . _("You are:") . "</strong> "
                . (isset($_SESSION['name']) ? $_SESSION['name'] : _("Unnamed User")) . "
              <br/>
              <br/>
              <a href='overview_user.php'>" . _("Go to your Profile page") . "</a> 
              <a href='inc/logout.php'>" . _("Logout") . "</a> ";
            }
            $startPageUrl = "../";
            if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE) {
                $startPageUrl = dirname($_SERVER['SCRIPT_NAME']) . "/";
            }

            echo "<a href='" . $startPageUrl . "'>" . _("Start page") . "</a>";
            ?>
        </p>
    </div> <!-- sidebar -->
    <?php
}

/**
 * the entire top of the page (<body> part)
 * 
 * @param string $area the area we are in
 * @param string $language the currently set language
 */
function productheader($area, $language) {
    // this <div is closing in footer, keep it in PHP for Netbeans syntax
    // highlighting to work
    echo "<div class='maincontent'>";

    switch ($area) {
        case "ADMIN-IDP":
            $cap1 = CONFIG['APPEARANCE']['productname_long'];
            $cap2 = _("Administrator Interface - Identity Provider");
            $advancedControls = TRUE;
            break;
        case "ADMIN-IDP-USERS":
            $cap1 = CONFIG['APPEARANCE']['productname_long'];
            $cap2 = _("Administrator Interface - eduroam-as-a-service User Management");
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
            $cap2 = _("Administrator Interface - Federation Management");
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
        default:
            $cap1 = CONFIG['APPEARANCE']['productname_long'];
            $cap2 = "It is an error if you ever see this string.";
            $advancedControls = FALSE;
    }


    echo headerDiv($cap1, $language);
    // content from here on will SCROLL instead of being fixed at the top
    echo "<div class='pagecontent'>"; // closes in footer again
    echo "<div class='trick'>"; // closes in footer again
    ?>
    <div id='secondrow' style='border-bottom:5px solid <?php echo CONFIG['APPEARANCE']['colour1']; ?>; min-height:100px;'>
        <div id='secondarycaptions' style='display:inline-block; float:left'>
            <h2><?php echo $cap2; ?></h2>
        </div><!--secondarycaptions-->
        <?php
        if (isset(CONFIG['APPEARANCE']['MOTD']) && CONFIG['APPEARANCE']['MOTD'] != "") {
            echo "<div id='header_MOTD' style='display:inline-block; padding-left:20px;vertical-align:top;'>
              <p class='MOTD'>" . CONFIG['APPEARANCE']['MOTD'] . "</p>
              </div><!--header_MOTD-->";
        }
        echo sidebar($advancedControls);
        ?>

    </div><!--secondrow-->
    <?php
}
