<?php

/* * ********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
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

    private $massaged_inst;
    private $massaged_profile;
    private $massaged_country;
    private $massaged_consortium;
    private $lang;

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

        $this->massaged_inst = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(array('/ /', '/\//'), '_', $this->attributes['general:instname'][0]))), ENT_XML1, 'UTF-8');
        $this->massaged_profile = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(array('/ /', '/\//'), '_', $this->attributes['profile:name'][0]))), ENT_XML1, 'UTF-8');
        $this->massaged_country = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(array('/ /', '/\//'), '_', $this->attributes['internal:country'][0]))), ENT_XML1, 'UTF-8');
        $this->massaged_consortium = htmlspecialchars(strtolower(iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace(array('/ /', '/\//'), '_', Config::$CONSORTIUM['name']))), ENT_XML1, 'UTF-8');
        $this->lang = preg_replace('/\..+/', '', setlocale(LC_ALL, "0"));

        // inst and profile MUST NOT be empty (needed to construct apple OID strings)
        if ($this->massaged_inst == "")
            $this->massaged_inst = "unnamed-inst";
        if ($this->massaged_profile == "")
            $this->massaged_profile = "unnamed-profile";

        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {

            $use_realm = "@" . $this->attributes['internal:realm'][0];
            if (isset($this->attributes['internal:anon_local_value']))
                $use_realm = $this->attributes['internal:anon_local_value'][0] . $use_realm;
        }
        else {
            $use_realm = 0;
        }

        $ssid_list = $this->attributes['internal:SSID'];
        $OI_list = $this->attributes['internal:consortia'];
        $server_names = $this->attributes['eap:server_name'];
        $uuid_list = $this->list_ca_uuids($this->attributes['internal:CAs'][0]);
        $eap_type = $this->selected_eap;
        $output_xml = "";
        $output_xml .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\"
\"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
   <dict>
      <key>PayloadContent</key>
         <array>";

        // did the admin want wired config?
        if (isset($this->attributes['media:wired']) && __CLASS__ == "Device_mobileconfig_os_x")
            $include_wired = TRUE;
        else
            $include_wired = FALSE;
        
        $output_xml .= $this->all_network_blocks($ssid_list, $OI_list, $server_names, $uuid_list, $eap_type, $include_wired, $use_realm);

        $output_xml .= $this->all_ca($this->attributes['internal:CAs'][0]);

        $output_xml .= "
         </array>
      <key>PayloadDescription</key>
         <string>" . sprintf(_("Network configuration profile '%s' of '%s' - provided by %s"), htmlspecialchars($this->attributes['profile:name'][0], ENT_XML1, 'UTF-8'), htmlspecialchars($this->attributes['general:instname'][0], ENT_XML1, 'UTF-8'), Config::$CONSORTIUM['name']) . "</string>
      <key>PayloadDisplayName</key>
         <string>" . Config::$CONSORTIUM['name'] . "</string>
      <key>PayloadIdentifier</key>
         <string>" . mobileconfig_superclass::$IPHONE_PAYLOAD_PREFIX . ".$this->massaged_consortium.$this->massaged_country.$this->massaged_inst.$this->massaged_profile.$this->lang</string>
      <key>PayloadOrganization</key>
         <string>" . $this->attributes['general:instname'][0] . ( $this->attributes['internal:profile_count'][0] > 1 ? " (" . $this->attributes['profile:name'][0] . ")" : "") . "</string>
      <key>PayloadType</key>
         <string>Configuration</string>
      <key>PayloadUUID</key>
         <string>" . uuid() . "</string>
      <key>PayloadVersion</key>
         <integer>1</integer>";
        if (isset($this->attributes['support:info_file']))
            $output_xml .= "
      <key>ConsentText</key>
         <dict>
            <key>default</key>
               <string>" . htmlspecialchars(iconv("UTF-8", "UTF-8//TRANSLIT", $this->attributes['support:info_file'][0]), ENT_XML1, 'UTF-8') . "</string>
         </dict>
         ";

        $output_xml .= "</dict></plist>";

        $xml_f = fopen('installer_profile', 'w');
        fwrite($xml_f, $output_xml);
        fclose($xml_f);

        $e = $this->installerBasename . '.mobileconfig';
        if ($this->sign) {
            $o = system($this->sign . " installer_profile '$e' > /dev/null");
            if ($o === FALSE)
                debug(2, "Signing the mobileconfig installer $e FAILED!\n");
        }
        else
            rename("installer_profile", $e);

        textdomain($dom);
        return $e;
    }

    public function writeDeviceInfo() {
        $ssid_ct = count($this->attributes['internal:SSID']);
        $cert_ct = count($this->attributes['eap:ca_file']);
        $out = "<p>";
        $out .= _("The profile will install itself after you click (or tap) the button. You will be asked for confirmation/input at several points:");
        $out .= "<ul>";
        $out .= "<li>" . _("to install the profile") . "</li>";
        $out .= "<li>" . ngettext("to accept the server certificate authority", "to accept the server certificate authorities", $cert_ct);
        if ($cert_ct > 1)
            $out .= " " . sprintf(_("(%d times)"), $cert_ct);
        $out .= "</li>";
        $out .= "<li>" . _("to enter the username and password of your institution");
        if ($ssid_ct > 1)
            $out .= " " . sprintf(_("(%d times each, because %s is installed for %d SSIDs)"), $ssid_ct, Config::$CONSORTIUM['name'], $ssid_ct);
        $out .= "</li>";
        $out .= "</ul>";
        $out .= "</p>";
        return $out;
    }

    static private $IPHONE_PAYLOAD_PREFIX = "org.1x-config";

    private function list_ca_uuids($ca_array) {
        $retval = array();
        foreach ($ca_array as $ca)
            $retval[] = $ca['uuid'];
        return $retval;
    }

    private static $serial;

    private function network_block($ssid, $oi, $server_list, $CA_UUID_list, $eap_type, $wired, $realm = 0) {
        $SSID = htmlspecialchars($ssid, ENT_XML1, 'UTF-8');
        $retval = "
            <dict>
               <key>EAPClientConfiguration</key>
                  <dict>
                      <key>AcceptEAPTypes</key>
                         <array>
                            <integer>" . $eap_type['OUTER'] . "</integer>
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
        if ($realm !== 0)
            $retval .= "<key>OuterIdentity</key>
                                    <string>" . htmlspecialchars($realm, ENT_XML1, 'UTF-8') . "</string>
";
        $retval .= "<key>PayloadCertificateAnchorUUID</key>
                         <array>";
        foreach ($CA_UUID_list as $uuid)
            $retval .= "
<string>$uuid</string>";
        $retval .= "
                         </array>
                      <key>TLSAllowTrustExceptions</key>
                         <false />
                      <key>TLSTrustedServerNames</key>
                         <array>";
        foreach ($server_list as $cn)
            $retval .= "
<string>$cn</string>";
        $retval .= "
                         </array>
                      <key>TTLSInnerAuthentication</key>
                         <string>" . ($eap_type['INNER'] == NONE ? "PAP" : "MSCHAPv2") . "</string>
                   </dict>
               <key>EncryptionType</key>
                  <string>";
        if ($wired)
            $retval .= "any";
        else if (count($oi) > 0)
            $retval .= "WPA";
        else
            $retval .= "WPA";

        $retval .= "</string>
               <key>HIDDEN_NETWORK</key>
                  <true />
               <key>PayloadDescription</key>
                  <string>";
        if ($wired)
            $retval .= sprintf(_("%s configuration for wired network"), Config::$CONSORTIUM['name']);
        else if (count($oi) > 0)
            $retval .= sprintf(_("%s Hotspot 2.0 configuration"), Config::$CONSORTIUM['name']);
        else
            $retval .= sprintf(_("%s configuration for network name %s"), Config::$CONSORTIUM['name'], $SSID);
        $retval .= "</string>
               <key>PayloadDisplayName</key>
                  <string>";
        if ($wired)
            $retval .= _("Wired Network");
        else if (count($oi) > 0)
            $retval .= _("Hotspot 2.0 Settings");
        else
            $retval .= sprintf(_("SSID %s"), $SSID);
        $retval .= "</string>
               <key>PayloadIdentifier</key>
                  <string>" . mobileconfig_superclass::$IPHONE_PAYLOAD_PREFIX . ".$this->massaged_consortium.$this->massaged_country.$this->massaged_inst.$this->massaged_profile.$this->lang.";
        if ($wired)
            $retval .= "firstactiveethernet";
        else if ( count($oi) == 0 )
            $retval .= "wifi.".$this->serial;
        else
            $retval .= "hs20";
        $retval .="</string>
               <key>PayloadOrganization</key>
                  <string>" . $this->massaged_consortium . ".1x-config.org</string>
               <key>PayloadType</key>
                  <string>com.apple." . ($wired ? "firstactiveethernet" : "wifi") . ".managed</string>";
        if ($wired)
            $retval .= "
               <key>ProxyType</key>
                  <string>None</string>
               <key>SetupModes</key>
                  <array>
                     <string>System</string>
                  </array>";
        $retval .= "
               <key>PayloadUUID</key>
                  <string>" . uuid() . "</string>
               <key>PayloadVersion</key>
                  <integer>1</integer>";
        if (!$wired && count($oi) == 0)
            $retval .= "<key>SSID_STR</key>
                  <string>$SSID</string>";
        if (count($oi)>0) {
            $retval .= "
               <key>IsHotspot</key>
               <true/>
               <key>ServiceProviderRoamingEnabled</key>
               <true/>
               <key>DisplayedOperatorName</key>
               <string>".Config::$CONSORTIUM['name']."</string>
               <key>DomainName</key>
               <string>";
            // what do we do if we did not get a realm? try to leave empty...
                if (isset($this->attributes['internal:realm']))
                    $retval .= $this->attributes['internal:realm'][0];
            $retval .= "</string>
                ";
            $retval .= "
                <key>RoamingConsortiumOIs</key>
                <array>";
            foreach ($oi as $oi_value)
                $retval .= "<string>$oi_value</string>";
            $retval .= "</array>";
                
        }
        $retval .= "</dict>";
        $this->serial = $this->serial + 1;
        return $retval;
    }

    private function removenetwork_block($SSID,$sequence) {
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
	<string>".sprintf(_("This SSID should not be used after bootstrapping %s"),Config::$CONSORTIUM['name'])."</string>
	<key>PayloadDisplayName</key>
	<string>"._("Disabled WiFi network")."</string>
	<key>PayloadIdentifier</key>
	<string>".mobileconfig_superclass::$IPHONE_PAYLOAD_PREFIX . ".$this->massaged_consortium.$this->massaged_country.$this->massaged_inst.$this->massaged_profile.$this->lang.wifi.disabled.$sequence</string>
	<key>PayloadType</key>
	<string>com.apple.wifi.managed</string>
	<key>PayloadUUID</key>
	<string>".uuid()."</string>
	<key>PayloadVersion</key>
	<real>1</real>
	<key>ProxyType</key>
	<string>Auto</string>
	<key>SSID_STR</key>
	<string>$SSID</string>
</dict>
";
        return $retval;
    }
    private function all_network_blocks($SSID_list, $OI_list, $server_list, $CA_UUID_list, $eap_type, $include_wired, $realm = 0) {
        $retval = "";
        $this->serial = 0;
        foreach (array_keys($SSID_list) as $SSID) {
            $retval .= $this->network_block($SSID, NULL, $server_list, $CA_UUID_list, $eap_type, FALSE, $realm);
        }
        if ($include_wired)
            $retval .= $this->network_block("IRRELEVANT", NULL, $server_list, $CA_UUID_list, $eap_type, TRUE, $realm);
        if (count($OI_list) > 0)
            $retval .= $this->network_block("IRRELEVANT", $OI_list, $server_list, $CA_UUID_list, $eap_type, FALSE, $realm);
        if (isset($this->attributes['media:remove_SSID']) )
            foreach ($this->attributes['media:remove_SSID'] as $index => $remove_SSID) 
                $retval .= $this->removenetwork_block($remove_SSID, $index);
        return $retval;
    }

    private function all_ca($ca_array) {
        $retval = "";
        $i = 0;
        foreach ($ca_array as $ca) {
            $retval .= $this->ca_blob($ca['uuid'], $ca['pem'], $i);
            $i = $i + 1;
        }
        return $retval;
    }

    private function ca_blob($uuid, $pem, $serial) {
        // cut lines with CERTIFICATE
        $pem = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pem);
        $pem = preg_replace('/-----END CERTIFICATE-----/', '', $pem);
        $pem = trim($pem);
        //return print_r($result);
        $stream = "
            <dict>
               <key>PayloadCertificateFileName</key>
               <string>$uuid.der</string>
               <key>PayloadContent</key>
               <data>
" . $pem . "</data>
               <key>PayloadDescription</key>
               <string>" . _("Your Identity Providers Certification Authority") . "</string>
               <key>PayloadDisplayName</key>
               <string>" . _("Identity Provider's CA") . "</string>
               <key>PayloadIdentifier</key>
               <string>" . mobileconfig_superclass::$IPHONE_PAYLOAD_PREFIX . ".$this->massaged_consortium.$this->massaged_country.$this->massaged_inst.$this->massaged_profile.credential.$serial</string>
               <key>PayloadOrganization</key>
               <string>" . $this->massaged_consortium . ".1x-config.org</string>
               <key>PayloadType</key>
               <string>com.apple.security.root</string>
               <key>PayloadUUID</key><string>" . $uuid . "</string>
               <key>PayloadVersion</key>
               <integer>1</integer>
            </dict>";

        return $stream;
    }

}
