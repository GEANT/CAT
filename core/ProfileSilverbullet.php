<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php

/**
 * This file contains the Profile class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

/**
 * This class represents an EAP Profile.
 * Profiles can inherit attributes from their IdP, if the IdP has some. Otherwise,
 * one can set attribute in the Profile directly. If there is a conflict between
 * IdP-wide and Profile-wide attributes, the more specific ones (i.e. Profile) win.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class ProfileSilverbullet extends AbstractProfile {

    const SB_TOKENSTATUS_VALID = 0;
    const SB_TOKENSTATUS_REDEEMED = 1;
    const SB_TOKENSTATUS_EXPIRED = 2;
    const SB_TOKENSTATUS_INVALID = 3;
    const SB_CERTSTATUS_NONEXISTENT = 0;
    const SB_CERTSTATUS_VALID = 1;
    const SB_CERTSTATUS_EXPIRED = 2;
    const SB_CERTSTATUS_REVOKED = 3;

    /*
     * 
     */
    const PRODUCTNAME = "eduroam-no-cloud-no-box-no-service";

    public static function random_str(
    $length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ) {
        $str = '';
        $max = strlen($keyspace) - 1;
        if ($max < 1) {
            throw new Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject) {
        parent::__construct($profileId, $idpObject);
        $this->loggerInstance->debug(3, "--- BEGIN Constructing new Profile object ... ---\n");

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];

        $tempMaxUsers = 200; // abolutely last resort fallback if no per-fed and no config option
// set to global config value

        if (isset(CONFIG['CONSORTIUM']['silverbullet_default_maxusers'])) {
            $tempMaxUsers = CONFIG['CONSORTIUM']['silverbullet_default_maxusers'];
        }
        $myInst = new IdP($this->institution);
        $myFed = new Federation($myInst->federation);
        $fedMaxusers = $myFed->getAttributes("fed:silverbullet-maxusers");
        if (isset($fedMaxusers[0])) {
            $tempMaxUsers = $fedMaxusers[0]['value'];
        }

// realm is automatically calculated, then stored in DB

        $this->realm = "opaquehash@$myInst->identifier-$this->identifier." . strtolower($myInst->federation) . CONFIG['CONSORTIUM']['silverbullet_realm_suffix'];
        $this->setRealm("$myInst->identifier-$this->identifier." . strtolower($myInst->federation) . CONFIG['CONSORTIUM']['silverbullet_realm_suffix']);
        $localValueIfAny = "";

// but there's some common internal attributes populated directly
        $internalAttributes = [
            "internal:profile_count" => $this->idpNumberOfProfiles,
            "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
            "internal:use_anon_outer" => FALSE,
            "internal:anon_local_value" => $localValueIfAny,
            "internal:silverbullet_maxusers" => $tempMaxUsers,
            "profile:production" => "on",
        ];

// and we need to populate eap:server_name and eap:ca_file with the NRO-specific EAP information
        $silverbulletAttributes = [
            "eap:server_name" => "auth." . strtolower($myFed->identifier) . CONFIG['CONSORTIUM']['silverbullet_realm_suffix'],
        ];
        $x509 = new X509();
        $caHandle = fopen(dirname(__FILE__) . "/../config/SilverbulletServerCerts/" . strtoupper($myFed->identifier) . "/root.pem", "r");
        if ($caHandle !== FALSE) {
            $cAFile = fread($caHandle, 16000000);
            $silverbulletAttributes["eap:ca_file"] = $x509->der2pem(($x509->pem2der($cAFile)));
        }

        $tempArrayProfLevel = array_merge($this->addInternalAttributes($internalAttributes), $this->addInternalAttributes($silverbulletAttributes));

// now, fetch and merge IdP-wide attributes

        $this->attributes = $this->levelPrecedenceAttributeJoin($tempArrayProfLevel, $this->idpAttributes, "IdP");

        $this->privEaptypes = $this->fetchEAPMethods();

        $this->name = ProfileSilverbullet::PRODUCTNAME;

        $this->loggerInstance->debug(3, "--- END Constructing new Profile object ... ---\n");
    }

    /**
     * Updates database with new installer location; NOOP because we do not
     * cache anything in Silverbullet
     * 
     * @param string device the device identifier string
     * @param string path the path where the new installer can be found
     */
    public function updateCache($device, $path, $mime, $integerEapType) {
// params are needed for proper overriding, but not needed at all.
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param array $type The EAP Type, as defined in class EAP
     * @param int $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     *
     */
    public function addSupportedEapMethod($type, $preference) {
// params are needed for proper overriding, but not used at all.
        parent::addSupportedEapMethod(\core\EAP::EAPTYPE_SILVERBULLET, 1);
    }

    /**
     * It's EAP-TLS and there is no point in anonymity
     * @param boolean $shallwe
     */
    public function setAnonymousIDSupport($shallwe) {
// params are needed for proper overriding, but not used at all.
        $this->databaseHandle->exec("UPDATE profile SET use_anon_outer = 0 WHERE profile_id = $this->identifier");
    }

    /**
     * issue a certificate based on a token
     *
     * @param string $token
     * @param string $importPassword
     * @return array
     */
    public function generateCertificate($token, $importPassword) {
        $tokenStatus = ProfileSilverbullet::tokenStatus($token);
        if ($tokenStatus['status'] != self::SB_TOKENSTATUS_VALID) {
            throw new Exception("Attempt to generate a SilverBullet installer with an invalid/redeemed/expired token. The user should never have gotten that far!");
        }
        if ($tokenStatus['profile'] != $this->identifier) {
            throw new Exception("Attempt to generate a SilverBullet installer, but the profile ID (constructor) and the profile from token do not match!");
        }
// SQL query to find the expiry date of the *user* to find the correct ValidUntil for the cert
        $userrow = $this->databaseHandle->exec("SELECT expiry FROM silverbullet_user WHERE id = ?", "i", $tokenStatus['user']);
        if (!$userrow || $userrow->num_rows != 1) {
            throw new Exception("Despite a valid token, the corresponding user was not found in database or database query error!");
        }
        $expiryObject = mysqli_fetch_object($userrow);
        $this->loggerInstance->debug(5, "EXP: ".$expiryObject->expiry."\n");
        $expiryDateObject = date_create_from_format("Y-m-d H:i:s", $expiryObject->expiry);
        $this->loggerInstance->debug(5, $expiryDateObject->format("Y-m-d H:i:s")."\n");
        $validity = date_diff(date_create(), $expiryDateObject);
        if ($validity->invert == 1) { // negative! That should not be possible
            throw new Exception("Attempt to generate a certificate for a user which is already expired!");
        }
// token leads us to the NRO, to set the OU property of the cert
        $inst = new IdP($this->institution);
        $federation = strtoupper($inst->federation);
        $usernameLocalPart = self::random_str(32);
        $username = $usernameLocalPart . "@" . $this->realm;

        $expiryDays = $validity->days;

        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'encrypt_key' => FALSE]);
        $csr = openssl_csr_new(
                ['O' => CONFIG['CONSORTIUM']['name'],
            'OU' => $federation,
            'CN' => $username,
            'emailAddress' => $username,
                ], $privateKey, [
            'digest_alg' => 'sha256',
            'req_extensions' => 'v3_req',
                ]
        );

// HTTP POST the CSR to the CA with the $expiryDays as parameter
// on successful execution, gets back a PEM file which is the certificate (in JSON, structure TBD)
// $httpResponse = httpRequest("https://clientca.hosted.eduroam.org/issue/", ["csr" => $csr, "expiry" => $expiryDays ] );
// as that is still TODO, generate a slightly stubby implementation of a CA right here
// it can do all we have in the spec for eaas, but cannot generate CRL/OCSP
// serial numbers are not maintaining state because random, and could be duplicate
// on heavy use. But this is a temporary stopgap CA only anyway, so who cares.
        $rootCaHandle = fopen(ROOT . "/config/SilverbulletClientCerts/rootca.pem", "r");
        $rootCaPem = fread($rootCaHandle, 1000000);
        $issuingCaHandle = fopen(ROOT . "/config/SilverbulletClientCerts/real.pem", "r");
        $issuingCaPem = fread($issuingCaHandle, 1000000);
        $issuingCa = openssl_x509_read($issuingCaPem);
        $issuingCaKey = openssl_pkey_get_private("file://" . ROOT . "/config/SilverbulletClientCerts/real.key");
        $serial = mt_rand(1000000, 100000000);
        $cert = openssl_csr_sign($csr, $issuingCa, $issuingCaKey, $expiryDays, ['digest_alg' => 'sha256'], $serial);
// get the SHA1 fingerprint, this will be handy for Windows installers
        $sha1 = openssl_x509_fingerprint($cert, "sha1");
// with the cert, our private key and import password, make a PKCS#12 container out of it
        $exportedCertProt = "";
        openssl_pkcs12_export($cert, $exportedCertProt, $privateKey, $importPassword, ['extracerts' => [$issuingCaPem /* , $rootCaPem */]]);
        $exportedCertClear = "";
        openssl_pkcs12_export($cert, $exportedCertClear, $privateKey, "", ['extracerts' => [$issuingCaPem, $rootCaPem]]);
// store resulting cert CN and expiry date in separate columns into DB - do not store the cert data itself as it contains the private key!
        // we need the *real* expiry date, not just the day-approximation
        $x509 = new X509();
        $certString = "";
        openssl_x509_export($cert, $certString);
        $parsedCert = $x509->processCertificate($certString);
        $this->loggerInstance->debug(5,"CERTINFO: " . print_r($parsedCert['full_details'],true));
        $realExpiryDate = date_create_from_format("U", $parsedCert['full_details']['validTo_time_t'])->format("Y-m-d H:i:s");
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET cn = ?, serial_number = ?, expiry = ? WHERE one_time_token = ?", "siss", $username, $serial, $realExpiryDate, $token);
        // newborn cert immediately gets its "valid" OCSP response
        $this->triggerNewOCSPStatement($serial);
// return PKCS#12 data stream
        return [
            "username" => $username,
            "certdata" => $exportedCertProt,
            "certdataclear" => $exportedCertClear,
            "expiry" => $expiryDateObject->format("Y-m-d\TH:i:s\Z"),
            "sha1" => $sha1,
            'importPassword' => $importPassword,
            'serial' => $serial,
        ];
    }

    /**
     * triggers a new OCSP statement for the given serial number
     * 
     * @param string $serial the serial number of the cert in question
     * @return string DER-encoded OCSP status info (binary data!)
     */
    private function triggerNewOCSPStatement($serial) {
        // get all relevant info from DB
        $cn = "";
        $federation = NULL;
        $certstatus = "";
        $originalStatusQuery = $this->databaseHandle->exec("SELECT profile_id, cn, revocation_status, expiry, revocation_time, OCSP FROM silverbullet_certificate WHERE serial_number = ?", "i", $serial);
        if (mysqli_num_rows($originalStatusQuery) > 0) {
            $certstatus = "V";
        }
        while ($runner = mysqli_fetch_object($originalStatusQuery)) { // there can be only one row
            if ($runner->revocation_status == "REVOKED") {
                // already revoked, simply return canned OCSP response
                $certstatus = "R";
            }
            $originalExpiry = date_create_from_format("Y-m-d H:i:s", $runner->expiry);
            $validity = date_diff(date_create(), $originalExpiry);
            if ($validity->invert == 1) {
                // negative! Cert is already expired, no need to revoke. 
                // No need to return anything really, but do return the last known OCSP statement to prevent special case
                $certstatus = "E";
            }
            $cn = $runner->cn;
            $profile = new ProfileSilverbullet($runner->profile_id);
            $inst = new IdP($profile->institution);
            $federation = strtoupper($inst->federation);
        }

        // generate stub index.txt file
        $tempdirArray = $this->createTemporaryDirectory("test");
        $tempdir = $tempdirArray['dir'];
        $nowIndexTxt = (new \DateTime())->format("ymdHis") . "Z";
        $expiryIndexTxt = $originalExpiry->format("ymdHis") . "Z";
        $serialHex = strtoupper(dechex($serial));
        if (strlen($serialHex) % 2 == 1) {
            $serialHex = "0" . $serialHex;
        }
        $indexfile = fopen($tempdir . "/index.txt", "w");
        $indexStatement = "$certstatus\t$expiryIndexTxt\t".($certstatus == "R" ? "$nowIndexTxt,unspecified" : "")."\t$serialHex\tunknown\t/O=" . CONFIG['CONSORTIUM']['name'] . "/OU=$federation/CN=$cn/emailAddress=$cn\n";
        $this->loggerInstance->debug(4, "index.txt contents-to-be: $indexStatement");
        fwrite($indexfile, $indexStatement);
        fclose($indexfile);
        // index.attr is dull but needs to exist
        $indexAttrFile = fopen($tempdir . "/index.txt.attr", "w");
        fwrite($indexAttrFile, "unique_subject = yes\n");
        fclose($indexAttrFile);
        // call "openssl ocsp" to manufacture our own OCSP statement
        $execCmd = CONFIG['PATHS']['openssl'] . " ocsp -issuer " . ROOT . "/config/SilverbulletClientCerts/real.pem -sha256 -sha256 -no_nonce -serial 0x$serialHex -CA " . ROOT . "/config/SilverbulletClientCerts/real.pem -ndays 365 -rsigner " . ROOT . "/config/SilverbulletClientCerts/real.pem -rkey " . ROOT . "/config/SilverbulletClientCerts/real.key -index $tempdir/index.txt -no_cert_verify -respout $tempdir/$serialHex.response.der";
        $this->loggerInstance->debug(2, "Calling openssl ocsp with following cmdline: $execCmd\n");
        $output = [];
        $return = 999;
        exec($execCmd, $output, $return);
        if ($return != 0) {
            throw new Exception("Non-zero return value from openssl ocsp!");
        }
        $ocspFile = fopen($tempdir . "/$serialHex.response.der", "r");
        $ocsp = fread($ocspFile, 1000000);
        fclose($ocspFile);
        // write the new statement into DB
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET OCSP = ? WHERE serial_number = ?", "si", $ocsp, $serial);
        return $ocsp;
    }

    /**
     * revokes a certificate
     * @param string $serial the serial number of the cert to revoke (decimal!)
     * @return array with revocation information
     */
    public function revokeCertificate($serial) {
// this is a stub, as we do not have a proper CA yet
// it will again be replaced with a HTTP POST of the revocation request
// and will get an updated CRL and OCSP statement back, in JSON
// $httpResponse = httpRequest("https://clientca.hosted.eduroam.org/revoke/", ["serial" => $serial ] );

// TODO for now, just mark as revoked in the certificates table (and use the stub OCSP updater)
        $nowSql = (new \DateTime())->format("Y-m-d H:i:s");
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET revocation_status = 'REVOKED', revocation_time = ? WHERE serial_number = ?", "si", $nowSql, $serial);
        // 2) generate OCSP response that contains new status
        $ocsp = $this->triggerNewOCSPStatement($serial);
        return ["OCSP" => $ocsp];
    }

    /**
     * 
     * @param string $url the URL to send the request to
     * @param array $postValues POST values to send
     */
    private function httpRequest($url, $postValues) {
        $options = [
            'http' => ['header' => 'Content-type: application/x-www-form-urlencoded\r\n', "method" => 'POST', 'content' => http_build_query($postValues)]
        ];
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }

    public static function tokenStatus($tokenvalue) {
        $databaseHandle = DBConnection::handle("INST");
        $loggerInstance = new Logging();
        $tokenrow = $databaseHandle->exec("SELECT profile_id, silverbullet_user_id, expiry, cn, serial_number, revocation_status, device FROM silverbullet_certificate WHERE one_time_token = ?", "s", $tokenvalue);
        if (!$tokenrow || $tokenrow->num_rows != 1) {
            $loggerInstance->debug(2, "Token  $$tokenvalue not found in database or database query error!\n");
            return ["status" => self::SB_TOKENSTATUS_INVALID,
                "cert_status" => self::SB_CERTSTATUS_NONEXISTENT,];
        }
// still here? then the token was found
        $details = mysqli_fetch_object($tokenrow);
        if ($details->cn == NULL && $details->serial_number == NULL) { // no cert exists yet; token is either still acive or expired
            $now = new \DateTime();
            $expiryObject = new \DateTime($details->expiry);
            $delta = $now->diff($expiryObject);

            return ["status" => ($delta->invert == 1 ? self::SB_TOKENSTATUS_EXPIRED : self::SB_TOKENSTATUS_VALID), // negative means token has expired, otherwise good
                "cert_status" => self::SB_CERTSTATUS_NONEXISTENT,
                "profile" => $details->profile_id,
                "user" => $details->silverbullet_user_id,
                "expiry" => $expiryObject->format("Y-m-d H:i:s")];
        }
// still here? then there is certificate data, so token was redeemed
// add the corresponding cert details here

        $now = new \DateTime();
        $cert_expiry = new \DateTime($details->expiry);
        $delta = $now->diff($cert_expiry);
        $certStatus = ($delta->invert == 1 ? self::SB_CERTSTATUS_EXPIRED : self::SB_CERTSTATUS_VALID);
        // expired is expired; even if it was previously revoked. But do update status for revoked ones...
        if ($certStatus == self::SB_CERTSTATUS_VALID && $details->revocation_status == "REVOKED") {
            $certStatus = self::SB_CERTSTATUS_REVOKED;
        }
        

        return ["status" => self::SB_TOKENSTATUS_REDEEMED,
            "cert_status" => $certStatus,
            "profile" => $details->profile_id,
            "user" => $details->silverbullet_user_id,
            "cert_serial" => $details->serial_number,
            "cert_name" => $details->cn,
            "cert_expiry" => $details->expiry,
            "device" => $details->device,
                ];
    }

    public function userStatus($username) {
        $retval = [];
        $userrows = $this->databaseHandle->exec("SELECT one_time_token FROM silverbullet_certificate WHERE silverbullet_user_id = ? AND profile_id = ? ", "si", $username, $this->identifier);
        while ($returnedData = mysqli_fetch_object($userrows)) {
            $retval[] = ProfileSilverbullet::tokenStatus($returnedData->one_time_token);
        }
        return $retval;
    }

}
