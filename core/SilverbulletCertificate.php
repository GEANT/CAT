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
 * This file contains the SilverbulletInvitation class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

class SilverbulletCertificate extends EntityWithDBProperties {

    public $username;
    public $expiry;
    public $serial;
    public $dbId;
    public $invitationId;
    public $userId;
    public $profileId;
    public $issued;
    public $device;
    public $revocationStatus;
    public $revocationTime;
    public $ocsp;
    public $ocspTimestamp;
    public $status;

    const CERTSTATUS_VALID = 1;
    const CERTSTATUS_EXPIRED = 2;
    const CERTSTATUS_REVOKED = 3;
    const CERTSTATUS_INVALID = 4;

    /**
     * instantiates an existing certificate, identified either by its serial
     * number or the username. 
     * 
     * Use static issueCertificate() to generate a whole new cert.
     * 
     * @param int|string $identifier
     */
    public function __construct($identifier) {
        $this->databaseType = "INST";
        parent::__construct();
        $this->username = "";
        $this->expiry = "2000-01-01 00:00:00";
        $this->serial = -1;
        $this->dbId = -1;
        $this->invitationId = -1;
        $this->userId = -1;
        $this->profileId = -1;
        $this->issued = "2000-01-01 00:00:00";
        $this->device = NULL;
        $this->revocationStatus = "REVOKED";
        $this->revocationTime = "2000-01-01 00:00:00";
        $this->ocsp = NULL;
        $this->ocspTimestamp = "2000-01-01 00:00:00";
        $this->status = SilverbulletCertificate::CERTSTATUS_INVALID;

        $incoming = FALSE;
        if (is_numeric($identifier)) {
            $incoming = $this->databaseHandle->exec("SELECT `id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`, `issued`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp` FROM `silverbullet_certificate` WHERE serial_number = ?", "i", $identifier);
        } else { // it's a string instead
            $incoming = $this->databaseHandle->exec("SELECT `id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`, `issued`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp` FROM `silverbullet_certificate` WHERE cn = ?", "s", $identifier);
        }

        // SELECT -> mysqli_resource, not boolean
        while ($oneResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $incoming)) { // there is only at most one
            $this->username = $oneResult->cn;
            $this->expiry = $oneResult->expiry;
            $this->serial = $oneResult->serial_number;
            $this->dbId = $oneResult->id;
            $this->invitationId = $oneResult->silverbullet_invitation_id;
            $this->userId = $oneResult->silverbullet_user_id;
            $this->profileId = $oneResult->profile_id;
            $this->issued = $oneResult->issued;
            $this->device = $oneResult->device;
            $this->revocationStatus = $oneResult->revocation_status;
            $this->revocationTime = $oneResult->revocation_time;
            $this->ocsp = $oneResult->OCSP;
            $this->ocspTimestamp = $oneResult->OCSP_timestamp;
            // is the cert expired?
            $now = new \DateTime();
            $cert_expiry = new \DateTime($this->expiry);
            $delta = $now->diff($cert_expiry);
            $this->status = ($delta->invert == 1 ? SilverbulletCertificate::CERTSTATUS_EXPIRED : SilverbulletCertificate::CERTSTATUS_VALID);
            // expired is expired; even if it was previously revoked. But do update status for revoked ones...
            if ($this->status == SilverbulletCertificate::CERTSTATUS_VALID && $this->revocationStatus == "REVOKED") {
                $this->status = SilverbulletCertificate::CERTSTATUS_REVOKED;
            }
        }
    }

    /**
     * 
     * @return array of basic certificate details
     */
    public function getBasicInfo() {
        $returnArray = []; // unnecessary because the iterator below is never empty, but Scrutinizer gets excited nontheless
        foreach (['status', 'serial', 'username', 'device', 'issued', 'expiry'] as $key) {
            $returnArray[$key] = $this->$key;
        }
        return($returnArray);
    }

    public function updateFreshness() {
        // nothing to be done here.
    }

    /**
     * issue a certificate based on a token
     *
     * @param string $token
     * @param string $importPassword
     * @return array
     */
    public static function issueCertificate($token, $importPassword) {
        $loggerInstance = new common\Logging();
        $databaseHandle = DBConnection::handle("INST");
        $loggerInstance->debug(5, "generateCertificate() - starting.\n");
        $invitationObject = new SilverbulletInvitation($token);
        $profile = new ProfileSilverbullet($invitationObject->profile);
        $inst = new IdP($profile->institution);
        $loggerInstance->debug(5, "tokenStatus: done, got " . $invitationObject->invitationTokenStatus . ", " . $invitationObject->profile . ", " . $invitationObject->userId . ", " . $invitationObject->expiry . ", " . $invitationObject->invitationTokenString . "\n");
        if ($invitationObject->invitationTokenStatus != SilverbulletInvitation::SB_TOKENSTATUS_VALID && $invitationObject->invitationTokenStatus != SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
            throw new Exception("Attempt to generate a SilverBullet installer with an invalid/redeemed/expired token. The user should never have gotten that far!");
        }

        // SQL query to find the expiry date of the *user* to find the correct ValidUntil for the cert
        $user = $invitationObject->userId;
        $userrow = $databaseHandle->exec("SELECT expiry FROM silverbullet_user WHERE id = ?", "i", $user);
        // SELECT -> resource, not boolean
        if ($userrow->num_rows != 1) {
            throw new Exception("Despite a valid token, the corresponding user was not found in database or database query error!");
        }
        $expiryObject = mysqli_fetch_object(/** @scrutinizer ignore-type */ $userrow);
        $loggerInstance->debug(5, "EXP: " . $expiryObject->expiry . "\n");
        $expiryDateObject = date_create_from_format("Y-m-d H:i:s", $expiryObject->expiry);
        if ($expiryDateObject === FALSE) {
            throw new Exception("The expiry date we got from the DB is bogus!");
        }
        $loggerInstance->debug(5, $expiryDateObject->format("Y-m-d H:i:s") . "\n");
        // date_create with no parameters can't fail, i.e. is never FALSE
        $validity = date_diff(/** @scrutinizer ignore-type */ date_create(), $expiryDateObject);
        $expiryDays = $validity->days + 1;
        if ($validity->invert == 1) { // negative! That should not be possible
            throw new Exception("Attempt to generate a certificate for a user which is already expired!");
        }

        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'encrypt_key' => FALSE]);

        $csr = SilverbulletCertificate::generateCsr($privateKey, strtoupper($inst->federation), $profile->getAttributes("internal:realm")[0]['value']);

        $loggerInstance->debug(5, "generateCertificate: proceeding to sign cert.\n");

        $certMeta = SilverbulletCertificate::signCsr($csr, $expiryDays);
        $cert = $certMeta["CERT"];
        $issuingCaPem = $certMeta["ISSUER"];
        $rootCaPem = $certMeta["ROOT"];
        $serial = $certMeta["SERIAL"];

        $loggerInstance->debug(5, "generateCertificate: post-processing certificate.\n");

        // with the cert, our private key and import password, make a PKCS#12 container out of it
        $exportedCertProt = "";
        openssl_pkcs12_export($cert, $exportedCertProt, $privateKey, $importPassword, ['extracerts' => [$issuingCaPem /* , $rootCaPem */]]);
        $exportedCertClear = "";
        openssl_pkcs12_export($cert, $exportedCertClear, $privateKey, "", ['extracerts' => [$issuingCaPem, $rootCaPem]]);
        // store resulting cert CN and expiry date in separate columns into DB - do not store the cert data itself as it contains the private key!
        // we need the *real* expiry date, not just the day-approximation
        $x509 = new \core\common\X509();
        $certString = "";
        openssl_x509_export($cert, $certString);
        $parsedCert = $x509->processCertificate($certString);
        $loggerInstance->debug(5, "CERTINFO: " . print_r($parsedCert['full_details'], true));
        $realExpiryDate = date_create_from_format("U", $parsedCert['full_details']['validTo_time_t'])->format("Y-m-d H:i:s");

        // store new cert info in DB
        $databaseHandle->exec("INSERT INTO `silverbullet_certificate` (`profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`) VALUES (?, ?, ?, ?, ?, ?)", "iiisss", $invitationObject->profile, $invitationObject->userId, $invitationObject->identifier, $serial, $csr["USERNAME"], $realExpiryDate);
        // newborn cert immediately gets its "valid" OCSP response
        $certObject = new SilverbulletCertificate($serial);
        $certObject->triggerNewOCSPStatement();
// return PKCS#12 data stream
        return [
            "certObject" => $certObject,
            "certdata" => $exportedCertProt,
            "certdataclear" => $exportedCertClear,
            "sha1" => openssl_x509_fingerprint($cert, "sha1"),
            "sha256" => openssl_x509_fingerprint($cert, "sha256"),
            'importPassword' => $importPassword,
            'GUID' => common\Entity::uuid("", $exportedCertProt),
        ];
    }

    /**
     * triggers a new OCSP statement for the given serial number
     * 
     * @return string DER-encoded OCSP status info (binary data!)
     */
    public function triggerNewOCSPStatement() {
        $logHandle = new \core\common\Logging();
        $logHandle->debug(2, "Triggering new OCSP statement for serial $this->serial.\n");
        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":
                $certstatus = "";
                // get all relevant info from object properties
                if ($this->serial >= 0) { // let's start with the assumption that the cert is valid
                    if ($this->revocationStatus == "REVOKED") {
                        // already revoked, simply return canned OCSP response
                        $certstatus = "R";
                    } else {
                        $certstatus = "V";
                    }
                }

                $originalExpiry = date_create_from_format("Y-m-d H:i:s", $this->expiry);
                if ($originalExpiry === FALSE) {
                    throw new Exception("Unable to calculate original expiry date, input data bogus!");
                }
                $validity = date_diff(/** @scrutinizer ignore-type */ date_create(), $originalExpiry);
                if ($validity->invert == 1) {
                    // negative! Cert is already expired, no need to revoke. 
                    // No need to return anything really, but do return the last known OCSP statement to prevent special case
                    $certstatus = "E";
                }
                $profile = new ProfileSilverbullet($this->profileId);
                $inst = new IdP($profile->institution);
                $federation = strtoupper($inst->federation);
                // generate stub index.txt file
                $tempdirArray = \core\common\Entity::createTemporaryDirectory("test");
                $tempdir = $tempdirArray['dir'];
                $nowIndexTxt = (new \DateTime())->format("ymdHis") . "Z";
                $expiryIndexTxt = $originalExpiry->format("ymdHis") . "Z";
                $serialHex = strtoupper(dechex($this->serial));
                if (strlen($serialHex) % 2 == 1) {
                    $serialHex = "0" . $serialHex;
                }

                $indexStatement = "$certstatus\t$expiryIndexTxt\t" . ($certstatus == "R" ? "$nowIndexTxt,unspecified" : "") . "\t$serialHex\tunknown\t/O=" . CONFIG_CONFASSISTANT['CONSORTIUM']['name'] . "/OU=$federation/CN=$this->username/emailAddress=$this->username\n";
                $logHandle->debug(4, "index.txt contents-to-be: $indexStatement");
                if (!file_put_contents($tempdir . "/index.txt", $indexStatement)) {
                    $logHandle->debug(1, "Unable to write openssl index.txt file for revocation handling!");
                }
                // index.txt.attr is dull but needs to exist
                file_put_contents($tempdir . "/index.txt.attr", "unique_subject = yes\n");
                // call "openssl ocsp" to manufacture our own OCSP statement
                // adding "-rmd sha1" to the following command-line makes the
                // choice of signature algorithm for the response explicit
                // but it's only available from openssl-1.1.0 (which we do not
                // want to require just for that one thing).
                $execCmd = CONFIG['PATHS']['openssl'] . " ocsp -issuer " . ROOT . "/config/SilverbulletClientCerts/real.pem -sha1 -ndays 10 -no_nonce -serial 0x$serialHex -CA " . ROOT . "/config/SilverbulletClientCerts/real.pem -rsigner " . ROOT . "/config/SilverbulletClientCerts/real.pem -rkey " . ROOT . "/config/SilverbulletClientCerts/real.key -index $tempdir/index.txt -no_cert_verify -respout $tempdir/$serialHex.response.der";
                $logHandle->debug(2, "Calling openssl ocsp with following cmdline: $execCmd\n");
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
                break;
            case "eduPKI":
                // nothing to be done here - eduPKI have their own OCSP responder
                // and the certs point to it. So we are not in the loop.
                break;
            default:
                /* HTTP POST the serial to the CA. The CA knows about the state of
                 * the certificate.
                 *
                 * $httpResponse = httpRequest("https://clientca.hosted.eduroam.org/ocsp/", ["serial" => $serial ] );
                 *
                 * The result of this if clause has to be a DER-encoded OCSP statement
                 * to be stored in the variable $ocsp
                 */
                throw new Exception("This type of silverbullet CA is not implemented yet!");
        }
        // write the new statement into DB
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET OCSP = ?, OCSP_timestamp = NOW() WHERE serial_number = ?", "si", $ocsp, $this->serial);
        return $ocsp;
    }

    /**
     * revokes a certificate
     * @return array with revocation information
     */
    public function revokeCertificate() {
        $nowSql = (new \DateTime())->format("Y-m-d H:i:s");
        // regardless if embedded or not, always keep local state in our own DB
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET revocation_status = 'REVOKED', revocation_time = ? WHERE serial_number = ?", "si", $nowSql, $this->serial);
        $this->loggerInstance->debug(2, "Certificate revocation status for $this->serial updated, about to call triggerNewOCSPStatement().\n");
        // newly instantiate us, DB content has changed...
        $certObject = new SilverbulletCertificate($this->serial);
        // embedded CA does "nothing special" for revocation: the DB change was the entire thing to do
        // but for external CAs, we need to notify explicitly that the cert is now revoked
        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":
                break;
            case "eduPKI":
                try {
                    $soap = SilverbulletCertificate::initEduPKISoapSession("RA");
                    $soapRevocationSerial = $soap->newRevocationRequest($this->serial, "");
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
                    $raCertFile = file_get_contents(ROOT . "../edupki-test-ra.pem");
                    $raCert = openssl_x509_read($raCertFile);
                    $raKey = openssl_pkey_get_private("file://" . ROOT . "../edupki-test-ra.clearkey");
                    // sign the data
                    if (openssl_pkcs7_sign($tempdir['dir'] . "/content.txt", $tempdir['dir'] . "/signature.txt", $raCert, $raKey, []) === FALSE) {
                        throw new Exception("Unable to sign the revocation approval data!");
                    }
                    // and get the signature blob back from the filesystem
                    $detachedSig = file_get_contents($tempdir['dir'] . "/signature.txt");
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
                break;
            default:
                throw new Exception("Unknown type of CA requested!");
        }
        // what happens wrt OCSP etc. is really something for the following function to decide. We just call it.
        $certObject->triggerNewOCSPStatement();
    }

    /**
     * create a CSR
     * 
     * @param resource $privateKey the private key to create the CSR with
     * @return array with the CSR and some meta info
     */
    private static function generateCsr($privateKey, $fed, $realm) {
        $databaseHandle = DBConnection::handle("INST");
        $loggerInstance = new common\Logging();
        $usernameIsUnique = FALSE;
        $username = "";
        while ($usernameIsUnique === FALSE) {
            $usernameLocalPart = common\Entity::randomString(64 - 1 - strlen($realm), "0123456789abcdefghijklmnopqrstuvwxyz");
            $username = $usernameLocalPart . "@" . $realm;
            $uniquenessQuery = $databaseHandle->exec("SELECT cn from silverbullet_certificate WHERE cn = ?", "s", $username);
            // SELECT -> resource, not boolean
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $uniquenessQuery) == 0) {
                $usernameIsUnique = TRUE;
            }
        }

        $loggerInstance->debug(5, "generateCertificate: generating CSR.\n");

        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":

                $newCsr = openssl_csr_new(
                        ['O' => CONFIG_CONFASSISTANT['CONSORTIUM']['name'],
                    'OU' => $fed,
                    'CN' => $username,
                    'emailAddress' => $username,
                        ], $privateKey, [
                    'digest_alg' => 'sha256',
                    'req_extensions' => 'v3_req',
                        ]
                );
                break;
            case "eduPKI":
                $tempdirArray = \core\common\Entity::createTemporaryDirectory("test");
                $tempdir = $tempdirArray['dir'];
                // dump private key into directly
                $outstring = "";
                openssl_pkey_export($privateKey, $outstring);
                file_put_contents($tempdir . "/pkey.pem", $outstring);
                // PHP can only do one DC in the Subject. But we need three.
                $execCmd = CONFIG['PATHS']['openssl'] . " req -new -sha256 -key $tempdir/pkey.pem -out $tempdir/request.csr -subj /DC=test/DC=test/DC=eduroam/C=$fed/O=".CONFIG_CONFASSISTANT['CONSORTIUM']['name']."/OU=$fed/CN=$username/emailAddress=$username";
                $loggerInstance->debug(2, "Calling openssl req with following cmdline: $execCmd\n");
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
                break;
            default:
                throw new Exception("Unknown CA!");
        }
        if ($newCsr === FALSE) {
            throw new Exception("Unable to create a CSR!");
        }
        return [
            "CSR" => $newCsr, // a resource for embedded, a string for eduPKI
            "USERNAME" => $username,
            "FED" => $fed
        ];
    }

    private static function initEduPKISoapSession($type) {
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
                'cafile' => ROOT . "/config/SilverbulletClientCerts/eduPKI-webserver-root.pem",
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
            $context_params['ssl']['local_cert'] = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem";
            $context_params['ssl']['local_pk'] = ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.key";
            $context_params['ssl']['passphrase'] = SilverbulletCertificate::EDUPKI_RA_PKEY_PASSPHRASE;
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
                ]
        );
        return $soap;
    }

    const EDUPKI_RA_ID = 700;
    const EDUPKI_CERT_PROFILE = "User SOAP";
    const EDUPKI_RA_PKEY_PASSPHRASE = "...";

    /**
     * take a CSR and sign it with our issuing CA's certificate
     * 
     * @param mixed $csr the CSR
     * @param int $expiryDays the number of days until the cert is going to expire
     * @return array the cert and some meta info
     */
    private static function signCsr($csr, $expiryDays) {
        $loggerInstance = new common\Logging();
        $databaseHandle = DBConnection::handle("INST");
        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":
                $rootCaPem = file_get_contents(ROOT . "/config/SilverbulletClientCerts/rootca.pem");
                $raCertFile = file_get_contents(ROOT . "/config/SilverbulletClientCerts/real.pem");
                $raCert = openssl_x509_read($raCertFile);
                $raKey = openssl_pkey_get_private("file://" . ROOT . "/config/SilverbulletClientCerts/real.key");
                $nonDupSerialFound = FALSE;
                do {
                    $serial = random_int(1000000000, PHP_INT_MAX);
                    $dupeQuery = $databaseHandle->exec("SELECT serial_number FROM silverbullet_certificate WHERE serial_number = ?", "i", $serial);
                    // SELECT -> resource, not boolean
                    if (mysqli_num_rows(/** @scrutinizer ignore-type */$dupeQuery) == 0) {
                        $nonDupSerialFound = TRUE;
                    }
                } while (!$nonDupSerialFound);
                $loggerInstance->debug(5, "generateCertificate: signing imminent with unique serial $serial.\n");
                return [
                    "CERT" => openssl_csr_sign($csr["CSR"], $raCert, $raKey, $expiryDays, ['digest_alg' => 'sha256'], $serial),
                    "SERIAL" => $serial,
                    "ISSUER" => $raCertFile,
                    "ROOT" => $rootCaPem,
                ];
            case "eduPKI":
                // initialse connection to eduPKI CA / eduroam RA and send the request to them
                try {
                    $altArray = [# Array mit den Subject Alternative Names
                        "email:" . $csr["USERNAME"]
                    ];
                    $soapPub = SilverbulletCertificate::initEduPKISoapSession("PUBLIC");
                    $loggerInstance->debug(5, "FIRST ACTUAL SOAP REQUEST (Public, newRequest)!\n");
                    $loggerInstance->debug(5, "PARAM_1: " . SilverbulletCertificate::EDUPKI_RA_ID . "\n");
                    $loggerInstance->debug(5, "PARAM_2: ".$csr["CSR"]."\n");
                    $loggerInstance->debug(5, "PARAM_3: ");
                    $loggerInstance->debug(5, $altArray);
                    $loggerInstance->debug(5, "PARAM_4: " . SilverbulletCertificate::EDUPKI_CERT_PROFILE . "\n");
                    $loggerInstance->debug(5, "PARAM_5: " . sha1("notused") . "\n");
                    $loggerInstance->debug(5, "PARAM_6: " . $csr["USERNAME"] . "\n");
                    $loggerInstance->debug(5, "PARAM_7: " . $csr["USERNAME"] . "\n");
                    $loggerInstance->debug(5, "PARAM_8: " . ProfileSilverbullet::PRODUCTNAME . "\n");
                    $loggerInstance->debug(5, "PARAM_9: false\n");
                    $soapNewRequest = $soapPub->newRequest(
                            SilverbulletCertificate::EDUPKI_RA_ID, # RA-ID
                            $csr["CSR"], # Request im PEM-Format
                            $altArray, # altNames
                            SilverbulletCertificate::EDUPKI_CERT_PROFILE, # Zertifikatprofil
                            sha1("notused"), # PIN
                            $csr["USERNAME"], # Name des Antragstellers
                            $csr["USERNAME"], # Kontakt-E-Mail
                            ProfileSilverbullet::PRODUCTNAME, # Organisationseinheit des Antragstellers
                            false                   # Veröffentlichen des Zertifikats?
                    );
                    $loggerInstance->debug(5, $soapPub->__getLastRequest());
                    $loggerInstance->debug(5, $soapPub->__getLastResponse());
                    if ($soapNewRequest == 0) {
                        throw new Exception("Error when sending SOAP request (request serial number was zero). No further details available.");
                    }
                    $soapReqnum = intval($soapNewRequest);
                } catch (Exception $e) {
                    // PHP 7.1 can do this much better
                    if (is_soap_fault($e)) {
                        throw new Exception("Error when sending SOAP request: " . "{$e->faultcode}: {$e->faultstring}\n");
                    }
                    throw new Exception("Something odd happened while doing the SOAP request:" . $e->getMessage());
                }
                try {
                    $soap = SilverbulletCertificate::initEduPKISoapSession("RA");
                    // tell the CA the desired expiry date of the new certificate
                    $expiry = new \DateTime();
                    $expiry->modify("+$expiryDays day");
                    $expiry->setTimezone(new \DateTimeZone("UTC"));
                    $soapExpiryChange = $soap->setRequestParameters(
                            $soapReqnum, [
                        "RaID" => SilverbulletCertificate::EDUPKI_RA_ID,
                        "Role" => SilverbulletCertificate::EDUPKI_CERT_PROFILE,
                        "Subject" => "DC=eduroam,DC=test,DC=test,C=".$csr["FED"].",O=".CONFIG_CONFASSISTANT['CONSORTIUM']['name'].",OU=".$csr["FED"].",CN=".$csr['USERNAME'].",emailAddress=".$csr['USERNAME'],
                        "SubjectAltNames" => ["email:".$csr["USERNAME"]],
                        "NotBefore" => (new \DateTime())->format('c'),
                        "NotAfter" => $expiry->format('c'),
                            ]
                    );
                    if ($soapExpiryChange === FALSE) {
                        throw new Exception("Error when sending SOAP request (unable to change expiry date).");
                    }
                    // retrieve the raw request to prepare for signature and approval
                    $soapRawRequest = $soap->getRawRequest($soapReqnum);
                    if (strlen($soapRawRequest) < 10) { // very basic error handling
                        throw new Exception("Suspiciously short data to sign!");
                    }
                    // for obnoxious reasons, we have to dump the request into a file and let pkcs7_sign read from the file
                    // rather than just using the string. Grr.
                    $tempdir = \core\common\Entity::createTemporaryDirectory("test");
                    file_put_contents($tempdir['dir'] . "/content.txt", $soapRawRequest);
                    // retrieve our RA cert from filesystem
                    $raCertFile = file_get_contents(ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.pem");
                    $raCert = openssl_x509_read($raCertFile);
                    $raKey = openssl_pkey_get_private("file://" . ROOT . "/config/SilverbulletClientCerts/edupki-test-ra.clearkey");
                    // sign the data
                    if (openssl_pkcs7_sign($tempdir['dir'] . "/content.txt", $tempdir['dir'] . "/signature.txt", $raCert, $raKey, []) === FALSE) {
                        throw new Exception("Unable to sign the certificate approval data!");
                    }
                    // and get the signature blob back from the filesystem
                    $detachedSigBloat = file_get_contents($tempdir['dir'] . "/signature.txt");
                    $loggerInstance->debug(5, "Raw Request is:\n");
                    $loggerInstance->debug(5, $soapRawRequest."\n");
                    $loggerInstance->debug(5, "Signature is:\n");
                    $loggerInstance->debug(5, $detachedSigBloat."\n");
                    $detachedSigBloatArray = explode("\n",$detachedSigBloat);
                    $index = array_search('Content-Disposition: attachment; filename="smime.p7s"',$detachedSigBloatArray);
                    $detachedSigSmall = array_slice($detachedSigBloatArray, $index+1);
                    $detachedSigSmall[0] = "-----BEGIN PKCS7-----";
                    array_pop($detachedSigSmall);
                    array_pop($detachedSigSmall);
                    array_pop($detachedSigSmall);
                    $detachedSigSmall[count($detachedSigSmall)-1] = "-----END PKCS7-----";
                    $detachedSig = implode("\n",$detachedSigSmall);
                    $loggerInstance->debug(5, "Request for server approveRequest has parameters:\n");
                    $loggerInstance->debug(5, $soapReqnum."\n");
                    $loggerInstance->debug(5, base64_encode($soapRawRequest)."\n");
                    $loggerInstance->debug(5, $detachedSig."\n");
                    $soapIssueCert = $soap->approveRequest($soapReqnum, base64_encode($soapRawRequest), $detachedSig);
                    if ($soapIssueCert === FALSE) {
                        throw new Exception("The locally approved request was NOT processed by the CA.");
                    }
                    // now, get the actual cert from the CA
                    $soapCert = $soap->getCertificateByRequestSerial($soapReqnum);
                    $x509 = new common\X509();
                    $parsedCert = $x509->processCertificate($soapCert);
                    if (!is_array($parsedCert)) {
                        throw new Exception("We did not actually get a certificate.");
                    }
                    // let's get the CA certificate chain
                    $caInfo = $soap->getCAInfo();
                    $certList = $x509->splitCertificate($caInfo['CAChain']);
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
                } catch (Exception $e) {
                    // PHP 7.1 can do this much better
                    if (is_soap_fault($e)) {
                        throw new Exception("Error when sending SOAP request: " . "{$e->faultcode}: {$e->faultstring}\n");
                    }
                    throw new Exception("Something odd happened while doing the SOAP request:" . $e->getMessage());
                }
                return [
                    "CERT" => openssl_x509_read($parsedCert['pem']),
                    "SERIAL" => $parsedCert['serial'],
                    "ISSUER" => $raCertFile, // change this to the actual eduPKI Issuer CA
                    "ROOT" => $theRoot, // change this to the actual eduPKI Root CA
                ];
            default:
                /* HTTP POST the CSR to the CA with the $expiryDays as parameter
                 * on successful execution, gets back a PEM file which is the
                 * certificate (structure TBD)
                 * $httpResponse = httpRequest("https://clientca.hosted.eduroam.org/issue/", ["csr" => $csr, "expiry" => $expiryDays ] );
                 *
                 * The result of this if clause has to be a certificate in PHP's 
                 * "openssl_object" style (like the one that openssl_csr_sign would 
                 * produce), to be stored in the variable $cert; we also need the
                 * serial - which can be extracted from the received cert and has
                 * to be stored in $serial.
                 */
                throw new Exception("External silverbullet CA is not implemented yet!");
        }
    }

}
