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

namespace devices\chromebook;

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
class Device_Chromebook extends \core\DeviceConfig {

    /**
     * Number of iterations for the PBKDF2 function. 
     * 20000 is the minimum as per ChromeOS ONC spec
     * 500000 is the maximum as per Chromium source code
     * https://cs.chromium.org/chromium/src/chromeos/network/onc/onc_utils.cc?sq=package:chromium&dr=CSs&rcl=1482394814&l=110
     */
    const PBKDF2_ITERATIONS = 20000;

    /**
     * Constructs a Device object.
     *
     * @final not to be redefined
     */
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_SILVERBULLET]);
    }

    private function encryptConfig($clearJson, $password) {
        $salt = \core\ProfileSilverbullet::randomString(12);
        $encryptionKey = hash_pbkdf2("sha1", $password, $salt, Device_Chromebook::PBKDF2_ITERATIONS, 32, TRUE); // the spec is not clear about the algo. Source code in Chromium makes clear it's SHA1.
        $strong = FALSE; // should become TRUE if strong crypto is available like it should.
        $initVector = openssl_random_pseudo_bytes(16, $strong);
        if ($strong === FALSE) {
            $this->loggerInstance->debug(1, "WARNING: OpenSSL reports that a random value was generated with a weak cryptographic algorithm (Device_chromebook::writeInstaller()). You should investigate the reason for this!");
        }
        $cryptoJson = openssl_encrypt($clearJson, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $initVector);
        $hmac = hash_hmac("sha1", $cryptoJson, $encryptionKey, TRUE);

        $this->loggerInstance->debug(4, "Clear = $clearJson\nSalt = $salt\nPW = " . $password . "\nb(IV) = " . base64_encode($initVector) . "\nb(Cipher) = " . base64_encode($cryptoJson) . "\nb(HMAC) = " . base64_encode($hmac));

        // now, generate the container that holds all the crypto data
        $finalArray = [
            "Cipher" => "AES256",
            "Ciphertext" => base64_encode($cryptoJson),
            "HMAC" => base64_encode($hmac), // again by reading source code! And why?
            "HMACMethod" => "SHA1",
            "Salt" => base64_encode($salt), // this is B64 encoded, but had to read Chromium source code to find out! Not in the spec!
            "Stretch" => "PBKDF2",
            "Iterations" => Device_Chromebook::PBKDF2_ITERATIONS,
            "IV" => base64_encode($initVector),
            "Type" => "EncryptedConfiguration",
        ];
        return json_encode($finalArray);
    }

    private function wifiBlock($ssid, $eapdetails) {
        return [
                "GUID" => $this->uuid('', $ssid),
                "Name" => "$ssid",
                "Remove" => false,
                "Type" => "WiFi",
                "WiFi" => [
                    "AutoConnect" => true,
                    "EAP" => $eapdetails,
                    "HiddenSSID" => false,
                    "SSID" => $ssid,
                    "Security" => "WPA-EAP",
                ],
                "ProxySettings" => ["Type" => "WPAD"],
            ];
    }
    
    private function wiredBlock($eapdetails) {
        return [
                "GUID" => $this->uuid('', "wired-dot1x-ethernet") . "}",
                "Name" => "eduroam configuration (wired network)",
                "Remove" => false,
                "Type" => "Ethernet",
                "Ethernet" => [
                    "Authentication" => "8021X",
                    "EAP" => $eapdetails,
                ],
                "ProxySettings" => ["Type" => "WPAD"],
            ];
    }
    
    private function eapBlock($selectedEap, $outerId, $caRefs) {
        $eapPrettyprint = \core\common\EAP::eapDisplayName($selectedEap);
        // ONC has its own enums, and guess what, they don't always match
        if ($eapPrettyprint["INNER"] == "MSCHAPV2") {
            $eapPrettyprint["INNER"] = "MSCHAPv2";
        }
        if ($eapPrettyprint["OUTER"] == "TTLS") {
            $eapPrettyprint["OUTER"] = "EAP-TTLS";
        }
        if ($eapPrettyprint["OUTER"] == "TLS") {
            $eapPrettyprint["OUTER"] = "EAP-TLS";
        }

        // define EAP properties

        $eaparray = [];

        // if silverbullet, we deliver the client cert inline

        if ($selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $eaparray['ClientCertRef'] = "[" . $this->clientCert['GUID'] . "]";
            $eaparray['ClientCertType'] = "Ref";
        }

        $eaparray["Outer"] = $eapPrettyprint["OUTER"];
        if ($eapPrettyprint["INNER"] == "MSCHAPv2") {
            $eaparray["Inner"] = $eapPrettyprint["INNER"];
        }
        $eaparray["SaveCredentials"] = true;
        $eaparray["ServerCARefs"] = $caRefs; // maybe takes just one CA?
        $eaparray["UseSystemCAs"] = false;

        if ($outerId) {
            $eaparray["AnonymousIdentity"] = $outerId;
        }
        if ($selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $eaparray["Identity"] = $this->clientCert["username"];
        }
        return $eaparray;
    }
    /**
     * prepare a ONC file
     *
     * {@source}
     * @return string installer path name
     */
    public function writeInstaller() {
        $this->loggerInstance->debug(4, "Chromebook Installer start\n");
        $caRefs = [];
        // we don't do per-user encrypted containers
        $jsonArray = ["Type" => "UnencryptedConfiguration"];

        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            $caRefs[] = "{" . $ca['uuid'] . "}";
        }
        // define CA certificates
        foreach ($this->attributes['internal:CAs'][0] as $ca) {
            // strip -----BEGIN CERTIFICATE----- and -----END CERTIFICATE-----
            $this->loggerInstance->debug(3, $ca['pem']);
            $caSanitized1 = substr($ca['pem'], 27, strlen($ca['pem']) - 27 - 25 - 1);
            $this->loggerInstance->debug(4, $caSanitized1 . "\n");
            // remove \n
            $caSanitized = str_replace("\n", "", $caSanitized1);
            $jsonArray["Certificates"][] = ["GUID" => "{" . $ca['uuid'] . "}", "Remove" => false, "Type" => "Authority", "X509" => $caSanitized];
            $this->loggerInstance->debug(3, $caSanitized . "\n");
        }
        // if we are doing silverbullet, include the unencrypted(!) P12 as a client certificate
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $jsonArray["Certificates"][] = ["GUID" => "[" . $this->clientCert['GUID'] . "]", "PKCS12" => base64_encode($this->clientCert['certdataclear']), "Remove" => false, "Type" => "Client"];
        }
        $eaparray = $this->eapBlock($this->selectedEap, $this->determineOuterIdString(), $caRefs);
        // define Wi-Fi networks
        foreach ($this->attributes['internal:SSID'] as $ssid => $cryptolevel) {
            $jsonArray["NetworkConfigurations"][] = $this->wifiBlock($ssid, $eaparray);
        }
        // are we also configuring wired?
        if (isset($this->attributes['media:wired'])) {
            $jsonArray["NetworkConfigurations"][] = $this->wiredBlock($eaparray);
        }

        $clearJson = json_encode($jsonArray, JSON_PRETTY_PRINT);
        $finalJson = $clearJson;
        // if we are doing silverbullet we should also encrypt the entire structure(!) with the import password and embed it into a EncryptedConfiguration
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $finalJson = $this->encryptConfig($clearJson, $this->clientCert['importPassword']);
        }

        $outputFile = fopen('installer_profile', 'w');
        fwrite($outputFile, $finalJson);
        fclose($outputFile);

        $fileName = $this->installerBasename . '.onc';

        if (!$this->sign) {
            rename("installer_profile", $fileName);
            return $fileName;
        }

        // still here? We are signing. That actually can't be - ONC does not
        // have the notion of signing
        // but if they ever change their mind, we are prepared

        $outputFromSigning = system($this->sign . " installer_profile '$fileName' > /dev/null");
        if ($outputFromSigning === FALSE) {
            $this->loggerInstance->debug(2, "Signing the ONC installer $fileName FAILED!\n");
        }

        return $fileName;
    }

    /**
     * prepare module desctiption and usage information
     * {@source}
     * @return string HTML text to be displayed in the information window
     */
    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= _("This installer is an example only. It produces a zip file containig the IdP certificates, info and logo files (if such have been defined by the IdP administrator) and a dump of all available attributes.");
        return $out;
    }

}
