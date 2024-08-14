<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace core;

use \Exception;
use \SoapFault;

class CertificationAuthorityEduPkiServer extends EntityWithDBProperties implements CertificationAuthorityInterface
{
    private $locationRaCert;
    private $locationRaKey;
    private $locationWebRoot;
    private $eduPkiRaId;
    private $eduPkiCertProfileBoth;
    private $eduPkiCertProfileIdp;
    private $eduPkiCertProfileSp;
    private $eduPkiRaPkeyPassphrase;
    private $eduPkiEndpointPublic;
    private $eduPkiEndpointRa;

    /**
     * sets up the environment so that we can talk to eduPKI
     * 
     * @throws Exception
     */
    public function __construct()
    {
            
        if ( \config\ConfAssistant::eduPKI['testing'] === true ) {
            $this->locationRaCert = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem";
            $this->locationRaKey = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.clearkey";
            $this->locationWebRoot = ROOT . "/config/SilverbulletClientCerts/eduPKI-webserver-root.pem";
            $this->eduPkiRaId = 700;
            $this->eduPkiCertProfileBoth = "Radius Server SOAP";
            $this->eduPkiCertProfileIdp = "Radius Server SOAP";
            $this->eduPkiCertProfileSp = "Radius Server SOAP";
            $this->eduPkiRaPkeyPassphrase = "...";
            $this->eduPkiEndpointPublic = "https://pki.edupki.org/edupki-test-ca/cgi-bin/pub/soap?wsdl=1";
            $this->eduPkiEndpointRa = "https://ra.edupki.org/edupki-test-ca/cgi-bin/ra/soap?wsdl=1";
        } else {
            $this->locationRaCert = ROOT . "/config/SilverbulletClientCerts/edupki-prod-ra.pem";
            $this->locationRaKey = ROOT . "/config/SilverbulletClientCerts/edupki-prod-ra.clearkey";
            $this->locationWebRoot = ROOT . "/config/SilverbulletClientCerts/eduPKI-webserver-root.pem";
            $this->eduPkiRaId = 100;
            $this->eduPkiCertProfileBoth = "eduroam IdP and SP";
            $this->eduPkiCertProfileIdp = "eduroam IdP";
            $this->eduPkiCertProfileSp = "eduroam SP";
            $this->eduPkiRaPkeyPassphrase = "...";
            $this->eduPkiEndpointPublic = "https://pki.edupki.org/edupki-ca/cgi-bin/pub/soap?wsdl=1";
            $this->eduPkiEndpointRa = "https://ra.edupki.org/edupki-ca/cgi-bin/ra/soap?wsdl=1";        
        }
        
        $this->databaseType = "INST";
        parent::__construct();

        if (stat($this->locationRaCert) === FALSE) {
            throw new Exception("RA operator PEM file not found: " . $this->locationRaCert);
        }
        if (stat($this->locationRaKey) === FALSE) {
            throw new Exception("RA operator private key file not found: " . $this->locationRaKey);
        }
        if (stat($this->locationWebRoot) === FALSE) {
            throw new Exception("CA website root CA file not found: " . $this->locationWebRoot);
        }
    }

    /**
     * Creates an updated OCSP statement. Nothing to be done here - eduPKI have
     * their own OCSP responder and the certs point to it. So we are not in the 
     * loop.
     * 
     * @param string $serial serial number of the certificate. Serials are 128 bit, so forcibly a string.
     * @return string a dummy string instead of a real statement
     */
    public function triggerNewOCSPStatement($serial): string
    {
        unset($serial); // not needed
        return "EXTERNAL";
    }

    /**
     * signs a CSR and returns the certificate (blocking wait)
     * 
     * @param array  $csr        the request metadata
     * @param integer $expiryDays how many days should the certificate be valid
     * @return array the certificate with some meta info
     * @throws Exception
     */
    public function signRequest($csr, $expiryDays): array
    {
        if ($csr["CSR_STRING"] === NULL) {
            throw new Exception("This CA needs the CSR in a string (PEM)!");
        }
        $revocationPin = common\Entity::randomString(10, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");
        $soapReqnum = $this->sendRequestToCa($csr, $revocationPin, $expiryDays);
        sleep(55);
        // now, get the actual cert from the CA
        $returnValue = $this->pickupFinalCert($soapReqnum, TRUE);
        if ($returnValue === FALSE) {
            throw new Exception("We wanted to wait, but still got no cert!");
        }
        return $returnValue;
    }

    /**
     * sends the request to the CA and asks for the certificate. Does not block
     * until the certificate is issued, it needs to be picked up separately
     * using its request number.
     * 
     * @param array  $csr           the CSR to sign. The member $csr['CSR'] must contain the CSR in *PEM* format
     * @param string $revocationPin a PIN to be able to revoke the cert later on
     * @param int    $expiryDays    how many days should the certificate be valid
     * @return int the request serial number
     * @throws Exception
     */
    public function sendRequestToCa($csr, $revocationPin, $expiryDays): int
    {
        // initialise connection to eduPKI CA / eduroam RA and send the request to them
        try {            
            if (in_array("eduroam IdP", $csr["POLICIES"]) && in_array("eduroam SP", $csr["POLICIES"])) {
                $profile = $this->eduPkiCertProfileBoth;
            } elseif (in_array("eduroam IdP", $csr["POLICIES"])) {
                $profile = $this->eduPkiCertProfileIdp;
            } elseif (in_array("eduroam IdP", $csr["POLICIES"])) {
                $profile = $this->eduPkiCertProfileSp;
            } else {
                throw new Exception("Unexpected policies requested.");
            }
            $altArray = [# Array mit den Subject Alternative Names
                "email:" . $csr["USERMAIL"]
            ];
            foreach ($csr["ALTNAMES"] as $oneAltName) {
                $altArray[] = "DNS:" . $oneAltName;
            }
            $soapPub = $this->initEduPKISoapSession("PUBLIC");
            $this->loggerInstance->debug(5, "FIRST ACTUAL SOAP REQUEST (Public, newRequest)!\n");
            $this->loggerInstance->debug(5, "PARAM_1: " . $this->eduPkiRaId . "\n");
            $this->loggerInstance->debug(5, "PARAM_2: " . $csr["CSR_STRING"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_3: ");
            $this->loggerInstance->debug(5, $altArray);
            $this->loggerInstance->debug(5, "PARAM_4: " . $profile . "\n");
            $this->loggerInstance->debug(5, "PARAM_5: " . sha1("notused") . "\n");
            $this->loggerInstance->debug(5, "PARAM_6: " . $csr["USERNAME"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_7: " . $csr["USERMAIL"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_8: " . ProfileSilverbullet::PRODUCTNAME . "\n");
            $this->loggerInstance->debug(5, "PARAM_9: false\n");
            $soapNewRequest = $soapPub->newRequest(
                    $this->eduPkiRaId, # RA-ID
                    $csr["CSR_STRING"], # Request im PEM-Format
                    $altArray, # altNames
                    $profile, # Zertifikatprofil
                    sha1($revocationPin), # PIN
                    $csr["USERNAME"], # Name des Antragstellers
                    $csr["USERMAIL"], # Kontakt-E-Mail
                    ProfileSilverbullet::PRODUCTNAME, # Organisationseinheit des Antragstellers
                    false                   # Veröffentlichen des Zertifikats?
            );
            $this->loggerInstance->debug(5, $soapPub->__getLastRequest());
            $this->loggerInstance->debug(5, $soapPub->__getLastResponse());
            if ($soapNewRequest == 0) {
                throw new Exception("Error when sending SOAP request (request serial number was zero). No further details available.");
            }
            $soapReqnum = intval($soapNewRequest);
        } catch (Exception $e) {
            // PHP 7.1 can do this much better
            if (is_soap_fault($e)) {
                throw new Exception("Error when sending SOAP request: " . "{$e->faultcode}:  {
                    $e->faultstring
                }\n");
            }
            throw new Exception("Something odd happened while doing the SOAP request:" . $e->getMessage());
        }
        try {
            $soap = $this->initEduPKISoapSession("RA");
            // tell the CA the desired expiry date of the new certificate
            $expiry = new \DateTime();
            $expiry->modify("+$expiryDays day");
            $expiry->setTimezone(new \DateTimeZone("UTC"));
            $soapExpiryChange = $soap->setRequestParameters(
                    $soapReqnum, [
                "RaID" => $this->eduPkiRaId,
                "Role" => $profile,
                "Subject" => $csr['SUBJECT'],
                "SubjectAltNames" => $altArray,
                "NotBefore" => (new \DateTime())->format('c'),
                "NotAfter" => $expiry->format('c'),
                    ]
            );
            if ($soapExpiryChange === FALSE) {
                throw new Exception("Error when sending SOAP request (unable to change expiry date).");
            }
            // retrieve the raw request to prepare for signature and approval
            // this seems to come out base64-decoded already; maybe PHP
            // considers this "convenience"? But we need it as sent on
            // the wire, so re-encode it!
            $soapCleartext = $soap->getRawRequest($soapReqnum);

            $this->loggerInstance->debug(2, "Actual received SOAP response for getRawRequest was:\n\n");
            $this->loggerInstance->debug(2, $soap->__getLastResponse());
            // for obnoxious reasons, we have to dump the request into a file and let pkcs7_sign read from the file
            // rather than just using the string. Grr.
            $tempdir = \core\common\Entity::createTemporaryDirectory("test");
            file_put_contents($tempdir['dir'] . "/content.txt", $soapCleartext);
            // retrieve our RA cert from filesystem                    
            // the RA certificates are not needed right now because we
            // have resorted to S/MIME signatures with openssl command-line
            // rather than the built-in functions. But that may change in
            // the future, so let's park these two lines for future use.
            // $raCertFile = file_get_contents(ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem");
            // $raCert = openssl_x509_read($raCertFile);
            // $raKey = openssl_pkey_get_private("file://" . ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.clearkey");
            // sign the data, using cmdline because openssl_pkcs7_sign produces strange results
            // -binary didn't help, nor switch -md to sha1 sha256 or sha512
            $this->loggerInstance->debug(2, "Actual content to be signed is this:\n  $soapCleartext\n");
            $execCmd = \config\Master::PATHS['openssl'] . " smime -sign -binary -in " . $tempdir['dir'] . "/content.txt -out " . $tempdir['dir'] . "/signature.txt -outform pem -inkey " . $this->locationRaKey . " -signer " . $this->locationRaCert;
            $this->loggerInstance->debug(2, "Calling openssl smime with following cmdline:   $execCmd\n");
            $output = [];
            $return = 999;
            exec($execCmd, $output, $return);
            if ($return !== 0) {
                throw new Exception("Non-zero return value from openssl smime!");
            }
            // and get the signature blob back from the filesystem
            $detachedSig = trim(file_get_contents($tempdir['dir'] . "/signature.txt"));
            $this->loggerInstance->debug(5, "Request for server approveRequest has parameters:\n");
            $this->loggerInstance->debug(5, $soapReqnum . "\n");
            $this->loggerInstance->debug(5, $soapCleartext . "\n"); // PHP magically encodes this as base64 while sending!
            $this->loggerInstance->debug(5, $detachedSig . "\n");
            $soapIssueCert = $soap->approveRequest($soapReqnum, $soapCleartext, $detachedSig);
            $this->loggerInstance->debug(5, "approveRequest Request was: \n" . $soap->__getLastRequest());
            $this->loggerInstance->debug(5, "approveRequest Response was: \n" . $soap->__getLastResponse());
            if ($soapIssueCert === FALSE) {
                throw new Exception("The locally approved request was NOT processed by the CA.");
            }
        } catch (SoapFault $e) {
            throw new Exception("SoapFault: Error when sending or receiving SOAP message: " . "{$e->faultcode}: {$e->faultname}: {$e->faultstring}: {$e->faultactor}: {$e->detail}: {$e->headerfault}\n");
        } catch (Exception $e) {
            throw new Exception("Exception: Something odd happened between the SOAP requests:" . $e->getMessage());
        }
        return $soapReqnum;
    }

    /**
     * Polls the CA regularly until it gets the certificate for the request at hand. Gives up after 5 minutes.
     * 
     * @param int  $soapReqnum the certificate request for which the cert should be picked up
     * @param bool $wait       whether to wait until the cert is issued or return immediately
     * @return array|false the certificate along with some meta info, or false if we did not want to wait or got a timeout
     * @throws Exception
     */
    public function pickupFinalCert($soapReqnum, $wait)
    {
        try {
            $soap = $this->initEduPKISoapSession("RA");
            $counter = 0;
            $parsedCert = FALSE;
            $x509 = new common\X509();
            while ($parsedCert === FALSE && $counter < 300) {
                $soapCert = $soap->getCertificateByRequestSerial($soapReqnum);

                if (strlen($soapCert) > 10) { // we got the cert
                    $parsedCert = $x509->processCertificate($soapCert);
                } elseif ($wait) { // let's wait five seconds and try again
                    $counter += 5;
                    sleep(5);
                } else {
                    return FALSE; // don't wait, abort without result
                }
            }
            // we should now have an array
            if ($parsedCert === FALSE) {
                throw new Exception("We did not actually get a certificate after waiting for 5 minutes.");
            }
            // let's get the CA certificate chain

            $caInfo = $soap->getCAInfo();
            $certList = $x509->splitCertificate($caInfo->CAChain[0]);
            // find the root
            $theRoot = "";
            foreach ($certList as $oneCert) {
                $content = $x509->processCertificate($oneCert);
                if ($content['root'] == 1) {
                    $theRoot = $content;
                }
            }
            if ($theRoot == "") {
                throw new Exception("CAInfo has no root certificate for us!");
            }
        } catch (SoapFault $e) {
            throw new Exception("SoapFault: Error when sending or receiving SOAP message: " . "{$e->faultcode}: {$e->faultname}: {$e->faultstring}: {$e->faultactor}: {$e->detail}: {$e->headerfault}\n");
        } catch (Exception $e) {
            throw new Exception("Exception: Something odd happened between the SOAP requests:" . $e->getMessage());
        }
        return [
            "CERT" => openssl_x509_read($parsedCert['pem']),
            "SERIAL" => $parsedCert['full_details']['serialNumber'],
            "ISSUER" => $theRoot,
            "ROOT" => $theRoot,
        ];
    }

    /**
     * revokes a certificate
     * 
     * @param string $serial the serial, as a string because it is a 128 bit number
     * @return void
     * @throws Exception
     */
    public function revokeCertificate($serial): void
    {
        try {
            $soap = $this->initEduPKISoapSession("RA");
            $soapRevocationSerial = $soap->newRevocationRequest(["Serial", $serial], "");
            if ($soapRevocationSerial == 0) {
                throw new Exception("Unable to create revocation request, serial number was zero.");
            }
            // retrieve the raw request to prepare for signature and approval
            $soapRawRevRequest = $soap->getRawRevocationRequest($soapRevocationSerial);
            if (strlen($soapRawRevRequest) < 10) { // very basic error handling
                throw new Exception("Suspiciously short data to sign!");
            }
            // for obnoxious reasons, we have to dump the request into a file and let pkcs7_sign read from the file
            // rather than just using the string. Grr.
            $tempdir = \core\common\Entity::createTemporaryDirectory("test");
            file_put_contents($tempdir['dir'] . "/content.txt", $soapRawRevRequest);
            // retrieve our RA cert from filesystem
            // sign the data, using cmdline because openssl_pkcs7_sign produces strange results
            // -binary didn't help, nor switch -md to sha1 sha256 or sha512
            $this->loggerInstance->debug(5, "Actual content to be signed is this:\n$soapRawRevRequest\n");
        $execCmd = \config\Master::PATHS['openssl'] . " smime -sign -binary -in " . $tempdir['dir'] . "/content.txt -out " . $tempdir['dir'] . "/signature.txt -outform pem -inkey " . $this->locationRaKey . " -signer " . $this->locationRaCert;
            $this->loggerInstance->debug(2, "Calling openssl smime with following cmdline: $execCmd\n");
            $output = [];
            $return = 999;
            exec($execCmd, $output, $return);
            if ($return !== 0) {
                throw new Exception("Non-zero return value from openssl smime!");
            }
            // and get the signature blob back from the filesystem
            $detachedSig = trim(file_get_contents($tempdir['dir'] . "/signature.txt"));
            $soapIssueRev = $soap->approveRevocationRequest($soapRevocationSerial, $soapRawRevRequest, $detachedSig);
            if ($soapIssueRev === FALSE) {
                throw new Exception("The locally approved revocation request was NOT processed by the CA.");
            }
        } catch (Exception $e) {
            // PHP 7.1 can do this much better
            if (is_soap_fault($e)) {
                throw new Exception("Error when sending SOAP request: " . "{$e->faultcode}: {$e->faultstring}\n");
            }
            throw new Exception("Something odd happened while doing the SOAP request:" . $e->getMessage());
        }
    }

    /**
     * sets up a connection to the eduPKI SOAP interfaces
     * There is a public interface and an RA-restricted interface;
     * the latter needs an RA client certificate to identify the operator
     * 
     * @param string $type to which interface should we connect to - "PUBLIC" or "RA"
     * @return \SoapClient the connection object
     * @throws Exception
     */
    private function initEduPKISoapSession($type)
    {
        // set context parameters common to both endpoints
        $context_params = [
            'http' => [
                'timeout' => 60,
                'user_agent' => 'Stefan',
                'protocol_version' => 1.1
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                // below is the CA "/C=DE/O=Deutsche Telekom AG/OU=T-TeleSec Trust Center/CN=Deutsche Telekom Root CA 2"
                'cafile' => $this->locationWebRoot,
                'verify_depth' => 5,
                'capture_peer_cert' => true,
            ],
        ];
        $url = "";
        switch ($type) {
            case "PUBLIC":
                $url = $this->eduPkiEndpointPublic;
                $context_params['ssl']['peer_name'] = 'pki.edupki.org';
                break;
            case "RA":
                $url = $this->eduPkiEndpointRa;
                $context_params['ssl']['peer_name'] = 'ra.edupki.org';
                break;
            default:
                throw new Exception("Unknown type of eduPKI interface requested.");
        }
        if ($type == "RA") { // add client auth parameters to the context
            $context_params['ssl']['local_cert'] = $this->locationRaCert;
            $context_params['ssl']['local_pk'] = $this->locationRaKey;
            // $context_params['ssl']['passphrase'] = SilverbulletCertificate::EDUPKI_RA_PKEY_PASSPHRASE;
        }
        // initialise connection to eduPKI CA / eduroam RA
        $soap = new \SoapClient($url, [
            'soap_version' => SOAP_1_1,
            'trace' => TRUE,
            'exceptions' => TRUE,
            'connection_timeout' => 5, // if can't establish the connection within 5 sec, something's wrong
            'cache_wsdl' => WSDL_CACHE_NONE,
            'user_agent' => 'eduroam CAT to eduPKI SOAP Interface',
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'stream_context' => stream_context_create($context_params),
            'typemap' => [
                [
                    'type_ns' => 'http://www.w3.org/2001/XMLSchema',
                    'type_name' => 'integer',
                    'from_xml' => 'core\CertificationAuthorityEduPkiServer::soapFromXmlInteger',
                    'to_xml' => 'core\CertificationAuthorityEduPkiServer::soapToXmlInteger',
                ],
            ],
                ]
        );
        return $soap;
    }

    /**
     * a function that converts integers beyond PHP_INT_MAX to strings for
     * sending in XML messages
     *
     * taken and adapted from 
     * https://www.uni-muenster.de/WWUCA/de/howto-special-phpsoap.html
     * 
     * @param string $x the integer as an XML fragment
     * @return array the integer in array notation
     */
    public function soapFromXmlInteger($x)
    {
        $y = simplexml_load_string($x);
        return array(
            $y->getName(),
            $y->__toString()
        );
    }

    /**
     * a function that converts integers beyond PHP_INT_MAX to strings for
     * sending in XML messages
     * 
     * @param array $x the integer in array notation
     * @return string the integer as string in an XML fragment
     */
    public function soapToXmlInteger($x)
    {
        return '<' . $x[0] . '>'
                . htmlentities($x[1], ENT_NOQUOTES | ENT_XML1)
                . '</' . $x[0] . '>';
    }

    /**
     * generates a CSR which eduPKI likes (DC components etc.)
     * 
     * @param \OpenSSLAsymmetricKey $privateKey a private key
     * @param string                $fed        name of the federation, for C= field
     * @param string                $username   username, for CN= field
     * @return array the CSR along with some meta information
     * @throws Exception
     */
    public function generateCompatibleCsr($privateKey, $fed, $username): array
    {
        $tempdirArray = \core\common\Entity::createTemporaryDirectory("test");
        $tempdir = $tempdirArray['dir'];
        // dump private key into directory
        $outstring = "";
        openssl_pkey_export($privateKey, $outstring);
        file_put_contents($tempdir . "/pkey.pem", $outstring);
        // PHP can only do one DC in the Subject. But we need three.
        $execCmd = \config\Master::PATHS['openssl'] . " req -new -sha256 -key $tempdir/pkey.pem -out $tempdir/request.csr -subj /DC=test/DC=test/DC=eduroam/C=$fed/O=" . \config\ConfAssistant::CONSORTIUM['name'] . "/OU=$fed/CN=$username/emailAddress=$username";
        $this->loggerInstance->debug(2, "Calling openssl req with following cmdline: $execCmd\n");
        $output = [];
        $return = 999;
        exec($execCmd, $output, $return);
        if ($return !== 0) {
            throw new Exception("Non-zero return value from openssl req!");
        }
        $newCsr = file_get_contents("$tempdir/request.csr");
        // remove the temp dir!
        unlink("$tempdir/pkey.pem");
        unlink("$tempdir/request.csr");
        rmdir($tempdir);
        if ($newCsr === FALSE) {
            throw new Exception("Unable to create a CSR!");
        }
        return [
            "CSR_STRING" => $newCsr, // a string
            "CSR_OBJECT" => NULL,
            "USERNAME" => $username,
            "FED" => $fed
        ];
    }

    /**
     * generates a private key eduPKI can handle
     * 
     * @return \OpenSSLAsymmetricKey the key
     * @throws Exception
     */
    public function generateCompatiblePrivateKey()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'encrypt_key' => FALSE]);
        if ($key === FALSE || is_resource($key)) {
            throw new Exception("Unable to generate a private key / not a PHP8 object.");
        }
        return $key;
    }

    /**
     * CAs don't have any local caching or other freshness issues
     * 
     * @return void
     */
    public function updateFreshness()
    {
        // nothing to be done here.
    }
}
