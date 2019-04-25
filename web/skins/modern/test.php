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
    front_page=0;
<?php

$divs = new \web\skins\modern\Divs($Gui);
$visibility = 'sb';
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, $operatingSystem);
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
//include("user/js/roll.php");
require "user/js/cat_js.php";
?>
    var loading_ico = new Image();
    loading_ico.src = "<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/loading51.gif")?>";
</script>
<?php $Gui->languageInstance->setTextDomain("web_user"); ?>
<!-- DiscoJuice -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "discojuice/discojuice.js")?>"></script>
<script type="text/javascript">
    front_page = 0;
    var lang = "<?php echo($Gui->languageInstance->getLang()) ?>";
</script>
<link rel="stylesheet" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","discojuice/css/discojuice.css")?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS","cat-user.css");?>" />
</head>
<body>
<div id="wrap">
    <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/">
    <?php echo $divs->divHeading($visibility); ?>
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
                         echo $divs->divTopWelcome();
//                         echo $divs->div_roller();
//                         echo $divs->div_main_button(); ?>
              </div> <!-- id="front_page" -->
         <?php } ?>
            <!-- the user_page div contains all information for a given IdP, i.e. the profile selection (if multiple profiles are defined)
                 and the device selection (including the automatic OS detection ) -->
      </div>
    </div>
   </form>
</div>
<?php echo $divs->divFooter(); ?>
</body>
</html>
