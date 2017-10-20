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
 * 401 and 403 error handler
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);
include(dirname(dirname(__FILE__)) . "/config/_config.php");

$langObject = new \core\common\Language();
$langObject->setTextDomain("web_user");

$deco = new \web\lib\admin\PageDecoration();

echo $deco->defaultPagePrelude(CONFIG['APPEARANCE']['productname_long'], FALSE);
?>
</head>
<body style='background: #fff url(resources/images/bg_grey_tile.png) repeat-x;'>
    <div id="heading">
        <?php
        print '<img src="'. dirname($_SERVER['SCRIPT_NAME']) .'/resources/images/consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
        print '<div id="motd">' . ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp' ) . '</div>';
        print '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("Welcome to %s"), CONFIG['APPEARANCE']['productname']) . '</h1>
<h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . CONFIG['APPEARANCE']['productname_long'] . '</h2>';
        echo '<table id="lang_select"><tr><td>';
        echo _("View this page in");
        ?>
        <?php
        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
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