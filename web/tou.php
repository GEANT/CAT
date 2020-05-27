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
 * This file contains the implementation of the simple CAT user interdace
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package UserGUI
 * 
 */
require dirname(dirname(__FILE__)) . "/config/_config.php";
$loggerInstance = new \core\common\Logging();
$langObject = new \core\common\Language();
$cat = new \core\CAT();
$loggerInstance->debug(4, "\n----------------------------------TOU.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $langObject->getLang() ?>">
    <head lang="<?php echo $langObject->getLang() ?>"> 
        <title><?php echo \config\Master::APPEARANCE['productname_long']; ?></title>
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

        <div id="motd"><?php print ( isset(\config\Master::APPEARANCE['MOTD']) ? \config\Master::APPEARANCE['MOTD'] : '&nbsp'); ?></div>

        <h1><a href="<?php echo dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $langObject->getLang(); ?>"><?php echo \config\Master::APPEARANCE['productname']; ?></a></h1>
        <div id="tou">
            <?php
            require "user/tou.php";
            ?>
        </div>
        <div>
            <table style='width:100%'>
                <caption><?php echo "Legalese";?></caption>
                <tr>
                    <th class='wai-invisible' scope='col'><?php echo "Copyright";?></th>
                    <th class='wai-invisible' scope='col'><?php echo "Privacy Notice";?></th>
                    <th class='wai-invisible' scope='col'><?php echo "Attribution";?></th>
                </tr>
                <tr>
                    <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>
                        <?php echo $cat->catCopyright; ?>
                    </td>
                    <?php
                    if (!empty(\config\Master::APPEARANCE['privacy_notice_url'])) {
                        $retval .= "<td><a href='".\config\Master::APPEARANCE['privacy_notice_url']."'>" . sprintf(_("%s Privacy Notice"),\config\ConfAssistant::CONSORTIUM['name']) . "</a></td>";
                    }
                    ?>
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
