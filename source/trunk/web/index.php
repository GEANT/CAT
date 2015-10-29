<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/**
 * Front-end for the user GUI
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 */
error_reporting(E_ALL | E_STRICT);
include(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("UserAPI.php");
require_once("resources/inc/header.php");
require_once("resources/inc/footer.php");
require_once("CAT.php");
$Gui = new UserAPI();
$Gui->set_locale("web_user");
debug(4, "\n---------------------- index.php START --------------------------\n");
//debug(4,$_REQUEST);

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
public function __construct($menu_array) {
  $this->menu = $menu_array;
}

private function printMenuLine($index,$title="",$style="") {
if ($style !== "")
   print  "<tr><td style='$style'><a href='javascript:infoCAT(\"$index\",\"".rawurlencode($title)."\")'>$title</a></td></tr>\n";
else
   print  "<tr><td><a href='javascript:infoCAT(\"$index\",\"".rawurlencode($title)."\")'>$title</a></td></tr>\n";
}

public function printMenu() {
  foreach ($this->menu as $index => $title)
  if(is_array($title))
     $this->printMenuLine($index,$title[0],$title[1]); 
  else
     $this->printMenuLine($index,$title); 
}

private $menu;
}

defaultPagePrelude(Config::$APPEARANCE['productname_long'], FALSE);

?>


        <script type="text/javascript">
            if(screen.width <= 480) {
                window.location.href = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/basic.php?<?php echo htmlspecialchars($_SERVER['QUERY_STRING']) ?>";
            }
        </script>
        <script type="text/javascript">ie_version=0;</script>
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
        <link rel="stylesheet" media="screen" type="text/css" href="resources/css/cat-user.css" />
        <!-- JQuery --> 
        <script type="text/javascript" src="external/jquery/jquery.js"></script> 
        <script type="text/javascript" src="external/jquery/jquery-migrate-1.2.1.js"></script>
        <script type="text/javascript" src="external/jquery/jquery-ui.js"></script> 
        <!-- JQuery --> 
        <script type="text/javascript">
        var recognised_os = '';
        var download_message;
<?php
$OS = $Gui->detectOS();
debug(4,$OS);
if($OS)
   print "recognised_os = '".$OS['id']."';\n";
$download_message = sprintf(_("Download your %s installer"),Config::$CONSORTIUM['name']);
print 'download_message = "'.$download_message.'";';
//TODO modify this based on OS detection
if (preg_match('/Android/', $_SERVER['HTTP_USER_AGENT']))
    $profile_list_size = 1;
else
    $profile_list_size = 4;
include("user/js/roll.php");
include("user/js/cat_js.php");
?>
    var loading_ico = new Image();
    loading_ico.src="resources/images/icons/loading51.gif";
        </script>
        <?php $Gui->set_locale("web_user"); ?>
        <!-- DiscoJuice -->
        <script type="text/javascript" src="external/discojuice/discojuice.js"></script>
        <script type="text/javascript">
            var lang = "<?php echo(CAT::$lang_index) ?>";
        </script>
        <link rel="stylesheet" type="text/css" href="external/discojuice/css/discojuice.css" />
    </head>
    <body>
        <div id="heading">
            <?php
            print '<img src="resources/images/consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
            print '<div id="motd">'.( isset(Config::$APPEARANCE['MOTD']) ? Config::$APPEARANCE['MOTD'] : '&nbsp' ).'</div>';
            print '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("Welcome to %s"), Config::$APPEARANCE['productname']) . '</h1>
<h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . Config::$APPEARANCE['productname_long'] . '</h2>';
            echo '<table id="lang_select"><tr><td>';
            echo _("View this page in");
            ?>
            <?php
            foreach (Config::$LANGUAGES as $lang => $value) {
                echo "<a href='javascript:changeLang(\"$lang\")'>" . $value['display'] . "</a> ";
            }
            echo '</td><td style="text-align:right;padding-right:20px"><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '?lang=' . CAT::$lang_index . '">' . _("Start page") . '</a></td></tr></table>';
            ?>
        </div> <!-- id="heading" -->
        <div id="loading_ico">
            <?php echo _("Authenticating") . "..." ?><br><img src="resources/images/icons/loading51.gif" alt="Authenticating ..."/>
        </div>
        <div id="info_overlay">
            <div id="info_window"></div>
            <span id="close_button"><?php echo _("Close") ?></span>
        </div>
        <form id="cat_form" name="cat_form" method="POST"  accept-charset="UTF-8" action="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/">
        <div id="main_body">
<?php if(! isset($_REQUEST['idp']) || ! $_REQUEST['idp']) { ?>
                <table id="front_page">
                    <tr>
                        <td rowspan=2 id="menu_column">
                            <table id="left_menu">
<?php
                                $menu = new Menu( [
                                   "about_consortium"=>[sprintf(_("About %s"), Config::$CONSORTIUM['name']),'padding-bottom:20px;font-weight: bold; '],
                                   "about"=>sprintf(_("About %s"), Config::$APPEARANCE['productname']),
                                   "tou"=>sprintf(_("Terms of use")),
                                   "faq"=>sprintf(_("FAQ")),
                                   "report"=>sprintf(_("Report a problem")),
                                   "develop"=>sprintf(_("Become a CAT developer")),
                                   "admin"=>[sprintf(_("%s admin:<br>manage your IdP"), Config::$CONSORTIUM['name']),'padding-top:30px;'],
                                ]);

                                $menu->printMenu(); ?>
                            </table>
                        </td>
                        <td style="vertical-align: top; height:280px; background: #fff; padding-left: 20px; padding-right: 20px">
                            <div id="main_menu_info" style="display:none">
                                    <img id="main_menu_close" src="resources/images/icons/button_cancel.png" ALT="Close"  style="float:right"/>
                                <div id="main_menu_content"></div>
                            </div>
                            <table style="background: #fff; width:100%; padding-top: 5px">
                                <tr>
                                    <td id="slides" style="background: #fff url(resources/images/gradient-bg.png) repeat-x; height:272px; border-radius: 16px; width: 100%; padding-left:20px;">
                                        <div>
                                            <span id="line1"><?php printf(_("%s installation made easy:"), Config::$CONSORTIUM['name']) ?></span>
                                            <span id="line2"></span>
                                            <span id="line3"></span>
                                            <span id="line4"><?php echo _("Custom built for your home institution") ?></span>
                                            <span id="line5">
                                            <?php
                                            if (isset(Config::$CONSORTIUM['signer_name']) && Config::$CONSORTIUM['signer_name'] != "")
                                                echo sprintf(_("Digitally signed by the organisation that coordinates %s: %s"),Config::$CONSORTIUM['name'],Config::$CONSORTIUM['signer_name']);
                                            ?>
                                            </span>
                                        </div>
                                        <div id = "img_roll">
                                        <img id="img_roll_0" src="resources/images/empty.png" alt="Rollover 0"/> <img id="img_roll_1" src="resources/images/empty.png" alt="Rollover 1"/></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td id="user_button_td">
<?php print '<span id="signin"><button class="signin signin_large" id="user_button1"><span id="user_button">' . sprintf(_("%s user:<br>download your %s installer"), Config::$CONSORTIUM['name'], Config::$CONSORTIUM['name']) . '</span></button></span><span style="padding-left:50px">&nbsp;</span>'; ?>

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
                        <img id="idp_logo" src="resources/images/empty.png" alt="IdP Logo"/>
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
                       <strong><?php echo _("Welcome aboard the eduroam® user community!")?></strong>
                       <p>
                       <span id="download_info"><?php 
                       /// the empty href is dynamically exchanged with the actual path by jQuery at runtime
                       echo _("Your download will start shortly, in case of problems with the automatic download please use this direct <a href=''>link</a>.");
                       ?></span>
                       <p>
                       <?php printf(_("Dear user from %s,"),"<span class='inst_name'></span>") ?>
                       <br/>
                       <br/>
                       <?php echo _("we would like to warmly welcome you among the several million users of eduroam®! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!") ?>
                       </p>
                       <p>
                       <?php echo _("Now that you have downloaded and installed a client configurator, all you need to do is find an eduroam® hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!") ?>
                       <p>
                       <?php printf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means shown above."),"<span class='inst_name'></span>") ?>
                       </p>
                       <p>
                       <a href="javascript:back_to_downloads()"><strong><?php echo _("Back to downloads") ?></strong></a>
                       </p>
                    </div> <!-- id="user_welcomer_page" -->
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
<?php if($OS) { ?>  <!-- this part is shown when we have guessed the OS -->
                        
                        <div class="sub_h" id="guess_os"> 
                              <table id='browser'>
                                  <tr>
                                     <td>
                                         <button style='height:70px; width:450px; padding-bottom:0px;
                                              position:relative; 
                                              background-image:url("resources/images/vendorlogo/<?php echo $OS['group']?>.png");
                                              background-repeat:no-repeat;
                                              background-position: 10px 10px;' id='g_<?php echo $OS['id']?>'>
                                             <img id='cross_icon_<?php echo $OS['id']?>' src='resources/images/icons/delete_32.png' 
                                                  style='position:absolute; left:16px; top:25px; opacity:0.9; display:none; '>
                                              <div class='download_button_text' 
                                                   style='font-size:12px; top:5px; height: 30px'
                                                   id='download_button_header_<?php echo $OS['id']?>'>
                                                   <?php print $download_message ?>
                                             </div>
                                             <div class='download_button_text' style='font-size:20px; bottom: 5px; '>
                                                <?php echo $OS['display']?>
                                             </div>
                                         </button>
                                         <div class='device_info' id='info_g_<?php echo $OS['id']?>'></div>
                                     </td>
                                     <td style='vertical-align:top'>
                                         <button class='more_info_b' 
                                              style='height:70px; width:70px; 
                                                position:relative;
                                                background-image:url("resources/images/icons/info_b.png");
                                                background-repeat:no-repeat;
                                                background-position: 2px 7px;' 
                                              id='g_info_b_<?php echo $OS['id']?>'>
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
                                    $Gui->set_locale("devices");
                                    foreach ($Gui->listDevices(isset($_REQUEST['hidden']) ? $_REQUEST['hidden'] : 0) as $group => $G) {
                                        $ct = count($G);
                                        $i = 0;
                                        print '<tbody><tr><td class="vendor" rowspan="' . $ct . '"><img src="resources/images/vendorlogo/' . $group . '.png" alt="'.$group.' Device"></td>';
                                        foreach ($G as $d => $D) {
                                            if ($i)
                                                print '<tr>';
                                            $j = ($i+1)*20;
                                            print "<td><button id='" . $d . "'>" . $D['display'] . "</button>";
                                            print "<div class='device_info' id='info_" . $d . "'></div></td>";
                                            print "<td><button class='more_info_b' id='info_b_" . $d . "'></button></td></tr>\n";
                                            $i++;
                                        }
                                        print "</tbody>";
                                    }
                                    $Gui->set_locale("web_user");
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
                    echo Config::$APPEARANCE['productname'] . " - ";
                    if (CAT::$VERSION != "UNRELEASED")
                        echo sprintf(_("Release %s"), CAT::$VERSION);
                    else
                        echo _("Unreleased SVN Revision");
                    echo " &copy; 2011-15 G&Eacute;ANT on behalf of the GN3, GN3plus, GN4 consortia and others <a href='copyright.php'>Full Copyright and Licenses</a>";
                ?>
                 </td>
                 <td style="padding-left:80px; text-align:right;">
                 <?php
                     if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") // SW: APPROVED
                         echo "
                  <span id='logos' style='position:fixed; left:50%;'><img src='resources/images/dante.png' alt='DANTE' style='height:23px;width:47px'/>
                  <img src='resources/images/eu.png' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
                  <span id='eu_text' style='text-align:right;'><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top;'>European Commission Communications Networks, Content and Technology</a></span>";
                     else
                         echo "&nbsp;";
                 ?>
                </td>
            </tr>
        </table>
    </div>
    </body>
</html>
