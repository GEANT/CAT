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

class CertificationAuthorityEduPki extends EntityWithDBProperties implements CertificationAuthorityInterface
{

    private const LOCATION_RA_CERT = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem";
    private const LOCATION_RA_KEY = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.clearkey";
    private const LOCATION_WEBROOT = ROOT . "/config/SilverbulletClientCerts/eduPKI-webserver-root.pem";
    private const EDUPKI_RA_ID = 700;
    private const EDUPKI_CERT_PROFILE = "User SOAP";
    private const EDUPKI_RA_PKEY_PASSPHRASE = "...";

    /**
     * sets up the environment so that we can talk to eduPKI
     * 
     * @throws Exception
     */
    public function __construct()
    {
        $this->databaseType = "INST";
        parent::__construct();

        if (stat(CertificationAuthorityEduPki::LOCATION_RA_CERT) === FALSE) {
            throw new Exception("RA operator PEM file not found: " . CertificationAuthorityEduPki::LOCATION_RA_CERT);
        }
        if (stat(CertificationAuthorityEduPki::LOCATION_RA_KEY) === FALSE) {
            throw new Exception("RA operator private key file not found: " . CertificationAuthorityEduPki::LOCATION_RA_KEY);
        }
        if (stat(CertificationAuthorityEduPki::LOCATION_WEBROOT) === FALSE) {
            throw new Exception("CA website root CA file not found: " . CertificationAuthorityEduPki::LOCATION_WEBROOT);
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
     * signs a CSR
     * 
     * @param array   $csr        the request structure, an array
     * @param integer $expiryDays how many days should the certificate be valid
     * @return array the certificate with some meta info
     * @throws Exception
     */
    public function signRequest($csr, $expiryDays): array
    {
        if ($csr["CSR_STRING"] === NULL) {
            throw new Exception("This CA needs the CSR in a string (PEM)!");
        }
        // initialise connection to eduPKI CA / eduroam RA and send the request to them
        try {
            $altArray = [# Array mit den Subject Alternative Names
                "email:" . $csr["USERNAME"]
            ];
            $soapPub = $this->initEduPKISoapSession("PUBLIC");
            $this->loggerInstance->debug(5, "FIRST ACTUAL SOAP REQUEST (Public, newRequest)!\n");
            $this->loggerInstance->debug(5, "PARAM_1: " . CertificationAuthorityEduPki::EDUPKI_RA_ID . "\n");
            $this->loggerInstance->debug(5, "PARAM_2: " . $csr["CSR_STRING"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_3: ");
            $this->loggerInstance->debug(5, $altArray);
            $this->loggerInstance->debug(5, "PARAM_4: " . CertificationAuthorityEduPki::EDUPKI_CERT_PROFILE . "\n");
            $this->loggerInstance->debug(5, "PARAM_5: " . sha1("notused") . "\n");
            $this->loggerInstance->debug(5, "PARAM_6: " . $csr["USERNAME"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_7: " . $csr["USERNAME"] . "\n");
            $this->loggerInstance->debug(5, "PARAM_8: " . \config\ConfAssistant::SILVERBULLET['product_name'] . "\n");
            $this->loggerInstance->debug(5, "PARAM_9: false\n");
            $soapNewRequest = $soapPub->newRequest(
                    CertificationAuthorityEduPki::EDUPKI_RA_ID, # RA-ID
                    $csr["CSR_STRING"], # Request im PEM-Format
                    $altArray, # altNames
                    CertificationAuthorityEduPki::EDUPKI_CERT_PROFILE, # Zertifikatprofil
                    sha1("notused"), # PIN
                    $csr["USERNAME"], # Name des Antragstellers
                    $csr["USERNAME"], # Kontakt-E-Mail
                    \config\ConfAssistant::SILVERBULLET['product_name'], # Organisationseinheit des Antragstellers
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
                "RaID" => CertificationAuthorityEduPki::EDUPKI_RA_ID,
                "Role" => CertificationAuthorityEduPki::EDUPKI_CERT_PROFILE,
                "Subject" => "DC=eduroam,DC=test,DC=test,C=" . $csr["FED"] . ",O=" . \config\ConfAssistant::CONSORTIUM['name'] . ",OU=" . $csr["FED"] . ",CN=" . $csr['USERNAME'] . ",emailAddress=" . $csr['USERNAME'],
                "SubjectAltNames" => ["email:" . $csr["USERNAME"]],
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

            $this->loggerInstance->debug(5, "Actual received SOAP resonse for getRawRequest was:\n\n");
            $this->loggerInstance->debug(5, $soap->__getLastResponse());
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
            $this->loggerInstance->debug(5, "Actual content to be signed is this:\n  $soapCleartext\n");
            $execCmd = \config\Master::PATHS['openssl'] . " smime -sign -binary -in " . $tempdir['dir'] . "/content.txt -out " . $tempdir['dir'] . "/signature.txt -outform pem -inkey " . ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.clearkey -signer " . ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem";
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
            // now, get the actual cert from the CA
            sleep(55);
            $counter = 55;
            $parsedCert = FALSE;
            do {
                $counter += 5;
                sleep(5); // always start with a wait. Signature round-trip on the server side is at least one minute.
                $soapCert = $soap->getCertificateByRequestSerial($soapReqnum);
                $x509 = new common\X509();
                if (strlen($soapCert) > 10) {
                    $parsedCert = $x509->processCertificate($soapCert);
                }
            } while ($parsedCert === FALSE && $counter < 500);
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
            $execCmd = \config\Master::PATHS['openssl'] . " smime -sign -binary -in " . $tempdir['dir'] . "/content.txt -out " . $tempdir['dir'] . "/signature.txt -outform pem -inkey " . CertificationAuthorityEduPki::LOCATION_RA_KEY . " -signer " . CertificationAuthorityEduPki::LOCATION_RA_CERT;
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
                'cafile' => CertificationAuthorityEduPki::LOCATION_WEBROOT,
                'verify_depth' => 5,
                'capture_peer_cert' => true,
            ],
        ];
        $url = "";
        switch ($type) {
            case "PUBLIC":
                $url = "https://pki.edupki.org/edupki-test-ca/cgi-bin/pub/soap?wsdl=1";
                $context_params['ssl']['peer_name'] = 'pki.edupki.org';
                break;
            case "RA":
                $url = "https://ra.edupki.org/edupki-test-ca/cgi-bin/ra/soap?wsdl=1";
                $context_params['ssl']['peer_name'] = 'ra.edupki.org';
                break;
            default:
                throw new Exception("Unknown type of eduPKI interface requested.");
        }
        if ($type == "RA") { // add client auth parameters to the context
            $context_params['ssl']['local_cert'] = CertificationAuthorityEduPki::LOCATION_RA_CERT;
            $context_params['ssl']['local_pk'] = CertificationAuthorityEduPki::LOCATION_RA_KEY;
            // $context_params['ssl']['passphrase'] = SilverbulletCertificate::EDUPKI_RA_PKEY_PASSPHRASE;
        }
        // initialse connection to eduPKI CA / eduroam RA
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
                    'from_xml' => 'core\CertificationAuthorityEduPki::soapFromXmlInteger',
                    'to_xml' => 'core\CertificationAuthorityEduPki::soapToXmlInteger',
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