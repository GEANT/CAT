<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
/**
 * This file contains the implementation of the simple CAT user interface
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package UserGUI
 * 
 */

include(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("CAT.php");
require_once("UserAPI.php");

debug(4,"basic.php\n");
debug(4,$_POST);

/**
  * SimpleGUI defines extensions of the GUI class used only in the simple interface
  * this class does not define its own constructor.
  */
class SimpleGUI extends UserAPI {

/**
  *  create the SimpleGUI object calling CAT constructor first
  *
  *  sets up all public prperties of the object
  */
public function __construct() {
  parent::__construct();
  $this->Args = array();
  $this->page =  0;
  $this->set_locale('core');
  $this->Args['lang'] = CAT::$lang_index;
//print "<pre>"; print_r($_REQUEST); print "</pre>";

/*
   The request may contain identifiers of country, idp, profile, device
   We require that if an identifiet of a lower level exists then all higher identifiers must also
   be present and match. If a mismatch occures that the lower level identifiers are dropped
*/


    if(isset($_REQUEST['reset_dev']) && $_REQUEST['reset_dev'] == 1)
      unset($_REQUEST['device']);

/* Start with checking if we have the country code if not then use geolocation..
*/
    if(isset($_REQUEST['country']) && $_REQUEST['country']) {
       $c = strtoupper($_REQUEST['country']);
    } else {
       $L = $this->locateUser();
       if( $L['status'] == 'ok' ) {
         $c = strtoupper($L['country']);
       } else {
         debug(2, "No coutry provided and unable to locate the address\n");
         $c=0;
       }
    }
    $this->Country = new Federation($c);
    $this->Args['country'] = $this->Country->identifier;
    $this->page =  1;

// If we have IdP identifier then match country to this identifier
// if the request contians a country code and an IdP code that do nat match
// then drop the IdP code and just leave the country 
// If we have Profile identifier then test if we also have IdP identifier, if we do
// and they do not match then drop the profile code and just leave the IdP

    if(isset($_REQUEST['idp']) && $_REQUEST['idp']) {
       $this->page =  2;
       try {
         $this->Idp = new IdP($_REQUEST['idp']);
       }
       catch (Exception $fail) {
         $this->page =  1;
         $this->set_locale("web_user");
         return;
       }
       $country_tmp = new Federation($this->Idp->federation);
       if(strtoupper($this->Country->identifier) !== strtoupper($country_tmp->identifier)) {
         unset($this->Idp);
         $this->page = 1;
         $this->set_locale("web_user");
         return;
       } 
       $this->Args['idp'] = $_REQUEST['idp'];
       $this->profile_count = $this->Idp->profileCount();
       if(!isset($_REQUEST['profile'])) {
         $this->set_locale("web_user");
         return;
       }
       $this->page =  3;
       try {
         $this->Profile = new Profile($_REQUEST['profile']);
       }
       catch (Exception $fail) {
         $this->page =  2;
         $this->set_locale("web_user");
         return;
       }
       if($this->Profile->institution != $this->Idp->identifier)  {
          unset($this->Profile);
          $this->page = 2;
          $this->set_locale("web_user");
          return;
       }
       $this->Args['profile'] = $_REQUEST['profile'];
       if(isset($_REQUEST['device'])) {
             $this->Args['device'] = $_REQUEST['device'];
       }

    }
//print "<pre>"; print_r($_REQUEST); print "</pre>";
   $this->set_locale("web_user");
}

// print coutry selection
public function listCountries() {
   $out = '';
   $FED = $this->printCountryList(1);
   $out .= _('Select your country').'<br>';
   $out .= '<select name="country" onchange="submit_form(this)">'."\n";
   foreach ($FED as $f => $F) {
     $out .= '<option value="'.$f.'"';
     if($f === $this->Country->identifier)
         $out .= ' selected';
     $out .= '>'.$F.'</option>'."\n";
   }
   $out .= '</select>';
   return $out;
}

public function listIdPs() {
   $Inst = $this->orderIdentityProviders($this->Country->identifier);
   if(! isset($this->Idp))
     $this->Idp = new Idp ($Inst[0]['id']);
   $i_id = $this->Idp->identifier;
   $out = '';
   $out .= _("Select your institution");
   $out .= '<select name="idp" onchange="submit_form(this)">';
   foreach ($Inst as $I) {
      $out .= '<option value="'.$I['id'].'"';
      if($I['id'] == $i_id)
         $out .= ' selected';
      $out .= '>'.$I['title'].'</option>';
   }
   $out .= '</select>'; 
   return $out;
}

public function listProfiles() {
   $Prof = $this->Idp->listProfiles(1);
   if(! isset($this->Profile))
     $this->Profile = $Prof[0];
   $p_id = $this->Profile->identifier;
   $this->Args['profile'] = $p_id;
   $out = '';
   if (count($Prof) > 1) {
     $out .=  _("Select the user group").'<br>';
     $out .= '<select name="profile" onchange="submit_form(this)">';
     foreach ($Prof as $P) {
       $out .= '<option value="'.$P->identifier.'"';
       if($P->identifier == $p_id)
         $out .= ' selected';
       $out .= '>'.$P->name.'</option>';
     }
     $out .= '</select>';
   } else {
     $out .= $this->passArgument('profile');
   }
   return $out;
}



public function listDevices() {
   if(! isset($this->Profile))
      return '';
   $OS = $this->detectOS();
   $os = $OS['id'];
   $this->Args['device'] = $os;
   $profile_redirect = 0;
   $redirect_target = '';
   $device_redirects = '';
   $selected_os = 0;
   $unsupported_message = '<div id="unsupported_os">'._("Your operating system was not properly detected, is not supported yet or cannot be configured with settings provided by your institution")."</div><br>";
   
   $a = $this->profileAttributes($this->Profile->identifier);
   $thedevices = $a['devices'];
   $message = '';
   if(! $os)
     $message = $unsupported_message;
   $out = _("Choose an installer to download").'<br>';
   $out .= '<select name="device" onchange="set_device(this)">';
   $i= 0;
   foreach ($thedevices as $D) {
      if((isset($D['options']) && isset($D['options']['hidden']) &&  $D['options']['hidden']) || $D['status'] )
         continue; 
      if(! $os)
         $os = $D['id'];
      $disp = $D['display'];
      if($D['id'] === '0') {
        $profile_redirect = 1;
        $redirect_target = $D['redirect'];
      }
      $out .= '<option value="'.$D['id'].'"';
      if($D['id'] == $os) {
        $out .= ' selected';
        $selected_os = 1;
        if($D['redirect']) {
           $redirect_target = $D['redirect'];
        }
      }
      $out .= '>'.$disp.'</option>';
      $device_redirects .= 'redirects['.$i.'] = '.( $D['redirect'] ? 1 : 0 ).';';
      $i++;
   }
   $out .= '</select>';
   if( $selected_os == 0)
      $message = $unsupported_message;
   $out = $message . $out;
   if($profile_redirect)
      $out = '';
   if($redirect_target) {
      $device_redirects .= 'is_redirected = 1;';
      $out .= _("Your local administrator has specified a redirect to a local support page.").'<br>'. _("When you click <b>CONTINUE</b> this support page will be opened.");
      $action = 'window.location.href=\''.$redirect_target.'\'; return(false);';
   $out .= "<p><button id='devices' name='devices' style='width:100%;' onclick=\"".$action.'">'._("CONTINUE to local support page")."</button>";
   } else {
      $device_redirects .= 'is_redirected = 0;';
      $action = 'submit_form(this)';
   $out .= "<p><button id='devices' name='devices' style='width:100%;' onclick=\"".$action.'">'._("Do you have an account at this institution?").'<br>'._("If so and if the other settings above are OK then click here to download...")."</button>";
   }
   $out .= '<script type="text/javascript">'.$device_redirects.'</script>';
   return $out;
}

public function displayDeviceDownload() {
   $this->set_locale('devices');
   $a = $this->profileAttributes($this->Profile->identifier);
   $thedevices = $a['devices'];
   $this->set_locale("web_user");
   $out = '';
   if(isset($a['description']) && $a['description'])
     print '<div>'.$a['description'] . '</div>';
   if (isset($a['local_email']) && $a['local_email'])
     $out .= '<p>Email: <a href="mailto:' . $a['local_email'] . '">' . $a['local_email'] . '</a>';
   if (isset($a['local_url']) && $a['local_url'])
     $out .= '<p>WWW: <a href="' . $a['local_url'] . '">' . $a['local_url'] . '</a>';
   if (isset($a['local_phone']) && $a['local_phone'])
     $out .= '<p>Tel: <a href="' . $a['local_phone'] . '">' . $a['local_phone'] . '</a>';
   if( $out !== '') {
     print '<div class="user_info">';
     print _("If you encounter problems you should ask for help at your home institution");
     print $out;
     print "</div>\n";
   }
                   
   foreach ($thedevices as $D) {
      if(isset($D['options']) && isset($D['options']['hidden']) &&  $D['options']['hidden'])
          continue; 
      $disp = $D['display'];
      if($D['id'] === '0') {
          print _("Your local administrator has specified a redirect to a local support page.").' '. _("Click on the link below to continue.");
          print '<div style="width:100%; text-align:center"><a href ="'.$D['redirect'].'">'.$D['redirect'].'</a></div>';
          exit;
      }
      if($D['id'] === $this->Args['device']) 
          break;
   }
   $this->set_locale("web_user");

   $o = $this->generateInstaller($this->Args['device'], $this->Profile->identifier);
   if (!$o['link']) {
      print _("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.");
      return;
   }
   $extra_text = '';
   if(isset($D['message']) && $D['message']) 
      $extra_text = $D['message']; 
   if(isset($D['device_customtext']) && $D['device_customtext']) {
      if($extra_text)
           $extra_text .= '<p>';
      $extra_text = $D['device_customtext']; 
    }
   if(isset($D['eap_customtext']) && $D['eap_customtext']) {
      if($extra_text)
          $extra_text .= '<p>';
      $extra_text .= $D['eap_customtext']; 
    }
   if($extra_text)
      $extra_text .= '<p>';
      print $extra_text;

      $download_link = 'user/API.php?action=downloadInstaller&generatedfor=user&lang='.CAT::$lang_index.'&id='.$o['device'].'&profile='.$o['profile'];

      print '<p><button id="download_button" onclick="window.location.href=\'' . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/'). '/' . $download_link . '\'; return(false)"><div>' . _("Download installer for") . '<br><span style="color:yellow; font-weight: bold">' . $D['display']. '</span></div></button>';

      print '<p><button id="start_over" name="start_over" onclick="submit_form(this)">'._("Start over").'</button>';
   print $this->passArgument('country');
   print $this->passArgument('idp');
   print $this->passArgument('profile');
   print $this->passArgument('device');
}



public function langSelection() {
   $out = _("View this page in")." ";
   $out .= '<select onchange="submit_form(this)" name="lang">';
   foreach (Config::$LANGUAGES as $lng => $value) {
       $out .= '<option value="'.$lng.'"';
       if ($lng === CAT::$lang_index)
          $out .= ' selected';
       $out .= '>'. $value['display'] . '</option>';
   }
   $out .= '</select>';
   return $out;
}

/**
  * displays the navigation bar showing the current location of the page
  */

public function yourChoice() {
  $out = '';
   $c = strtoupper($this->Country->identifier);
   $name = isset(Federation::$FederationList[$c]) ? Federation::$FederationList[$c] : $c;
   $name = preg_replace('/ +/','&nbsp;',$name);
   $out .= "$name; ";
   $name = $this->Idp->name;
   $name = preg_replace('/ +/','&nbsp;',$name);
   $out .= "$name";
   if($this->profile_count > 1) {
     $name = '; '.$this->Profile->name;
     $name = preg_replace('/ +/','&nbsp;',$name);
     $out .= "$name";
   }
  return $out;
}

/**
  * returns the navigation link to a given GUI page
  * @param int $new_page new page number
  * @param string $text link text
  * @return string
  */

public function passArgument($arg_name) {
   return '<input type="hidden" name="'.$arg_name.'" value="'.$this->Args[$arg_name].'">';
}

public $Country;
public $Idp;
public $Profile;
public $Args;
public $profile_count;

}

$Gui = new SimpleGUI();

debug(4,"\n----------------------------------SIMPLE.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo CAT::$lang_index?>">
    <head lang="<?php echo CAT::$lang_index?>"> 
        <title><?php echo Config::$APPEARANCE['productname_long'];?></title>
<link href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/') ?>/resources/css/cat-basic.css.php" type= "text/css" rel="stylesheet" />
        <meta charset="utf-8" /> 
    <script type="text/javascript">
    var redirects = new Array();
    var is_redirected = 0;
    function set_device(s) {
       if(redirects[s.selectedIndex] || is_redirected){
         my_form.submit();
       } else {
         return;
       }
    }
    function submit_form(id) {
      if(id.name == 'country') 
          document.getElementById('reset_dev').value = 1;
      if(id.name == 'profile') 
          document.getElementById('reset_dev').value = 1;
      if(id.name == 'idp') 
          document.getElementById('reset_dev').value = 1;
      if(id.name == 'start_over') 
          document.getElementById('devices_h').value = 0;
      if(id.name == 'devices') 
          document.getElementById('devices_h').value = 1;
         my_form.submit();
    }
    </script>
    </head>
    <body style="">

<?php debug(4,"SERVER\n"); debug(4,$_SERVER) ?>
<?php    print '<div id="motd">'.( isset(Config::$APPEARANCE['MOTD']) ? Config::$APPEARANCE['MOTD'] : '&nbsp' ).'</div>'; ?>
<form name="my_form" method="POST" action="<?php echo $_SERVER['SCRIPT_NAME']?>" accept-charset='UTF-8'>
        <img src="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/images/consortium_logo.png" style="width: 20%; padding-right:20px; padding-top:0px; float:right" alt="logo" />
         <?php
/*
if($Gui->page == 0) {
       print "<h1 style='color:red'>"._("no matching data found")."</h1>";
       $Gui->page = 2;
}
*/
        print '<h1><a href="' . $_SERVER['SCRIPT_NAME'] . '?lang=' . CAT::$lang_index . '">' . Config::$APPEARANCE['productname'] . '</a></h1>';
        print $Gui->langSelection();
        if(! isset($_REQUEST['devices_h']) || $_REQUEST['devices_h'] == 0 || isset($_REQUEST['start_over'])) {
        print "<p>\n";
          print $Gui->listCountries();
          if($Gui->page == 2 && ! isset($FED[strtoupper($Gui->Country->identifier)]))
             $Gui->page = 1;
          print "<p>".$Gui->listIdPs();
          print "<p>".$Gui->listProfiles();
          print "<p>".$Gui->listDevices();
          print '<input type="hidden" name="devices_h" id="devices_h" value="0">';

        } else {
          if($Gui->page != 3) {
             print "Arguments missmatch error.";
             exit;
          }
        print '<div id="user_choice">'.$Gui->yourChoice().'</div><p>';
          $Gui->displayDeviceDownload();
        print '<input type="hidden" name="devices_h" id="devices_h" value="1">';
        }
     ?>
      <input type="hidden" name="reset_dev" id="reset_dev" value="0">
        </form>
        <div class='footer'><hr />
<?php
print('<a href="tou.php">'._("Terms of use")."</a><p>");
// this variable gets set during "make distribution" only
$RELEASE = "THERELEASE";
echo "".Config::$APPEARANCE['productname']." - ";
if ($RELEASE != "THERELEASE") 
    echo sprintf(_("Release %s"), $RELEASE);
else
    echo _("Unreleased SVN Revision");
echo " &copy; 2011-15 G&Eacute;ANT Ltd. on behalf of the GN3 and GN3plus consortia and others <a href='copyright.php'>Full Copyright and Licenses</a></div>";?>
</body>
</html>
