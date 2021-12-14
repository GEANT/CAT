<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * This file creates MS Windows 8 and 10 installers
 * It supports EAP-TLS, TTLS (both native and GEANTLink), PEAP.
 * Other EAP methods could be added.
 * 
 * The file is an interface between the global CAT system and individual EAP
 * methods modules. It also performs global operations like preparing
 * and saving cerificates and generating the installers.
 * 
 * Adding a new EAP handler requres defining an extension of the MsEapProfile
 * class. Such an extension is required to define a public getConfig method
 * returning a valid Windows XML <Config> element.
 * Extensions to Files/common.inc will also be required.
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\ms;
use Exception;

class DeviceW8W10 extends \devices\ms\WindowsCommon
{
    public function __construct()
    {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->setSupportedEapMethods([
            \core\common\EAP::EAPTYPE_TLS,
            \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
            \core\common\EAP::EAPTYPE_TTLS_PAP,
            \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
            \core\common\EAP::EAPTYPE_SILVERBULLET
        ]);
        $this->profileNames = [];
        $this->specialities['internal:use_anon_outer'][serialize(\core\common\EAP::EAPTYPE_PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
        $this->specialities['media:openroaming'] = _("While OpenRoaming can be configured, it is possible that the Wi-Fi hardware does not support it; then the network definition is ignored.");
        $this->specialities['media:consortium_OI'] = _("While Passpoint networks can be configured, it is possible that the Wi-Fi hardware does not support it; then the network definition is ignored.");
        \core\common\Entity::outOfThePotatoes();
    } 
    
    /**
     * create the actual installer executable
     * 
     * @return string filename of the generated installer
     *
     */  
    public function writeInstaller()
    {
        \core\common\Entity::intoThePotatoes();
        $this->prepareInstallerLang();
        $this->setupEapConfig();
        $setWired = isset($this->attributes['media:wired'][0]) && $this->attributes['media:wired'][0] == 'on' ? 1 : 0;
        $this->iterator = 0;
        $fcontentsProfile = '';
        $this->createProfileDir();
        foreach ($this->attributes['internal:networks'] as $networkName => $oneNetwork) {
            if ($this::separateHS20profiles === true) {
                $fcontentsProfile .= $this->saveNetworkProfileSeparateHS($networkName, $oneNetwork);
            } else {
                $fcontentsProfile .= $this->saveNetworkProfileJoinedHS($networkName, $oneNetwork);
            }
        }
        file_put_contents('profiles.nsh', $fcontentsProfile);
        $delSSIDs = $this->attributes['internal:remove_SSID'];
        $delProfiles = [];
        foreach ($delSSIDs as $ssid => $cipher) {
            if ($cipher == 'DEL') {
                $delProfiles[] = $ssid;
            }
            if ($cipher == 'TKIP') {
                $delProfiles[] = $ssid.' (TKIP)';
            }
        }
        $this->writeAdditionalDeletes($delProfiles);
        if ($setWired) {
            $this->loggerInstance->debug(4, "Saving LAN profile\n");
            $windowsProfile = $this->generateLANprofile();
            $this->saveProfile($windowsProfile);
        }
        $this->saveCerts();
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $this->writeClientP12File();
        }
        $this->copyFiles($this->selectedEap);
        $this->saveLogo();
        $this->writeMainNSH($this->selectedEap, $this->attributes);
        $this->compileNSIS();
        $installerPath = $this->signInstaller();
        \core\common\Entity::outOfThePotatoes();
        return $installerPath;
    }
    
    private function createProfileDir()
    {
        if (!is_dir('profiles')) {
            mkdir('profiles');
        }
    }
    
    /**
     * If separateHS20profiles is true then we should be saving OID and SSID
     * profiles separately. OID profiles should be considered optionl, i.e.
     * the installer should not report installation failure (??). If we decide
     * that such installation failures should be silent then it is enough if
     * these profiles are marked as hs20 and no "nohs" profiles are created
     */
    
    private function saveNetworkProfileSeparateHS($profileName, $network)
    {
        $out = '';
        if (!empty($network['ssid'])) {
            $windowsProfileSSID = $this->generateWlanProfile($profileName, $network['ssid'], 'WPA2', 'AES', [], false);
            $this->saveProfile($windowsProfileSSID, $this->iterator, true);
            $out = "!insertmacro define_wlan_profile \"$profileName\" \"AES\" 0\n";
            $this->iterator++;
            $profileName .= " via partner";
        }
        if (!empty($network['oi'])) {
            $windowsProfileHS = $this->generateWlanProfile($profileName, ['cat-passpoint-profile'], 'WPA2', 'AES', $network['oi'], true);
            $this->saveProfile($windowsProfileHS, $this->iterator, true);
            $out .= "!insertmacro define_wlan_profile \"$profileName\" \"AES\" 1\n";
            $this->iterator++;
        }
        return($out);
    }
    
    /**
     * If separateHS20profiles is false then we should be saving a hs20 profile
     * containing both OIDs and SSIDs. In addiotion we should also be saving
     * a nohs_... profile. When  the installer runs it first tries the normal
     * profile and if this fails it will try the nohs (if one exists)
     */
    
    private function saveNetworkProfileJoinedHS($profileName, $network)
    {
        $oiOnly = false;
        if ($network['ssid'] == []) {
            $oiOnly = true;
            $network['ssid'] = ['cat-passpoint-profile'];
        }
        $windowsProfile = $this->generateWlanProfile($profileName, $network['ssid'], 'WPA2', 'AES', $network['oi'], true);
        $this->saveProfile($windowsProfile, $this->iterator, true);
        if (!$oiOnly) {
            $windowsProfile = $this->generateWlanProfile($profileName, $network['ssid'], 'WPA2', 'AES', [], false);
            $this->saveProfile($windowsProfile, $this->iterator, false);
        }
        $this->iterator++;
        return("!insertmacro define_wlan_profile \"$profileName\" \"AES\" 1\n");
    }

    private function saveLogo()
    {
        $fedLogo = $this->attributes['fed:logo_file'] ?? NULL;
        $idpLogo = $this->attributes['internal:logo_file'] ?? NULL;
        $this->combineLogo($idpLogo, $fedLogo);
    }

    private function writeMainNSH($eap, $attr)
    {
        $this->loggerInstance->debug(4, "writeMainNSH");
        $this->loggerInstance->debug(4, $attr);
        $this->loggerInstance->debug(4, "Device_id = ".$this->device_id."\n");
        $fcontents = "!define W8\n";
        if ($this->device_id == 'w10') {
            $fcontents .= "!define W10\n";
        }
        $fcontents .= "Unicode true\n";
        if ($this->useGeantLink) {
            $eapStr = 'GEANTLink';
        } else {
            $eapStr = \core\common\EAP::eapDisplayName($this->selectedEap)['OUTER'];
        }
        if (isset($this->tlsOtherUsername) && $this->tlsOtherUsername == 1) {
            $fcontents .= "!define PFX_USERNAME\n";
        }
        if ($eap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $fcontents .= "!define SILVERBULLET\n";
        }
        $fcontents .= '!define '.$eapStr;
        $fcontents .= "\n".'!define EXECLEVEL "user"';
        $fcontents .= $this->writeNsisDefines($attr);
        file_put_contents('main.nsh', $fcontents);
    }

    
    private function copyFiles($eap)
    {
        $this->loggerInstance->debug(4, "copyFiles start\n");
        $this->copyBasicFiles();
        switch ($eap["OUTER"]) {
            case \core\common\EAP::TTLS:
                if ($this->useGeantLink) {
                    $this->copyGeantLinkFiles();
                } else {
                    $this->copyStandardNsi();
                }
                break;
            default:
                $this->copyStandardNsi();
        }
        $this->loggerInstance->debug(4, "copyFiles end\n");
        return true;
    }
    
    private function copyStandardNsi()
    {
        if (!$this->translateFile('eap_w8.inc', 'cat.NSI')) {
            throw new Exception("Translating needed file eap_w8.inc failed!");
        }
    }
    
    private function saveCerts()
    {
        $caArray = $this->saveCertificateFiles('der');
        $fcontentsCerts = '';
        $fileHandleCerts = fopen('certs.nsh', 'w');
        if ($fileHandleCerts === false) {
            throw new Exception("Unable to open new certs.nsh file for writing CAs.");
        }
        foreach ($caArray as $certAuthority) {
            $store = $certAuthority['root'] ? "root" : "ca";
            $fcontentsCerts .= '!insertmacro install_ca_cert "'.$certAuthority['file'].'" "'.$certAuthority['sha1'].'" "'.$store."\"\n";
        }
        fwrite($fileHandleCerts, $fcontentsCerts);
        fclose($fileHandleCerts);
    }

    /* saveProvile writes a LAN or WLAN profile
     * @param string $profile the XML content to be saved
     * @param int $profileNumber the profile index or NULL to indicate a LAN profile
     * @param boolean $hs20 for WLAN profiles indicates if use the nohs prefix
     */
    private function saveProfile($profile, $profileNumber = NULL, $hs20 = false)
    {
        if ($hs20) {
            $prefix = 'w';
        } else {
            $prefix = 'nohs_w';
        }
        if (is_null($profileNumber)) {
            $prefix = '';
            $suffix = '';
        } else {
            $suffix = "-$profileNumber";
        }
        $xmlFname = "profiles/".$prefix."lan_prof".$suffix.".xml";
        $this->loggerInstance->debug(4, "Saving profile to ".$xmlFname."\n");
        file_put_contents($xmlFname, $profile);
    }

    /**
     * Selects the approprate handler for a given EAP type and retirns
     * an initiated object
     * 
     * @return a profile object
     */
    
    private function setEapObject()
    {        
        switch ($this->selectedEap['OUTER']) {
            case \core\common\EAP::TTLS:
                if ($this->useGeantLink) {
                    return(new GeantLinkTtlsProfile());
                } else {
                    return(new MsTtlsProfile());
                }
            case \core\common\EAP::PEAP:
                return(new MsPeapProfile());
            case \core\common\EAP::TLS:
                return(new MsTlsProfile());
            default:
                // use Exception here
                break;
        }
    }
    
    private function setupEapConfig() {
        $servers = empty($this->attributes['eap:server_name']) ? '' : implode(';', $this->attributes['eap:server_name']);
        $outerId = $this->determineOuterIdString();
        $nea = (\core\common\Entity::getAttributeValue($this->attributes, 'media:wired', 0) === 'on') ? 'true' : 'false';
        $otherTlsName = \core\common\Entity::getAttributeValue($this->attributes, 'eap-specific:tls_use_other_id', 0) === 'on' ? 'true' : 'false';
        $this->useGeantLink = \core\common\Entity::getAttributeValue($this->attributes, 'device-specific:geantlink', $this->device_id)[0] === 'on' ? true : false;
        $eapConfig = $this->setEapObject();
        $eapConfig->setInnerType($this->selectedEap['INNER']);
        $eapConfig->setInnerTypeDisplay(\core\common\EAP::eapDisplayName($this->selectedEap)['INNER']);
        $eapConfig->setCAList($this->getAttribute('internal:CAs')[0]);
        $eapConfig->setServerNames($servers);
        $eapConfig->setOuterId($outerId);
        $eapConfig->setNea($nea);
        $eapConfig->setDisplayName($this->translateString($this->attributes['general:instname'][0]));
        $eapConfig->setIdPId($this->deviceUUID);
        $eapConfig->setOtherTlsName($otherTlsName);
        $eapConfig->setConfig();
        $this->eapConfigObject = $eapConfig;
    } 
        
    private function generateWlanProfile($networkName, $ssids, $authentication, $encryption, $ois, $hs20 = false)
    {
        if (empty($this->attributes['internal:realm'][0])) {
            $domainName = CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-domainname-fallback'];
        } else {
            $domainName = $this->attributes['internal:realm'][0];
        }
        $wlanProfile = new MsWlanProfile();
        $wlanProfile->setName($networkName);       
        $wlanProfile->setEncryption($authentication, $encryption);
        $wlanProfile->setSSIDs($ssids);
        $wlanProfile->setHS20($hs20);
        $wlanProfile->setOIs($ois);
        $wlanProfile->setDomainName($domainName);
        $wlanProfile->setEapConfig($this->eapConfigObject);
        return($wlanProfile->writeWLANprofile());
    }
    
    private function generateLanProfile()
    {
        $lanProfile = new MsLanProfile();
        $lanProfile->setEapConfig($this->eapConfigObject);
        return($lanProfile->writeLANprofile());
    }

    private $eapTypeId;
    private $eapAuthorId;
    private $eapConfigObject;
    private $profileNames;
    private $iterator;
}



