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

// set_locale("devices");

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
abstract class mobileconfig_superclass extends DeviceConfig {

    private $massagedInst;
    private $massagedProfile;
    private $massagedCountry;
    private $massagedConsortium;
    private $lang;
    static private $iPhonePayloadPrefix = "org.1x-config";

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

        debug(4, "mobileconfig Module Installer start\n");

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
        $this->massagedConsortium = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(['/ /', '/\//'], '_', Config::$CONSORTIUM['name']))), ENT_XML1, 'UTF-8');
        $this->lang = preg_replace('/\..+/', '', setlocale(LC_ALL, "0"));

        $useRealm = 0;
        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {

            $useRealm = "@" . $this->attributes['internal:realm'][0];
            if (isset($this->attributes['internal:anon_local_value'])) {
                $useRealm = $this->attributes['internal:anon_local_value'][0] . $useRealm;
            }
        }

        $ssidList = $this->attributes['internal:SSID'];
        $consortiumOIList = $this->attributes['internal:consortia'];
        $serverNames = $this->attributes['eap:server_name'];
        $cAUUIDs = $this->list_ca_uuids($this->attributes['internal:CAs'][0]);
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
        if (isset($this->attributes['media:wired']) && get_class($this) == "Device_mobileconfig_os_x") {
            $include_wired = TRUE;
        } else {
            $include_wired = FALSE;
        }

        $outputXml .= $this->all_network_blocks($ssidList, $consortiumOIList, $serverNames, $cAUUIDs, $eapType, $include_wired, $useRealm);

        $outputXml .= $this->all_ca($this->attributes['internal:CAs'][0]);

        $outputXml .= "
         </array>
      <key>PayloadDescription</key>
         <string>" . sprintf(_("Network configuration profile '%s' of '%s' - provided by %s"), htmlspecialchars($profileName, ENT_XML1, 'UTF-8'), htmlspecialchars($instName, ENT_XML1, 'UTF-8'), Config::$CONSORTIUM['name']) . "</string>
      <key>PayloadDisplayName</key>
         <string>" . Config::$CONSORTIUM['name'] . "</string>
      <key>PayloadIdentifier</key>
         <string>" . mobileconfig_superclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang</string>
      <key>PayloadOrganization</key>
         <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['general:instname'][0]), ENT_XML1, 'UTF-8') . ( $this->attributes['internal:profile_count'][0] > 1 ? " (" . htmlspecialchars(iconv("UTF-8", "UTF-8//IGNORE", $this->attributes['profile:name'][0]), ENT_XML1, 'UTF-8') . ")" : "") . "</string>
      <key>PayloadType</key>
         <string>Configuration</string>
      <key>PayloadUUID</key>
         <string>" . uuid('', mobileconfig_superclass::$iPhonePayloadPrefix . $this->massagedConsortium . $this->massagedCountry . $this->massagedInst . $this->massagedProfile) . "</string>
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
                debug(2, "Signing the mobileconfig installer $fileName FAILED!\n");
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
            $out .= " " . sprintf(_("(%d times each, because %s is installed for %d SSIDs)"), $ssidCount, Config::$CONSORTIUM['name'], $ssidCount);
        }
        $out .= "</li>";
        $out .= "</ul>";
        $out .= "</p>";
        return $out;
    }

    private function list_ca_uuids($caArray) {
        $retval = [];
        foreach ($caArray as $ca) {
            $retval[] = $ca['uuid'];
        }
        return $retval;
    }

    private static $serial;

    private function network_block($ssid, $consortiumOi, $serverList, $cAUUIDList, $eapType, $wired, $realm = 0) {
        $SSID = htmlspecialchars($ssid, ENT_XML1, 'UTF-8');
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
                  <string>";
        if ($wired) {
            $retval .= "any";
        } elseif (count($consortiumOi) > 0) {
            $retval .= "WPA";
        } else {
            $retval .= "WPA";
        }

        $retval .= "</string>
               <key>HIDDEN_NETWORK</key>
                  <true />
               <key>PayloadDescription</key>
                  <string>";
        if ($wired) {
            $retval .= sprintf(_("%s configuration for wired network"), Config::$CONSORTIUM['name']);
        } elseif (count($consortiumOi) > 0) {
            $retval .= sprintf(_("%s Hotspot 2.0 configuration"), Config::$CONSORTIUM['name']);
        } else {
            $retval .= sprintf(_("%s configuration for network name %s"), Config::$CONSORTIUM['name'], $SSID);
        }
        $retval .= "</string>
               <key>PayloadDisplayName</key>
                  <string>";
        if ($wired) {
            $retval .= _("Wired Network");
        } elseif (count($consortiumOi) > 0) {
            $retval .= _("Hotspot 2.0 Settings");
        } else {
            $retval .= sprintf(_("SSID %s"), $SSID);
        }
        $retval .= "</string>
               <key>PayloadIdentifier</key>
                  <string>" . mobileconfig_superclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.";
        if ($wired) {
            $retval .= "firstactiveethernet";
        } elseif (count($consortiumOi) == 0) {
            $retval .= "wifi." . $this->serial;
        } else {
            $retval .= "hs20";
        }
        $retval .="</string>
               <key>PayloadOrganization</key>
                  <string>" . $this->massagedConsortium . ".1x-config.org</string>
               <key>PayloadType</key>
                  <string>com.apple." . ($wired ? "firstactiveethernet" : "wifi") . ".managed</string>";
        debug(2, get_class($this));
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
                  <string>$SSID</string>";
        }
        if (count($consortiumOi) > 0) {
            $retval .= "
               <key>IsHotspot</key>
               <true/>
               <key>ServiceProviderRoamingEnabled</key>
               <true/>
               <key>DisplayedOperatorName</key>
               <string>" . Config::$CONSORTIUM['name'] . "</string>";
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
        }
        $retval .= "</dict>";
        $this->serial = $this->serial + 1;
        return $retval;
    }

    private function removenetwork_block($SSID, $sequence) {
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
	<string>" . sprintf(_("This SSID should not be used after bootstrapping %s"), Config::$CONSORTIUM['name']) . "</string>
	<key>PayloadDisplayName</key>
	<string>" . _("Disabled WiFi network") . "</string>
	<key>PayloadIdentifier</key>
	<string>" . mobileconfig_superclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.$this->lang.wifi.disabled.$sequence</string>
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
	<string>$SSID</string>
</dict>
";
        return $retval;
    }

    private function all_network_blocks($SSIDList, $consortiumOIList, $serverNameList, $cAUUIDList, $eapType, $includeWired, $realm = 0) {
        $retval = "";
        $this->serial = 0;
        foreach (array_keys($SSIDList) as $SSID) {
            $retval .= $this->network_block($SSID, NULL, $serverNameList, $cAUUIDList, $eapType, FALSE, $realm);
        }
        if ($includeWired) {
            $retval .= $this->network_block("IRRELEVANT", NULL, $serverNameList, $cAUUIDList, $eapType, TRUE, $realm);
        }
        if (count($consortiumOIList) > 0) {
            $retval .= $this->network_block("IRRELEVANT", $consortiumOIList, $serverNameList, $cAUUIDList, $eapType, FALSE, $realm);
        }
        if (isset($this->attributes['media:remove_SSID'])) {
            foreach ($this->attributes['media:remove_SSID'] as $index => $remove_SSID) {
                $retval .= $this->removenetwork_block($remove_SSID, $index);
            }
        }
        return $retval;
    }

    private function all_ca($caArray) {
        $retval = "";
        $iterator = 0;
        foreach ($caArray as $ca) {
            $retval .= $this->ca_blob($ca['uuid'], $ca['pem'], $iterator);
            $iterator = $iterator + 1;
        }
        return $retval;
    }

    private function ca_blob($uuid, $pem, $serial) {
        // cut lines with CERTIFICATE
        $stage1 = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pem);
        $stage2 = preg_replace('/-----END CERTIFICATE-----/', '', $stage1);
        $trimmedPem = trim($stage2);
        //return print_r($result);
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
               <string>" . mobileconfig_superclass::$iPhonePayloadPrefix . ".$this->massagedConsortium.$this->massagedCountry.$this->massagedInst.$this->massagedProfile.credential.$serial</string>
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
