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
 * This file contains the DevicePPOSUXML class.
 *
 * This device module implements the Wi-Fi Alliance specification for Passpoint:
 * 
 * Per-Provider Subscription Online Sign-Up XML.
 * 
 * The only consuming device we have seen in the field is Android versions 8+
 * and higher (working only on device builds that include Passpoint 
 * functionality; which was optional before Android 11). 
 * 
 * The specification is somewhat limited in that
 * - it EXCLUSIVELY configures Passpoint - no way of configuring SSID networks
 * - Versions before Android 10(?) did not allow to install a custom root CA;
 *   while a full PEM-encoded CA could be included, it had to match an already
 *   installed and trusted CA. This restrictions was lifted recently.
 * 
 * All this makes the device module rather useless - but is left here as a PoC
 * for future re-use if things change in the ecosystem.
 * 
 * This device would typically NOT be enabled in production deployments.
 *  
 * @package ModuleWriting
 */

namespace devices\PP_OSU_XML;

use Exception;

/**
 * This is the main implementation class of the module
 * 
 * Implementation inspired by following the guide at: 
 * https://source.android.com/devices/tech/connect/wifi-passpoint
 * 
 * -> "Passpoint R1 provisioning"
 *
 * @package ModuleWriting
 */
class DevicePPOSUXML extends \core\DeviceConfig {

    /**
     * Constructs a Device object.
     *
     * @final not to be redefined
     */
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_SILVERBULLET]);
    }

    /**
     * creates a AAAServerTrustRoot XML fragment. Currently unused, not clear
     * if Android supports this.
     * 
     * @return string
     */
    private function aaaServerTrustRoot() {

        $retval = '<Node>
        <NodeName>AAAServerTrustRoot</NodeName>';
        foreach ($this->attributes['internal:CAs'][0] as $oneCert) {
            $retval .= '<Node>
                         <NodeName>' . $oneCert['uuid'] . '</NodeName>
                             <Node>
                               <NodeName>CertSHA256Fingerprint</NodeName>
                               <Value>' . $oneCert['sha256'] . '</Value>
                             </Node>
                       </Node>
                  ';
        }
        $retval .= '</Node>';
        return $retval;
    }

    /**
     * creates a CreationDate XML fragment for use in Credential. Currently
     * unused, not clear if Android supports this.
     * 
     * @return string
     */
    private function credentialCreationDate() {
        $now = new \DateTime();
        return '<Node>
          <NodeName>CreationDate</NodeName>
          <Value>' . $now->format("Y-m-d") . "T" . $now->format("H:i:s") . "Z" . '</Value>
        </Node>';
    }

    /**
     * creates a HomeSP XML fragment for consortium identification.
     * 
     * @return string
     */
    private function homeSP() {
        $retval = '<Node>
        <NodeName>HomeSP</NodeName>
        <Node>
          <NodeName>FriendlyName</NodeName>
          <Value>' . sprintf(_("%s via Passpoint"), \config\ConfAssistant::CONSORTIUM['display_name']) . '</Value>
        </Node>
        <Node>
          <NodeName>FQDN</NodeName>
          <Value>' . $this->attributes['eap:server_name'][0] /* what, only one FQDN allowed? */ . '</Value>
        </Node>
        <Node>
          <NodeName>RoamingConsortiumOI</NodeName>
          <Value>';
        $oiList = "";
        $numberOfOi = count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']);
        foreach (\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'] as $index => $oneOi) {
            // according to spec, must be lowercase ASCII without dashes
            // but sample I got was all uppercase, so let's try with that
            $oiList .= str_replace("-", "", trim(strtoupper($oneOi)));
            if ($index < $numberOfOi - 1) {
                // according to spec, comma-separated
                $oiList .= ",";
            }
        }
        $retval .= $oiList . '</Value>
        </Node>
      </Node>
';
        return $retval;
    }

    /**
     * creates a Credential XML fragment for client identification
     * 
     * @return string
     */
    private function credential() {
        $retval = '      <Node>
        <NodeName>Credential</NodeName>
        <Node>
              <NodeName>Realm</NodeName>
              <Value>' . $this->attributes['internal:realm'][0] . '</Value>
            </Node>';
        /* the example file I got did not include CreationDate, so omit it
         * 
         * $content .= $this->credentialCreationDate();
         */
        $retval .= '
          <Node>
            <NodeName>DigitalCertificate</NodeName>
            <Node>
              <NodeName>CertificateType</NodeName>
              <Value>x509v3</Value>
            </Node>
            <Node>
              <NodeName>CertSHA256Fingerprint</NodeName>
              <Value>' . strtoupper($this->clientCert["sha256"]) /* the actual cert has to go... where? */ . '</Value>
            </Node>
          </Node>
      </Node>
';
        return $retval;
    }

    /**
     * creates the overall perProviderSubscription XML
     * 
     * @return string
     */
    private function perProviderSubscription() {
        $retval = '<MgmtTree xmlns="syncml:dmddf1.2">
  <VerDTD>1.2</VerDTD>
  <Node>
    <NodeName>PerProviderSubscription</NodeName>
    <RTProperties>
      <Type>
        <DDFName>urn:wfa:mo:hotspot2dot0-perprovidersubscription:1.0</DDFName>
      </Type>
    </RTProperties>
    <Node>
      <NodeName>CATPasspointSetting</NodeName>
';
        /* it seems that Android does NOT want the AAAServerTrustRoot section
          and instead always validates against the MIME cert attached

          $content .= $this->aaaServerTrustRoot();
         */
        $retval .= $this->homeSP();
        $retval .= $this->credential();

        $retval .= '</Node>
  </Node>
</MgmtTree>';
        return $retval;
    }

    /**
     * creates a MIME part containing the base64-encoded PPS-MO
     * 
     * @return string
     */
    private function mimeChunkPpsMo() {
        return '--{boundary}
Content-Type: application/x-passpoint-profile
Content-Transfer-Encoding: base64

' . chunk_split(base64_encode($this->perProviderSubscription()), 76, "\n");
    }

    /**
     * creates a MIME part containing the base64-encoded CA certs (PEM)
     * 
     * @return string
     */
    private function mimeChunkCaCerts() {
        $retval = '--{boundary}
Content-Type: application/x-x509-ca-cert
Content-Transfer-Encoding: base64

';
        // then, another PEM chunk for each CA certificate we referenced earlier
        // only leaves me to wonder what the "URL" for those is...
        // TODO: more than one CA is currently untested
        foreach ($this->attributes['internal:CAs'][0] as $oneCert) {
            $retval .= chunk_split(base64_encode($oneCert['pem']), 64, "\n");
        }
        return $retval;
    }

    /**
     * creates a MIME part containing the base64-encoded client cert PKCS#12
     * structure - no password.
     * 
     * @return string
     */
    private function mimeChunkClientCert() {
        return '--{boundary}
Content-Type: application/x-pkcs12
Content-Transfer-Encoding: base64

' . chunk_split(base64_encode($this->clientCert['certdataclear']), 76, "\n"); // is PKCS#12, with cleartext key
    }
    /**
     * prepare the PPS-MO file with cert MIME attachments
     *
     * @return string installer path name
     */
    public function writeInstaller() {
        $this->loggerInstance->debug(4, "HS20 PerProviderSubscription Managed Object Installer start\n");
        // sigh... we need to construct a MIME envelope for the payload and the cert data
        $content_encoded = 'Content-Type: multipart/mixed; boundary={boundary}
Content-Transfer-Encoding: base64

';
        $content_encoded .= $this->mimeChunkPpsMo();
        $content_encoded .= $this->mimeChunkCaCerts();
        $content_encoded .= $this->mimeChunkClientCert();
        // this was the last MIME chunk; end the file orderly
        $content_encoded .= "--{boundary}--\n";
        // strangely enough, now encode ALL OF THIS in base64 again. Whatever.
        file_put_contents('installer_profile', chunk_split(base64_encode($content_encoded), 76, "\n"));

        // $fileName = $this->installerBasename . '.bin';
        $fileName = "passpoint.config";

        if (!$this->sign) {
            rename("installer_profile", $fileName);
            return $fileName;
        }

        // still here? We are signing. That actually can't be - the spec doesn't
        // foresee signing.
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
    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= _("This installer is an example only. It produces a zip file containig the IdP certificates, info and logo files (if such have been defined by the IdP administrator) and a dump of all available attributes.");
        return $out;
    }

}
