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
abstract class mobileconfigSuperclass extends \core\DeviceConfig {

    private $instName;
    private $profileName;
    private $massagedInst;
    private $massagedProfile;
    private $massagedCountry;
    private $massagedConsortium;
    private $lang;
    static private $iPhonePayloadPrefix = "org.1x-config";
    private $clientCertUUID;

    public function __construct() {
        parent::__construct();
        // that's what all variants support. Sub-classes can change it.
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        $this->specialities['internal:verify_userinput_suffix'] = _("It is not possible to actively verify the user input for suffix match; but if there is no 'Terms of Use' configured, the installer will display a corresponding hint to the user instead.");
    }

    private function massageName($input) {
        return htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', $input))), ENT_XML1, 'UTF-8');
    }

    private function generalPayload() {
        $tagline = sprintf(_("Network configuration profile '%s' of '%s' - provided by %s"), htmlspecialchars($this->profileName, ENT_XML1, 'UTF-8'), htmlspecialchars($this->instName, ENT_XML1, 'UTF-8'), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);

        $eapType = $this->selectedEap;
        // simpler message for silverbullet
        if ($eapType['INNER'] == \core\common\EAP::NE_SILVERBULLET) {
            $tagline = sprintf(_("%s configuration for IdP '%s' - provided by %s"), \core\ProfileSilverbullet::PRODUCTNAME, htmlspecialchars($this->instName, ENT_XML1, 'UTF-8'), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        }

        return "
      <key>PayloadDescription</key>
         <string>$tagline</string>
      <key>PayloadDisplayName</key>
         <string>" . CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'] . "</string>
      <key>PayloadIdentifier</key>
         <string>" . self::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang</string>
      <key>PayloadOrganization</key>
         <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['general:instname'][0]), ENT_XML1, 'UTF-8') . ( $this->attributes['internal:profile_count'][0] > 1 ? " (" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['profile:name'][0]), ENT_XML1, 'UTF-8') . ")" : "") . "</string>
      <key>PayloadType</key>
         <string>Configuration</string>
      <key>PayloadUUID</key>
         <string>" . \core\common\Entity::uuid('', self::$iPhonePayloadPrefix . $this->massagedConsortium . $this->massagedCountry . $this->massagedInst . $this->massagedProfile) . "</string>
      <key>PayloadVersion</key>
         <integer>1</integer>";
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

    protected function consentBlock() {
        if (isset($this->attributes['support:info_file'])) {
            return mobileconfigSuperclass::BUFFER_CONSENT_PRE . htmlspecialchars(iconv("UTF-8", "UTF-8//TRANSLIT", $this->attributes['support:info_file'][0]), ENT_XML1, 'UTF-8') . mobileconfigSuperclass::BUFFER_CONSENT_POST;
        }
        if ($this->attributes['internal:verify_userinput_suffix'][0] != 0) {
            if (strlen($this->attributes['internal:realm'][0]) > 0) {
                return mobileconfigSuperclass::BUFFER_CONSENT_PRE . sprintf(_("Important Notice: your username must end with @%s!"), $this->attributes['internal:realm'][0]) . mobileconfigSuperclass::BUFFER_CONSENT_POST;
            }
            return mobileconfigSuperclass::BUFFER_CONSENT_PRE . _("Important Notice: your username MUST be in the form of xxx@yyy where the yyy is a common suffix identifying your Identity Provider. Please find out what to use there and enter the username in the correct format.") . mobileconfigSuperclass::BUFFER_CONSENT_POST;
        }
        return "";
    }

    /**
     * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
     *
     */
    public function writeInstaller() {
        /** run innitial setup
          this will:
          - create the temporary directory and save its path as $this->FPATH
          - process the CA certificates and store results in $this->attributes['internal:CAs'][0]
          $this->attributes['internal:CAs'][0] is an array of processed CA certificates
          a processed certifincate is an array
          'pem' points to pem feromat certificate
          'der' points to der format certificate
          'md5' points to md5 fingerprint
          'sha1' points to sha1 fingerprint
          'name' points to the certificate subject
          'root' can be 1 for self-signed certificate or 0 otherwise

          - save the info_file (if exists) and put the name in $this->attributes['internal:info_file_name'][0]
         */
        $dom = textdomain(NULL);
        textdomain("devices");

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
        $this->massagedConsortium = $this->massageName(CONFIG_CONFASSISTANT['CONSORTIUM']['name']);
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

        textdomain($dom);

        $fileName = $this->installerBasename . '.mobileconfig';

        if (!$this->sign) {
            rename("installer_profile", $fileName);
            return $fileName;
        }
        // still here? Then we are signing.
        $signing = system($this->sign . " installer_profile '$fileName' > /dev/null");
        if ($signing === FALSE) {
            $this->loggerInstance->debug(2, "Signing the mobileconfig installer $fileName FAILED!\n");
        }
        return $fileName;
    }

    public function writeDeviceInfo() {
        $ssidCount = count($this->attributes['internal:SSID']);
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
            $out .= " " . sprintf(_("(%d times each, because %s is installed for %d SSIDs)"), $ssidCount, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $ssidCount);
        }
        $out .= "</li>";
        $out .= "</ul>";
        $out .= "</p>";
        return $out;
    }

    private function listCAUuids() {
        $retval = [];
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $retval[] = $ca['uuid'];
        }
        return $retval;
    }

    private function passPointBlock($consortiumOi) {
        $retval = "
               <key>IsHotspot</key>
               <true/>
               <key>ServiceProviderRoamingEnabled</key>
               <true/>
               <key>DisplayedOperatorName</key>
               <string>" . CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'] . " via Passpoint</string>";
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
        foreach ($consortiumOi as $oiValue) {
            $retval .= "<string>$oiValue</string>";
        }
        $retval .= "</array>";
        // this is an undocmented value found on the net. Does it do something useful?
        $retval .= "<key>_UsingHotspot20</key>
                <true/>
                ";
        // do we need to set NAIRealmName ? In Rel 1, probably yes, in Rel 2, 
        // no because ConsortiumOI is enough.
        // but which release is OS X doing? And what should we fill in, given
        // that we have thousands of realms? Try just eduroam.org
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam") {
            $retval .= "<key>NAIRealmNames</key>
                <array>
                    <string>eduroam.org</string>
                </array>";
        }
        return $retval;
    }

    private $serial;
    private $removeSerial;
    private $caSerial;

    private function eapBlock($eapType) {
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
                         <string>" . ($eapType['INNER'] == \core\common\EAP::NONE ? "PAP" : "MSCHAPv2") . "</string>
                   </dict>";
        return $retval;
    }

    protected function proxySettings() {
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

    private function networkBlock($blocktype, $toBeConfigured) {
        $eapType = $this->selectedEap;
        switch ($blocktype) {
            case mobileconfigSuperclass::NETWORK_BLOCK_TYPE_SSID:
                $escapedSSID = htmlspecialchars($toBeConfigured, ENT_XML1, 'UTF-8');
                $payloadIdentifier = "wifi." . $this->serial;
                $payloadShortName = sprintf(_("SSID %s"), $escapedSSID);
                $payloadName = sprintf(_("%s configuration for network name %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $escapedSSID);
                $encryptionTypeString = "WPA";
                $setupModesString = "";
                $wifiNetworkIdentification = "<key>SSID_STR</key>
                  <string>$escapedSSID</string>";
                break;
            case mobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED:
                $payloadIdentifier = "firstactiveethernet";
                $payloadShortName = _("Wired Network");
                $payloadName = sprintf(_("%s configuration for wired network"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
                $encryptionTypeString = "any";
                $setupModesString = "
               <key>SetupModes</key>
                  <array>
                     <string>System</string>
                  </array>";
                $wifiNetworkIdentification = "";
                break;
            case mobileconfigSuperclass::NETWORK_BLOCK_TYPE_CONSORTIUMOIS:
                $payloadIdentifier = "hs20";
                $payloadShortName = _("Hotspot 2.0 Settings");
                $payloadName = sprintf(_("%s Hotspot 2.0 configuration"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
                $encryptionTypeString = "WPA";
                $setupModesString = "";
                $wifiNetworkIdentification = $this->passPointBlock($toBeConfigured);
                break;
            default:
                throw new Exception("This type of network block is unknown!");
        }
        $retval = "<dict>";
        $retval .= $this->eapBlock($eapType);
        $retval .= "<key>EncryptionType</key>
                  <string>$encryptionTypeString</string>
               <key>HIDDEN_NETWORK</key>
                  <true />
               <key>PayloadDescription</key>
                  <string>$payloadName</string>
               <key>PayloadDisplayName</key>
                  <string>$payloadShortName</string>
               <key>PayloadIdentifier</key>
                  <string>" . self::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.$payloadIdentifier</string>
               <key>PayloadOrganization</key>
                  <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
                  <string>com.apple." . ($blocktype == mobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED ? "firstactiveethernet" : "wifi") . ".managed</string>";
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
        return $retval;
    }

    private function removenetworkBlock($ssid) {
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
	<string>" . sprintf(_("This SSID should not be used after bootstrapping %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</string>
	<key>PayloadDisplayName</key>
	<string>" . _("Disabled WiFi network") . "</string>
	<key>PayloadIdentifier</key>
	<string>" . self::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.wifi.disabled.$this->removeSerial</string>
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
        return $retval;
    }

    const NETWORK_BLOCK_TYPE_SSID = 100;
    const NETWORK_BLOCK_TYPE_CONSORTIUMOIS = 101;
    const NETWORK_BLOCK_TYPE_WIRED = 102;

    private function allNetworkBlocks() {
        $retval = "";
        $this->serial = 0;

        foreach (array_keys($this->attributes['internal:SSID']) as $ssid) {
            $retval .= $this->networkBlock(mobileconfigSuperclass::NETWORK_BLOCK_TYPE_SSID, $ssid);
        }
        if (isset($this->attributes['media:wired']) && get_class($this) == "devices\apple_mobileconfig\Device_mobileconfig_os_x") {
            $retval .= $this->networkBlock(mobileconfigSuperclass::NETWORK_BLOCK_TYPE_WIRED, TRUE);
        }
        if (count($this->attributes['internal:consortia']) > 0) {
            $retval .= $this->networkBlock(mobileconfigSuperclass::NETWORK_BLOCK_TYPE_CONSORTIUMOIS, $this->attributes['internal:consortia']);
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

    private function allCA() {
        $retval = "";
        $this->caSerial = 0;
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $retval .= $this->caBlob($ca);
            $this->caSerial = $this->caSerial + 1;
        }
        return $retval;
    }

    private function clientP12Block() {
        if (!is_array($this->clientCert)) {
            throw new Exception("the client block was called but there is no client certificate!");
        }
        $binaryBlob = $this->clientCert["certdata"];
        $mimeBlob = base64_encode($binaryBlob);
        $mimeFormatted = chunk_split($mimeBlob, 52, "\r\n");
        $payloadUUID = \core\common\Entity::uuid('', $mimeBlob);
        return ["block" => "<dict>" .
            // we don't include the import password. It's displayed on screen, and should be input by the user.
            // <key>Password</key>
            //   <string>" . $this->clientCert['password'] . "</string>
            "<key>PayloadCertificateFileName</key>
                     <string>cert-cli.pfx</string>
                  <key>PayloadContent</key>
                     <data>
$mimeFormatted
                     </data>
                  <key>PayloadDescription</key>
                     <string>MIME Base-64 encoded PKCS#12 Client Certificate</string>
                  <key>PayloadDisplayName</key>
                     <string>" . _("eduroam user certificate") . "</string>
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
    }

    private function expiryBlock() {
        if (!is_array($this->clientCert)) {
            throw new Exception("the expiry block was called but there is no client certificate!");
        }
        $expiryTime = new \DateTime($this->clientCert['certObject']->expiry);
        return "<key>RemovalDate</key>
        <date>" . $expiryTime->format("Y-m-d") . "T" . $expiryTime->format("H:i:s") . "Z</date>";
    }

    private $CAsAccountedFor = [];

    private function caBlob($ca) {
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
               <string>" . _("Your Identity Providers Certification Authority") . "</string>
               <key>PayloadDisplayName</key>
               <string>" . _("Identity Provider's CA") . "</string>
               <key>PayloadIdentifier</key>
               <string>" . self::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.credential.$this->caSerial</string>
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
        return $stream;
    }

}
