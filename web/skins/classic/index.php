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
/**
 * Front-end for the user GUI
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);

$Gui->langObject->setTextDomain("web_user");
$Gui->loggerInstance->debug(4, "\n---------------------- index.php START --------------------------\n");

/**
 * Menu class helps to define the menu on the main page
 */
class Menu {

    /**
     * the constructor takes an array argument defining menu items.
     * the array must be indexed by strings which will be passed to user/cat_info.php a the page argument
     * the values of the array can be either a simple string which is passed to user/cat_info.php
     * as the title argument or an two element array - the first element of this array will be
     * the title and the second is a style specification applied to the given menu item
     */
    public function __construct($menuArray) {
        $this->menu = $menuArray;
    }

    private function printMenuLine($index, $title = "", $style = "") {
        if ($style !== "") {
            print "<tr><td style='$style'><a href='javascript:infoCAT(\"$index\",\"" . rawurlencode($title) . "\")'>$title</a></td></tr>\n";
        } else {
            print "<tr><td><a href='javascript:infoCAT(\"$index\",\"" . rawurlencode($title) . "\")'>$title</a></td></tr>\n";
        }
    }

    public function printMenu() {
        foreach ($this->menu as $index => $title) {
            if (is_array($title)) {
                $this->printMenuLine($index, $title[0], $title[1]);
            } else {
                $this->printMenuLine($index, $title);
            }
        }
    }

    private $menu;

}

$deco = new \web\lib\admin\PageDecoration();
?>
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS","cat-user.css");?>" />
<!-- JQuery -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery.js") ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-migrate-1.2.1.js") ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL","jquery/jquery-ui.js") ?>"></script>
<!-- JQuery -->
<script type="text/javascript">
    if (screen.width <= 480) {
        window.location.href = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/basic.php?<?php echo htmlspecialchars($_SERVER['QUERY_STRING']) ?>";
            }
</script>
<script type="text/javascript">
    var recognisedOS = '';
    var downloadMessage;
<?php
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, print_r($operatingSystem, true));
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
}
$downloadMessage = sprintf(_("Download your %s installer"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
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
    loading_ico.src = "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/loading51.gif"); ?>";
</script>
<?php $Gui->langObject->setTextDomain("web_user"); ?>
<!-- DiscoJuice -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "discojuice/discojuice.js"); ?>"></script>
<script type="text/javascript">
    var lang = "<?php echo($Gui->langObject->getLang()) ?>";
</script>
<link rel="stylesheet" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "discojuice/css/discojuice.css"); ?>" />
</head>
<body>
    <div id="heading">
        <?php
        print '<img src="' . $Gui->skinObject->findResourceUrl("IMAGES", "consortium_logo.png") . '" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
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
        echo '</td><td style="text-align:right;padding-right:20px"><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . $Gui->langObject->getLang() . '">' . _("Start page") . '</a></td></tr></table>';
        ?>
    </div> <!-- id="heading" -->
    <div id="loading_ico">
        <?php echo _("Authenticating") . "..." ?><br><img src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/loading51.gif"); ?>" alt="Authenticating ..."/>
    </div>
    <div id="info_overlay">
        <div id="info_window"></div>
        <span id="close_button"><?php echo _("Close") ?></span>
    </div>
    <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/">
        <div id="main_body">
            <?php if (!isset($_REQUEST['idp']) || !$_REQUEST['idp']) { ?>
                <table id="front_page">
                    <tr>
                        <td rowspan=2 id="menu_column">
                            <table id="left_menu">
                                <?php
                                $menu = new Menu([
                                    "about_consortium" => [sprintf(_("About %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']), 'padding-bottom:20px;font-weight: bold; '],
                                    "about" => sprintf(_("About %s"), CONFIG['APPEARANCE']['productname']),
                                    "tou" => sprintf(_("Terms of use")),
                                    "faq" => sprintf(_("FAQ")),
                                    "report" => sprintf(_("Report a problem")),
                                    "develop" => sprintf(_("Become a CAT developer")),
                                    "admin" => [sprintf(_("%s admin:<br>manage your %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $Gui->nomenclature_inst), 'padding-top:30px;'],
                                ]);

                                $menu->printMenu();
                                ?>
                            </table>
                        </td>
                        <td style="vertical-align: top; height:280px; background: #fff; padding-left: 20px; padding-right: 20px">
                            <div id="main_menu_info" style="display:none">
                                <img id="main_menu_close" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/button_cancel.png"); ?>" ALT="Close"  style="float:right"/>
                                <div id="main_menu_content"></div>
                            </div>
                            <table style="background: #fff; width:100%; padding-top: 5px">
                                <tr>
                                    <td id="slides" style="background: #fff url(<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "gradient-bg.png"); ?>) repeat-x; height:272px; border-radius: 16px; width: 100%; padding-left:20px;">
                                        <div>
                                            <span id="line1"><?php printf(_("%s installation made easy:"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) ?></span>
                                            <span id="line2"></span>
                                            <span id="line3"></span>
                                            <span id="line4"><?php echo sprintf(_("Custom built for your %s"),$Gui->nomenclature_inst) ?></span>
                                            <span id="line5">
                                                <?php
                                                if (isset(CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']) && CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name'] != "") {
                                                    echo sprintf(_("Digitally signed by the organisation that coordinates %s: %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']);
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div id = "img_roll">
                                            <img id="img_roll_0" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "empty.png"); ?>" alt="Rollover 0"/> <img id="img_roll_1" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "empty.png"); ?>" alt="Rollover 1"/></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td id="user_button_td">
                            <?php print '<span id="signin"><button class="signin signin_large" id="user_button1"><span id="user_button">' . sprintf(_("%s user:<br>download your %s installer"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . '</span></button></span><span style="padding-left:50px">&nbsp;</span>'; ?>

                        </td>
                    </tr>
                </table> <!-- id="front_page" -->
            <?php } ?>
            <!-- the user_page div contains all information for a given IdP, i.e. the profile selection (if multiple profiles are defined)
                 and the device selection (including the automatic OS detection ) -->
            <div id="user_page">
                <div id="institution_name">
                    <span id="inst_name_span"></span> <!-- this will be filled with the IdP name -->
                    <button class="signin">
                        <?php echo _("select another"); ?>
                    </button>
                </div>
                <div> <!-- IdP logo, if present -->
                    <img id="idp_logo" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "empty.png"); ?>" alt="IdP Logo"/>
                </div>
                <div id="profiles"> <!-- this is the profile selection filled during run time -->
                    <div id="profiles_h" class="sub_h">
                        <?php echo _("Select the user group"); ?>
                    </div>
                    <table>
                        <tr>
                            <td><select id="profile_list"></select></td>
                            <td><div id="profile_desc" class="profile_desc"></div></td>
                        </tr>
                    </table>
                </div>
                <div id="user_info"></div> <!-- this will be filled with the profile contact information -->
                <div id="user_welcome" style="display:none"> <!-- this information is shown just pefore the download -->
                    <strong><?php echo _("Welcome aboard the eduroam® user community!") ?></strong>
                    <p>
                        <span id="download_info"><?php
/// the empty href is dynamically exchanged with the actual path by jQuery at runtime
                            echo _("Your download will start shortly. In case of problems with the automatic download please use this direct <a href=''>link</a>.");
                            ?></span>
                    <p>
                        <?php printf(_("Dear user from %s,"), "<span class='inst_name'></span>") ?>
                        <br/>
                        <br/>
                        <?php echo _("we would like to warmly welcome you among the several million users of eduroam®! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!") ?>
                    </p>
                    <p>
                        <?php echo _("Now that you have downloaded and installed a client configurator, all you need to do is find an eduroam® hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!") ?>
                    <p>
                        <?php printf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means shown above."), "<span class='inst_name'></span>") ?>
                    </p>
                    <p>
                        <a href="javascript:back_to_downloads()"><strong><?php echo _("Back to downloads") ?></strong></a>
                    </p>
                </div> <!-- id="user_welcomer_page" -->
                <div id="silverbullet">
                    <?php echo _("You can download your eduroam installer via a personalised invitation link sent from your IT support. Please talk to the IT department to get this link."); ?>
                </div>
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
                    <?php if ($operatingSystem) { ?>  <!-- this part is shown when we have guessed the OS -->

                        <div class="sub_h" id="guess_os"> 
                            <table id='browser'>
                                <tr>
                                    <td>
                                        <button style='height:70px; width:450px; padding-bottom:0px;
                                                position:relative; 
                                                background-image:url("<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "vendorlogo/" . $operatingSystem['group'] . ".png"); ?>");
                                                background-repeat:no-repeat;
                                                background-position: 10px 10px;' id='g_<?php echo $operatingSystem['device'] ?>'>
                                            <img id='cross_icon_<?php echo $operatingSystem['device'] ?>' src='<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/delete_32.png"); ?>' 
                                                 style='position:absolute; left:16px; top:25px; opacity:0.9; display:none; '>
                                            <div class='download_button_text' 
                                                 style='font-size:12px; top:5px; height: 30px'
                                                 id='download_button_header_<?php echo $operatingSystem['device'] ?>'>
                                                     <?php print $downloadMessage ?>
                                            </div>
                                            <div class='download_button_text' style='font-size:20px; bottom: 5px; '>
                                                <?php echo $operatingSystem['display'] ?>
                                            </div>
                                        </button>
                                        <div class='device_info' id='info_g_<?php echo $operatingSystem['device'] ?>'></div>
                                    </td>
                                    <td style='vertical-align:top'>
                                        <button class='more_info_b' 
                                                style='height:70px; width:70px; 
                                                position:relative;
                                                background-image:url("<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/info_b.png"); ?>");
                                                background-repeat:no-repeat;
                                                background-position: 2px 7px;' 
                                                id='g_info_b_<?php echo $operatingSystem['device'] ?>'>
                                        </button>
                                    </td>
                                </tr>
                            </table> <!-- id='browser' -->
                            <div class="sub_h">
                                <a href="javascript:other_installers()"><?php echo _("All platforms"); ?></a>
                            </div>
                        </div> <!-- id="guess_os" -->
                    <?php } ?>
                    <div class="sub_h">
                        <div id="other_installers">
                            <?php echo _("Choose an installer to download"); ?>
                            <table id="device_list" style="padding:0px;">
                                <?php
                                $Gui->langObject->setTextDomain("devices");
                                foreach ($Gui->listDevices(isset($_REQUEST['hidden']) ? $_REQUEST['hidden'] : 0) as $group => $deviceGroup) {
                                    $groupIndex = count($deviceGroup);
                                    $deviceIndex = 0;
                                    print '<tbody><tr><td class="vendor" rowspan="' . $groupIndex . '"><img src="' . $Gui->skinObject->findResourceUrl("IMAGES", "vendorlogo/$group.png") . '" alt="' . $group . ' Device"></td>';
                                    foreach ($deviceGroup as $d => $D) {
                                        if ($deviceIndex) {
                                            print '<tr>';
                                        }
                                        $j = ($deviceIndex + 1) * 20;
                                        print "<td><button id='" . $d . "'>" . $D['display'] . "</button>";
                                        print "<div class='device_info' id='info_" . $d . "'></div></td>";
                                        print "<td><button class='more_info_b' id='info_b_" . $d . "'></button></td></tr>\n";
                                        $deviceIndex++;
                                    }
                                    print "</tbody>";
                                }
                                $Gui->langObject->setTextDomain("web_user");
                                ?>
                            </table>
                        </div>
                    </div>
                </div> <!-- id="devices" -->
                <input type="hidden" name="profile" id="profile_id"/>
                <input type="hidden" name="idp" id="inst_id"/>
                <input type="hidden" name="inst_name" id="inst_name"/>
                <input type="hidden" name="lang" id="lang"/>
            </div> <!-- id="user_page" -->
        </div> <!-- id="main_body" -->
    </form>
    <div class='footer' id='footer'>
        <table style='width:100%'>
            <tr>
                <td style="padding-left:20px; text-align:left">
                    <?php
                    echo $Gui->CAT_COPYRIGHT;
                    ?>
                </td>
                <td style="padding-left:80px; text-align:right;">
                    <?php
                    echo $deco->attributionEurope();
                    ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
