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
 * This file contains the installer for iOS devices and Apple 10.7 Lion
 *
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Developer
 */

namespace devices\apple_mobileconfig;

use \Exception;

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
abstract class MobileconfigSuperclass extends \core\DeviceConfig
{

    /**
     * institution name in preferred language
     * 
     * @var string
     */
    private $instName;
    
    /**
     * profile name in preferred language
     * 
     * @var string
     */
    private $profileName;
    
    /**
     * institution name, massaged to make it fit into the XML payload descriptor
     * 
     * @var string
     */
    private $massagedInst;
    
    /**
     * profile name, massaged to make it fit into the XML payload descriptor
     * 
     * @var string
     */
    private $massagedProfile;
    
    /**
     * country name, massaged to make it fit into the XML payload descriptor
     * 
     * @var string
     */
    private $massagedCountry;
    
    /**
     * consortium name, massaged to make it fit into the XML payload descriptor
     * 
     * @var string
     */
    private $massagedConsortium;
    
    /**
     * current language, without any .CHARSET suffixes
     * 
     * @var string
     */
    private $lang;
    
    /**
     * if we include a client certificate (silverbullet), the UUID of the certificate
     * 
     * @var string
     */
    private $clientCertUUID;

    /**
     * iterator to uniquely identify every Wi-Fi payload with a suffix in the file
     * 
     * @var integer
     */
    private $serial;
    
    /**
     * iterator to uniquely identify every removal payload with a suffix in the file
     * @var integer
     */
    private $removeSerial;
    
    /**
     * iterator to uniquely identify evera CA payload with a suffix in the file
     * 
     * @var integer
     */
    private $caSerial;
    
    /**
     * we need to be cautious not to include a CA twice, even if the admin
     * included it twice in the CAT config. This variable takes note of all
     * CA certs we already added to the file.
     * 
     * @var array
     */
    private $CAsAccountedFor = [];
    
    /**
     * we need a globally valid domain anchor, independent of the consortium
     */
    const IPHONE_PAYLOAD_PREFIX = "org.1x-config";
    

    /**
     * construct with the standard set of EAP methods we support, and preload
     * specialities
     */
    public function __construct()
    {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        // that's what all variants support. Sub-classes can change it.
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        foreach(\core\common\EAP::listKnownEAPTypes() as $eapType) {
            if ($eapType->isPasswordRequired() || $eapType->isPasswordOptional()) {
                $this->specialities['internal:verify_userinput_suffix'][serialize($eapType->getArrayRep())] = _("It is not possible to actively verify the user input for suffix match; but if there is no 'Terms of Use' configured, the installer will display a corresponding hint to the user instead.");
                $this->specialities['media:openroaming'][serialize($eapType->getArrayRep())] = _("OpenRoaming is not provisioned due to severe UI limitations during install time.");
                $this->specialities['media:consortium_OI'][serialize($eapType->getArrayRep())] = _("Passpoint networks are not provisioned due to severe UI limitations during install time.");
            }
        }
        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * massage a name so that it becomes acceptable inside the plist XML
     * 
     * @param string $input the literal name
     * @return string
     */
    private function massageName($input)
    {
        return htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', $input))), ENT_XML1, 'UTF-8');
    }

    /**
     * the general part of a mobileconfig file in plist format
     * @return string
     */
    private function generalPayload()
    {
        \core\common\Entity::intoThePotatoes();
        $tagline = sprintf(_("Network configuration profile '%s' of '%s' - provided by %s"), htmlspecialchars($this->profileName, ENT_XML1, 'UTF-8'), htmlspecialchars($this->instName, ENT_XML1, 'UTF-8'), \config\ConfAssistant::CONSORTIUM['display_name']);

        $eapType = $this->selectedEap;
        // simpler message for silverbullet
        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            $tagline = sprintf(_("%s configuration for IdP '%s' - provided by %s"), \core\ProfileSilverbullet::PRODUCTNAME, htmlspecialchars($this->instName, ENT_XML1, 'UTF-8'), \config\ConfAssistant::CONSORTIUM['display_name']);
        }

        $retval = "
      <key>PayloadDescription</key>
         <string>$tagline</string>
      <key>PayloadDisplayName</key>
         <string>" . \config\ConfAssistant::CONSORTIUM['display_name'] . "</string>
      <key>PayloadIdentifier</key>
         <string>" . self::IPHONE_PAYLOAD_PREFIX . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang</string>
      <key>PayloadOrganization</key>
         <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['general:instname'][0]), ENT_XML1, 'UTF-8') . ( $this->attributes['internal:profile_count'][0] > 1 ? " (" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['profile:name'][0]), ENT_XML1, 'UTF-8') . ")" : "") . "</string>
      <key>PayloadType</key>
         <string>Configuration</string>
      <key>PayloadUUID</key>
         <string>" . \core\common\Entity::uuid('', self::IPHONE_PAYLOAD_PREFIX . $this->massagedConsortium . $this->massagedCountry . $this->massagedInst . $this->massagedProfile) . "</string>
      <key>PayloadVersion</key>
         <integer>1</integer>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    const FILE_START = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\"
\"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
<dict>";
    const FILE_END = "</dict></plist>";
    const BUFFER_CONSENT_PRE = "
      <key>ConsentText</key>
         <dict>
            <key>default</key>
               <string>";
    const BUFFER_CONSENT_POST = "</string>
         </dict>
         ";

    /**
     * creates a ConsentText block if either Terms of Use are specified or the
     * user input hints should be displayed. Otherwise, produces nothing.
     * 
     * @return string
     */
    protected function consentBlock()
    {
        \core\common\Entity::intoThePotatoes();
        if (isset($this->attributes['support:info_file'])) {
            return MobileconfigSuperclass::BUFFER_CONSENT_PRE . htmlspecialchars(iconv("UTF-8", "UTF-8//TRANSLIT", $this->attributes['support:info_file'][0]), ENT_XML1, 'UTF-8') . MobileconfigSuperclass::BUFFER_CONSENT_POST;
        }
        if ($this->attributes['internal:verify_userinput_suffix'][0] != 0) {
            if ($this->attributes['internal:hint_userinput_suffix'][0] != 0) {
                $retval = MobileconfigSuperclass::BUFFER_CONSENT_PRE . sprintf(_("Important Notice: your username MUST end exactly with '...@%s' !"), $this->attributes['internal:realm'][0]) . MobileconfigSuperclass::BUFFER_CONSENT_POST;
                \core\common\Entity::outOfThePotatoes();
                return $retval;
            } else { 
            if (strlen($this->attributes['internal:realm'][0]) > 0) {
                /// note space between variable and exclamation mark - makes sure users don't mistakenly think the exclamation mark is part of the required username!
                $retval = MobileconfigSuperclass::BUFFER_CONSENT_PRE . sprintf(_("Important Notice: your username MUST contain an '@' and end with ...%s !"), $this->attributes['internal:realm'][0]) . MobileconfigSuperclass::BUFFER_CONSENT_POST;
                \core\common\Entity::outOfThePotatoes();
                return $retval;
            }
            $retval = MobileconfigSuperclass::BUFFER_CONSENT_PRE . _("Important Notice: your username MUST be in the form of xxx@yyy where the yyy is a common suffix identifying your Identity Provider. Please find out what to use there and enter the username in the correct format.") . MobileconfigSuperclass::BUFFER_CONSENT_POST;
            \core\common\Entity::outOfThePotatoes();
            return $retval;
            }
        }
        \core\common\Entity::outOfThePotatoes();
        return "";
    }

    /**
     * create the actual installer XML file
     * 
     * @return string filename of the generated installer
     */
    public function writeInstaller()
    {
        \core\common\Entity::intoThePotatoes();

        $this->loggerInstance->debug(4, "mobileconfig Module Installer start\n");

        // remove spaces and slashes (filename!), make sure it's simple ASCII only, then lowercase it
        // also escape htmlspecialchars
        // not all names and profiles have a name, so be prepared

        $this->loggerInstance->debug(5, "List of available attributes: " . var_export($this->attributes, TRUE));

        $this->instName = $this->attributes['general:instname'][0] ?? _("Unnamed Organisation");
        $this->profileName = $this->attributes['profile:name'][0] ?? _("Unnamed Profile");

        $this->massagedInst = $this->massageName($this->instName);
        $this->massagedProfile = $this->massageName($this->profileName);
        $this->massagedCountry = $this->massageName($this->attributes['internal:country'][0]);
        $this->massagedConsortium = $this->massageName(\config\ConfAssistant::CONSORTIUM['name']);
        $this->lang = preg_replace('/\..+/', '', setlocale(LC_ALL, "0"));

        $eapType = $this->selectedEap;

        $outputXml = self::FILE_START;
        $outputXml .= "<key>PayloadContent</key>
         <array>";

        // if we are in silverbullet, we will need a whole own block for the client credential
        // and also for the profile expiry

        $this->clientCertUUID = NULL;
        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            $blockinfo = $this->clientP12Block();
            $outputXml .= $blockinfo['block'];
            $this->clientCertUUID = $blockinfo['UUID'];
        }

        $outputXml .= $this->allCA();

        $outputXml .= $this->allNetworkBlocks();

        $outputXml .= "</array>";
        $outputXml .= $this->generalPayload();
        $outputXml .= $this->consentBlock();

        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            $outputXml .= $this->expiryBlock();
        }
        $outputXml .= self::FILE_END;

        file_put_contents('installer_profile', $outputXml);

        $fileName = $this->installerBasename . '.mobileconfig';

        if (!$this->sign) {
            rename("installer_profile", $fileName);
            \core\common\Entity::outOfThePotatoes();
            return $fileName;
        }
        // still here? Then we are signing.
        $signing = system($this->sign . " installer_profile '$fileName' > /dev/null");
        if ($signing === FALSE) {
            $this->loggerInstance->debug(2, "Signing the mobileconfig installer $fileName FAILED!\n");
        }
        \core\common\Entity::outOfThePotatoes();
        return $fileName;
    }

    /**
     * produces the HTML text to be displayed when clicking on the "help" button
     * besides the download button.
     * 
     * @return string
     */
    public function writeDeviceInfo()
    {
        \core\common\Entity::intoThePotatoes();
        $ssidCount = 0;
        $oiCount = 0;
        foreach ($this->attributes['internal:networks'] as $netDetail) {
            $ssidCount = $ssidCount + count($netDetail['ssid']);
            $oiCount = $oiCount + count($netDetail['oi']);
        }
        $certCount = count($this->attributes['internal:CAs'][0]);
        $out = "<p>" . _("For best results, please use the built-in browser (Safari) to open the configuration file.") . "</p>";
        $out .= "<p>";
        $out .= _("The profile will install itself after you click (or tap) the button. You will be asked for confirmation/input at several points:");
        $out .= "<ul>";
        $out .= "<li>" . _("to install the profile") . "</li>";
        $out .= "<li>" . ngettext("to accept the server certificate authority", "to accept the server certificate authorities", $certCount);
        if ($certCount > 1) {
            $out .= " " . sprintf(_("(%d times)"), $certCount);
        }
        $out .= "</li>";
        $out .= "<li>" . _("to enter the username and password you have been given by your organisation");
        if ($ssidCount > 1) {
            $out .= " " . sprintf(_("(%d times each, because %d SSIDs and %d Passpoint networks are installed)"), $ssidCount+$oiCount, $ssidCount, $oiCount);
        }
        $out .= "</li>";
        $out .= "</ul>";
        $out .= "</p>";
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }

    /**
     * collates a list of the UUIDs of all the CAs which are to be included in
     * the mobileconfig file
     * 
     * @return array
     */
    private function listCAUuids()
    {
        $retval = [];
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $retval[] = $ca['uuid'];
        }
        return $retval;
    }

    /**
     * This is the XML structure subtree of a Network block which contains the
     * settings specific to Passpoint
     * 
     * @param array  $consortiumOi list of consortiumOi to put into structure
     * @param string $oiName       the pretty-print name of the RCOI
     * @return string
     */
    private function passPointBlock($consortiumOi, $oiName)
    {
        $retval = "
               <key>IsHotspot</key>
               <true/>
               <key>ServiceProviderRoamingEnabled</key>
               <true/>
               <key>DisplayedOperatorName</key>
               <string>" . $oiName . "</string>";
        // if we don't know the realm, omit the entire DomainName key
        if (isset($this->attributes['internal:realm'])) {
            $retval .= "<key>DomainName</key>
               <string>";
            $retval .= $this->attributes['internal:realm'][0];
            $retval .= "</string>
                ";
        }
        $retval .= "                <key>RoamingConsortiumOIs</key>
                <array>";

        foreach ($consortiumOi as $oneCons) {
            $retval .= "<string>" . strtoupper($oneCons) . "</string>";
        }

        $retval .= "</array>";
        // this is an undocumented value found on the net. Does it do something useful?
        $retval .= "<key>_UsingHotspot20</key>
                <true/>
                ";
        // do we need to set NAIRealmName ? In Rel 1, probably yes, in Rel 2, 
        // no because ConsortiumOI is enough.
        // but which release is OS X doing? And what should we fill in, given
        // that we have thousands of realms? Try just eduroam.org
        // 
        // tests from Hideaki suggest it's better not to set it; if Roaming
        // consortium OI and NAIRealmNames are both set, connecting to a hotspot
        // with just RCOI does not work
        /* if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam") {
            $retval .= "<key>NAIRealmNames</key>
                <array>
                    <string>eduroam.org</string>
                </array>";
        }*/
        return $retval;
    }

    /**
     * produces the EAP sub-block of a Network block
     * 
     * @param array $eapType EAP type in array notation
     * @return string
     */
    private function eapBlock($eapType)
    {
        $realm = $this->determineOuterIdString();
        $retval = "<key>EAPClientConfiguration</key>
                  <dict>
                      <key>AcceptEAPTypes</key>
                         <array>
                            <integer>" . $eapType['OUTER'] . "</integer>
                         </array>
                      <key>EAPFASTProvisionPAC</key>
                            <true />
                      <key>EAPFASTUsePAC</key>
                            <true />
                      <key>EAPFastProvisionPACAnonymously</key>
                            <false />
                      <key>OneTimeUserPassword</key>
                            <false />
";
        if ($realm !== NULL) {
            $retval .= "<key>OuterIdentity</key>
                                    <string>" . htmlspecialchars($realm, ENT_XML1, 'UTF-8') . "</string>
";
        }
        $retval .= "<key>PayloadCertificateAnchorUUID</key>
                         <array>";
        foreach ($this->listCAUuids() as $uuid) {
            if (in_array($uuid, $this->CAsAccountedFor)) {
                $retval .= "
<string>$uuid</string>";
            }
        }
        $retval .= "
                         </array>
                      <key>TLSAllowTrustExceptions</key>
                         <false />
                      <key>TLSTrustedServerNames</key>
                         <array>";
        foreach ($this->attributes['eap:server_name'] as $commonName) {
            $retval .= "
<string>$commonName</string>";
        }
        $retval .= "
                         </array>";
        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            $retval .= "<key>UserName</key><string>" . $this->clientCert["certObject"]->username . "</string>";
        }
        $retval .= "
                      <key>TTLSInnerAuthentication</key>
                         <string>" . ($eapType['INNER'] == \core\common\EAP::NE_PAP ? "PAP" : "MSCHAPv2") . "</string>
                   </dict>";
        return $retval;
    }

    /**
     * produces the Proxy sub-block of a Network block
     * 
     * @return string
     */
    protected function proxySettings()
    {
        $buffer = "<key>ProxyType</key>";
        if (isset($this->attributes['media:force_proxy'])) {
            // find the port delimiter. In case of IPv6, there are multiple ':' 
            // characters, so we have to find the LAST one
            $serverAndPort = explode(':', strrev($this->attributes['media:force_proxy'][0]), 2);
            // characters are still reversed, invert on use!
            $buffer .= "<string>Manual</string>
                  <key>ProxyServer</key>
                  <string>" . strrev($serverAndPort[1]) . "</string>
                  <key>ProxyServerPort</key>
                  <integer>" . strrev($serverAndPort[0]) . "</integer>
                  <key>ProxyPACFallbackAllowed</key>
                  <false/>";
        } else {
            $buffer .= "<string>Auto</string>
                  <key>ProxyPACFallbackAllowed</key>
                  <true/>";
        }
        return $buffer;
    }

    /**
     * iOS 8 introduced the new value "WPA2" to lock out WPA ("1"). Make sure
     * we use it for the most recent gen of mobileconfig for iOS.
     * 
     * According to reports on the eduroam dev ML, also recent macOS supports it
     * so add it there, too (spec is silent on macOS versions).
     * 
     * @return string either "WPA" or "WPA2
     */
    private function encryptionString() {
        if (
                get_class($this) == "devices\apple_mobileconfig\DeviceMobileconfigIos12plus" || 
                get_class($this) == "devices\apple_mobileconfig\DeviceMobileconfigOsX"
           ) {
                    return "WPA2";
                } else {
                    return "WPA";
                }
    }
    
    /**
     * produces an entire Network block
     * 
     * @param int                  $blocktype      which type of network block is this?
     * @param string|array|boolean $toBeConfigured variable part of the config. Single SSID or list of ConsortiumOi
     * @param string               $prettyName     name with which to present the network to users
     * @return string
     * @throws Exception
     */
    private function networkBlock($blocktype, $toBeConfigured, $prettyName)
    {
        \core\common\Entity::intoThePotatoes();
        $eapType = $this->selectedEap;
        switch ($blocktype) {
            case MobileconfigSuperclass::NETWORK_BLOCK_TYPE_SSID:
                if (!is_string($toBeConfigured)) {
                    throw new Exception("SSID must be a string!");
                }
                $escapedSSID = htmlspecialchars($toBeConfigured, ENT_XML1, 'UTF-8');
                $payloadIdentifier = "wifi." . $this->serial;
                $payloadShortName = sprintf(_("%s - SSID %s"), $prettyName, $escapedSSID);
                $payloadName = sprintf(_("%s configuration for network name %s"), $prettyName, $escapedSSID);
                $encryptionTypeString = $this->encryptionString();
                $setupModesString = "";
                $wifiNetworkIdentification = "<key>SSID_STR</key>
                  <string>$escapedSSID</string>";
                break;
            case MobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED:
                if (!is_bool($toBeConfigured)) {
                    throw new Exception("We expected a TRUE here!");
                }
                $payloadIdentifier = "firstactiveethernet";
                $payloadShortName = sprintf(_("%s - Wired Network"), $prettyName);
                $payloadName = sprintf(_("%s configuration for wired network"), $prettyName);
                $encryptionTypeString = "any";
                $setupModesString = "
               <key>SetupModes</key>
                  <array>
                     <string>System</string>
                  </array>";
                $wifiNetworkIdentification = "";
                break;
            case MobileconfigSuperclass::NETWORK_BLOCK_TYPE_CONSORTIUMOIS:
                if (!is_array($toBeConfigured)) {
                    throw new Exception("ConsortiumOI must be an array!");
                }
                if (count($toBeConfigured) == 0) {
                    return "";
                }
                $payloadIdentifier = "hs20.".implode('-',$toBeConfigured);
                $payloadShortName = sprintf(_("%s - RCOI"), $prettyName);
                $payloadName = sprintf(_("%s configuration (Passpoint RCOI)"),$prettyName);
                $encryptionTypeString = $this->encryptionString();
                $setupModesString = "";
                $wifiNetworkIdentification = $this->passPointBlock($toBeConfigured, $prettyName);
                break;
            default:
                throw new Exception("This type of network block is unknown!");
        }
        $retval = "<dict>";
        $retval .= $this->eapBlock($eapType);
        $retval .= "<key>EncryptionType</key>
                  <string>$encryptionTypeString</string>
               <key>HIDDEN_NETWORK</key>
                  <false />
               <key>PayloadDescription</key>
                  <string>$payloadName</string>
               <key>PayloadDisplayName</key>
                  <string>$payloadShortName</string>
               <key>PayloadIdentifier</key>
                  <string>" . self::IPHONE_PAYLOAD_PREFIX . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.$payloadIdentifier</string>
               <key>PayloadOrganization</key>
                  <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
                  <string>com.apple." . ($blocktype == MobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED ? "firstactiveethernet" : "wifi") . ".managed</string>";
        $retval .= $this->proxySettings();
        $retval .= $setupModesString;
        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            if ($this->clientCertUUID === NULL) {
                throw new Exception("Silverbullet REQUIRES a client certificate and we need to know the UUID!");
            }
            $retval .= "<key>PayloadCertificateUUID</key>
                        <string>$this->clientCertUUID</string>";
        }
        $retval .= "
               <key>PayloadUUID</key>
                  <string>" . \core\common\Entity::uuid() . "</string>
               <key>PayloadVersion</key>
                  <integer>1</integer>
                  $wifiNetworkIdentification</dict>";
        $this->serial = $this->serial + 1;
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * Produces a Network block which sets a network to manual join (we don't
     * get any closer to removing a network in mobileconfig)
     * 
     * @param string $ssid the SSID to set to manual join only
     * @return string
     */
    private function removenetworkBlock($ssid)
    {
        \core\common\Entity::intoThePotatoes();
        $retval = "
<dict>
	<key>AutoJoin</key>
	<false/>
	<key>EncryptionType</key>
	<string>None</string>
	<key>HIDDEN_NETWORK</key>
	<false/>
	<key>IsHotspot</key>
	<false/>
	<key>PayloadDescription</key>
	<string>" . sprintf(_("This SSID should not be used after bootstrapping %s"), \config\ConfAssistant::CONSORTIUM['display_name']) . "</string>
	<key>PayloadDisplayName</key>
	<string>" . _("Disabled WiFi network") . "</string>
	<key>PayloadIdentifier</key>
	<string>" . self::IPHONE_PAYLOAD_PREFIX . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.wifi.disabled.$this->removeSerial</string>
	<key>PayloadType</key>
	<string>com.apple.wifi.managed</string>
	<key>PayloadUUID</key>
	<string>" . \core\common\Entity::uuid() . "</string>
	<key>PayloadVersion</key>
	<real>1</real>";
        $retval .= $this->proxySettings();
        $retval .= "<key>SSID_STR</key>
	<string>$ssid</string>
</dict>
";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    const NETWORK_BLOCK_TYPE_SSID = 100;
    const NETWORK_BLOCK_TYPE_CONSORTIUMOIS = 101;
    const NETWORK_BLOCK_TYPE_WIRED = 102;

    /**
     * produces the entire series of Network blocks; all for SSID-based, 
     * Passpoint-based, wired, and manual-select only SSIDs
     * 
     * @return string
     */
    private function allNetworkBlocks()
    {
        $retval = "";
        $this->serial = 0;
        foreach ($this->attributes['internal:networks'] as $netName => $netDefinition) {
            // mobileconfig network blocks can only hold one SSID each, so iterate here
            foreach ($netDefinition['ssid'] as $ssid) {
                $retval .= $this->networkBlock(MobileconfigSuperclass::NETWORK_BLOCK_TYPE_SSID, $ssid, $netName);
            }
            // mobileconfig network blocks can accomodate a list of RCOIs in one statement, so just call it
            if ($this->selectedEapObject->isPasswordRequired() === FALSE || $netName != 'eduroam®') {
                $retval .= $this->networkBlock(MobileconfigSuperclass::NETWORK_BLOCK_TYPE_CONSORTIUMOIS, $netDefinition['oi'], $netName);
            }
        }
        if (isset($this->attributes['media:wired']) && get_class($this) == "devices\apple_mobileconfig\DeviceMobileconfigOsX") {
            $retval .= $this->networkBlock(MobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED, TRUE, \config\ConfAssistant::CONSORTIUM['display_name']);
        }
        if (isset($this->attributes['media:remove_SSID'])) {
            $this->removeSerial = 0;
            foreach ($this->attributes['media:remove_SSID'] as $removeSSID) {
                $retval .= $this->removenetworkBlock($removeSSID);
                $this->removeSerial = $this->removeSerial + 1;
            }
        }
        return $retval;
    }

    /**
     * collates a block with all CAs that are to be included in the mobileconfig
     * 
     * @return string
     */
    private function allCA()
    {
        $retval = "";
        $this->caSerial = 0;
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $retval .= $this->caBlob($ca);
            $this->caSerial = $this->caSerial + 1;
        }
        return $retval;
    }

    /**
     * creates a Cert block containing a client certificate (used in SB only)
     * @return array the block itself, and the UUID of the certificate
     * @throws Exception
     */
    private function clientP12Block()
    {
        \core\common\Entity::intoThePotatoes();
        if (count($this->clientCert) == 0) {
            throw new Exception("the client block was called but there is no client certificate!");
        }
        $binaryBlob = $this->clientCert["certdata_nointermediate"];
        $mimeBlob = base64_encode($binaryBlob);
        $mimeFormatted = chunk_split($mimeBlob, 52, "\r\n");
        $payloadUUID = \core\common\Entity::uuid('', $mimeBlob);
        $retArray = ["block" => "<dict>" .
            // we don't include the import password. It's displayed on screen, and should be input by the user.
            // <key>Password</key>
            //   <string>" . $this->clientCert['password'] . "</string>
            "<key>PayloadCertificateFileName</key>
                     <string>" . $this->massagedConsortium . ".pfx</string>
                  <key>PayloadContent</key>
                     <data>
$mimeFormatted
                     </data>
                  <key>PayloadDescription</key>
                     <string>MIME Base-64 encoded PKCS#12 Client Certificate</string>
                  <key>PayloadDisplayName</key>
                     <string>" . _("User certificate") . "</string>
                  <key>PayloadIdentifier</key>
                     <string>com.apple.security.pkcs12.$payloadUUID</string>
                  <key>PayloadType</key>
                     <string>com.apple.security.pkcs12</string>
                  <key>PayloadUUID</key>
                     <string>$payloadUUID</string>
                  <key>PayloadVersion</key>
                     <integer>1</integer>
                </dict>",
            "UUID" => $payloadUUID,];
        \core\common\Entity::outOfThePotatoes();
        return $retArray;
    }

    /**
     * creates an Expiry block. This is only done in SB; the profile expires
     * when the client cert expires.
     * 
     * @return string
     * @throws Exception
     */
    private function expiryBlock() {
        if (count($this->clientCert) == 0) {
            throw new Exception("the expiry block was called but there is no client certificate!");
        }
        $expiryTime = new \DateTime($this->clientCert['certObject']->expiry);
        return "<key>RemovalDate</key>
        <date>" . $expiryTime->format("Y-m-d") . "T" . $expiryTime->format("H:i:s") . "Z</date>";
    }

    /**
     * creates a block for one single CA
     * 
     * @param array $ca the CA for which to generate the XML block
     * @return string
     */
    private function caBlob($ca)
    {
        \core\common\Entity::intoThePotatoes();
        $stream = "";
        if (!in_array($ca['uuid'], $this->CAsAccountedFor)) { // skip if this is a duplicate
            // cut lines with CERTIFICATE
            $stage1 = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $ca['pem']);
            $stage2 = preg_replace('/-----END CERTIFICATE-----/', '', $stage1);
            $trimmedPem = trim($stage2);

            $stream = "
            <dict>
               <key>PayloadCertificateFileName</key>
               <string>" . $ca['uuid'] . ".der</string>
               <key>PayloadContent</key>
               <data>
" . $trimmedPem . "</data>
               <key>PayloadDescription</key>
               <string>" . sprintf(_("The %s Certification Authority"), \core\common\Entity::$nomenclature_idp) . "</string>
               <key>PayloadDisplayName</key>
               <string>" . 
                    /// example: "Identity Provider CA #1 (Root)"
                    sprintf(_("%s CA #%d (%s)" ), 
                            \core\common\Entity::$nomenclature_idp, 
                            count($this->CAsAccountedFor)+1, 
                            ($ca['root'] ? _("Root") : _("Intermediate"))) . 
              "</string>
               <key>PayloadIdentifier</key>
               <string>" . self::IPHONE_PAYLOAD_PREFIX . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.credential.$this->caSerial</string>
               <key>PayloadOrganization</key>
               <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
               <string>com.apple.security.root</string>
               <key>PayloadUUID</key><string>" . $ca['uuid'] . "</string>
               <key>PayloadVersion</key>
               <integer>1</integer>
            </dict>";
            $this->CAsAccountedFor[] = $ca['uuid'];
        }
        \core\common\Entity::outOfThePotatoes();
        return $stream;
    }

}
