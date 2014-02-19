<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
  * Back-end supplying information for the main_menu_content window
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 *
 * This handles the popups from the main menu. The page argument is saved in the $page variable and used
 * to select the proper handler. The title argument is saced as $title and dipplayes as <h1> on the popup
 *
 */
include(dirname(dirname(dirname(__FILE__)))."/config/_config.php");
require_once("GUI.php");
$Gui = new GUI();
$Gui->set_locale("web_user");

$page = $_REQUEST['page'];
$title = $_REQUEST['title'];

switch($page) {
  case 'consortium':
    $out = '<script type="text/javascript">document.location.href="'.Config::$CONSORTIUM['homepage'].'"</script>';
    break;
  case 'about_consortium':
      if (Config::$CONSORTIUM['name'] == "eduroam")
          $out =  sprintf(_("eduroam is a global WiFi roaming consortium which gives members of education and research access to the internet <i>for free</i> on all eduroam hotspots on the planet. There are several million eduroam users already, enjoying free internet access on more than 6.000 hotspots! Visit <a href='http://www.eduroam.org'>the eduroam homepage</a> for more details."));
      else
          $out = "";
    break;
  case 'about':
    $out = sprintf(_("<span class='edu_cat'>%s</span> is built as a cooperation platform.<p>Local %s administrators enter their %s configuration details and based on them, <span class='edu_cat'>%s</span> builds customised installers for a number of popular platforms. An installer prepared for one institution will not work for users of another one, therefore if your institution is not on the list, you cannot use this system. Please contact your local administrators and try to influence them to add your institution configuration to <span class='edu_cat'>%s</span>."),Config::$APPEARANCE['productname'],Config::$CONSORTIUM['name'],Config::$CONSORTIUM['name'],Config::$APPEARANCE['productname'],Config::$APPEARANCE['productname']);
    break;
  case 'tou':
//     $out = sprintf(_("Currently this is a placeholder for ToU"));
     include('tou.php');
     return;
  case 'develop':
     $out = sprintf(_("The most important need is adding new installer modules, which will configure particular devices.  CAT is makig this easy for you. If you know how to create an automatic installer then fitting it into CAT should be a piece of cake. You should start by contacting us at <a href='mailto:%s'>%s</a>, but please also take a look at <a href='%s'>CAT documentation</a>."),Config::$APPEARANCE['admin-mail'],Config::$APPEARANCE['admin-mail'],'doc/');
    break;
   case 'report':
     $out = sprintf(_("Please send a problem report to <a href='mailto:%s'>%s</a>. Some screen dumps are very welcome."),Config::$APPEARANCE['admin-mail'],Config::$APPEARANCE['admin-mail']);
     break;
   case 'faq':
     include('faq.php');
     return;
   case 'admin' :
       $out = "";
       require_once(Config::$AUTHENTICATION['ssp-path-to-autoloader']);

       $as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);
       if($as->isAuthenticated())
          $out.='<script type="text/javascript">goAdmin()</script>';
       else {
          if (Config::$CONSORTIUM['selfservice_registration'] === NULL)
            $out .= sprintf(_("You must have received an invitation from your national %s operator before being able to manage your institution. If that is the case, please continue and log in."),Config::$CONSORTIUM['name']);
          else
            $out .= _("Please authenticate yourself and login");
          $out .= "<p><button onclick='goAdmin(); return(false);'>"._("Login")."</button>";
       }
     break;
  default:
    break;
 }
if($title != "") {
  print "<h1>".urldecode($title)."</h1>\n";
}
print $out;

?>


