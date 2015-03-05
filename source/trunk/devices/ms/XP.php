<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file creates MS Windows XP installers
 * It supports TTLS only (so far, but with hooks open for others)
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

/**
 * necessary includes
 */
require_once('DeviceConfig.php');
require_once('WindowsCommon.php');

/**
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package ModuleWriting
 */
class Device_XP extends WindowsCommon {
    final public function __construct() {
      $this->supportedEapMethods  = array(EAP::$TTLS_PAP);
//      $this->supportedEapMethods  = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP);
      debug(4,"This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
    }

  public function writeInstaller() {
      $dom = textdomain(NULL);
      textdomain("devices");
   // create certificate files and save their names in $CA_files arrary
     $CA_files = $this->saveCertificateFiles('der');

     $SSIDs = $this->attributes['internal:SSID'];
     $this->prepareInstallerLang();
     $set_wired = isset($this->attributes['media:wired'][0]) && $this->attributes['media:wired'][0] == 'on' ? 1 : 0;

     if ($this->selected_eap == EAP::$TLS || $this->selected_eap == EAP::$PEAP_MSCHAP2) {
       $WindowsProfile = array();
       $i = 0;
       foreach (array_keys($SSIDs) as $ssid) {
          $WindowsProfile[$i] = $this->writeWLANprofile ($ssid,$ssid,'WPA2','AES',$this->attributes,$i);
          $i++;
       }
     } elseif($this->selected_eap == EAP::$TTLS_PAP) {
       if($set_wired) {
         $eap_config = $this->prepareEapConfig($this->attributes);
         $this->writeLANprofile($eap_config);
       }
       $WindowsProfile = $this->writeSW2profile($this->attributes,$CA_files);
     } else {
       error("  this EAP type is not handled yet");
       return;
     }
    debug(4,"WindowsProfile"); debug(4,$WindowsProfile);
    
    $this->writeProfilesNSH($WindowsProfile, $CA_files);
    $this->copyFiles($this->selected_eap);
    if(isset($this->attributes['internal:logo_file']))
       $this->combineLogo($this->attributes['internal:logo_file']);
    $this->writeMainNSH($this->selected_eap,$this->attributes);
    $this->compileNSIS();
    $installer_path = $this->signInstaller($this->attributes); 

    textdomain($dom);
    return($installer_path);  
  }

  public function writeDeviceInfo() {
    $ssid_ct=count($this->attributes['internal:SSID']);
    $out = "<p>";
   $out .= sprintf(_("%s installer will be in the form of an EXE file. It will configure %s on your device, by creating wireless network profiles.<p>When you click the download button, the installer will be saved by your browser. Copy it to the machine you want to configure and execute."),Config::$CONSORTIUM['name'],Config::$CONSORTIUM['name']);
   $out .= "<p>";
    if($ssid_ct > 1) {
        if($ssid_ct > 2) {
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to the following networks:"),Config::$CONSORTIUM['ssid'])." ";
        } else
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to:"),Config::$CONSORTIUM['ssid'])." ";
        $i = 0;
        foreach (array_keys($this->attributes['internal:SSID']) as $ssid) {
           if($ssid !== Config::$CONSORTIUM['ssid']) {
             if($i > 0)
           $out .= ", ";
         $i++;
         $out .= "<strong>$ssid</strong>";
       }
    }
    $out .= "<p>";
    }

   $out .= _("In order to connect to the network you will need an account from your home institution. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
   $out .= "<p>";
   $out .= _("The installer will also install additional software - SecureW2 EAP Suite (GNU General Public License).<p>You will be requested to enter your account credentials into a pop box during the installation. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
    return $out;
  }

// $servers is an array of allowed server names (regular expressions allowed)
// $ca is an array of allowed CA fingerprints

/**
 * produce PEAP and TLS configuration files for Windows XP
 */
  private function writeWLANprofile($wlan_profile_name,$ssid,$auth,$encryption,$attr,$i) {
    $eap = $this->selected_eap;
    if ($eap != EAP::$TLS && $eap != EAP::$PEAP_MSCHAP2) {
      debug(2,"this method only allows TLS or PEAP");
      error("this method only allows TLS or PEAP");
     return;
    }
   $use_anon = $attr['internal:use_anon_outer'] [0];
   $servers = implode(';',$attr['eap:server_name']);
   $ca_array = $attr['internal:CAs'][0];

debug(2,"Installer has been written into directory $this->FPATH\n");
debug(4,"WLAN_Profile:$wlan_profile_name:$encryption\n");
return("\"$wlan_profile_name\" \"$encryption\"");
}


private function writeLANprofile($eap_config) {
$profile_file_contents = '<?xml version="1.0"?>
<LANProfile xmlns="http://www.microsoft.com/networking/LAN/profile/v1">
<MSM>
<security>
<OneXEnforced>false</OneXEnforced>
<OneXEnabled>true</OneXEnabled>
<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
';
$closing = '
</OneX>
</security>
</MSM>
</LANProfile>
';
$xml_f_name = "lan_prof.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eap_config['xp']. $closing) ;
fclose($xml_f);
}


private function prepareEapConfig($attr) {
  $profile_file_contents = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig"><EapMethod><Type xmlns="http://www.microsoft.com/provisioning/EapCommon">21</Type><VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId><VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType><AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</AuthorId></EapMethod><ConfigBlob>440045004600410055004C0054000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000</ConfigBlob></EapHostConfig></EAPConfig>
';
$return_array = array();
$return_array['xp'] = $profile_file_contents;
return $return_array;
}

private function glueServerNames($server_list) {
//print_r($server_list);
 $A0 =  array_reverse(explode('.',array_shift($server_list)));
 $B = $A0;
 if($server_list) {
   foreach($server_list as $a) {
   $A= array_reverse(explode('.',$a));
   $B = array_intersect_assoc($A0,$A);
   $A0 = $B;
   }
  }
  return(implode('.',array_reverse($B)));
}

/**
 * produce SecureW2 configuration for Windows XP
 */

  private function writeSW2profile($attr,$ca_array) {
// global section
   $sw2_server = $this->glueServerNames($attr['eap:server_name']);
   $SSIDs = $attr['internal:SSID'];
   $use_anon = $attr['internal:use_anon_outer'] [0];
   if ($use_anon)
     $outer_id = $attr['internal:anon_local_value'][0].'@'.$attr['internal:realm'][0];

$P = array();

$profile_file_contents = '[Version]
Signature = "$Windows NT$"
Provider = "SecureW2"
Config = 7

[WZCSVC]
Enable = AUTO
Restart = TRUE

[DOT3SVC]
Enable = AUTO
Restart = TRUE

[Certificates]
';
$i = 1;
if($ca_array)
foreach ($ca_array as $CA) {
  $profile_file_contents .= "Certificate.$i = ".$CA['file']."\n";
     $i++;
}
// profiles section
$j = 1;
debug(4,"SW2-HERE\n");
foreach ($SSIDs as $ssid => $enc) {
$profile_file_contents .= '
[SSID.'.$j.']
Name = "'.$ssid.'"
SSID = "'.$ssid.'"
Profile = "DEFAULT"
AuthenticationMode = "WPA2"
EncryptionType = "AES"
';
$j++;
$P[] = '"'.$ssid.'" "AES"';
}

// user section
$profile_file_contents .= '
[Profile.1]
Name = "DEFAULT"
Description = "'._("Login credentials").':"';

$profile_file_contents0 = $profile_file_contents;
$profile_file_contents = '';

$user_interaction1 = '
UserName = PROMPTUSER
PromptUserForCredentials = FALSE';

$user_interaction2 = '
PromptUserForCredentials = TRUE';

$profile_file_contents = '
UseAnonymousOuterIdentity = FALSE;
UseEmptyOuterIdentity = FALSE
';
if($use_anon) {
$profile_file_contents .= 'UseAlternateOuterIdentity = TRUE
UseAlternateOuterIdentity = TRUE
AlternateOuterIdentity = '.$outer_id.'
';
} else {
$profile_file_contents .= 'UseAlternateOuterIdentity = FALSE
UseAlternateOuterIdentity = FALSE
';
}

$profile_file_contents .= 'VerifyServerName = TRUE
ServerName = "'.$sw2_server.'"
VerifyServerCertificate = TRUE
';
$i = 0;
foreach ($ca_array as $CA) {
    if($CA['root']) {
     $profile_file_contents .= "TrustedRootCA.$i = ".$CA['sha1']."\n";
     $i++;
 }
}


$p = $profile_file_contents0 . $user_interaction1 . $profile_file_contents;
$f_name = "SecureW2.INF";
$sw2_f = fopen($f_name,'w');
fwrite($sw2_f,$p);
fclose($sw2_f);

$p = $profile_file_contents0 . $user_interaction2 . $profile_file_contents;
$f_name = "SecureW2S.INF";
$sw2_f = fopen($f_name,'w');
fwrite($sw2_f,$p);
fclose($sw2_f);

debug(2,"Installer has been written into directory $this->FPATH\n");
return($P);
}


private function writeMainNSH($eap,$attr) {
debug(4,"writeMainNSH"); debug(4,$attr);
$fcontents = '';
// $fcontents = "!define ALLOW_VISTA\n";
$fcontents = "!define XP\n";
if($eap["OUTER"] == PEAP) 
  $eap_str = 'PEAP';
if($eap["OUTER"] == TLS) 
  $eap_str = 'TLS';
if($eap["OUTER"] == TTLS) 
  $eap_str = 'TTLS';

$fcontents .= '!define '.$eap_str;
if($attr['internal:profile_count'][0] > 1)
$fcontents .= "\n".'!define USER_GROUP "'.str_replace('"','$\\"',$attr['profile:name'][0]).'"';
$fcontents .= '
Caption "'. $this->translateString(sprintf(sprint_nsi(_("%s installer for %s")),Config::$CONSORTIUM['name'],$attr['general:instname'][0]), $this->code_page).'"
!define APPLICATION "'. $this->translateString(sprintf(sprint_nsi(_("%s installer for %s")),Config::$CONSORTIUM['name'],$attr['general:instname'][0]), $this->code_page).'"
!define VERSION "1.00"
!define INSTALLER_NAME "installer.exe"
!define LANG "'.$this->lang.'"
';
$fcontents .= $this->msInfoFile($attr);

$fcontents .= ';--------------------------------
!define ORGANISATION "'.$this->translateString($attr['general:instname'][0], $this->code_page).'"
!define SUPPORT "'. ((isset($attr['support:email'][0]) && $attr['support:email'][0] ) ? $attr['support:email'][0] : $this->translateString($this->support_email_substitute , $this->code_page)) .'"
!define URL "'. ((isset($attr['support:url'][0]) && $attr['support:url'][0] ) ? $attr['support:url'][0] : $this->translateString($this->support_url_substitute, $this->code_page)) .'"

!ifdef TLS
';
//TODO this must be changed with a new option
$fcontents .= '!define TLS_CERT_STRING "certyfikaty.umk.pl"
!define TLS_FILE_NAME "cert*.p12"
!endif
';


if(isset($this->attributes['media:wired'][0]) && $attr['media:wired'][0] == 'on')
  $fcontents .= '!define WIRED
';
$f = fopen('main.nsh','w');
fwrite($f, $fcontents);
fclose($f);

}

private function writeProfilesNSH($P,$ca_array) {
debug(4,"writeProfilesNSH");
debug(4,$P);
$fcontents = '';
  foreach($P as $p) 
    $fcontents .= "!insertmacro define_wlan_profile $p\n";
$f = fopen('profiles.nsh','w');
fwrite($f, $fcontents);
fclose($f);

$fcontents = '';

foreach ($ca_array as $CA) {
      $store = $CA['root'] ? "root" : "ca";
      $fcontents .= '!insertmacro install_ca_cert "'.$CA['file']."\"\n";
//      $fcontents .= '!insertmacro install_ca_cert "'.$CA['file'].'" "'.$CA['sha1'].'" "'.$store."\"\n";
    }
$f = fopen('certs.nsh','w');
fwrite($f, $fcontents);
fclose($f);
}

private function copyFiles ($eap) {
debug(4,"copyFiles start\n");
   $result;
   $result = $this->copyFile('wlan_test.exe');
   $result = $this->copyFile('cat_bg.bmp');
   $result = $result && $this->copyFile('cat32.ico');
   $result = $result && $this->copyFile('cat_150.bmp');
   $this->translateFile('common.inc','common.nsh',$this->code_page);
   if($eap["OUTER"] == TTLS)  {
     $this->translateFile('ttls.inc','cat.NSI',$this->code_page);
     $result = $this->copyFile('sw2_license.txt');
     $result = $result && $this->copyFile('SecureW2_EAP_Suite_113.zip');
    } else {
     $this->translateFile('peap_tls.inc','cat.NSI',$this->code_page);
     $result = 1;
    }
debug(4,"copyFiles end\n");
   return($result);
}

}

?>
