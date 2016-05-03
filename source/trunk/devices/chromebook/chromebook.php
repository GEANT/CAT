<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the TestModule class
 *
 * This is a very basic example of using the CAT API.  
 *
 * The module contains two files
 * in the Files directory. They will illustrate the use of the {@link DeviceConfig::copyFile()} method.
 * One fille will be coppied without the name change, for the second we will provide a new name.
 * The API also contains a similar {@link DeviceConfig::translateFile()} method, which is special to Windows installers and not used in this example.
 *
 * This module will collect all certificate files stored in the database for a given profile and will copy them to the working directory.
 *
 * If, for the given profile, an information file is available, this will also be copied to the working directory.
 *
 * The installer will collect all available configuration attributes and save them to a file in the form of the PHP print_r output.
 *
 * Finally, the installer will create a zip archive containing all above files and this file 
 * will be sent to the user as the configurator file.
 *
 * Go to the {@link Device_TestModule} and {@link DeviceConfig} class definitions to learn more.
 *  
 * @package ModuleWriting
 */
/**
 * this array holds the list of EAP methods supported by this device
 */
/**
 * 
 */
require_once('DeviceConfig.php');
require_once('EAP.php');

/**
 * This is the main implementation class of the module
 *
 * The name of the class must the the 'Device_' followed by the name of the module file
 * (without the '.php' extension), so in this case the file is "TestModule.php" and
 * the class is Device_TestModule.
 *
 * The class MUST define the constructor method and one additional 
 * public method: {@link writeInstaller()}.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * It is important to understand how the device module fits into the whole picture, so here is s short descrption.
 * An external caller (for instance {@link GUI::generateInstaller()}) creates the module device instance and prepares
 * its environment for a given user profile by calling {@link DeviceConfig::setup()} method.
 *      this will:
 *       - create the temporary directory and save its path as $this->FPATH
 *       - process the CA certificates and store results in $this->attributes['internal:CAs'][0]
 *            $this->attributes['internal:CAs'][0] is an array of processed CA certificates
 *            a processed certifincate is an array 
 *               'pem' points to pem feromat certificate
 *               'der' points to der format certificate
 *               'md5' points to md5 fingerprint
 *               'sha1' points to sha1 fingerprint
 *               'name' points to the certificate subject
 *               'root' can be 1 for self-signed certificate or 0 otherwise
 *       - save the info_file (if exists) and put the name in $this->attributes['internal:info_file_name'][0]
 * Finally, the module {@link DeviceConfig::writeInstaller ()} is called and the returned path name is used for user download.
 *
 * @package ModuleWriting
 */
class Device_Chromebook extends DeviceConfig {

    /**
     * Constructs a Device object.
     *
     * It is CRUTCIAL that the constructor sets $this->supportedEapMethods to an array of methods
     * available for the particular device.
     * {@source}
     * @param string $device a pointer to a device module, which must
     * be an index of one of the devices defined in the {@link Devices}
     * array in {@link devices.php}.
     * @final not to be redefined
     */
    final public function __construct() {
        $this->supportedEapMethods = [EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_MSCHAP2, EAP::$TLS];
        debug(4, "This device supports the following EAP methods: ");
        debug(4, $this->supportedEapMethods);
    }

    /**
     * prepare a ONC file
     *
     * {@source}
     * @return string installer path name
     */
    public function writeInstaller() {
        debug(4, "Chromebook Installer start\n");
        // we don't do per-user encrypted containers
        $json_array = [];

        $json_array["Type"] = "UnencryptedConfiguration";

        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $ca_refs[] = "{" . $ca['uuid'] . "}";
        }
        // construct outer id, if anonymity is desired
        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {
            $outer_id = "@" . $this->attributes['internal:realm'][0];
            if (isset($this->attributes['internal:anon_local_value']))
                $outer_id = $this->attributes['internal:anon_local_value'][0] . $outer_id;
        }
        else {
            $outer_id = 0;
        }
        // define networks
        foreach ($this->attributes['internal:SSID'] as $ssid => $cryptolevel) {
            $network_uuid = uuid($prefix, $ssid);
            $eap_prettyprint = EAP::eapDisplayName($this->selected_eap);
            // ONC has its own enums, and guess what, they don't always match
            if ($eap_prettyprint["OUTER"] == "PEAP" && $eap_prettyprint["INNER"] == "MSCHAPV2")
                // the dictionary entry EAP-MSCHAPv2 does not work. Setting MSCHAPv2 does. (ChromeOS 50)
                $eap_prettyprint["INNER"] = "MSCHAPv2";
            if ($eap_prettyprint["OUTER"] == "TTLS" && $eap_prettyprint["INNER"] == "MSCHAPV2")
                $eap_prettyprint["INNER"] = "MSCHAPv2";
            if ($eap_prettyprint["OUTER"] == "TLS")
                $eap_prettyprint["OUTER"] = "EAP-TLS";
            // define EAP properties

            $eaparray = array("Outer" => $eap_prettyprint["OUTER"]);
            if ($eap_prettyprint["INNER"] == "MSCHAPv2")
                $eaparray["Inner"] = $eap_prettyprint["INNER"];
            $eaparray["SaveCredentials"] = true;
            $eaparray["ServerCARefs"] = $ca_refs; // maybe takes just one CA?
            $eaparray["UseSystemCAs"] = false;
                    
            if ($outer_id)
                $eaparray["AnonymousIdentity"] = "$outer_id";
            
            $json_array["NetworkConfigurations"][] = [
                "GUID" => $network_uuid,
                "Name" => "$ssid",
                "Type" => "WiFi",
                "WiFi" => [
                    "AutoConnect" => true,
                    "EAP" => $eaparray,
                    "HiddenSSID" => false,
                    "SSID" => $ssid,
                    "Security" => "WPA-EAP",
                ],
                "ProxySettings" => ["Type" => "WPAD"],
            ];
        };
        // are we also configuring wired?
        if (isset($this->attributes['media:wired'])) {
            $network_uuid = "{" . uuid($prefix, "wired-dot1x-ethernet") . "}";
            $json_array["NetworkConfigurations"][] = [
                "GUID" => $network_uuid,
                "Name" => "eduroam configuration (wired network)",
                "Type" => "Ethernet",
                "Ethernet" => [
                    "Authentication" => "8021X",
                    "EAP" => $eaparray,
                ],
                "ProxySettings" => ["Type" => "WPAD"],
            ];
        };

        // define CA certificates
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            // strip -----BEGIN CERTIFICATE----- and -----END CERTIFICATE-----
            $ca_sanitized = substr($ca['pem'], 27, strlen($ca['pem']) - 27 - 25);
            // remove \n
            $ca_sanitized = str_replace("\n", "", $ca_sanitized);
            $json_array["Certificates"][] = ["GUID" => "{" . $ca['uuid'] . "}", "Type" => "Authority", "X509" => $ca_sanitized];
        }
                
        $output_json = json_encode($json_array, JSON_PRETTY_PRINT);
        $xml_f = fopen('installer_profile', 'w');
        fwrite($xml_f, $output_json);
        fclose($xml_f);

        $e = $this->installerBasename . '.onc';
//        if ($this->sign) {
//            $o = system($this->sign . " installer_profile '$e' > /dev/null");
//           if ($o === FALSE)
//                debug(2, "Signing the mobileconfig installer $e FAILED!\n");
//        } else
        rename("installer_profile", $e);

        textdomain($dom);
        return $e;
    }

    /**
     * prepare module desctiption and usage information
     * {@source}
     * @return string HTML text to be displayed in the information window
     */
    public function writeDeviceInfo() {
        $ssid_ct = count($this->attributes['internal:SSID']);
        $out = "<p>";
        $out .= _("This installer is an example only. It produces a zip file containig the IdP certificates, info and logo files (if such have been defined by the IdP administrator) and a dump of all available attributes.");
        return $out;
    }

    /**
     * zip files and return the archive name
     *
     * inline{@source}
     * return string
     */
    private function zipInstaller($attr) {
        $e = $this->installerBasename . '.zip';
        $o = system('zip -q ' . $e . ' *');
        return $e;
    }

}
