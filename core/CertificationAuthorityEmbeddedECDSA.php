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

class CertificationAuthorityEmbeddedECDSA extends EntityWithDBProperties implements CertificationAuthorityInterface
{

    private const LOCATION_ROOT_CA = ROOT . "/config/SilverbulletClientCerts/rootca-ECDSA.pem";
    private const LOCATION_ISSUING_CA = ROOT . "/config/SilverbulletClientCerts/real-ECDSA.pem";
    private const LOCATION_ISSUING_KEY = ROOT . "/config/SilverbulletClientCerts/real-ECDSA.key";
    private const LOCATION_CONFIG = ROOT . "/config/SilverbulletClientCerts/openssl-ECDSA.cnf";

    /**
     * string with the PEM variant of the root CA
     * 
     * @var string
     */
    public $rootPem;

    /**
     * string with the PEM variant of the issuing CA
     * 
     * @var string
     */
    public $issuingCertRaw;

    /**
     * certificate of the issuing CA
     * 
     * @var \OpenSSLCertificate
     */
    private $issuingCert;

    /**
     * filename of the openssl.cnf file we use
     * @var string
     */
    private $conffile;

    /**
     * resource for private key
     * 
     * @var \OpenSSLAsymmetricKey
     */
    private $issuingKey;

    /**
     * sets up the environment so that we can do certificate stuff
     * 
     * @throws Exception
     */
    public function __construct()
    {
        $this->databaseType = "INST";
        parent::__construct();
        $this->rootPem = file_get_contents(CertificationAuthorityEmbeddedECDSA::LOCATION_ROOT_CA);
        if ($this->rootPem === FALSE) {
            throw new Exception("Root CA PEM file not found: " . CertificationAuthorityEmbeddedECDSA::LOCATION_ROOT_CA);
        }
        $this->issuingCertRaw = file_get_contents(CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_CA);
        if ($this->issuingCertRaw === FALSE) {
            throw new Exception("Issuing CA PEM file not found: " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_CA);
        }
        $rootParsed = openssl_x509_read($this->rootPem);
        $issuingCertCandidate = openssl_x509_read($this->issuingCertRaw);
        if ($issuingCertCandidate === FALSE || is_resource($issuingCertCandidate)|| $rootParsed === FALSE) {
            throw new Exception("At least one CA PEM file did not parse correctly (or not a PHP8 resource)!");
        }
        $this->issuingCert = $issuingCertCandidate;
        
        if (stat(CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_KEY) === FALSE) {
            throw new Exception("Private key not found: " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_KEY);
        }
        $issuingKeyTemp = openssl_pkey_get_private("file://" . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_KEY);
        if ($issuingKeyTemp === FALSE || is_resource($issuingKeyTemp)) {
            throw new Exception("The private key did not parse correctly (or not a PHP8 resource)!");
        }
        $this->issuingKey = $issuingKeyTemp;
        if (stat(CertificationAuthorityEmbeddedECDSA::LOCATION_CONFIG) === FALSE) {
            throw new Exception("openssl configuration not found: " . CertificationAuthorityEmbeddedECDSA::LOCATION_CONFIG);
        }
        $this->conffile = CertificationAuthorityEmbeddedECDSA::LOCATION_CONFIG;
    }

    /**
     * create new OCSP statement by making an ephemeral index.txt file for
     * openssl and working with that on the cmdline
     * 
     * @param integer $serial serial number; integer because it is <=64 bit
     * @return string the OCSP statement
     * @throws Exception
     */
    public function triggerNewOCSPStatement($serial): string
    {
        $cert = new SilverbulletCertificate($serial, \devices\Devices::SUPPORT_EMBEDDED_ECDSA);
        $certstatus = "";
        // get all relevant info from object properties
        if ($cert->serial >= 0) { // let's start with the assumption that the cert is valid
            if ($cert->revocationStatus == "REVOKED") {
                // already revoked, simply return canned OCSP response
                $certstatus = "R";
            } else {
                $certstatus = "V";
            }
        }

        $originalExpiry = date_create_from_format("Y-m-d H:i:s", $cert->expiry);
        if ($originalExpiry === FALSE) {
            throw new Exception("Unable to calculate original expiry date, input data bogus!");
        }
        $validity = date_diff(/** @scrutinizer ignore-type */ date_create(), $originalExpiry);
        if ($validity->invert == 1) {
            // negative! Cert is already expired, no need to revoke. 
            // No need to return anything really, but do return the last known OCSP statement to prevent special case
            $certstatus = "E";
        }
        $profile = new ProfileSilverbullet($cert->profileId);
        $inst = new IdP($profile->institution);
        $federation = strtoupper($inst->federation);
        // generate stub index.txt file
        $tempdirArray = \core\common\Entity::createTemporaryDirectory("test");
        $tempdir = $tempdirArray['dir'];
        $nowIndexTxt = (new \DateTime())->format("ymdHis") . "Z";
        $expiryIndexTxt = $originalExpiry->format("ymdHis") . "Z";
        // serials for our CA are always integers
        $serialHex = strtoupper(dechex((int) $cert->serial));
        if (strlen($serialHex) % 2 == 1) {
            $serialHex = "0" . $serialHex;
        }

        $indexStatement = "$certstatus\t$expiryIndexTxt\t" . ($certstatus == "R" ? "$nowIndexTxt,unspecified" : "") . "\t$serialHex\tunknown\t/O=" . \config\ConfAssistant::CONSORTIUM['name'] . "/OU=$federation/CN=$cert->username\n";
        $this->loggerInstance->debug(4, "index.txt contents-to-be: $indexStatement");
        if (!file_put_contents($tempdir . "/index.txt", $indexStatement)) {
            $this->loggerInstance->debug(1, "Unable to write openssl index.txt file for revocation handling!");
        }
        // index.txt.attr is dull but needs to exist
        file_put_contents($tempdir . "/index.txt.attr", "unique_subject = yes\n");
        // call "openssl ocsp" to manufacture our own OCSP statement
        // adding "-rmd sha1" to the following command-line makes the
        // choice of signature algorithm for the response explicit
        // but it's only available from openssl-1.1.0 (which we do not
        // want to require just for that one thing).
        $execCmd = \config\Master::PATHS['openssl'] . " ocsp -issuer " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_CA . " -sha1 -ndays 10 -no_nonce -serial 0x$serialHex -CA " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_CA . " -rsigner " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_CA . " -rkey " . CertificationAuthorityEmbeddedECDSA::LOCATION_ISSUING_KEY . " -index $tempdir/index.txt -no_cert_verify -respout $tempdir/$serialHex.response.der";
        $this->loggerInstance->debug(2, "Calling openssl ocsp with following cmdline: $execCmd\n");
        $output = [];
        $return = 999;
        exec($execCmd, $output, $return);
        if ($return !== 0) {
            throw new Exception("Non-zero return value from openssl ocsp!");
        }
        $ocsp = file_get_contents($tempdir . "/$serialHex.response.der");
        // remove the temp dir!
        unlink($tempdir . "/$serialHex.response.der");
        unlink($tempdir . "/index.txt.attr");
        unlink($tempdir . "/index.txt");
        rmdir($tempdir);
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET OCSP = ?, OCSP_timestamp = NOW() WHERE serial_number = ?", "si", $ocsp, $cert->serial);
        return $ocsp;
    }

    /**
     * sign CSR
     * 
     * @param array   $csr        the request as an opaque PHP8 class
     * @param integer $expiryDays how many days should the cert be valid?
     * @return array the cert and some metadata
     * @throws Exception
     */
    public function signRequest($csr, $expiryDays): array
    {
        if (!($csr["CSR_OBJECT"] instanceof \OpenSSLCertificateSigningRequest)) {
            throw new Exception("This CA needs the CA as an OpenSSLCertificateSigningRequest object!");
        }
        $nonDupSerialFound = FALSE;
        do {
            $serial = random_int(1000000000, PHP_INT_MAX);
            $ecdsa = \devices\Devices::SUPPORT_EMBEDDED_ECDSA;
            $dupeQuery = $this->databaseHandle->exec("SELECT serial_number FROM silverbullet_certificate WHERE serial_number = ? AND ca_type = ?", "is", $serial, $ecdsa);
            // SELECT -> resource, not boolean
            if (mysqli_num_rows(/** @scrutinizer ignore-type */$dupeQuery) == 0) {
                $nonDupSerialFound = TRUE;
            }
        } while (!$nonDupSerialFound);
        $this->loggerInstance->debug(5, "generateCertificate: signing imminent with unique serial $serial, cert type ECDSA.\n");
        $cert = openssl_csr_sign($csr["CSR_OBJECT"], $this->issuingCert, $this->issuingKey, $expiryDays, ['digest_alg' => 'ecdsa-with-SHA1', 'config' => $this->conffile], $serial);
        if ($cert === FALSE) {
            throw new Exception("Unable to sign the request and generate the certificate!");
        }
        return [
            "CERT" => $cert,
            "SERIAL" => $serial,
            "ISSUER" => $this->issuingCertRaw,
            "ROOT" => $this->rootPem,
        ];
    }

    /**
     * the generic caller in SilverbulletCertificate::revokeCertificate
     * has already updated the DB. So all is done; we simply create a new
     * OCSP statement based on the updated DB content
     * 
     * @param integer $serial the serial to revoke, integer because <=64 bit
     * @return void
     */
    public function revokeCertificate($serial): void
    {
        $this->triggerNewOCSPStatement($serial);
    }

    /**
     * generates a CSR with parameters compatible with the CA.
     * 
     * @param \OpenSSLAsymmetricKey $privateKey the private key
     * @param string                $fed        federation, for the C= field
     * @param string                $username   the username, for the CN= field
     * @return array
     * @throws Exception
     */
    public function generateCompatibleCsr($privateKey, $fed, $username): array
    {
        $newCsr = openssl_csr_new(
                ['O' => \config\ConfAssistant::CONSORTIUM['name'],
                    'OU' => $fed,
                    'CN' => $username,
                // 'emailAddress' => $username,
                ], $privateKey, [
            'digest_alg' => "ecdsa-with-SHA1",
            'req_extensions' => 'v3_req',
                ]
        );
        if ($newCsr === FALSE || is_resource($newCsr)) {
            throw new Exception("Unable to create a CSR (or not a PHP8 object)!");
        }
        return [
            "CSR_STRING" => NULL,
            "CSR_OBJECT" => $newCsr, // OpenSSLCertificateSigningRequest
            "USERNAME" => $username,
            "FED" => $fed
        ];
    }

    /**
     * generates a private key compatible with the CA
     * 
     * @return \OpenSSLAsymmetricKey
     * @throws Exception
     */
    public function generateCompatiblePrivateKey()
    {
        $key = openssl_pkey_new(['curve_name' => 'secp384r1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'encrypt_key' => FALSE]);
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
