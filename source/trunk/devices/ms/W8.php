<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file creates MS Windows 8 installers
 * It supports EAP-TLS, TTLS, PEAP and EAP-pwd
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
class Device_W8 extends WindowsCommon {
    final public function __construct() {
      $this->supportedEapMethods = [EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_MSCHAP2, EAP::$PWD];
#      $this->supportedEapMethods = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$PWD);
      debug(4,"This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
      $this->specialities['anon_id'][serialize(EAP::$PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
    }

  public function writeInstaller() {
      $dom = textdomain(NULL);
      textdomain("devices");
   // create certificate files and save their names in $CA_files arrary
     $CA_files = $this->saveCertificateFiles('der');

     $SSIDs = $this->attributes['internal:SSID'];
     $delSSIDs = $this->attributes['internal:remove_SSID'];
     $this->prepareInstallerLang();
     $set_wired = isset($this->attributes['media:wired'][0]) && $this->attributes['media:wired'][0] == 'on' ? 1 : 0;
//   create a list of profiles to be deleted after installation
     $delProfiles = [];
     foreach ($delSSIDs as $ssid => $cipher) {
         if($cipher == 'DEL')
          $delProfiles[] = $ssid;
         if($cipher == 'TKIP')
          $delProfiles[] = $ssid.' (TKIP)';
     }


     if ($this->selected_eap == EAP::$TLS || $this->selected_eap == EAP::$PEAP_MSCHAP2 || $this->selected_eap ==  EAP::$TTLS_PAP || $this->selected_eap == EAP::$TTLS_MSCHAP2 || $this->selected_eap == EAP::$PWD) {
       $WindowsProfile = [];
       $eap_config = $this->prepareEapConfig($this->attributes);
       $i = 0;
       foreach ($SSIDs as $ssid => $cipher) {
          if($cipher == 'TKIP') {
             $WindowsProfile[$i] = $this->writeWLANprofile ($ssid.' (TKIP)',$ssid,'WPA','TKIP',$eap_config,$i);
             $i++;
          }
          $WindowsProfile[$i] = $this->writeWLANprofile ($ssid,$ssid,'WPA2','AES',$eap_config,$i);
          $i++;
       }
       if($set_wired) {
         $this->writeLANprofile($eap_config);
       }
     } else {
       error("  this EAP type is not handled yet");
       return;
     }
    debug(4,"WindowsProfile"); debug(4,$WindowsProfile);
    
    $this->writeProfilesNSH($WindowsProfile, $CA_files,$set_wired);
    $this->writeAdditionalDeletes($delProfiles);
    if(isset($additional_deletes) && count($additional_deletes))
       $this->writeAdditionalDeletes($additional_deletes);
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
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to the following networks:"),implode(', ',Config::$CONSORTIUM['ssid']))." ";
        } else
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to:"),implode(', ',Config::$CONSORTIUM['ssid']))." ";
        $i = 0;
        foreach ($this->attributes['internal:SSID'] as $ssid=>$v) {
           if(! in_array($ssid, Config::$CONSORTIUM['ssid'])) {
             if($i > 0)
           $out .= ", ";
         $i++;
         $out .= "<strong>$ssid</strong>";
       }
    }
    $out .= "<p>";
    }

if($this->eap == EAP::$TLS)
   $out .= _("In order to connect to the network you will need an a personal certificate in the form of a p12 file. You should obtain this certificate from your home institution. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process.");
else {
   $out .= _("In order to connect to the network you will need an account from your home institution. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
   $out .= "<p>";
   $out .= _("When you are connecting to the network for the first time, Windows will pop up a login box, where you should enter your user name and password. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
        if($ssid_ct > 1) {
             $out .= "<p>";
             $out .= _("You will be required to enter the same credentials for each of the configured notworks:")." ";
             $i = 0;
            foreach ($this->attributes['internal:SSID'] as $ssid=>$v) {
                 if($i > 0)
                   $out .= ", ";
                 $i++;
                 $out .= "<strong>$ssid</strong>";
            }
    }


}
    return $out;
  }


private function prepareEapConfig($attr) {
   $eap = $this->selected_eap;
   $w8_ext = '';
   $use_anon = $attr['internal:use_anon_outer'] [0];
   if ($use_anon) {
     $outer_user = $attr['internal:anon_local_value'][0];
     $outer_id = $outer_user.'@'.$attr['internal:realm'][0];
   }
//   $servers = preg_quote(implode(';',$attr['eap:server_name']));
   $servers = implode(';',$attr['eap:server_name']);
   
   $ca_array = $attr['internal:CAs'][0];


$profile_file_contents = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapMethod>
';

$profile_file_contents .= '<Type xmlns="http://www.microsoft.com/provisioning/EapCommon">'.
    $this->selected_eap["OUTER"].'</Type>
<VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
<VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
';
if( $eap == EAP::$TLS) {
$profile_file_contents .= '<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</AuthorId>
</EapMethod>
';
  $profile_file_contents .= '

<Config xmlns:baseEap="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1" 
  xmlns:eapTls="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV1">
<baseEap:Eap>
<baseEap:Type>13</baseEap:Type> 
<eapTls:EapType>
<eapTls:CredentialsSource>
<eapTls:CertificateStore />
</eapTls:CredentialsSource>
<eapTls:ServerValidation>
<eapTls:DisableUserPromptForServerValidation>true</eapTls:DisableUserPromptForServerValidation>
<eapTls:ServerNames>'.$servers.'</eapTls:ServerNames>';
if($ca_array) {
foreach ($ca_array as $CA)
    if($CA['root'])
       $profile_file_contents .= "<eapTls:TrustedRootCA>".$CA['sha1']."</eapTls:TrustedRootCA>\n";
}
$profile_file_contents .= '</eapTls:ServerValidation>
';
if(isset($attr['eap-specific:tls_use_other_id']) && $attr['eap-specific:tls_use_other_id'][0] == 'on')
   $profile_file_contents .= '<eapTls:DifferentUsername>true</eapTls:DifferentUsername>';
else
   $profile_file_contents .= '<eapTls:DifferentUsername>false</eapTls:DifferentUsername>';
$profile_file_contents .= '
</eapTls:EapType>
</baseEap:Eap>
</Config>
';
} elseif ( $eap == EAP::$PEAP_MSCHAP2) {
if(isset($attr['eap:enable_nea']) && $attr['eap:enable_nea'][0] == 'on')
   $nea = 'true';
else
   $nea = 'false';
$profile_file_contents .= '<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</AuthorId>
</EapMethod>
';
$w8_ext = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
<ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>'.$servers.'</ServerNames>';
if($ca_array) {
foreach ($ca_array as $CA)
    if($CA['root'])
        $w8_ext .= "<TrustedRootCA>".$CA['sha1']."</TrustedRootCA>\n";
}
$w8_ext .= '</ServerValidation>
<FastReconnect>true</FastReconnect> 
<InnerEapOptional>false</InnerEapOptional> 
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>26</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1">
<UseWinLogonCredentials>false</UseWinLogonCredentials> 
</EapType>
</Eap>
<EnableQuarantineChecks>'.$nea.'</EnableQuarantineChecks>
<RequireCryptoBinding>false</RequireCryptoBinding>
';
if($use_anon == 1) {
$w8_ext .='<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
';
if(isset($outer_user) && $outer_user) 
$w8_ext .='<AnonymousUserName>'.$outer_user.'</AnonymousUserName>
';
else
$w8_ext .='<AnonymousUserName/>
';
$w8_ext .='</IdentityPrivacy>
</PeapExtensions>
';
}
$w8_ext .='</EapType>
</Eap>
</Config>
';
} elseif ( $eap == EAP::$TTLS_PAP || $eap == EAP::$TTLS_MSCHAP2) {
$profile_file_contents .= '<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">311</AuthorId>
</EapMethod>
';
$w8_ext = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapTtls xmlns="http://www.microsoft.com/provisioning/EapTtlsConnectionPropertiesV1">
<ServerValidation>
<ServerNames>'.$servers.'</ServerNames> ';
if($ca_array) {
foreach ($ca_array as $CA)
    if($CA['root'])
        $w8_ext .= "<TrustedRootCAHash>".chunk_split($CA['sha1'],2,' ')."</TrustedRootCAHash>\n";
}
$w8_ext .='<DisablePrompt>true</DisablePrompt> 
</ServerValidation>
<Phase2Authentication>
';
if ( $eap == EAP::$TTLS_PAP) {
   $w8_ext .='<PAPAuthentication /> ';
}
if ( $eap == EAP::$TTLS_MSCHAP2)  {
   $w8_ext .='<MSCHAPv2Authentication>
<UseWinlogonCredentials>false</UseWinlogonCredentials>
</MSCHAPv2Authentication>
';
}
$w8_ext .= '</Phase2Authentication>
<Phase1Identity>
';
if($use_anon == 1) {
  $w8_ext .= '<IdentityPrivacy>true</IdentityPrivacy> 
';
  if(isset($outer_id) && $outer_id) 
    $w8_ext .='<AnonymousIdentity>'.$outer_id.'</AnonymousIdentity>
';
  else
    $w8_ext .='<AnonymousIdentity/>
';
} else {
  $w8_ext .= '<IdentityPrivacy>false</IdentityPrivacy>
';
}
$w8_ext .='</Phase1Identity>
</EapTtls>
</Config>
';
} elseif ( $eap == EAP::$PWD) {
$profile_file_contents .= '<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</AuthorId>
</EapMethod>
';
   $profile_file_contents .= '<ConfigBlob></ConfigBlob>';
}

$profile_file_contents_end = '</EapHostConfig></EAPConfig>';
$return_array = [];
$return_array['w8'] = $profile_file_contents.$w8_ext.$profile_file_contents_end;
return $return_array;
}

// $auth can be one of: "WPA", "WPA2"
// $encryption can be one of: "TKIP", "AES"
// $servers is an array of allowed server names (regular expressions allowed)
// $ca is an array of allowed CA fingerprints

/**
 * produce PEAP, TLS and TTLS configuration files for Windows 8
 */
  private function writeWLANprofile($wlan_profile_name,$ssid,$auth,$encryption,$eap_config,$i) {
$profile_file_contents = '<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
<name>'.$wlan_profile_name.'</name>
<SSIDConfig>
<SSID>
<name>'.$ssid.'</name>
</SSID>
<nonBroadcast>true</nonBroadcast>
</SSIDConfig>
<connectionType>ESS</connectionType>
<connectionMode>auto</connectionMode>
<autoSwitch>false</autoSwitch>
<MSM>
<security>
<authEncryption>
<authentication>'.$auth.'</authentication>
<encryption>'.$encryption.'</encryption>
<useOneX>true</useOneX>
</authEncryption>
';
if($auth == 'WPA2') 
$profile_file_contents .= '<PMKCacheMode>enabled</PMKCacheMode> 
<PMKCacheTTL>720</PMKCacheTTL> 
<PMKCacheSize>128</PMKCacheSize> 
<preAuthMode>disabled</preAuthMode> 
';
$profile_file_contents .= '<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
<cacheUserData>true</cacheUserData>
<authMode>user</authMode>
';

$closing = '
</OneX>
</security>
</MSM>
</WLANProfile>
';

if(! is_dir('w8'))
  mkdir('w8');
$xml_f_name = "w8/wlan_prof-$i.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eap_config['w8']. $closing) ;
fclose($xml_f);
debug(2,"Installer has been written into directory $this->FPATH\n");
debug(4,"WWWWLAN_Profile:$wlan_profile_name:$encryption\n");
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

if(! is_dir('w8'))
  mkdir('w8');
$xml_f_name = "w8/lan_prof.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eap_config['w8']. $closing) ;
fclose($xml_f);
debug(2,"Installer has been written into directory $this->FPATH\n");
}



private function writeMainNSH($eap,$attr) {
debug(4,"writeMainNSH"); debug(4,$attr);
$fcontents = "!define W8\n";

$EAP_OPTS = [
PEAP=>['str'=>'PEAP','exec'=>'user'],
TLS=>['str'=>'TLS','exec'=>'user'],
TTLS=>['str'=>'TTLS','exec'=>'user'],
PWD=>['str'=>'PWD','exec'=>'user'],
];
 
// Uncomment the line below if you want this module to run under XP (only displaying a warning)
// $fcontents .= "!define ALLOW_XP\n";
// Uncomment the line below if you want this module to produce debugging messages on the client
// $fcontents .= "!define DEBUG_CAT\n";
$exec_level = $EAP_OPTS[$eap["OUTER"]]['exec'];
$eap_str = $EAP_OPTS[$eap["OUTER"]]['str'];

$fcontents .= '!define '.$eap_str;
$fcontents .= "\n".'!define EXECLEVEL "'.$exec_level.'"';

if($attr['internal:profile_count'][0] > 1)
$fcontents .= "\n".'!define USER_GROUP "'.$this->translateString(str_replace('"','$\\"',$attr['profile:name'][0]), $this->code_page).'"';
$fcontents .= '
Caption "'. $this->translateString(sprintf(sprint_nsi(_("%s installer for %s")),Config::$CONSORTIUM['name'],$attr['general:instname'][0]), $this->code_page).'"
!define APPLICATION "'. $this->translateString(sprintf(sprint_nsi(_("%s installer for %s")),Config::$CONSORTIUM['name'],$attr['general:instname'][0]), $this->code_page).'"
!define VERSION "'.CAT::$VERSION_MAJOR.'.'.CAT::$VERSION_MINOR.'"
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

private function writeProfilesNSH($P,$ca_array,$wired=0) {
debug(4,"writeProfilesNSH");
debug(4,$P);
$fcontents = '';
  foreach($P as $p) 
    $fcontents .= "!insertmacro define_wlan_profile $p\n";

$f = fopen('profiles.nsh','w');
fwrite($f, $fcontents);
fclose($f);

$fcontents = '';
$f = fopen('certs.nsh','w');
if($ca_array) {
foreach ($ca_array as $CA) {
      $store = $CA['root'] ? "root" : "ca";
      $fcontents .= '!insertmacro install_ca_cert "'.$CA['file'].'" "'.$CA['sha1'].'" "'.$store."\"\n";
    }
fwrite($f, $fcontents);
}
fclose($f);
}

//private function write

private function copyFiles ($eap) {
debug(4,"copyFiles start\n");
   $result;
   $result = $this->copyFile('wlan_test.exe');
   $result = $this->copyFile('check_wired.cmd');
   $result = $this->copyFile('install_wired.cmd');
   $result = $this->copyFile('setEAPCred.exe');
   $result = $this->copyFile('cat_bg.bmp');
   $result = $this->copyFile('base64.nsh');
   $result = $result && $this->copyFile('cat32.ico');
   $result = $result && $this->copyFile('cat_150.bmp');
   $this->translateFile('common.inc','common.nsh',$this->code_page);
   if($eap["OUTER"] == PWD) {
     $this->translateFile('pwd.inc','cat.NSI',$this->code_page);
     $result = $result && $this->copyFile('Aruba_Networks_EAP-pwd_x32.msi');
     $result = $result && $this->copyFile('Aruba_Networks_EAP-pwd_x64.msi');
   } else {
   $this->translateFile('eap_w8.inc','cat.NSI',$this->code_page);
   $result = 1;
   }
debug(4,"copyFiles end\n");
   return($result);
}

}

?>
