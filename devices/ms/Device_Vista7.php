<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

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

namespace devices\ms;
use \Exception;

class Device_Vista7 extends WindowsCommon {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_PWD, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        $this->loggerInstance->debug(4, "This device supports the following EAP methods: ");
        $this->loggerInstance->debug(4, $this->supportedEapMethods);
        $this->specialities['anon_id'][serialize(\core\common\EAP::EAPTYPE_PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
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
            if ($cipher == 'DEL') {
                $delProfiles[] = $ssid;
            }
            if ($cipher == 'TKIP') {
                $delProfiles[] = $ssid . ' (TKIP)';
            }
        }

        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS || $this->selectedEap == \core\common\EAP::EAPTYPE_PEAP_MSCHAP2 || $this->selectedEap == \core\common\EAP::EAPTYPE_PWD || $this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP || $this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $windowsProfile = [];
            $eapConfig = $this->prepareEapConfig($this->attributes);
            $iterator = 0;
            foreach ($allSSID as $ssid => $cipher) {
                if ($cipher == 'TKIP') {
                    $windowsProfile[$iterator] = $this->writeWLANprofile($ssid . ' (TKIP)', $ssid, 'WPA', 'TKIP', $eapConfig, $iterator);
                    $iterator++;
                }
                $windowsProfile[$iterator] = $this->writeWLANprofile($ssid, $ssid, 'WPA2', 'AES', $eapConfig, $iterator);
                $iterator++;
            }
            if ($setWired) {
                $this->writeLANprofile($eapConfig);
            }
        } else {
            print("  this EAP type is not handled yet.\n");
            return;
        }
        $this->loggerInstance->debug(4, "windowsProfile");
        $this->loggerInstance->debug(4, $windowsProfile);

        $this->writeProfilesNSH($windowsProfile, $caFiles, $setWired);
        $this->writeAdditionalDeletes($delProfiles);
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $this->writeClientP12File();
        }
        $this->copyFiles($this->selectedEap);
        $fedLogo = $this->attributes['fed:logo_file'] ?? NULL;
        $idpLogo = $this->attributes['internal:logo_file'] ?? NULL;
        $this->combineLogo($idpLogo, $fedLogo);
        $this->writeMainNSH($this->selectedEap, $this->attributes);
        $this->compileNSIS();
        $installerPath = $this->signInstaller();

        textdomain($dom);
        return($installerPath);
    }

    private function prepareEapConfig($attr) {
        $vistaExt = '';
        $w7Ext = '';
        $useAnon = $attr['internal:use_anon_outer'] [0];
        $realm = $attr['internal:realm'] [0];
        if ($useAnon) {
            $outerUser = $attr['internal:anon_local_value'][0];
        }
//   $servers = preg_quote(implode(';',$attr['eap:server_name']));
        $servers = implode(';', $attr['eap:server_name']);
        $caArray = $attr['internal:CAs'][0];
        $authorId = "0";
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP || $this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            $authorId = "67532";
            $servers = implode('</ServerName><ServerName>', $attr['eap:server_name']);
        }

        $profileFileCont = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapMethod>
<Type xmlns="http://www.microsoft.com/provisioning/EapCommon">' .
                $this->selectedEap["OUTER"] . '</Type>
<VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
<VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">' . $authorId . '</AuthorId>
</EapMethod>
';


        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP || $this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            $innerMethod = 'MSCHAPv2';
            if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP) {
                $innerMethod = 'PAP';
            }
            $profileFileCont .= '
<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EAPIdentityProviderList xmlns="urn:ietf:params:xml:ns:yang:ietf-eap-metadata">
<EAPIdentityProvider ID="' . $this->deviceUUID . '" namespace="urn:UUID">
<ProviderInfo>
<DisplayName>' . $this->translateString($attr['general:instname'][0], $this->code_page) . '</DisplayName>
</ProviderInfo>
<AuthenticationMethods>
<AuthenticationMethod>
<EAPMethod>21</EAPMethod>
<ClientSideCredential>
<allow-save>true</allow-save>
';
            if ($useAnon == 1) {
                if ($outerUser == '') {
                    $profileFileCont .= '<AnonymousIdentity>@</AnonymousIdentity>';
                } else {
                    $profileFileCont .= '<AnonymousIdentity>' . $outerUser . '@' . $realm . '</AnonymousIdentity>';
                }
            }
            $profileFileCont .= '</ClientSideCredential>
<ServerSideCredential>
';

            foreach ($caArray as $ca) {
                $profileFileCont .= '<CA><format>PEM</format><cert-data>';
                $profileFileCont .= base64_encode($ca['der']);
                $profileFileCont .= '</cert-data></CA>
';
            }
            $profileFileCont .= "<ServerName>$servers</ServerName>\n";

            $profileFileCont .= '
</ServerSideCredential>
<InnerAuthenticationMethod>
<NonEAPAuthMethod>' . $inner_method . '</NonEAPAuthMethod>
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
        } elseif ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS || $this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {

            $profileFileCont .= '

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
<eapTls:ServerNames>' . $servers . '</eapTls:ServerNames>';
            if ($caArray) {
                foreach ($caArray as $certAuthority) {
                    if ($certAuthority['root']) {
                        $profileFileCont .= "<eapTls:TrustedRootCA>" . $certAuthority['sha1'] . "</eapTls:TrustedRootCA>\n";
                    }
                }
            }
            $profileFileCont .= '</eapTls:ServerValidation>
';
            if (isset($attr['eap-specific:tls_use_other_id']) && $attr['eap-specific:tls_use_other_id'][0] == 'on') {
                $profileFileCont .= '<eapTls:DifferentUsername>true</eapTls:DifferentUsername>';
                $this->tlsOtherUsername = 1;
            } else {
                $profileFileCont .= '<eapTls:DifferentUsername>false</eapTls:DifferentUsername>';
            }
            $profileFileCont .= '
</eapTls:EapType>
</baseEap:Eap>
</Config>
';
        } elseif ($this->selectedEap == \core\common\EAP::EAPTYPE_PEAP_MSCHAP2) {
            if (isset($attr['eap:enable_nea']) && $attr['eap:enable_nea'][0] == 'on') {
                $nea = 'true';
            } else {
                $nea = 'false';
            }
            $vistaExt = '<Config xmlns:eapUser="http://www.microsoft.com/provisioning/EapUserPropertiesV1" 
xmlns:baseEap="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1" 
  xmlns:msPeap="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1" 
  xmlns:msChapV2="http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1">
<baseEap:Eap>
<baseEap:Type>25</baseEap:Type> 
<msPeap:EapType>
<msPeap:ServerValidation>
<msPeap:DisableUserPromptForServerValidation>true</msPeap:DisableUserPromptForServerValidation> 
<msPeap:ServerNames>' . $servers . '</msPeap:ServerNames>';
            if ($caArray) {
                foreach ($caArray as $certAuthority) {
                    if ($certAuthority['root']) {
                        $vistaExt .= "<msPeap:TrustedRootCA>" . $certAuthority['sha1'] . "</msPeap:TrustedRootCA>\n";
                    }
                }
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
<msPeap:EnableQuarantineChecks>' . $nea . '</msPeap:EnableQuarantineChecks>
<msPeap:RequireCryptoBinding>false</msPeap:RequireCryptoBinding>
</msPeap:EapType>
</baseEap:Eap>
</Config>
';
            $w7Ext = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
<ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>' . $servers . '</ServerNames>';
            if ($caArray) {
                foreach ($caArray as $certAuthority) {
                    if ($certAuthority['root']) {
                        $w7Ext .= "<TrustedRootCA>" . $certAuthority['sha1'] . "</TrustedRootCA>\n";
                    }
                }
            }
            $w7Ext .= '</ServerValidation>
<FastReconnect>true</FastReconnect> 
<InnerEapOptional>false</InnerEapOptional> 
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>26</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1">
<UseWinLogonCredentials>false</UseWinLogonCredentials> 
</EapType>
</Eap>
<EnableQuarantineChecks>' . $nea . '</EnableQuarantineChecks>
<RequireCryptoBinding>false</RequireCryptoBinding>
';
            if ($useAnon == 1) {
                $w7Ext .= '<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
<AnonymousUserName>' . $outerUser . '</AnonymousUserName>
</IdentityPrivacy>
</PeapExtensions>
            ';
            }
            $w7Ext .= '</EapType>
</Eap>
</Config>
';
        } elseif ($this->selectedEap == \core\common\EAP::EAPTYPE_PWD) {
            $profileFileCont .= '<ConfigBlob></ConfigBlob>';
        }



        $profileFileContEnd = '</EapHostConfig></EAPConfig>
';
        $returnArray = [];
        $returnArray['vista'] = $profileFileCont . $vistaExt . $profileFileContEnd;
        $returnArray['w7'] = $profileFileCont . $w7Ext . $profileFileContEnd;
        return $returnArray;
    }

    /**
     * produce PEAP, TLS and TTLS configuration files for Vista and Windows 7
     * 
     * @param string $wlanProfileName
     * @param string $ssid
     * @param string $auth can be one of "WPA", "WPA2"
     * @param string $encryption can be one of: "TKIP", "AES"
     * @param array $eapConfig XML configuration block with EAP config data (two entries, one for Vista, one for 7)
     * @param int $profileNumber counter, which profile number is this
     * @return string
     */
    private function writeWLANprofile($wlanProfileName, $ssid, $auth, $encryption, $eapConfig, $profileNumber) {
        $profileFileCont = '<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
<name>' . $wlanProfileName . '</name>
<SSIDConfig>
<SSID>
<name>' . $ssid . '</name>
</SSID>
<nonBroadcast>true</nonBroadcast>
</SSIDConfig>
<connectionType>ESS</connectionType>
<connectionMode>auto</connectionMode>
<autoSwitch>false</autoSwitch>
<MSM>
<security>
<authEncryption>
<authentication>' . $auth . '</authentication>
<encryption>' . $encryption . '</encryption>
<useOneX>true</useOneX>
</authEncryption>
';
        if ($auth == 'WPA2') {
            $profileFileCont .= '<PMKCacheMode>enabled</PMKCacheMode>
<PMKCacheTTL>720</PMKCacheTTL>
<PMKCacheSize>128</PMKCacheSize>
<preAuthMode>disabled</preAuthMode>
';
        }
        $profileFileCont .= '<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
<cacheUserData>true</cacheUserData>
<authMode>user</authMode>
';

        $closing = '
</OneX>
</security>
</MSM>
</WLANProfile>
';

        if (!is_dir('w7')) {
            mkdir('w7');
        }
        if (!is_dir('vista')) {
            mkdir('vista');
        }
        $vistaFileName = "vista/wlan_prof-$profileNumber.xml";
        $vistaFileHandle = fopen($vistaFileName, 'w');
        fwrite($vistaFileHandle, $profileFileCont . $eapConfig['vista'] . $closing);
        fclose($vistaFileHandle);
        $sevenFileName = "w7/wlan_prof-$profileNumber.xml";
        $sevenFileHandle = fopen($sevenFileName, 'w');
        fwrite($sevenFileHandle, $profileFileCont . $eapConfig['w7'] . $closing);
        fclose($sevenFileHandle);
        $this->loggerInstance->debug(2, "Installer has been written into directory $this->FPATH\n");
        $this->loggerInstance->debug(4, "WLAN_Profile:$wlanProfileName:$encryption\n");
        return("\"$wlanProfileName\" \"$encryption\"");
    }

    private function writeLANprofile($eapConfig) {
        $profileFileCont = '<?xml version="1.0"?>
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
        if (!is_dir('w7')) {
            mkdir('w7');
        }
        if (!is_dir('vista')) {
            mkdir('vista');
        }
        $vistaFileName = "vista/lan_prof.xml";
        $vistaFileHandle = fopen($vistaFileName, 'w');
        fwrite($vistaFileHandle, $profileFileCont . $eapConfig['vista'] . $closing);
        fclose($vistaFileHandle);
        $sevenFileName = "w7/lan_prof.xml";
        $sevenFileHandle = fopen($sevenFileName, 'w');
        fwrite($sevenFileHandle, $profileFileCont . $eapConfig['w7'] . $closing);
        fclose($sevenFileHandle);
    }

    private function writeMainNSH($eap, $attr) {
        $this->loggerInstance->debug(4, "writeMainNSH");
        $this->loggerInstance->debug(4, $attr);
        $this->loggerInstance->debug(4, "MYLANG=" . $this->lang . "\n");

        $eapOptions = [
            \core\common\EAP::PEAP => ['str' => 'PEAP', 'exec' => 'user'],
            \core\common\EAP::TLS => ['str' => 'TLS', 'exec' => 'user'],
// TODO for TW: the following line doesn't work - that constant is an array, which can't be a key for another array
// generated a PHP Warning but doesn't seem to have any catastrophic effect?
//           \core\common\EAP::EAPTYPE_SILVERBULLET => ['str' => 'TLS', 'exec' => 'user'],
            \core\common\EAP::TTLS => ['str' => 'GEANTLink', 'exec' => 'user'],
            \core\common\EAP::PWD => ['str' => 'PWD', 'exec' => 'user'],
        ];
        $fcontents = '';
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $fcontents .= "Unicode true\n";
        }

// Uncomment the line below if you want this module to run under XP (only displaying a warning)
// $fcontents .= "!define ALLOW_XP\n";
// Uncomment the line below if you want this module to produce debugging messages on the client
// $fcontents .= "!define DEBUG_CAT\n";
        if ($this->tlsOtherUsername == 1) {
            $fcontents .= "!define PFX_USERNAME\n";
        }
        $execLevel = $eapOptions[$eap["OUTER"]]['exec'];
        $eapStr = $eapOptions[$eap["OUTER"]]['str'];
        if ($eap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $fcontents .= "!define SILVERBULLET\n";
        }
        $this->loggerInstance->debug(4, "EAP_STR=$eapStr\n");
        $this->loggerInstance->debug(4, $eap);

        $fcontents .= '!define ' . $eapStr;
        $fcontents .= "\n" . '!define EXECLEVEL "' . $execLevel . '"';
        if ($attr['internal:profile_count'][0] > 1) {
            $fcontents .= "\n" . '!define USER_GROUP "' . $this->translateString(str_replace('"', '$\\"', $attr['profile:name'][0]), $this->codePage) . '"';
        }
        $fcontents .= '
Caption "' . $this->translateString(sprintf(WindowsCommon::sprint_nsi(_("%s installer for %s")), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $attr['general:instname'][0]), $this->codePage) . '"
!define APPLICATION "' . $this->translateString(sprintf(WindowsCommon::sprint_nsi(_("%s installer for %s")), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $attr['general:instname'][0]), $this->codePage) . '"
!define VERSION "' . \core\CAT::VERSION_MAJOR . '.' . \core\CAT::VERSION_MINOR . '"
!define INSTALLER_NAME "installer.exe"
!define LANG "' . $this->lang . '"
!define LOCALE "' . preg_replace('/\..*$/', '', CONFIG['LANGUAGES'][$this->languageInstance->getLang()]['locale']) . '"
';
        $fcontents .= $this->msInfoFile($attr);


        $fcontents .= ';--------------------------------
!define ORGANISATION "' . $this->translateString($attr['general:instname'][0], $this->codePage) . '"
!define SUPPORT "' . ((isset($attr['support:email'][0]) && $attr['support:email'][0] ) ? $attr['support:email'][0] : $this->translateString($this->support_email_substitute, $this->codePage)) . '"
!define URL "' . ((isset($attr['support:url'][0]) && $attr['support:url'][0] ) ? $attr['support:url'][0] : $this->translateString($this->support_url_substitute, $this->codePage)) . '"

!ifdef TLS
';
//TODO this must be changed with a new option
        if ($eap != \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $fcontents .= '!define TLS_CERT_STRING "certyfikaty.umk.pl"
';
        }
        $fcontents .= '!define TLS_FILE_NAME "cert*.p12"
!endif
';

        if (isset($this->attributes['media:wired'][0]) && $attr['media:wired'][0] == 'on') {
            $fcontents .= '!define WIRED
';
        }
        $fcontents .= '!define PROVIDERID "urn:UUID:' . $this->deviceUUID . '"
';


        $fileHandle = fopen('main.nsh', 'w');
        fwrite($fileHandle, $fcontents);
        fclose($fileHandle);
    }

    private function writeProfilesNSH($wlanProfiles, $caArray, $wired = 0) {
        $this->loggerInstance->debug(4, "writeProfilesNSH");
        $this->loggerInstance->debug(4, $wlanProfiles);
        $contentWlan = '';
        foreach ($wlanProfiles as $wlanProfile) {
            $contentWlan .= "!insertmacro define_wlan_profile $wlanProfile\n";
        }

        $fileHandleProfiles = fopen('profiles.nsh', 'w');
        fwrite($fileHandleProfiles, $contentWlan);
        fclose($fileHandleProfiles);

        $contentCerts = '';
        $fileHandleCerts = fopen('certs.nsh', 'w');
        if ($caArray) {
            foreach ($caArray as $certAuthority) {
                $store = $certAuthority['root'] ? "root" : "ca";
                $contentCerts .= '!insertmacro install_ca_cert "' . $certAuthority['file'] . '" "' . $certAuthority['sha1'] . '" "' . $store . "\"\n";
            }
            fwrite($fileHandleCerts, $contentCerts);
        }
        fclose($fileHandleCerts);
    }

    private function copyFiles($eap) {
        $this->loggerInstance->debug(4, "copyFiles start\n");
        $this->loggerInstance->debug(4, "code_page=" . $this->codePage . "\n");
        $this->copyBasicFiles();

        switch ($eap["OUTER"]) {
            case \core\common\EAP::TTLS:
                $this->copyGeantLinkFiles();
                break;
            case \core\common\EAP::PWD:
                $this->copyPwdFiles();
                break;
            default:
                if (!$this->translateFile('peap_tls.inc', 'cat.NSI', $this->codePage)) {
                    throw new Exception("Translating needed file peap_tls.inc failed!");
                }
        }
        $this->loggerInstance->debug(4, "copyFiles end\n");
        return TRUE;
    }

    private $tlsOtherUsername = 0;

}
