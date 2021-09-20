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
 * This file contains the DeviceChromebook class, for generating installers
 * on ChromeOS.
 *
 * We follow the specification at: https://chromium.googlesource.com/chromium/src/+/refs/heads/main/components/onc/docs/onc_spec.md
 *  
 * @package ModuleWriting
 */

namespace devices\chromebook;

use Exception;

/**
 * This is the main implementation class of the ChromeOS module
 *
 * @package ModuleWriting
 */
class DeviceChromebook extends \core\DeviceConfig
{

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
    final public function __construct()
    {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        $this->specialities['media:openroaming'] = _("This device does not support provisioning of OpenRoaming.");
        $this->specialities['media:consortium_OI'] = _("This device does not support provisioning of Passpoint networks.");
    }

    /**
     * encrypts the entire configuration. Only used in SB to protect the client
     * credential
     * 
     * @param string $clearJson the cleartext JSON string to encrypt
     * @param string $password  the import PIN we told the user
     * @return string
     */
    private function encryptConfig($clearJson, $password)
    {
        $salt = \core\common\Entity::randomString(12);
        $encryptionKey = hash_pbkdf2("sha1", $password, $salt, DeviceChromebook::PBKDF2_ITERATIONS, 32, TRUE); // the spec is not clear about the algo. Source code in Chromium makes clear it's SHA1.
        $strong = FALSE; // should become TRUE if strong crypto is available like it should.
        $initVector = openssl_random_pseudo_bytes(16, $strong);
        if ($strong === FALSE) {
            $this->loggerInstance->debug(1, "WARNING: OpenSSL reports that a random value was generated with a weak cryptographic algorithm (DeviceChromebook::writeInstaller()). You should investigate the reason for this!");
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
            "Iterations" => DeviceChromebook::PBKDF2_ITERATIONS,
            "IV" => base64_encode($initVector),
            "Type" => "EncryptedConfiguration",
        ];
        return json_encode($finalArray);
    }

    /**
     * Creates a WiFi block (SSID based only, no support for Passpoint)
     * @param string $ssid       the SSID to configure
     * @param array  $eapdetails the EAP sub-block as derived from EapBlock()
     * @return array
     */
    private function wifiBlock($ssid, $eapdetails)
    {
        return [
            "GUID" => \core\common\Entity::uuid('', $ssid),
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
            "ProxySettings" => $this->proxySettings(),
        ];
    }

    /**
     * Creates the ProxySettings block
     * 
     * @return array
     */
    protected function proxySettings()
    {
        if (isset($this->attributes['media:force_proxy'])) {
            // find the port delimiter. In case of IPv6, there are multiple ':' 
            // characters, so we have to find the LAST one
            $serverAndPort = explode(':', strrev($this->attributes['media:force_proxy'][0]), 2);
            // characters are still reversed, invert on use!
            return ["Type" => "Manual",
                "Manual" => [
                    "SecureHTTPProxy" => [
                        "Host" => strrev($serverAndPort[1]),
                        "Port" => strrev($serverAndPort[0])
                    ]
                ]
            ];
        }
        return ["Type" => "WPAD"];
    }

    /**
     * Creates a configuration block for wired Ethernet
     * 
     * @param array $eapdetails the EAP configuration as created with eapBlock()
     * @return array
     */
    private function wiredBlock($eapdetails)
    {
        return [
            "GUID" => \core\common\Entity::uuid('', "wired-dot1x-ethernet") . "}",
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

    /**
     * Creates the EAP configuration sub-block
     * 
     * @param array $caRefs list of strings with CA references
     * @return array
     */
    private function eapBlock($caRefs)
    {
        $selectedEap = $this->selectedEap;
        $outerId = $this->determineOuterIdString();
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

        if ($outerId !== NULL) {
            $eaparray["AnonymousIdentity"] = $outerId;
        }
        if ($selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $eaparray["Identity"] = $this->clientCert["certObject"]->username;
        }
        return $eaparray;
    }

    /**
     * prepare a ONC file
     *
     * @return string installer path name
     * @throws Exception
     */
    public function writeInstaller()
    {
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
            if ($caSanitized1 === FALSE) {
                throw new Exception("Error cropping PEM data at its BEGIN marker.");
            }
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
        $eaparray = $this->eapBlock($caRefs);
        // define Wi-Fi networks
        foreach ($this->attributes['internal:networks'] as $netDefinition) {
            foreach ($netDefinition['ssid'] as $ssid) {
                $jsonArray["NetworkConfigurations"][] = $this->wifiBlock($ssid, $eaparray);
            }
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

        file_put_contents('installer_profile', $finalJson);

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
     * 
     * @return string HTML text to be displayed in the information window
     */
    public function writeDeviceInfo()
    {
        \core\common\Entity::intoThePotatoes();
        $out = "<p>";
        $out .= _("The installer is a file with the extension '.onc'. Please download it, open Chrome, and navigate to the URL <a href='chrome://net-internals/#chromeos'>chrome://net-internals/#chromeos</a>. Then, use the 'Import ONC file' button. The import is silent; the new network definitions will be added to the preferred networks.");
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }
}