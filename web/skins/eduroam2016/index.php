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
 * Front-end for the user GUI
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);

$Gui->langObject->setTextDomain("web_user");

function defaultPagePrelude($guiObject, $pagetitle, $authRequired = TRUE) {
    if ($authRequired === TRUE) {
    }
    $ourlocale = $guiObject->langObject->getLang();
    header("Content-Type:text/html;charset=utf-8");
    echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='" . $ourlocale . "'>
          <head lang='" . $ourlocale . "'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
    $cssUrl = $guiObject->skinObject->findResourceUrl("CSS","cat.css.php");

    echo "<link rel='stylesheet' type='text/css' href='$cssUrl' />";
    echo "<title>" . htmlspecialchars($pagetitle) . "</title>";
}




$Gui->loggerInstance->debug(4, "\n---------------------- index.php START --------------------------\n");

defaultPagePrelude($Gui,CONFIG['APPEARANCE']['productname_long'], FALSE);
?>


<script type="text/javascript">ie_version = 0;</script>
<!--[if IE]>
<script type="text/javascript">ie_version=1;</script>
<![endif]-->
<!--[if IE 7]>
<script type="text/javascript">ie_version=7;</script>
<![endif]-->
<!--[if IE 8]>
<script type="text/javascript">ie_version=8;</script>
<![endif]-->
<!--[if IE 9]>
<script type="text/javascript">ie_version=9;</script>
<![endif]-->
<!--[if IE 10]>
<script type="text/javascript">ie_version=10;</script>
<![endif]-->
<!-- JQuery --> 
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery.js");?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-migrate-1.2.1.js");?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-ui.js");?>"></script>
<!-- JQuery --> 
<script type="text/javascript">
    var recognisedOS = '';
    var downloadMessage;
<?php
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, print_r($operatingSystem, true));
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
}
$downloadMessage = sprintf(_("Download your %s installer"), CONFIG['CONSORTIUM']['name']);
print 'downloadMessage = "' . $downloadMessage . '";';
//TODO modify this based on OS detection
if (preg_match('/Android/', $_SERVER['HTTP_USER_AGENT'])) {
    $profile_list_size = 1;
} else {
    $profile_list_size = 4;
}
include("user/js/roll.php");
include("user/js/cat_js.php");
?>
    var loading_ico = new Image();
    loading_ico.src = "<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/loading51.gif")?>";
</script>
<?php $Gui->langObject->setTextDomain("web_user"); ?>
<!-- DiscoJuice -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","discojuice/discojuice.js")?>"></script>
<script type="text/javascript">
    var lang = "<?php echo($Gui->langObject->getLang()) ?>";
</script>
<link rel="stylesheet" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","discojuice/css/discojuice_n.css")?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS","cat-user.css");?>" />
</head>
<body>
<div id="wrap">
    <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/">
    <?php
    include "div_heading.php";
    print '<div id="main_page">';
    ?>
    <div id="loading_ico">
        <?php echo _("Authenticating") . "..." ?><br><img src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/loading51.gif")?>" alt="Authenticating ..."/>
    </div>
    <div id="info_overlay"> <!-- device info -->
        <div id="info_window"></div>
        <img id="info_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/button_cancel.png")?>" ALT="Close"/>
    </div>
    <div id="main_menu_info" style="display:none"> <!-- stuff triggered form main menu -->
          <img id="main_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/button_cancel.png")?>" ALT="Close"/>
          <div id="main_menu_content"></div>
    </div>
        <div id="main_body">
         <?php if (empty($_REQUEST['idp'])) { ?>
              <div id="front_page">
                  <?php
                         include "div_top_welcome.php";
                         include "div_roller.php";
                         include "div_main_button.php"; ?>
                </div> <!-- id="front_page" -->
            <?php } ?>
            <!-- the user_page div contains all information for a given IdP, i.e. the profile selection (if multiple profiles are defined)
                 and the device selection (including the automatic OS detection ) -->
            <div id="user_page">
            <?php  include "div_institution.php";
                   include "div_profiles.php"; ?>
                <div id="user_info"></div> <!-- this will be filled with the profile contact information -->
                <?php  include "div_user_welcome.php"; ?>
                <div id="profile_redirect"> <!-- this is shown when the entire profile is redirected -->
                    <?php echo _("Your local administrator has specified a redirect to a local support page.<br>
                            When you click <b>Continue</b> this support page will be opened in a new window/tab."); ?>
                    <br>
                    <span class="redirect_link">
                        <a id="profile_redirect_bt" href="" target="_blank"><?php echo _("Continue"); ?>
                        </a>
                    </span>
                </div> <!-- id="profile_redirect" -->
                <div id="devices" class="device_list">
                    <?php
                        // this part is shown when we have guessed the OS -->
                        if ($operatingSystem) {
                            include "div_guess_os.php";
                        }
                         include "div_other_installers.php";
                    ?>
                </div> <!-- id="devices" -->
                <input type="hidden" name="profile" id="profile_id"/>
                <input type="hidden" name="idp" id="inst_id"/>
                <input type="hidden" name="inst_name" id="inst_name"/>
                <input type="hidden" name="lang" id="lang"/>
            </div> <!-- id="user_page" -->
      </div>
    </div>
   </form>
</div>
<?php include "div_foot.php"; ?>
</body>
</html>
