<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file creates MS Windows Vista and MS Windows 7 installers
 * It supports EAP-TLS, PEAP and EAP-pwd (with external software)
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
class Device_Vista7 extends WindowsCommon {
    final public function __construct() {
      $this->supportedEapMethods = [EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$PWD, EAP::$TTLS_PAP];
      debug(4,"This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
      $this->specialities['anon_id'][serialize(EAP::$PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
    }

  public function writeInstaller() {
      $dom = textdomain(NULL);
      textdomain("devices");
   // create certificate files and save their names in $caFiles arrary
     $caFiles = $this->saveCertificateFiles('der');

     $allSSID = $this->attributes['internal:SSID'];
     $delSSIDs = $this->attributes['internal:remove_SSID'];
     $this->prepareInstallerLang();
     $setWired = isset($this->attributes['media:wired'][0]) && $this->attributes['media:wired'][0] == 'on' ? 1 : 0;
//   create a list of profiles to be deleted after installation
     $delProfiles = [];
     foreach ($delSSIDs as $ssid => $cipher) {
         if($cipher == 'DEL') 
          $delProfiles[] = $ssid;
         if($cipher == 'TKIP') 
          $delProfiles[] = $ssid.' (TKIP)';
     }

     if ($this->selected_eap == EAP::$TLS || $this->selected_eap == EAP::$PEAP_MSCHAP2 || $this->selected_eap == EAP::$PWD || $this->selected_eap == EAP::$TTLS_PAP) {
       $windowsProfile = [];
       $eapConfig = $this->prepareEapConfig($this->attributes);
       $i = 0;
       foreach ($allSSID as $ssid => $cipher) {
          if($cipher == 'TKIP') {
             $windowsProfile[$i] = $this->writeWLANprofile ($ssid.' (TKIP)',$ssid,'WPA','TKIP',$eapConfig,$i);
             $i++;
          }
          $windowsProfile[$i] = $this->writeWLANprofile ($ssid,$ssid,'WPA2','AES',$eapConfig,$i);
          $i++;
       }
       if($setWired) {
         $this->writeLANprofile($eapConfig);
       }
     } else {
       error("  this EAP type is not handled yet");
       return;
     }
    debug(4,"windowsProfile"); debug(4,$windowsProfile);
    
    $this->writeProfilesNSH($windowsProfile, $caFiles,$setWired);
    $this->writeAdditionalDeletes($delProfiles);
    $this->copyFiles($this->selected_eap);
    if(isset($this->attributes['internal:logo_file']))
       $this->combineLogo($this->attributes['internal:logo_file']);
    $this->writeMainNSH($this->selected_eap,$this->attributes);
    $this->compileNSIS();
    $installerPath = $this->signInstaller($this->attributes); 

    textdomain($dom);
    return($installerPath);  
  }

  public function writeDeviceInfo() {
    $ssidCount=count($this->attributes['internal:SSID']);
    $out = "<p>";
    $out .= sprintf(_("%s installer will be in the form of an EXE file. It will configure %s on your device, by creating wireless network profiles.<p>When you click the download button, the installer will be saved by your browser. Copy it to the machine you want to configure and execute."),Config::$CONSORTIUM['name'],Config::$CONSORTIUM['name']);
    $out .= "<p>";
    if($ssidCount > 1) {
        if($ssidCount > 2) {
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

    if($this->eap == EAP::$PEAP_MSCHAP2) {
        $out .= "<p>";
        $out .= _("When you are connecting to the network for the first time, Windows will pop up a login box, where you should enter your user name and password. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
        if($ssidCount > 1) {
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

   }
  return($out);
}

private function prepareEapConfig($attr) {
    $vistaExt = '';
    $w7_ext = '';
    $eap = $this->selected_eap;
    if ($eap != EAP::$TLS && $eap != EAP::$PEAP_MSCHAP2 && $eap != EAP::$PWD && $eap != EAP::$TTLS_PAP) {
      debug(2,"this method only allows TLS, PEAP, TTLS-PAP or EAP-pwd");
      error("this method only allows TLS, PEAP, TTLS-PAP or EAP-pwd");
     return;
    }
   $useAnon = $attr['internal:use_anon_outer'] [0];
   $realm = $attr['internal:realm'] [0];
   if ($useAnon) {
     $outerUser = $attr['internal:anon_local_value'][0];
   }
//   $servers = preg_quote(implode(';',$attr['eap:server_name']));
   $servers = implode(';',$attr['eap:server_name']);
   $caArray = $attr['internal:CAs'][0];
   $author_id = "0";
   if( $eap == EAP::$TTLS_PAP) {
      $author_id = "67532";
      $servers = implode('</ServerName><ServerName>',$attr['eap:server_name']);
   }

  $profile_file_contents = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapMethod>
<Type xmlns="http://www.microsoft.com/provisioning/EapCommon">'.
    $this->selected_eap["OUTER"] .'</Type>
<VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
<VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">'.$author_id.'</AuthorId>
</EapMethod>
';


   if( $eap == EAP::$TTLS_PAP) {
$profile_file_contents .= '
<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EAPIdentityProviderList xmlns="urn:ietf:params:xml:ns:yang:ietf-eap-metadata">
<EAPIdentityProvider>
<ID>CATinstaller</ID>
<ProviderInfo>
<CredentialPrompt>
<localized-text><lang>'.$this->lang_index.'</lang><text>'._("Please provide your user ID and password.").'</text></localized-text>
</CredentialPrompt>
<UserNameLabel>
<localized-text><lang>'.$this->lang_index.'</lang><text>'._("Username@domain:").'</text></localized-text>
</UserNameLabel>
<PasswordLabel>
<localized-text><lang>'.$this->lang_index.'</lang><text>'._("Password:").'</text></localized-text>
</PasswordLabel>
</ProviderInfo>
<AuthenticationMethods>
<AuthenticationMethod>
<EAPMethod>21</EAPMethod>
';
if($useAnon == 1) {
    $profile_file_contents .= '<ClientSideCredential>
';
    if($outerUser == '')
        $profile_file_contents .= '<AnonymousIdentity>@</AnonymousIdentity>';
    else
        $profile_file_contents .= '<AnonymousIdentity>'.$outerUser.'@'.$realm.'</AnonymousIdentity>';
$profile_file_contents .= '
</ClientSideCredential>
';
}
$profile_file_contents .= '<ServerSideCredential>
';

   foreach ($caArray as $ca) {
      
      $profile_file_contents .= '<CA><format>PEM</format><cert-data>';
      $profile_file_contents .= base64_encode($ca['der']);
      $profile_file_contents .= '</cert-data></CA>
';
   }
   $profile_file_contents .= "<ServerName>$servers</ServerName>\n";

$profile_file_contents .= '
</ServerSideCredential>
<InnerAuthenticationMethod>
<NonEAPAuthMethod>PAP</NonEAPAuthMethod>
</InnerAuthenticationMethod>
<VendorSpecific>
<SessionResumption>false</SessionResumption>
</VendorSpecific>
</AuthenticationMethod>
</AuthenticationMethods>
</EAPIdentityProvider>
</EAPIdentityProviderList>
</Config>
';

}
elseif( $eap == EAP::$TLS) {
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
if($caArray) {
foreach ($caArray as $CA)
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
$vistaExt = '<Config xmlns:eapUser="http://www.microsoft.com/provisioning/EapUserPropertiesV1" 
xmlns:baseEap="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1" 
  xmlns:msPeap="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1" 
  xmlns:msChapV2="http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1">
<baseEap:Eap>
<baseEap:Type>25</baseEap:Type> 
<msPeap:EapType>
<msPeap:ServerValidation>
<msPeap:DisableUserPromptForServerValidation>true</msPeap:DisableUserPromptForServerValidation> 
<msPeap:ServerNames>'.$servers.'</msPeap:ServerNames>';
if($caArray) {
foreach ($caArray as $CA)
    if($CA['root'])
       $vistaExt .= "<msPeap:TrustedRootCA>".$CA['sha1']."</msPeap:TrustedRootCA>\n";
}
$vistaExt .= '</msPeap:ServerValidation>
<msPeap:FastReconnect>true</msPeap:FastReconnect> 
<msPeap:InnerEapOptional>0</msPeap:InnerEapOptional> 
<baseEap:Eap>
<baseEap:Type>26</baseEap:Type>
<msChapV2:EapType>
<msChapV2:UseWinLogonCredentials>false</msChapV2:UseWinLogonCredentials> 
</msChapV2:EapType>
</baseEap:Eap>
<msPeap:EnableQuarantineChecks>'.$nea.'</msPeap:EnableQuarantineChecks>
<msPeap:RequireCryptoBinding>false</msPeap:RequireCryptoBinding>
</msPeap:EapType>
</baseEap:Eap>
</Config>
';
$w7_ext = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
<ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>'.$servers.'</ServerNames>';
if($caArray) {
foreach ($caArray as $CA)
    if($CA['root'])
        $w7_ext .= "<TrustedRootCA>".$CA['sha1']."</TrustedRootCA>\n";
}
$w7_ext .= '</ServerValidation>
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
if($useAnon == 1)
$w7_ext .='<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
<AnonymousUserName>'.$outerUser.'</AnonymousUserName>
</IdentityPrivacy>
</PeapExtensions>
';
$w7_ext .='</EapType>
</Eap>
</Config>
';
} elseif ( $eap == EAP::$PWD) {
   $profile_file_contents .= '<ConfigBlob></ConfigBlob>';
} 



$profile_file_contents_end = '</EapHostConfig></EAPConfig>
';
$return_array = [];
$return_array['vista']= $profile_file_contents.$vistaExt.$profile_file_contents_end;
$return_array['w7']= $profile_file_contents.$w7_ext.$profile_file_contents_end;
return $return_array;
}


// $auth can be one of: "WPA", "WPA2"
// $encryption can be one of: "TKIP", "AES"
// $servers is an array of allowed server names (regular expressions allowed)
// $ca is an array of allowed CA fingerprints

/**
 * produce PEAP and TLS configuration files for Vista and Windows 7
 */
  private function writeWLANprofile($wlanProfileName,$ssid,$auth,$encryption,$eapConfig,$i) {
$profile_file_contents = '<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
<name>'.$wlanProfileName.'</name>
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

if(! is_dir('w7'))
  mkdir('w7');
if(! is_dir('vista'))
  mkdir('vista');
$xml_f_name = "vista/wlan_prof-$i.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eapConfig['vista']. $closing) ;
fclose($xml_f);
$xml_f_name = "w7/wlan_prof-$i.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eapConfig['w7']. $closing) ;
fclose($xml_f);
debug(2,"Installer has been written into directory $this->FPATH\n");
debug(4,"WLAN_Profile:$wlanProfileName:$encryption\n");
return("\"$wlanProfileName\" \"$encryption\"");
}

private function writeLANprofile($eapConfig) {
$profile_file_contents = '<?xml version="1.0"?>
<LANProfile xmlns="http://www.microsoft.com/networking/LAN/profile/v1">
<MSM>
<security>
<OneXEnforced>false</OneXEnforced>
<OneXEnabled>true</OneXEnabled>
<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
<cacheUserData>true</cacheUserData>
<authMode>user</authMode>
';
$closing = '
</OneX>
</security>
</MSM>
</LANProfile>
';
if(! is_dir('w7'))
  mkdir('w7');
if(! is_dir('vista'))
  mkdir('vista');
$xml_f_name = "vista/lan_prof.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eapConfig['vista']. $closing) ;
fclose($xml_f);
$xml_f_name = "w7/lan_prof.xml";
$xml_f = fopen($xml_f_name,'w');
fwrite($xml_f,$profile_file_contents. $eapConfig['w7']. $closing) ;
fclose($xml_f);
}

private function writeMainNSH($eap,$attr) {
debug(4,"writeMainNSH"); debug(4,$attr);
debug(4,"MYLANG=".$this->lang."\n");

$EAP_OPTS = [
PEAP=>['str'=>'PEAP','exec'=>'user'],
TLS=>['str'=>'TLS','exec'=>'user'],
TTLS=>['str'=>'GEANTLink','exec'=>'user'],
PWD=>['str'=>'PWD','exec'=>'user'],
];
$fcontents = '';
if(Config::$NSIS_VERSION >= 3)
    $fcontents .=  "Unicode true\n";
 
// Uncomment the line below if you want this module to run under XP (only displaying a warning)
// $fcontents .= "!define ALLOW_XP\n";
// Uncomment the line below if you want this module to produce debugging messages on the client
// $fcontents .= "!define DEBUG_CAT\n";
$exec_level = $EAP_OPTS[$eap["OUTER"]]['exec'];
$eap_str = $EAP_OPTS[$eap["OUTER"]]['str'];
debug(4,"EAP_STR=$eap_str\n");
debug(4,$eap);

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

private function writeProfilesNSH($P,$caArray,$wired=0) {
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
if($caArray) {
foreach ($caArray as $CA) {
      $store = $CA['root'] ? "root" : "ca";
      $fcontents .= '!insertmacro install_ca_cert "'.$CA['file'].'" "'.$CA['sha1'].'" "'.$store."\"\n";
    }
fwrite($f, $fcontents);
}
fclose($f);
}

private function copyFiles ($eap) {
debug(4,"copyFiles start\n");
debug(4,"code_page=".$this->code_page."\n");
   $result;
   $result = $this->copyFile('wlan_test.exe');
   $result = $this->copyFile('check_wired.cmd');
   $result = $this->copyFile('install_wired.cmd');
   $result = $this->copyFile('setEAPCred.exe');
   $result = $this->copyFile('base64.nsh');
   $result = $this->copyFile('cat_bg.bmp');
   $result = $result && $this->copyFile('cat32.ico');
   $result = $result && $this->copyFile('cat_150.bmp');
   $this->translateFile('common.inc','common.nsh',$this->code_page);
   if( $eap["OUTER"] == TTLS)  {
     $result = $result && $this->copyFile('GEANTLink/GEANTLink32.msi','GEANTLink32.msi');
     $result = $result && $this->copyFile('GEANTLink/GEANTLink64.msi','GEANTLink64.msi');
     $result = $result && $this->copyFile('GEANTLink/CredWrite.exe','CredWrite.exe');
     $result = $result && $this->copyFile('GEANTLink/MsiUseFeature.exe','MsiUseFeature.exe');
     $this->translateFile('geant_link.inc','cat.NSI',$this->code_page);
   } elseif($eap["OUTER"] == PWD) {
     $this->translateFile('pwd.inc','cat.NSI',$this->code_page);
     $result = $result && $this->copyFile('Aruba_Networks_EAP-pwd_x32.msi');
     $result = $result && $this->copyFile('Aruba_Networks_EAP-pwd_x64.msi');
    } else {
     $this->translateFile('peap_tls.inc','cat.NSI',$this->code_page);
     $result = 1;
    }
debug(4,"copyFiles end\n");
   return($result);
}

}

?>
