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
?>

<!-- JQuery -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery.js") ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-migrate-1.2.1.js") ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-ui.js") ?>"></script>
<!-- JQuery -->

<script type="text/javascript">
    var recognisedOS = '';
    var downloadMessage;
<?php
include_once('Divs.php');
$divs = new Divs($Gui);
$visibility = 'index';
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, print_r($operatingSystem, true));
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
}

print 'downloadMessage = "' . $Gui->textTemplates->templates[\web\lib\user\DOWNLOAD_MESSAGE] . '";';
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
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "discojuice/discojuice.js")?>"></script>
<script type="text/javascript">
    var lang = "<?php echo($Gui->langObject->getLang()) ?>";
</script>
<link rel="stylesheet" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","discojuice/css/discojuice.css")?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS","cat-user.css");?>" />
</head>
<body>
<div id="wrap">
    <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/">
    <?php include "div_heading.php"; ?>
    <div id="main_page">
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
                         echo $divs->div_top_welcome();
                         echo $divs->div_roller();
                         echo $divs->div_main_button(); ?>
              </div> <!-- id="front_page" -->
         <?php } ?>
            <!-- the user_page div contains all information for a given IdP, i.e. the profile selection (if multiple profiles are defined)
                 and the device selection (including the automatic OS detection ) -->
            <div id="user_page">
            <?php  echo $divs->div_institution();
                   echo $divs->div_profiles(); ?>
                <div id="user_info"></div> <!-- this will be filled with the profile contact information -->
                <?php echo $divs->div_user_welcome() ?>
                <div id="profile_redirect"> <!-- this is shown when the entire profile is redirected -->
                    <?php echo $Gui->textTemplates->templates[web\lib\user\DOWNLOAD_REDIRECT]; ?>
                    <br>
                    <span class="redirect_link">
                        <a id="profile_redirect_bt" href="" target="_blank"><?php echo $Gui->textTemplates->templates[\web\lib\user\DOWNLOAD_REDIRECT_CONTINUE]; ?>
                        </a>
                    </span>
                </div> <!-- id="profile_redirect" -->
                <div id="devices" class="device_list">
                    <?php
                        // this part is shown when we have guessed the OS -->
                        if ($operatingSystem) {
                            echo $divs->div_guess_os($operatingSystem);
                        }
                         echo $divs->div_otherinstallers();
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
<?php echo $divs->div_footer(); ?>
</body>
</html>
