<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
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
$loggerInstance = new \core\common\Logging();
$langObject = new \core\common\Language();
$cat = new \core\CAT();
$loggerInstance->debug(4, "\n----------------------------------TOU.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $langObject->getLang() ?>">
    <head lang="<?php echo $langObject->getLang() ?>"> 
        <title><?php echo CONFIG['APPEARANCE']['productname_long']; ?></title>
        <link href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/css/cat.css.php" type= "text/css" rel="stylesheet" />
        <meta charset="utf-8" /> 
        <script type="text/javascript">
            function showTOU() {
                document.getElementById('all_tou_link').style.display = 'none';
                document.getElementById('tou_2').style.display = 'block';

            }
        </script>

    </head>
    <body style='background: #fff url(resources/images/bg_grey_tile.png) repeat-x;'>
        <img src="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/images/consortium_logo.png" style="padding-right:20px; padding-top:20px; float:right" alt="logo" />

        <div id="motd"><?php print ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp'); ?></div>

        <h1><a href="<?php echo dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $langObject->getLang(); ?>"><?php echo CONFIG['APPEARANCE']['productname']; ?></a></h1>
        <div id="tou">
            <?php
            include("user/tou.php");
            ?>
        </div>
        <div>
            <table style='width:100%'>
                <tr>
                    <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>
                        <?php echo $cat->CAT_COPYRIGHT; ?>
                    </td>
                    <td style='padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;'>
                        <?php
                        $deco = new \web\lib\admin\PageDecoration();
                        echo $deco->attributionEurope();
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>
