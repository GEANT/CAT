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
 * This file creates MS Windows 8 installers
 * It supports EAP-TLS, TTLS, PEAP and EAP-pwd
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\ms;
use \Exception;

/**
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package ModuleWriting
 */
 class Device_W8_10 extends WindowsCommon {
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_PWD, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        $this->specialities['internal:use_anon_outer'][serialize(\core\common\EAP::EAPTYPE_PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
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


        if (in_array($this->selectedEap, [\core\common\EAP::EAPTYPE_TLS,
                    \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
                    \core\common\EAP::EAPTYPE_TTLS_PAP,
                    \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
                    \core\common\EAP::EAPTYPE_PWD,
                    \core\common\EAP::EAPTYPE_SILVERBULLET])) {
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
        $this->loggerInstance->debug(4, print_r($windowsProfile, true));

        $this->writeProfilesNSH($windowsProfile, $caFiles);
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
        $outerUser = '';
        $outerId = '';
        $eap = $this->selectedEap;
        $wExt = '';
        // there is only one caller to this function, and it will always call
        // with exactly one of exactly the EAP types below. Let's assert() that
        // rather than returning void, otherwise this is a condition that needs
        // to be caught later on.
        assert(in_array($eap, [\core\common\EAP::EAPTYPE_TLS,
            \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
            \core\common\EAP::EAPTYPE_PWD,
            \core\common\EAP::EAPTYPE_TTLS_PAP,
            \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
            \core\common\EAP::EAPTYPE_SILVERBULLET]), new Exception("prepareEapConfig called for an EAP type it cannot handle!"));

        $useAnon = $attr['internal:use_anon_outer'] [0];
        if ($useAnon) {
            $outerUser = $attr['internal:anon_local_value'][0];
            $outerId = $outerUser . '@' . $attr['internal:realm'][0];
        }
//   $servers = preg_quote(implode(';',$attr['eap:server_name']));
        $servers = implode(';', $attr['eap:server_name']);
        $caArray = $attr['internal:CAs'][0];
        $authorId = "0";
        if ($eap == \core\common\EAP::EAPTYPE_TTLS_PAP || $eap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            if ($this->useGeantLink) {
                $authorId = "67532";
                $servers = implode('</ServerName><ServerName>', $attr['eap:server_name']);
            } else {
                $authorId = "311";
            }
        }

        $profileFileCont = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapMethod>
';

        $profileFileCont .= '<Type xmlns="http://www.microsoft.com/provisioning/EapCommon">' .
                $this->selectedEap["OUTER"] . '</Type>
<VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
<VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">' . $authorId . '</AuthorId>
</EapMethod>
';
        if ($eap == \core\common\EAP::EAPTYPE_TLS || $eap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
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
        } elseif ($eap == \core\common\EAP::EAPTYPE_PEAP_MSCHAP2) {
            if (isset($attr['eap:enable_nea']) && $attr['eap:enable_nea'][0] == 'on') {
                $nea = 'true';
            } else {
                $nea = 'false';
            }
            $wExt = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
<ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>' . $servers . '</ServerNames>';
            if ($caArray) {
                foreach ($caArray as $certAuthority) {
                    if ($certAuthority['root']) {
                        $wExt .= "<TrustedRootCA>" . $certAuthority['sha1'] . "</TrustedRootCA>\n";
                    }
                }
            }
            $wExt .= '</ServerValidation>
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
                $wExt .= '<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
';
                if ($outerUser) {
                    $wExt .= '<AnonymousUserName>' . $outerUser . '</AnonymousUserName>
                ';
                } else {
                    $wExt .= '<AnonymousUserName/>
                ';
                }
                $wExt .= '</IdentityPrivacy>
</PeapExtensions>
';
            }
            $wExt .= '</EapType>
</Eap>
</Config>
';
        } elseif ($eap == \core\common\EAP::EAPTYPE_TTLS_PAP || $eap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            if ($this->useGeantLink) {
                $innerMethod = 'MSCHAPv2';
                if ($eap == \core\common\EAP::EAPTYPE_TTLS_PAP) {
                    $innerMethod = 'PAP';
                }
                $profileFileCont .= '
<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EAPIdentityProviderList xmlns="urn:ietf:params:xml:ns:yang:ietf-eap-metadata">
<EAPIdentityProvider ID="' . $this->deviceUUID . '" namespace="urn:UUID">

<ProviderInfo>
<DisplayName>' . $this->translateString($attr['general:instname'][0], $this->codePage) . '</DisplayName>
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
                        $profileFileCont .= '<AnonymousIdentity>' . $outerId . '</AnonymousIdentity>';
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
<NonEAPAuthMethod>' . $innerMethod . '</NonEAPAuthMethod>
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
            } else {
                $wExt = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapTtls xmlns="http://www.microsoft.com/provisioning/EapTtlsConnectionPropertiesV1">
<ServerValidation>
<ServerNames>' . $servers . '</ServerNames> ';
                if ($caArray) {
                    foreach ($caArray as $certAuthority) {
                        if ($certAuthority['root']) {
                            $wExt .= "<TrustedRootCAHash>" . chunk_split($certAuthority['sha1'], 2, ' ') . "</TrustedRootCAHash>\n";
                        }
                    }
                }
                $wExt .= '<DisablePrompt>true</DisablePrompt> 
</ServerValidation>
<Phase2Authentication>
';
                if ($eap == \core\common\EAP::EAPTYPE_TTLS_PAP) {
                    $wExt .= '<PAPAuthentication /> ';
                }
                if ($eap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
                    $wExt .= '<MSCHAPv2Authentication>
<UseWinlogonCredentials>false</UseWinlogonCredentials>
</MSCHAPv2Authentication>
';
                }
                $wExt .= '</Phase2Authentication>
<Phase1Identity>
';
                if ($useAnon == 1) {
                    $wExt .= '<IdentityPrivacy>true</IdentityPrivacy> 
';
                    if (isset($outerId) && $outerId) {
                        $wExt .= '<AnonymousIdentity>' . $outerId . '</AnonymousIdentity>
                ';
                    } else {
                        $wExt .= '<AnonymousIdentity/>
                ';
                    }
                } else {
                    $wExt .= '<IdentityPrivacy>false</IdentityPrivacy>
';
                }
                $wExt .= '</Phase1Identity>
</EapTtls>
</Config>
';
            }
        } elseif ($eap == \core\common\EAP::EAPTYPE_PWD) {
            $profileFileCont .= '<ConfigBlob></ConfigBlob>';
        }

        $profileFileContEnd = '</EapHostConfig></EAPConfig>';
        $returnArray = [];
        $returnArray['win'] = $profileFileCont . $wExt . $profileFileContEnd;
        return $returnArray;
    }
    
    /**
     * produce PEAP, TLS and TTLS configuration files for Windows 8
     * 
     * @param string $wlanProfileName
     * @param string $ssid
     * @param string $auth can be one of "WPA", "WPA2"
     * @param string $encryption can be one of: "TKIP", "AES"
     * @param array $eapConfig XML configuration block with EAP config data
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

        if (!is_dir('w8')) {
            mkdir('w8');
        }
        $xmlFname = "w8/wlan_prof-$profileNumber.xml";
        file_put_contents($xmlFname, $profileFileCont . $eapConfig['win'] . $closing);
        $this->loggerInstance->debug(2, "Installer has been written into directory $this->FPATH\n");
        $this->loggerInstance->debug(4, "WWWWLAN_Profile:$wlanProfileName:$encryption\n");
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

        if (!is_dir('w8')) {
            mkdir('w8');
        }
        $xmlFname = "w8/lan_prof.xml";
        file_put_contents($xmlFname, $profileFileCont . $eapConfig['win'] . $closing);
        $this->loggerInstance->debug(2, "Installer has been written into directory $this->FPATH\n");
    }

    private function writeProfilesNSH($wlanProfiles, $caArray) {
        $this->loggerInstance->debug(4, "writeProfilesNSH");
        $this->loggerInstance->debug(4, $wlanProfiles);
        $fcontentsProfile = '';
        foreach ($wlanProfiles as $wlanProfile) {
            $fcontentsProfile .= "!insertmacro define_wlan_profile $wlanProfile\n";
        }

        file_put_contents('profiles.nsh', $fcontentsProfile);        

        $fcontentsCerts = '';
        $fileHandleCerts = fopen('certs.nsh', 'w');
        if ($fileHandleCerts === FALSE) {
            throw new Exception("Unable to open new certs.nsh file for writing CAs.");
        }
        if ($caArray) {
            foreach ($caArray as $certAuthority) {
                $store = $certAuthority['root'] ? "root" : "ca";
                $fcontentsCerts .= '!insertmacro install_ca_cert "' . $certAuthority['file'] . '" "' . $certAuthority['sha1'] . '" "' . $store . "\"\n";
            }
            fwrite($fileHandleCerts, $fcontentsCerts);
        }
        fclose($fileHandleCerts);
    }
    
    private function writeMainNSH($eap, $attr) {
        $this->loggerInstance->debug(4, "writeMainNSH");
        $this->loggerInstance->debug(4, $attr);
        $this->loggerInstance->debug(4, "Device_id = " . $this->device_id . "\n");
        $fcontents = "!define W8\n";
        if ($this->device_id == 'w10') {
            $fcontents .= "!define W10\n";
        }
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $fcontents .= "Unicode true\n";
        }
        $eapOptions = [
            \core\common\EAP::PEAP => ['str' => 'PEAP', 'exec' => 'user'],
            \core\common\EAP::TLS => ['str' => 'TLS', 'exec' => 'user'],
            \core\common\EAP::TTLS => ['str' => 'TTLS', 'exec' => 'user'],
            \core\common\EAP::PWD => ['str' => 'PWD', 'exec' => 'user'],
        ];
        if (isset($this->options['args']) && $this->options['args'] == 'gl') {
            $eapOptions[\core\common\EAP::TTLS]['str'] = 'GEANTLink';
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
        $fcontents .= '!define ' . $eapStr;
        $fcontents .= "\n" . '!define EXECLEVEL "' . $execLevel . '"';
        $fcontents .= $this->writeNsisDefines($attr);
        file_put_contents('main.nsh', $fcontents);
    }

    private function copyStandardNsi() {
        if (!$this->translateFile('eap_w8.inc', 'cat.NSI', $this->codePage)) {
            throw new Exception("Translating needed file eap_w8.inc failed!");
        }
    }

    private function copyFiles($eap) {
        $this->loggerInstance->debug(4, "copyFiles start\n");
        $this->copyBasicFiles();
        switch ($eap["OUTER"]) {
            case \core\common\EAP::TTLS:
                if (isset($this->options['args']) && $this->options['args'] == 'gl') {
                    $this->copyGeantLinkFiles();
                } else {
                    $this->copyStandardNsi();
                }
                break;
            case \core\common\EAP::PWD:
                $this->copyPwdFiles();
                break;
            default:
                $this->copyStandardNsi();
        }
        $this->loggerInstance->debug(4, "copyFiles end\n");
        return TRUE;
    }

    private $tlsOtherUsername = 0;

}

