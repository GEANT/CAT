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
        $this->setSupportedEapMethods(
                [
                    \core\common\EAP::EAPTYPE_TLS,
                    \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
                    \core\common\EAP::EAPTYPE_TTLS_PAP,
                    \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
                    \core\common\EAP::EAPTYPE_SILVERBULLET
                ]);
        $this->specialities['internal:use_anon_outer'][serialize(\core\common\EAP::EAPTYPE_PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
    }
    public function writeInstaller() {
        $dom = textdomain(NULL);
        textdomain("devices");
        // create certificate files and save their names in $caFiles arrary
        $caFiles = $this->saveCertificateFiles('der');
        $this->caArray = $this->getAttibute('internal:CAs')[0];
        $outerId = $this->determineOuterIdString();
        $this->useAnon = $outerId === NULL ? FALSE : TRUE;
        $this->servers = empty($this->attributes['eap:server_name']) ? '' :  implode(';', $this->attributes['eap:server_name']);
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
        $windowsProfile = [];
        $eapConfig = $this->prepareEapConfig();
        $iterator = 0;
        foreach ($allSSID as $ssid => $cipher) {
            if ($cipher == 'TKIP') {
                $windowsProfile[$iterator] = $this->writeWLANprofile($ssid . ' (TKIP)', $ssid, 'WPA', 'TKIP', $eapConfig, $iterator);
                $iterator++;
            }
            $windowsProfile[$iterator] = $this->writeWLANprofile($ssid, $ssid, 'WPA2', 'AES', $eapConfig, $iterator);
            $iterator++;
        }
        if (($this->device_id !== 'w8') && (count($this->attributes['internal:consortia']) > 0 )) {
            // this SSID name is later used in common.inc so if you decide to chage it here change it there as well
                $ssid = 'cat-passpoint-profile';
                $windowsProfile[$iterator] = $this->writeWLANprofile($ssid, $ssid, 'WPA2', 'AES', $eapConfig, $iterator, TRUE);
        }
        if ($setWired) {
            $this->writeLANprofile($eapConfig);
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

    private function setAuthorId() {
        if ($this->selectedEap['OUTER'] === \core\common\EAP::TTLS) {
            if ($this->useGeantLink) {
                $authorId = "67532";
            } else {
                $authorId = "311";
            }
        } else {
            $authorId = 0;
        }
        return($authorId);
    }

    private function addConsortia() {
        if ($this->device_id == 'w8') {
            return('');
        }
        $retval = '<Hotspot2>';
        $retval .= '<DomainName>';
        if (empty($this->attributes['internal:realm'][0])) {
            $retval .= CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-domainname-fallback'];
        } else {
            $retval .=  $this->attributes['internal:realm'][0];
        }
        $retval .= '</DomainName>';
        $retval .= '<RoamingConsortium><OUI>' . 
            implode('</OUI><OUI>', $this->attributes['internal:consortia']) .
            '</OUI></RoamingConsortium>';
        $retval .=  '</Hotspot2>';
        return($retval);
    }
    
    private function eapConfigHeader() {
        $authorId = $this->setAuthorId();
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
        return($profileFileCont);
    }

    private function tlsServerValidation() {
        $profileFileCont = '
<eapTls:ServerValidation>
<eapTls:DisableUserPromptForServerValidation>true</eapTls:DisableUserPromptForServerValidation>
';
        $profileFileCont .= '<eapTls:ServerNames>' . $this->servers . '</eapTls:ServerNames>';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<eapTls:TrustedRootCA>" . $certAuthority['sha1'] . "</eapTls:TrustedRootCA>\n";
            }
        }
        $profileFileCont .= '</eapTls:ServerValidation>
';
        return($profileFileCont);
    }
    
    private function msTtlsServerValidation() {
        $profileFileCont = '
        <ServerValidation>
';
        $profileFileCont .= '<ServerNames>' . $this->servers . '</ServerNames> ';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<TrustedRootCAHash>" . chunk_split($certAuthority['sha1'], 2, ' ') . "</TrustedRootCAHash>\n";
            }
        }
        $profileFileCont .= '<DisablePrompt>true</DisablePrompt>
</ServerValidation>
';
        return($profileFileCont);
    }
    
    private function glTtlsServerValidation() {
        $servers = implode('</ServerName><ServerName>', $this->attributes['eap:server_name']);
        $profileFileCont = '
<ServerSideCredential>
';
        foreach ($this->caArray as $ca) {
            $profileFileCont .= '<CA><format>PEM</format><cert-data>';
            $profileFileCont .= base64_encode($ca['der']);
            $profileFileCont .= '</cert-data></CA>
';
        }
        $profileFileCont .= "<ServerName>$servers</ServerName>\n";

        $profileFileCont .= '
</ServerSideCredential>
';
        return($profileFileCont);
    }
    
    private function peapServerValidation() {
        $profileFileCont = '
        <ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>' . $this->servers . '</ServerNames>';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<TrustedRootCA>" . $certAuthority['sha1'] . "</TrustedRootCA>\n";
            }
        }
        $profileFileCont .= '</ServerValidation>
';
        return($profileFileCont);
    }
    
    private function tlsConfig() {
        $profileFileCont = '
<Config xmlns:baseEap="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1"
  xmlns:eapTls="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV1">
<baseEap:Eap>
<baseEap:Type>13</baseEap:Type>
<eapTls:EapType>
<eapTls:CredentialsSource>
<eapTls:CertificateStore />
</eapTls:CredentialsSource>
';    
        $profileFileCont .= $this->tlsServerValidation();
        if (\core\common\Entity::getAttributeValue($this->attributes, 'eap-specific:tls_use_other_id', 0) === 'on') {
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
        return($profileFileCont);
    }

    private function msTtlsConfig() {        
        $profileFileCont = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapTtls xmlns="http://www.microsoft.com/provisioning/EapTtlsConnectionPropertiesV1">
';
        $profileFileCont .= $this->msTtlsServerValidation();
        $profileFileCont .= '<Phase2Authentication>
';
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP) {
            $profileFileCont .= '<PAPAuthentication /> ';
        }
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            $profileFileCont .= '<MSCHAPv2Authentication>
<UseWinlogonCredentials>false</UseWinlogonCredentials>
</MSCHAPv2Authentication>
';
        }
        $profileFileCont .= '</Phase2Authentication>
<Phase1Identity>
';
        if ($this->useAnon) {
            $profileFileCont .= '<IdentityPrivacy>true</IdentityPrivacy>
';
            $profileFileCont .= '<AnonymousIdentity>' . $this->outerId . '</AnonymousIdentity>
                ';
        } else {
            $profileFileCont .= '<IdentityPrivacy>false</IdentityPrivacy>
';
        }
        $profileFileCont .= '</Phase1Identity>
</EapTtls>
</Config>
';
        return($profileFileCont);
    }
    
    private function glTtlsConfig() {        
        $profileFileCont = '
<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EAPIdentityProviderList xmlns="urn:ietf:params:xml:ns:yang:ietf-eap-metadata">
<EAPIdentityProvider ID="' . $this->deviceUUID . '" namespace="urn:UUID">

<ProviderInfo>
<DisplayName>' . $this->translateString($this->attributes['general:instname'][0], $this->codePage) . '</DisplayName>
</ProviderInfo>
<AuthenticationMethods>
<AuthenticationMethod>
<EAPMethod>21</EAPMethod>
<ClientSideCredential>
<allow-save>true</allow-save>
';
        if ($this->useAnon) {
            if ($this->outerUser == '') {
                $profileFileCont .= '<AnonymousIdentity>@</AnonymousIdentity>';
            } else {
                $profileFileCont .= '<AnonymousIdentity>' . $this->outerId . '</AnonymousIdentity>';
            }
        }
        $profileFileCont .= '</ClientSideCredential>
';
        $profileFileCont .= $this->glTtlsServerValidation();
        $profileFileCont .= '
<InnerAuthenticationMethod>
<NonEAPAuthMethod>' . \core\common\EAP::eapDisplayName($this->selectedEap)['INNER'] . '</NonEAPAuthMethod>
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
        return($profileFileCont);
    }

    private function peapConfig() {
        $nea = (\core\common\Entity::getAttributeValue($this->attributes, 'media:wired', 0) == 'on') ? 'true' : 'false';
        $profileFileCont = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
';
        $profileFileCont .= $this->peapServerValidation();
        $profileFileCont .= '
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
        if ($this->useAnon) {
            $profileFileCont .= '<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
';
            if ($this->outerUser == '') {
                $profileFileCont .= '<AnonymousUserName/>
';
            } else {
                $profileFileCont .= '<AnonymousUserName>' . $this->outerUser . '</AnonymousUserName>
                ';
            }
            $profileFileCont .= '</IdentityPrivacy>
</PeapExtensions>
';
        }
        $profileFileCont .= '</EapType>
</Eap>
</Config>
';
        return($profileFileCont);
    }
    
    private function pwdConfig() {
        return('<ConfigBlob></ConfigBlob>');
    }

    private function prepareEapConfig() {
        if ($this->useAnon) {
            $this->outerUser = $this->attributes['internal:anon_local_value'][0];
            $this->outerId = $this->outerUser . '@' . $this->attributes['internal:realm'][0];
        }
        if (isset($this->options['args']) && $this->options['args'] == 'gl') {
            $this->useGeantLink = TRUE;
        } else {
            $this->useGeantLink = FALSE;
        }
        $profileFileCont = $this->eapConfigHeader();

        switch ($this->selectedEap['OUTER']) {
            case \core\common\EAP::TLS:
                $profileFileCont .= $this->tlsConfig();
                break;
            case \core\common\EAP::PEAP:
                $profileFileCont .= $this->peapConfig();
                break;
            case \core\common\EAP::TTLS:
                if ($this->useGeantLink) {
                    $profileFileCont .= $this->glTtlsConfig();
                } else {
                    $profileFileCont .= $this->msTtlsConfig();
                }
                break;
            case \core\common\EAP::PWD:
                $profileFileCont .= $this->pwdConfig();
                break;
            default:
                break;
        }
        return(['win' => $profileFileCont . '</EapHostConfig></EAPConfig>']);
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
    private function writeWLANprofile($wlanProfileName, $ssid, $auth, $encryption, $eapConfig, $profileNumber, $hs20 = FALSE) {
        $profileFileCont = '<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
<name>' . $wlanProfileName . '</name>
<SSIDConfig>
<SSID>
<name>' . $ssid . '</name>
</SSID>
<nonBroadcast>true</nonBroadcast>
</SSIDConfig>';
        if ($hs20) {
            $profileFileCont .= $this->addConsortia();
        }
        $profileFileCont .= '
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
        foreach ($caArray as $certAuthority) {
            $store = $certAuthority['root'] ? "root" : "ca";
            $fcontentsCerts .= '!insertmacro install_ca_cert "' . $certAuthority['file'] . '" "' . $certAuthority['sha1'] . '" "' . $store . "\"\n";
        }
        fwrite($fileHandleCerts, $fcontentsCerts);
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
    private $caArray;
    private $useAnon;
    private $servers;
    private $outerUser;
    private $outerId;

}

