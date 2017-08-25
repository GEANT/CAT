<?php
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
$Gui->loggerInstance->debug(4, print_r($operatingSystem, true));
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
include("user/js/aa_js.php");
}
?>
var lang = "<?php echo($Gui->langObject->getLang()) ?>";
</script>
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS","cat-user.css");?>" />

</head>
<body>
<div id="wrap">
    <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo $_SERVER['SCRIPT_NAME'] ?>/">
    <?php
    include "div_heading.php";
    print '<div id="main_page">';
    ?>
    <div id="info_overlay"> <!-- device info -->
        <div id="info_window"></div>
        <img id="info_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/button_cancel.png")?>" ALT="Close"/>
    </div>
    <div id="main_menu_info" style="display:none"> <!-- stuff triggered form main menu -->
          <img id="main_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/button_cancel.png")?>" ALT="Close"/>
          <div id="main_menu_content"></div>
    </div>
       <div id="main_body">
              <div id="front_page">
</div>
<div id="user_page">
<div id="institution_name">
    <span id="inst_name_span"><?php echo $statusInfo['idp']->name; ?></span>
</div>
<div> <!-- IdP logo, if present -->
    <img id="idp_logo" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","empty.png")?>" alt="IdP Logo"/>
</div>
<div id="user_info"></div> <!-- this will be filled with the profile contact information -->
<div id="devices" class="device_list">
<?php                            include "div_guess_os.php"; ?>
            </div> <!-- id="user_page" -->
      </div>
    </div>




    </form>
    </div>
<?php include "div_foot.php"; ?>

</body>
