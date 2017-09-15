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
    const SB_TOKENSTATUS_PARTIALLY_REDEEMED = 1;
    const SB_TOKENSTATUS_REDEEMED = 2;
    const SB_TOKENSTATUS_EXPIRED = 3;
    const SB_TOKENSTATUS_INVALID = 4;
    const SB_CERTSTATUS_VALID = 1;
    const SB_CERTSTATUS_EXPIRED = 2;
    const SB_CERTSTATUS_REVOKED = 3;

    /*
     * 
     */
    const PRODUCTNAME = "Managed IdP";

    public static function random_str(
    $length, $keyspace = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
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
    public function __construct($profileId, $idpObject = NULL) {
        parent::__construct($profileId, $idpObject);

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];

        $tempMaxUsers = 200; // abolutely last resort fallback if no per-fed and no config option
// set to global config value

        if (isset(CONFIG_CONFASSISTANT['SILVERBULLET']['default_maxusers'])) {
            $tempMaxUsers = CONFIG_CONFASSISTANT['SILVERBULLET']['default_maxusers'];
        }
        $myInst = new IdP($this->institution);
        $myFed = new Federation($myInst->federation);
        $fedMaxusers = $myFed->getAttributes("fed:silverbullet-maxusers");
        if (isset($fedMaxusers[0])) {
            $tempMaxUsers = $fedMaxusers[0]['value'];
        }

// realm is automatically calculated, then stored in DB

        $this->realm = "opaquehash@$myInst->identifier-$this->identifier." . strtolower($myInst->federation) . CONFIG_CONFASSISTANT['SILVERBULLET']['realm_suffix'];
        $this->setRealm("$myInst->identifier-$this->identifier." . strtolower($myInst->federation) . CONFIG_CONFASSISTANT['SILVERBULLET']['realm_suffix']);
        $localValueIfAny = "";

// but there's some common internal attributes populated directly
        $internalAttributes = [
            "internal:profile_count" => $this->idpNumberOfProfiles,
            "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
            "internal:use_anon_outer" => FALSE,
            "internal:checkuser_outer" => TRUE,
            "internal:checkuser_value" => "anonymous",
            "internal:anon_local_value" => $localValueIfAny,
            "internal:silverbullet_maxusers" => $tempMaxUsers,
            "profile:production" => "on",
        ];

// and we need to populate eap:server_name and eap:ca_file with the NRO-specific EAP information
        $silverbulletAttributes = [
            "eap:server_name" => "auth." . strtolower($myFed->identifier) . CONFIG_CONFASSISTANT['SILVERBULLET']['server_suffix'],
        ];
        $x509 = new \core\common\X509();
        $caHandle = fopen(dirname(__FILE__) . "/../config/SilverbulletServerCerts/" . strtoupper($myFed->identifier) . "/root.pem", "r");
        if ($caHandle !== FALSE) {
            $cAFile = fread($caHandle, 16000000);
            $silverbulletAttributes["eap:ca_file"] = $x509->der2pem(($x509->pem2der($cAFile)));
        }

        $temp = array_merge($this->addInternalAttributes($internalAttributes), $this->addInternalAttributes($silverbulletAttributes));
        $tempArrayProfLevel = array_merge($this->addDatabaseAttributes(), $temp);

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
        // caching is not supported in SB (private key in installers)
        // the following merely makes the "unused parameter" warnings go away
        // the FALSE in condition one makes sure it never gets executed
        if (FALSE || $device == "Macbeth" || $path == "heath" || $mime == "application/witchcraft" || $integerEapType == 0) {
            throw new Exception("FALSE is TRUE, and TRUE is FALSE! Hover through the browser and filthy code!");
        }
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param \core\common\EAP $type The EAP Type, as defined in class EAP
     * @param int $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     *
     */
    public function addSupportedEapMethod(\core\common\EAP $type, $preference) {
        // the parameters really should only list SB and with prio 1 - otherwise,
        // something fishy is going on
        if ($type->getIntegerRep() != \core\common\EAP::INTEGER_SILVERBULLET || $preference != 1) {
            throw new Exception("Silverbullet::addSupportedEapMethod was called for a non-SP EAP type or unexpected priority!");
        }
        parent::addSupportedEapMethod($type, 1);
    }

    /**
     * It's EAP-TLS and there is no point in anonymity
     * @param boolean $shallwe
     */
    public function setAnonymousIDSupport($shallwe) {
        // we don't do anonymous outer IDs in SB
        if ($shallwe === TRUE) {
            throw new Exception("Silverbullet: attempt to add anonymous outer ID support to a SB profile!");
        }
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
        $cert = "";
        $this->loggerInstance->debug(5, "generateCertificate() - starting.\n");
        $tokenStatus = ProfileSilverbullet::tokenStatus($token);
        $this->loggerInstance->debug(5, "tokenStatus: done, got " . $tokenStatus['status'] . ", " . $tokenStatus['profile'] . ", " . $tokenStatus['user'] . ", " . $tokenStatus['expiry'] . ", " . $tokenStatus['value'] . "\n");
        $this->loggerInstance->debug(5, "generateCertificate() - token status is " . $tokenStatus['status']);
        if ($tokenStatus['status'] != self::SB_TOKENSTATUS_VALID && $tokenStatus['status'] != self::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
            throw new Exception("Attempt to generate a SilverBullet installer with an invalid/redeemed/expired token. The user should never have gotten that far!");
        }
        if ($tokenStatus['profile'] != $this->identifier) {
            throw new Exception("Attempt to generate a SilverBullet installer, but the profile ID (constructor) and the profile from token do not match!");
        }
        // SQL query to find the expiry date of the *user* to find the correct ValidUntil for the cert
        $userStatus = $tokenStatus['user'];
        $userrow = $this->databaseHandle->exec("SELECT expiry FROM silverbullet_user WHERE id = ?", "i", $userStatus);
        if (!$userrow || $userrow->num_rows != 1) {
            throw new Exception("Despite a valid token, the corresponding user was not found in database or database query error!");
        }
        $expiryObject = mysqli_fetch_object($userrow);
        $this->loggerInstance->debug(5, "EXP: " . $expiryObject->expiry . "\n");
        $expiryDateObject = date_create_from_format("Y-m-d H:i:s", $expiryObject->expiry);
        $this->loggerInstance->debug(5, $expiryDateObject->format("Y-m-d H:i:s") . "\n");
        $validity = date_diff(date_create(), $expiryDateObject);
        if ($validity->invert == 1) { // negative! That should not be possible
            throw new Exception("Attempt to generate a certificate for a user which is already expired!");
        }
        // token leads us to the NRO, to set the OU property of the cert
        $inst = new IdP($this->institution);
        $federation = strtoupper($inst->federation);
        $usernameIsUnique = FALSE;
        $username = "";
        while ($usernameIsUnique === FALSE) {
            $usernameLocalPart = self::random_str(64 - 1 - strlen($this->realm));
            $username = $usernameLocalPart . "@" . $this->realm;
            $uniquenessQuery = $this->databaseHandle->exec("SELECT cn from silverbullet_certificate WHERE cn = ?", "s", $username);
            if (mysqli_num_rows($uniquenessQuery) == 0) {
                $usernameIsUnique = TRUE;
            }
        }
        $expiryDays = $validity->days;

        $this->loggerInstance->debug(5, "generateCertificate: generating private key.\n");
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'encrypt_key' => FALSE]);
        $csr = openssl_csr_new(
                ['O' => CONFIG_CONFASSISTANT['CONSORTIUM']['name'],
            'OU' => $federation,
            'CN' => $username,
            'emailAddress' => $username,
                ], $privateKey, [
            'digest_alg' => 'sha256',
            'req_extensions' => 'v3_req',
                ]
        );

        $this->loggerInstance->debug(5, "generateCertificate: proceeding to sign cert.\n");

        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":
                $rootCaHandle = fopen(ROOT . "/config/SilverbulletClientCerts/rootca.pem", "r");
                $rootCaPem = fread($rootCaHandle, 1000000);
                $issuingCaPem = file_get_contents(ROOT . "/config/SilverbulletClientCerts/real.pem");
                $issuingCa = openssl_x509_read($issuingCaPem);
                $issuingCaKey = openssl_pkey_get_private("file://" . ROOT . "/config/SilverbulletClientCerts/real.key");
                $nonDupSerialFound = FALSE;
                do {
                    $serial = random_int(1000000000, PHP_INT_MAX);
                    $dupeQuery = $this->databaseHandle->exec("SELECT serial_number FROM silverbullet_certificate WHERE serial_number = ?", "i", $serial);
                    if (mysqli_num_rows($dupeQuery) == 0) {
                        $nonDupSerialFound = TRUE;
                    }
                } while (!$nonDupSerialFound);
                $this->loggerInstance->debug(5, "generateCertificate: signing imminent with unique serial $serial.\n");
                $cert = openssl_csr_sign($csr, $issuingCa, $issuingCaKey, $expiryDays, ['digest_alg' => 'sha256'], $serial);
                break;
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

        $this->loggerInstance->debug(5, "generateCertificate: post-processing certificate.\n");

        // get the SHA1 fingerprint, this will be handy for Windows installers
        $sha1 = openssl_x509_fingerprint($cert, "sha1");
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
        $this->loggerInstance->debug(5, "CERTINFO: " . print_r($parsedCert['full_details'], true));
        $realExpiryDate = date_create_from_format("U", $parsedCert['full_details']['validTo_time_t'])->format("Y-m-d H:i:s");

        /*
         * Finds invitation by its token attribute, creates new certificate record with provided values inside the database.
         * There are three failures (theoreticaly) possible: no record has been found for given token, more than one record has been found for given token or invitation has reached a limit of alowed certificates.
         */
        $invitationsResult = $this->databaseHandle->exec("SELECT * FROM `silverbullet_invitation` WHERE `token`=? ORDER BY `expiry` DESC", "s", $token);
        $certificateId = null;
        if ($invitationsResult && $invitationsResult->num_rows > 0) {
            $invitationRow = mysqli_fetch_object($invitationsResult);
            $invitationId = $invitationRow->id;
            $certificatesResult = $this->databaseHandle->exec("SELECT * FROM `silverbullet_certificate` WHERE `silverbullet_invitation_id`=? ORDER BY `revocation_status`, `expiry` DESC", "i", $invitationId);
            if (!$certificatesResult || $certificatesResult->num_rows < $invitationRow->quantity) {
                $invitationProfile = $invitationRow->profile_id;
                $invitationSbId =  $invitationRow->silverbullet_user_id;
                $newCertificateResult = $this->databaseHandle->exec("INSERT INTO `silverbullet_certificate` (`profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`) VALUES (?, ?, ?, ?, ?, ?)", "iiisss", $invitationProfile, $invitationSbId, $invitationId, $serial, $username, $realExpiryDate);
                if ($newCertificateResult === true) {
                    $certificateId = $this->databaseHandle->lastID();
                }
            }
        }

        // newborn cert immediately gets its "valid" OCSP response
        ProfileSilverbullet::triggerNewOCSPStatement((int) $serial);
// return PKCS#12 data stream
        return [
            "username" => $username,
            "certdata" => $exportedCertProt,
            "certdataclear" => $exportedCertClear,
            "expiry" => $expiryDateObject->format("Y-m-d\TH:i:s\Z"),
            "sha1" => $sha1,
            'importPassword' => $importPassword,
            'serial' => $serial,
            'certificateId' => $certificateId
        ];
    }

    /**
     * triggers a new OCSP statement for the given serial number
     * 
     * @param int $serial the serial number of the cert in question (decimal)
     * @return string DER-encoded OCSP status info (binary data!)
     */
    public static function triggerNewOCSPStatement($serial) {
        $logHandle = new \core\common\Logging();
        $logHandle->debug(2, "Triggering new OCSP statement for serial $serial.\n");
        $ocsp = ""; // the statement
        switch (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type']) {
            case "embedded":
                // get all relevant info from DB
                $cn = "";
                $federation = NULL;
                $certstatus = "";
                $originalExpiry = date_create_from_format("Y-m-d H:i:s", "2000-01-01 00:00:00");
                $dbHandle = DBConnection::handle("INST");
                $originalStatusQuery = $dbHandle->exec("SELECT profile_id, cn, revocation_status, expiry, revocation_time, OCSP FROM silverbullet_certificate WHERE serial_number = ?", "i", $serial);
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
                $cat = new CAT();
                $tempdirArray = $cat->createTemporaryDirectory("test");
                $tempdir = $tempdirArray['dir'];
                $nowIndexTxt = (new \DateTime())->format("ymdHis") . "Z";
                $expiryIndexTxt = $originalExpiry->format("ymdHis") . "Z";
                $serialHex = strtoupper(dechex($serial));
                if (strlen($serialHex) % 2 == 1) {
                    $serialHex = "0" . $serialHex;
                }
                $indexfile = fopen($tempdir . "/index.txt", "w");
                $indexStatement = "$certstatus\t$expiryIndexTxt\t" . ($certstatus == "R" ? "$nowIndexTxt,unspecified" : "") . "\t$serialHex\tunknown\t/O=" . CONFIG_CONFASSISTANT['CONSORTIUM']['name'] . "/OU=$federation/CN=$cn/emailAddress=$cn\n";
                $logHandle->debug(4, "index.txt contents-to-be: $indexStatement");
                fwrite($indexfile, $indexStatement);
                fclose($indexfile);
                // index.attr is dull but needs to exist
                $indexAttrFile = fopen($tempdir . "/index.txt.attr", "w");
                fwrite($indexAttrFile, "unique_subject = yes\n");
                fclose($indexAttrFile);
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
                $ocspFile = fopen($tempdir . "/$serialHex.response.der", "r");
                $ocsp = fread($ocspFile, 1000000);
                fclose($ocspFile);
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
                throw new Exception("External silverbullet CA is not implemented yet!");
        }
        // write the new statement into DB
        $dbHandle->exec("UPDATE silverbullet_certificate SET OCSP = ?, OCSP_timestamp = NOW() WHERE serial_number = ?", "si", $ocsp, $serial);
        return $ocsp;
    }

    /**
     * revokes a certificate
     * @param int $serial the serial number of the cert to revoke (decimal!)
     * @return array with revocation information
     */
    public function revokeCertificate($serial) {


// TODO for now, just mark as revoked in the certificates table (and use the stub OCSP updater)
        $nowSql = (new \DateTime())->format("Y-m-d H:i:s");
        if (CONFIG_CONFASSISTANT['SILVERBULLET']['CA']['type'] != "embedded") {
            // send revocation request to CA.
            // $httpResponse = httpRequest("https://clientca.hosted.eduroam.org/revoke/", ["serial" => $serial ] );
            throw new Exception("External silverbullet CA is not implemented yet!");
        }
        // regardless if embedded or not, always keep local state in our own DB
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET revocation_status = 'REVOKED', revocation_time = ? WHERE serial_number = ?", "si", $nowSql, $serial);
        $this->loggerInstance->debug(2, "Certificate revocation status updated, about to call triggerNewOCSPStatement($serial).\n");
        $ocsp = ProfileSilverbullet::triggerNewOCSPStatement($serial);
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

    private static function enumerateCertDetails($certQuery) {
        $retval = [];
        while ($resource = mysqli_fetch_object($certQuery)) {
            // is the cert expired?
            $now = new \DateTime();
            $cert_expiry = new \DateTime($resource->expiry);
            $delta = $now->diff($cert_expiry);
            $certStatus = ($delta->invert == 1 ? self::SB_CERTSTATUS_EXPIRED : self::SB_CERTSTATUS_VALID);
            // expired is expired; even if it was previously revoked. But do update status for revoked ones...
            if ($certStatus == self::SB_CERTSTATUS_VALID && $resource->revocation_status == "REVOKED") {
                $certStatus = self::SB_CERTSTATUS_REVOKED;
            }
            $retval[] = [
                "status" => $certStatus,
                "serial" => $resource->serial_number,
                "name" => $resource->cn,
                "issued" => $resource->issued,
                "expiry" => $resource->expiry,
                "device" => $resource->device,
            ];
        }
        return $retval;
    }

    public static function tokenStatus($tokenvalue) {
        $databaseHandle = DBConnection::handle("INST");
        $loggerInstance = new \core\common\Logging();

        /*
         * Finds invitation by its token attribute and loads all certificates generated using the token.
         * Certificate details will always be empty, since code still needs to be adapted to return multiple certificates information.
         */
        $invColumnNames = "`id`, `profile_id`, `silverbullet_user_id`, `token`, `quantity`, `expiry`";
        $invitationsResult = $databaseHandle->exec("SELECT $invColumnNames FROM `silverbullet_invitation` WHERE `token`=? ORDER BY `expiry` DESC", "s", $tokenvalue);
        if (!$invitationsResult || $invitationsResult->num_rows == 0) {
            $loggerInstance->debug(2, "Token  $tokenvalue not found in database or database query error!\n");
            return ["status" => self::SB_TOKENSTATUS_INVALID,
                "cert_status" => [],];
        }
        // if not returned, we found the token in the DB
        $invitationRow = mysqli_fetch_object($invitationsResult);
        $rowId = $invitationRow->id;
        $certColumnNames = "`id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn`, `issued`, `expiry`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp`";
        $certificatesResult = $databaseHandle->exec("SELECT $certColumnNames FROM `silverbullet_certificate` WHERE `silverbullet_invitation_id` = ? ORDER BY `revocation_status`, `expiry` DESC", "i", $rowId);
        $certificatesNumber = ($certificatesResult ? $certificatesResult->num_rows : 0);
        $loggerInstance->debug(5, "At token validation level, " . $certificatesNumber . " certificates exist.\n");

        $retArray = [
            "cert_status" => \core\ProfileSilverbullet::enumerateCertDetails($certificatesResult),
            "profile" => $invitationRow->profile_id,
            "user" => $invitationRow->silverbullet_user_id,
            "expiry" => $invitationRow->expiry,
            "activations_remaining" => $invitationRow->quantity - $certificatesNumber,
            "value" => $invitationRow->token
        ];

        switch ($certificatesNumber) {
            case 0:
                // find out if it has expired
                $now = new \DateTime();
                $expiryObject = new \DateTime($invitationRow->expiry);
                $delta = $now->diff($expiryObject);
                if ($delta->invert == 1) {
                    $retArray['status'] = self::SB_TOKENSTATUS_EXPIRED;
                    $retArray['activations_remaining'] = 0;
                    break;
                }
                $retArray['status'] = self::SB_TOKENSTATUS_VALID;
                break;
            case $invitationRow->quantity:
                $retArray['status'] = self::SB_TOKENSTATUS_REDEEMED;
                break;
            default:
                assert($certificatesNumber > 0); // no negatives allowed
                assert($certificatesNumber < $invitationRow->quantity || $invitationRow->quantity == 0); // not more than max quantity allowed (unless quantity is zero)
                $retArray['status'] = self::SB_TOKENSTATUS_PARTIALLY_REDEEMED;
        }

        // now, look up certificate details and put them all in the cert_status property

        $loggerInstance->debug(5, "tokenStatus: done, returning " . $retArray['status'] . ", " . count($retArray['cert_status']) . ", " . $retArray['profile'] . ", " . $retArray['user'] . ", " . $retArray['expiry'] . ", " . $retArray['value'] . "\n");
        return $retArray;
    }

    /**
     * For a given certificate username, find the profile and username in CAT
     * this needs to be static because we do not have a known profile instance
     * 
     * @param type $certUsername a username from CN or sAN:email
     */
    public static function findUserIdFromCert($certUsername) {
        $dbHandle = \core\DBConnection::handle("INST");
        $userrows = $dbHandle->exec("SELECT silverbullet_user_id AS user_id, profile_id AS profile FROM silverbullet_certificate WHERE cn = ?", "s", $certUsername);
        while ($returnedData = mysqli_fetch_object($userrows)) { // only one
            return ["profile" => $returnedData->profile, "user" => $returnedData->user_id];
        }
    }

    public function userStatus($username) {
        $retval = [];
        $userrows = $this->databaseHandle->exec("SELECT `token` FROM `silverbullet_invitation` WHERE `silverbullet_user_id` = ? AND `profile_id` = ? ", "si", $username, $this->identifier);
        while ($returnedData = mysqli_fetch_object($userrows)) {
            $retval[] = ProfileSilverbullet::tokenStatus($returnedData->token);
        }
        return $retval;
    }

}
