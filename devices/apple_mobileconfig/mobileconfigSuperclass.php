<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the installer for iOS devices and Apple 10.7 Lion
 *
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Developer
 */
/**
 * 
 */
require_once('DeviceConfig.php');
require_once('Helper.php');

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
abstract class mobileconfigSuperclass extends DeviceConfig {

    private $massagedInst;
    private $massagedProfile;
    private $massagedCountry;
    private $massagedConsortium;
    private $lang;
    static private $iPhonePayloadPrefix = "org.1x-config";

    public function __construct() {
        parent::__construct();
    }
    
    /**
     * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
     *
     * {@source}
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
        $instName = _("Unnamed Institution");
        if (!empty($this->attributes['general:instname'][0])) {
            $instName = $this->attributes['general:instname'][0];
        }

        $profileName = _("Unnamed Profile");

        if (!empty($this->attributes['profile:name'][0])) {
            $profileName = $this->attributes['profile:name'][0];
        }

        $this->massagedInst = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', $instName))), ENT_XML1, 'UTF-8');
        $this->massagedProfile = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', $profileName))), ENT_XML1, 'UTF-8');
        $this->massagedCountry = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', $this->attributes['internal:country'][0]))), ENT_XML1, 'UTF-8');
        $this->massagedConsortium = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', CONFIG['CONSORTIUM']['name']))), ENT_XML1, 'UTF-8');
        $this->lang = preg_replace('/\..+/', '', setlocale(LC_ALL, "0"));

        $outerId = $this->determineOuterIdString();

        $ssidList = $this->attributes['internal:SSID'];
        $consortiumOIList = $this->attributes['internal:consortia'];
        $serverNames = $this->attributes['eap:server_name'];
        $cAUUIDs = $this->listCAUuids($this->attributes['internal:CAs'][0]);
        $eapType = $this->selected_eap;
        $outputXml = "";
        $outputXml .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\"
\"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
   <dict>
      <key>PayloadContent</key>
         <array>";

        // did the admin want wired config?
        $includeWired = FALSE;
        if (isset($this->attributes['media:wired']) && get_class($this) == "Device_mobileconfig_os_x") {
            $includeWired = TRUE;
        }

        $outputXml .= $this->allNetworkBlocks($ssidList, $consortiumOIList, $serverNames, $cAUUIDs, $eapType, $includeWired, $outerId);

        $outputXml .= $this->allCA($this->attributes['internal:CAs'][0]);

        $outputXml .= "
         </array>
      <key>PayloadDescription</key>
         <string>" . sprintf(_("Network configuration profile '%s' of '%s' - provided by %s"), htmlspecialchars($profileName, ENT_XML1, 'UTF-8'), htmlspecialchars($instName, ENT_XML1, 'UTF-8'), CONFIG['CONSORTIUM']['name']) . "</string>
      <key>PayloadDisplayName</key>
         <string>" . CONFIG['CONSORTIUM']['name'] . "</string>
      <key>PayloadIdentifier</key>
         <string>" . mobileconfigSuperclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang</string>
      <key>PayloadOrganization</key>
         <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['general:instname'][0]), ENT_XML1, 'UTF-8') . ( $this->attributes['internal:profile_count'][0] > 1 ? " (" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['profile:name'][0]), ENT_XML1, 'UTF-8') . ")" : "") . "</string>
      <key>PayloadType</key>
         <string>Configuration</string>
      <key>PayloadUUID</key>
         <string>" . uuid('', mobileconfigSuperclass::$iPhonePayloadPrefix . $this->massagedConsortium . $this->massagedCountry . $this->massagedInst . $this->massagedProfile) . "</string>
      <key>PayloadVersion</key>
         <integer>1</integer>";
        if (isset($this->attributes['support:info_file'])) {
            $outputXml .= "
      <key>ConsentText</key>
         <dict>
            <key>default</key>
               <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//TRANSLIT", $this->attributes['support:info_file'][0]), ENT_XML1, 'UTF-8') . "</string>
         </dict>
         ";
        }
        $outputXml .= "</dict></plist>";

        $xmlFile = fopen('installer_profile', 'w');
        fwrite($xmlFile, $outputXml);
        fclose($xmlFile);

        $fileName = $this->installerBasename . '.mobileconfig';
        if ($this->sign) {
            $signing = system($this->sign . " installer_profile '$fileName' > /dev/null");
            if ($signing === FALSE) {
                $this->loggerInstance->debug(2, "Signing the mobileconfig installer $fileName FAILED!\n");
            }
        } else {
            rename("installer_profile", $fileName);
        }

        textdomain($dom);
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
        $out .= "<li>" . _("to enter the username and password of your institution");
        if ($ssidCount > 1) {
            $out .= " " . sprintf(_("(%d times each, because %s is installed for %d SSIDs)"), $ssidCount, CONFIG['CONSORTIUM']['name'], $ssidCount);
        }
        $out .= "</li>";
        $out .= "</ul>";
        $out .= "</p>";
        return $out;
    }

    private function listCAUuids($caArray) {
        $retval = [];
        foreach ($caArray as $ca) {
            $retval[] = $ca['uuid'];
        }
        return $retval;
    }

    private function passPointBlock ($consortiumOi) {
        $retval = "
               <key>IsHotspot</key>
               <true/>
               <key>ServiceProviderRoamingEnabled</key>
               <true/>
               <key>DisplayedOperatorName</key>
               <string>" . CONFIG['CONSORTIUM']['name'] . "</string>";
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
            return $retval;
    }
    
    private $serial;

    private function networkBlock($ssid, $consortiumOi, $serverList, $cAUUIDList, $eapType, $wired, $realm = 0) {
        $escapedSSID = htmlspecialchars($ssid, ENT_XML1, 'UTF-8');
        
        $payloadIdentifier = "wifi." . $this->serial;
        $payloadShortName = sprintf(_("SSID %s"), $escapedSSID);
        $payloadName = sprintf(_("%s configuration for network name %s"), CONFIG['CONSORTIUM']['name'], $escapedSSID);
        $encryptionTypeString = "WPA";
        
        if ($wired) { // override the above defaults for wired interfaces
            $payloadIdentifier = "firstactiveethernet";
            $payloadShortName = _("Wired Network");
            $payloadName = sprintf(_("%s configuration for wired network"), CONFIG['CONSORTIUM']['name']);
            $encryptionTypeString = "any";
        }
        
        if (count($consortiumOi) > 0) { // override the above defaults for HS20 configuration
            $payloadIdentifier = "hs20";
            $payloadShortName = _("Hotspot 2.0 Settings");
            $payloadName = sprintf(_("%s Hotspot 2.0 configuration"), CONFIG['CONSORTIUM']['name']);
            $encryptionTypeString = "WPA";
        }
        
        $retval = "
            <dict>
               <key>EAPClientConfiguration</key>
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
        if ($realm !== 0) {
            $retval .= "<key>OuterIdentity</key>
                                    <string>" . htmlspecialchars($realm, ENT_XML1, 'UTF-8') . "</string>
";
        }
        $retval .= "<key>PayloadCertificateAnchorUUID</key>
                         <array>";
        foreach ($cAUUIDList as $uuid) {
            $retval .= "
<string>$uuid</string>";
        }
        $retval .= "
                         </array>
                      <key>TLSAllowTrustExceptions</key>
                         <false />
                      <key>TLSTrustedServerNames</key>
                         <array>";
        foreach ($serverList as $commonName) {
            $retval .= "
<string>$commonName</string>";
        }
        $retval .= "
                         </array>
                      <key>TTLSInnerAuthentication</key>
                         <string>" . ($eapType['INNER'] == NONE ? "PAP" : "MSCHAPv2") . "</string>
                   </dict>
               <key>EncryptionType</key>
                  <string>$encryptionTypeString</string>
               <key>HIDDEN_NETWORK</key>
                  <true />
               <key>PayloadDescription</key>
                  <string>$payloadName</string>
               <key>PayloadDisplayName</key>
                  <string>$payloadShortName</string>
               <key>PayloadIdentifier</key>
                  <string>" . mobileconfigSuperclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.$payloadIdentifier</string>
               <key>PayloadOrganization</key>
                  <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
                  <string>com.apple." . ($wired ? "firstactiveethernet" : "wifi") . ".managed</string>";
        $this->loggerInstance->debug(2, get_class($this));
        if (get_class($this) != "Device_mobileconfig_ios_56") {
            $retval .= "<key>ProxyType</key>
                  <string>Auto</string>
                ";
        }
        if ($wired) {
            $retval .= "
               <key>SetupModes</key>
                  <array>
                     <string>System</string>
                  </array>";
        }
        $retval .= "
               <key>PayloadUUID</key>
                  <string>" . uuid() . "</string>
               <key>PayloadVersion</key>
                  <integer>1</integer>";
        if (!$wired && count($consortiumOi) == 0) {
            $retval .= "<key>SSID_STR</key>
                  <string>$escapedSSID</string>";
        }
        if (count($consortiumOi) > 0) {
            $retval .= $this->passPointBlock($consortiumOi);
        }
        $retval .= "</dict>";
        $this->serial = $this->serial + 1;
        return $retval;
    }

    private function removenetworkBlock($ssid, $sequence) {
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
	<string>" . sprintf(_("This SSID should not be used after bootstrapping %s"), CONFIG['CONSORTIUM']['name']) . "</string>
	<key>PayloadDisplayName</key>
	<string>" . _("Disabled WiFi network") . "</string>
	<key>PayloadIdentifier</key>
	<string>" . mobileconfigSuperclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.wifi.disabled.$sequence</string>
	<key>PayloadType</key>
	<string>com.apple.wifi.managed</string>
	<key>PayloadUUID</key>
	<string>" . uuid() . "</string>
	<key>PayloadVersion</key>
	<real>1</real>";
        if (get_class($this) != "Device_mobileconfig_ios_56") {
            $retval .= "<key>ProxyType</key>
	<string>Auto</string>";
        }
        $retval .= "<key>SSID_STR</key>
	<string>$ssid</string>
</dict>
";
        return $retval;
    }

    private function allNetworkBlocks($sSIDList, $consortiumOIList, $serverNameList, $cAUUIDList, $eapType, $includeWired, $realm = 0) {
        $retval = "";
        $this->serial = 0;
        foreach (array_keys($sSIDList) as $ssid) {
            $retval .= $this->networkBlock($ssid, NULL, $serverNameList, $cAUUIDList, $eapType, FALSE, $realm);
        }
        if ($includeWired) {
            $retval .= $this->networkBlock("IRRELEVANT", NULL, $serverNameList, $cAUUIDList, $eapType, TRUE, $realm);
        }
        if (count($consortiumOIList) > 0) {
            $retval .= $this->networkBlock("IRRELEVANT", $consortiumOIList, $serverNameList, $cAUUIDList, $eapType, FALSE, $realm);
        }
        if (isset($this->attributes['media:remove_SSID'])) {
            foreach ($this->attributes['media:remove_SSID'] as $index => $removeSSID) {
                $retval .= $this->removenetworkBlock($removeSSID, $index);
            }
        }
        return $retval;
    }

    private function allCA($caArray) {
        $retval = "";
        $iterator = 0;
        foreach ($caArray as $ca) {
            $retval .= $this->caBlob($ca['uuid'], $ca['pem'], $iterator);
            $iterator = $iterator + 1;
        }
        return $retval;
    }

    private function caBlob($uuid, $pem, $serial) {
        // cut lines with CERTIFICATE
        $stage1 = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pem);
        $stage2 = preg_replace('/-----END CERTIFICATE-----/', '', $stage1);
        $trimmedPem = trim($stage2);
        
        $stream = "
            <dict>
               <key>PayloadCertificateFileName</key>
               <string>$uuid.der</string>
               <key>PayloadContent</key>
               <data>
" . $trimmedPem . "</data>
               <key>PayloadDescription</key>
               <string>" . _("Your Identity Providers Certification Authority") . "</string>
               <key>PayloadDisplayName</key>
               <string>" . _("Identity Provider's CA") . "</string>
               <key>PayloadIdentifier</key>
               <string>" . mobileconfigSuperclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.credential.$serial</string>
               <key>PayloadOrganization</key>
               <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
               <string>com.apple.security.root</string>
               <key>PayloadUUID</key><string>" . $uuid . "</string>
               <key>PayloadVersion</key>
               <integer>1</integer>
            </dict>";

        return $stream;
    }

}
