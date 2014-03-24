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

debug(4,"basic.php\n");
debug(4,$_POST);

/**
  * SimpleGUI defines extesions of the GUI class used only in the simpem interface
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
$this->page =  1;
$this->set_locale('core');

    if(isset($_REQUEST['idp']) && $_REQUEST['idp']) {
       $this->page =  3;
       try {
         $this->Idp = new IdP($_REQUEST['idp']);
       }
       catch (Exception $fail) {
         $this->page =  0;
       }
       if ( $this->page > 0 ) {
          $this->Args['idp'] = $_REQUEST['idp'];
          $this->Country = new Federation($this->Idp->federation);
          $this->Args['country'] = $this->Country->identifier;
          $this->profile_count = $this->Idp->profileCount();
          if(isset($_REQUEST['profile'])) {
             $this->page =  4;
             try {
               $this->Profile = new Profile($_REQUEST['profile']);
             }
             catch (Exception $fail) {
               $this->page =  0;
             }
            $this->Args['profile'] = $_REQUEST['profile'];
          }
          if(isset($_REQUEST['device'])) {
             $this->page =  4;
             $this->Args['device'] = $_REQUEST['device'];
             if(isset($_REQUEST['page']))
               $this->page = $_REQUEST['page'];
          }
      }
    } elseif(isset($_REQUEST['country']) && $_REQUEST['country']) {
         $this->Country = new Federation($_REQUEST['country']);
         $this->page =  2;
         $this->Args['country'] = $_REQUEST['country'];
         $this->Location = $this->locateUser();
    }
    if ($this->page <= 1) {
         $L = $this->locateUser();
         if( $L['status'] == 'ok' ) {
           $cn = strtoupper($L['country']);
           $this->Country = new Federation($cn);
           $this->Location = $L;
           if($this->page == 1)
             $this->page =  2;
           $this->Args['country'] = $cn;
         }
    }
   $this->Args['lang'] = $this->lang_index;
   $this->set_locale("web_user");
}

public function displayDeviceStatus($s) {
    if ($s > 0)
        return(_("not available with settings provided by your institution"));
    return('');
}

public function langSelection() {
   $out = _("View this page in")." ";
   foreach (Config::$LANGUAGES as $lng => $value) {
       $out .= '<a href="' . $_SERVER['SCRIPT_NAME'] . "?lang=$lng";
            foreach ($this->Args as $a=>$v) 
              if($a !== 'lang')
                $out .= '&amp;'.$a."=$v";
       $out .= '">' . $value['display'] . "</a> ";
        }
   return $out;
}

public function passArgument($arg_name) {
   return '<input type="hidden" name="'.$arg_name.'" value="'.$this->Args[$arg_name].'">';
}

public function printDevices($arg, $text, $status = '', $redirected = 0) {
    if ($redirected)
        print '<li><a href="' . $redirected . '">' . $text . '</a> - ' . _("you will be redirected to a local support page") . "\n";
    else {
        if ($status)
            print "<li>$text -<span style='color:red'> $status</span>\n";
        else {
            print '<li><a href="' . $_SERVER['SCRIPT_NAME'] . '?page=5';
            foreach ($this->Args as $a=>$v) 
              print '&'.$a."=$v";
            print '&device='.$arg;
            print '">' . $text . "</a>\n";
     }
    }
}

/**
  * displays the navigation bar showing the current location of the page
  */

public function yourChoice() {
 $out = '';
 if($this->page > 1) {
   $out = _("Your choice").": ";
   $c = strtoupper($this->Country->identifier);
   $name = isset(Federation::$FederationList[$c]) ? Federation::$FederationList[$c] : $c;
   $out .= $this->changePage(2,$name);
 }
 if($this->page > 2)
  $out .= '; '.$this->changePage(3,$this->Idp->name);
 if($this->page > 3 && $this->profile_count > 1)
  $out .= '; '.$this->changePage(4,$this->Profile->name);
  return $out;
}

/**
  * returns the navigation link to a given GUI page
  * @param int $new_page new page number
  * @param string $text link text
  * @return string
  */
private function changePage($new_page,$text) {
    $out = '<a href="' . $_SERVER['SCRIPT_NAME'] . '?page=' . $new_page . '&country='.$this->Args['country'];
    if($new_page > 2)
      $out .= '&idp='.$this->Args['idp']; 
    if($new_page > 3)
      $out .= '&profile='.$this->Args['profile']; 
    $out .= '&lang=' . $this->lang_index . '">' . $text . "</a>";

    return $out;
}

public $Country;
public $Idp;
public $Profile;
public $Args;
public $profile_count;
public $Location;

}

$Gui = new SimpleGUI();

debug(4,"\n----------------------------------SIMPLE.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo "$Gui->lang_index"?>">
    <head lang="<?php echo "$Gui->lang_index"?>"> 
        <title><?php echo Config::$APPEARANCE['productname_long'];?></title>
<link media="only screen and (max-device-width: 480px)" href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/') ?>/resources/css/cat-basic.css" type= "text/css" rel="stylesheet" />
<link media="only screen and (min-device-width: 481px)" href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/css/cat-basic-large.css" type= "text/css" rel="stylesheet" />
        <meta charset="utf-8" /> 
    </head>
    <body style="">
<form method="POST" action="<?php echo $_SERVER['SCRIPT_NAME']?>" accept-charset='UTF-8'>
<?php //print '<pre>'; print_r($_REQUEST); print_r($Gui->Args); print '</pre>'; ?>
        <img src="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/images/consortium_logo.png" style="padding-right:20px; padding-top:20px; float:right" alt="logo" />
         <?php
            print '<div id="motd">'.( isset(Config::$APPEARANCE['MOTD']) ? Config::$APPEARANCE['MOTD'] : '&nbsp' ).'</div>'; 
if($Gui->page == 0) {
       print "<h1 style='color:red'>"._("no matching data found")."</h1>";
       $Gui->page = 2;
}
        print '<h1><a href="' . $_SERVER['SCRIPT_NAME'] . '?lang=' . $Gui->lang_index . '">' . Config::$APPEARANCE['productname'] . '</a></h1>';
if($Gui->page <= 2) {
$FED = $Gui->printCountryList(1);
// print coutry selection
print '<select name="country" onchange="this.form.submit()">';
print '<option value="0" selected>'._("Change country").'</option>';
foreach ($FED as $f => $F) {
     print '<option value="'.$f.'"';
     print '>'.$F.'</option>';
}
print '</select><p>';
}
        unset($returnarray);
        print $Gui->langSelection();
        if($Gui->page == 2 && ! isset($FED[strtoupper($Gui->Country->identifier)]))
           $Gui->page = 1;
        print '<h4>'.$Gui->yourChoice().'</h4>';
        $returnarray = array();
        switch ($Gui->page) {
            case 1:
     break;
            case 2:
$loc=$Gui->Location;
$Inst = $Gui->orderIdentityProviders($loc['geo'],$Gui->Country->identifier);
print '<select name="idp" onchange="this.form.submit()">';
print '<option value="0">'._("Select your institution").'</option>';

                foreach ($Inst as $I)
print '<option value="'.$I['id'].'">'.$I['title'].'</option>';
                   
print '</select>'; 
print $Gui->passArgument('lang');
                break;
            case 3:
                $Prof = $Gui->Idp->listProfiles(1);
                if (count($Prof) == 1) {
                    $Gui->profile = $Prof[0]->identifier;
                    $Gui->Profile = new Profile($Gui->profile);
                    $Gui->Args['profile'] = $Prof[0]->identifier;
                    $Gui->page ++;
                } else {
        print '<h3>'._("Select the user group").'</h3>';
print '<select name="profile" onchange="this.form.submit()">';
print '<option value="0">'._("Select the user group").'</option>';
                    foreach ($Prof as $P) {
print '<option value="'.$P->identifier.'">'.$P->name.'</option>';
//                        $returnarray[] = array($P->identifier, $profile_name);
}
print '</select>'.$Gui->passArgument('country');
print $Gui->passArgument('idp');
print $Gui->passArgument('lang');
                    break;
                }
            case 4:
                $profile_name = $Gui->Profile->name;

                $c = _("Continue");
                $Gui->set_locale('devices');
                $a = $Gui->profileAttributes($Gui->Profile->identifier);
                $thedevices = $a['devices'];
                $Gui->set_locale("web_user");
                $out = '';
                if(isset($a['description']) && $a['description'])
                  print '<div>'.$a['description'] . '</div>';
                   
                if(!isset($profile_redirect) || ! $profile_redirect) {
                $profile_redirect = 0;
                foreach ($thedevices as $D) {
                     if(isset($D['options']) && isset($D['options']['hidden']) &&  $D['options']['hidden'])
                        continue; 
                    $disp = $D['display'];
                    if($D['id'] === '0') {
                      $profile_redirect = 1;
                      $disp = $c;
                    }
                    $returnarray[] = array($D['id'], $disp, $Gui->displayDeviceStatus($D['status']), $D['redirect']);
}
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
                print '<h3>' . _("Choose an installer to download") . '</h3>';
                }
                break;
            case 5:
                $profile_name = $Gui->Profile->name;
                $Gui->set_locale('devices');
                $a = $Gui->profileAttributes($Gui->Profile->identifier);
                $thedevices = $a['devices'];
                foreach ($thedevices as $D) {
                  if($D['id'] === $Gui->Args['device']) {
                    break;
                   }
                }
                $Gui->set_locale("web_user");

                if (!$Gui->Profile->institution || $Gui->Profile->institution !== $Gui->Idp->identifier) {
                    print _("Profile does not match the institution");
                    return;
                }

                $o = $Gui->generateInstaller($Gui->Args['device'], $Gui->Profile->identifier);
                if (!$o['link']) {
                    print _("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.");
                    return;
                }
                $extra_text = '';
                if(isset($D['device_customtext']) && $D['device_customtext']) 
                  $extra_text = $D['device_customtext']; 
                if(isset($D['eap_customtext']) && $D['eap_customtext']) {
                  if($extra_text)
                    $extra_text .= '<p>';
                  $extra_text .= $D['eap_customtext']; 
                }
                if($extra_text)
                  $extra_text .= '<p>';
                print $extra_text;

                $download_link = 'user/API.php?action=downloadInstaller&generatedfor=user&lang='.$Gui->lang_index.'&id='.$o['device'].'&profile='.$o['profile'];

                print '<center><button style="width: 90%; height: 3em; font-size: 200%; cursor: pointer" onclick="window.location.href=\'' . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/'). '/' . $download_link . '\'; return(false)">' . _("Download installer for") . '<br><span style="color:green; font-weight: bold">' . $D['display']. '</span></button></center>';
                $out = '';
                if (isset($a['local_email']) && $a['local_email'])
                    $out .= '<p>Email: <a href="mailto:' . $a['local_email'] . '">' . $a['local_email'] . '</a>';
                if (isset($a['local_url']) && $a['local_url'])
                    $out .= '<p>WWW: <a href="' . $a['local_url'] . '">' . $a['local_url'] . '</a>';
                if (isset($a['local_phone']) && $a['local_phone'])
                    $out .= '<p>Tel: <a href="' . $a['local_phone'] . '">' . $a['local_phone'] . '</a>';
                if( $out !== '') {
                print '<br><div class="user_info">';
                print _("If you encounter problems you should ask for help at your home institution");
                print $out;
                print "</div>\n";
                 }
                break;
            default:
                break;
        }
        $Gui->page++;

        print '<ul>';
        if(is_array($returnarray)) {
        foreach ($returnarray as $A) {
            $a = isset($A[2]) ? $A[2] : '';
            $Gui->printDevices($A[0], $A[1], $a, $A[3]);
        }
       }
        ?>
    </ul>
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
echo " &copy; 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia</div>";?>
</body>
</html>
