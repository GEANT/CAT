<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
/**
 * This file contains the implementation of the simple CAT user interdace
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package UserGUI
 * 
 */

include(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("UserAPI.php");

debug(4,"\n----------------------------------TOU.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo "$Gui->lang_index"?>">
    <head lang="<?php echo "$Gui->lang_index"?>"> 
        <title><?php echo Config::$APPEARANCE['productname_long'];?></title>
<link media="only screen and (max-device-width: 480px)" href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/') ?>/resources/css/cat-basic.css.php" type= "text/css" rel="stylesheet" />
<link media="only screen and (min-device-width: 481px)" href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/css/cat-basic-large.css" type= "text/css" rel="stylesheet" />
        <meta charset="utf-8" /> 
<script type="text/javascript">
function showTOU(){
  document.getElementById('all_tou_link').style.display = 'none';
  document.getElementById('tou_2').style.display = 'block';

}
</script>

    </head>
    <body style="">
        <img src="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/images/consortium_logo.png" style="padding-right:20px; padding-top:20px; float:right" alt="logo" />
         <?php
            print '<div id="motd">'.( isset(Config::$APPEARANCE['MOTD']) ? Config::$APPEARANCE['MOTD'] : '&nbsp' ).'</div>'; 

        print '<h1><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . CAT::$lang_index . '">' . Config::$APPEARANCE['productname'] . '</a></h1>';
print '<div id="tou">';
include("user/tou.php");
print '</div>';

// this variable gets set during "make distribution" only
$RELEASE = "THERELEASE";
echo "".Config::$APPEARANCE['productname']." - ";
if ($RELEASE != "THERELEASE") 
    echo sprintf(_("Release %s"), $RELEASE);
else
    echo _("Unreleased SVN Revision");
echo " &copy; 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia</div>";?>
</body>
</html>
