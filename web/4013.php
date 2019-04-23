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
 * 401 and 403 error handler
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);
require dirname(dirname(__FILE__)) . "/config/_config.php";

$langObject = new \core\common\Language();
$langObject->setTextDomain("web_user");

$deco = new \web\lib\admin\PageDecoration();

echo $deco->defaultPagePrelude(\config\Master::APPEARANCE['productname_long'], FALSE);
?>
</head>
<body style='background: #fff url(resources/images/bg_grey_tile.png) repeat-x;'>
    <div id="heading">
        <?php
        print '<img src="'. dirname($_SERVER['SCRIPT_NAME']) .'/resources/images/consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
        print '<div id="motd">' . ( isset(\config\Master::APPEARANCE['MOTD']) ? \config\Master::APPEARANCE['MOTD'] : '&nbsp' ) . '</div>';
        print '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("Welcome to %s"), \config\Master::APPEARANCE['productname']) . '</h1>
<h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . \config\Master::APPEARANCE['productname_long'] . '</h2>';
        echo '<table id="lang_select"><tr><td>';
        echo _("View this page in");
        ?>
        <?php
        foreach (\config\Master::LANGUAGES as $lang => $value) {
            echo "<a href='javascript:changeLang(\"$lang\")'>" . $value['display'] . "</a> ";
        }
        echo '</td><td style="text-align:right;padding-right:20px"><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $langObject->getLang() . '">' . _("Start page") . '</a></td></tr></table>';
        ?>
    </div> <!-- id="heading" -->
    <div id="main_body" style='padding:20px;'>
        <h1><?php echo _("Maybe this is the CAT you are looking for...");?></h1>
        <p><?php echo _("but we don't want to show it to you. You need to be authenticated and authorised to see this content. Since you are not, you got this error page usually known as");?></p>
        <h2>401/403</h2>
        <p><?php echo sprintf(_("Your mistake? Our error? Who knows! Maybe you should go back to the <a href='%s'>Start Page</a>."), dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $langObject->getLang())?></p>
    </div>
        <?php echo $deco->footer();
