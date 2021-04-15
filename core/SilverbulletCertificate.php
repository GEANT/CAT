<?php

/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
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
use \SoapFault;

class SilverbulletCertificate extends EntityWithDBProperties
{

    /**
     * The username in the certificate (CN)
     * 
     * @var string
     */
    public $username;

    /**
     * expiry date and time of the certificate in mysql date notation
     * 
     * @var string
     */
    public $expiry;

    /**
     * serial number of the certificate. For CAs with serials beyond 64 bit 
     * length, this is a string, otherwise an integer. Storage in SQL is always
     * a BLOB so can handle both.
     * 
     * @var integer|string
     */
    public $serial;

    /**
     * row index of this certificate in the database table
     * 
     * @var integer
     */
    public $dbId;

    /**
     * the row index of the invitation which was consumed to generate this 
     * certificate
     * 
     * @var integer
     */
    public $invitationId;

    /**
     * the user ID that belongs to this certificate
     * 
     * @var integer
     */
    public $userId;

    /**
     * the ID of the profile to which this certificate belongs
     * 
     * @var integer
     */
    public $profileId;

    /**
     * date and time of issuance of the certificate (start of validity) in MySQL
     * timestamp notation
     * 
     * @var string
     */
    public $issued;

    /**
     * the device for which this certificate was generated
     * 
     * @var string
     */
    public $device;

    /**
     * whether or not this certificate is revoked. Can take values REVOKED or
     * NOT_REVOKED
     * 
     * @var string
     */
    public $revocationStatus;

    /**
     * date and time of revocation of this certificate, if any. In MySQL 
     * timestamp notation
     * 
     * @var string
     */
    public $revocationTime;

    /**
     * the most current OCSP statement for this certificate (binary data)
     * 
     * @var string
     */
    public $ocsp;

    /**
     * date and time of issuance of the current OCSP statement for this 
     * certificate (mySQL timestamp notation)
     * 
     * @var string
     */
    public $ocspTimestamp;

    /**
     * overall status of the certificate. See constants below for possible 
     * values.
     * 
     * @var integer
     */
    public $status;

    /**
     * which CA issued the certificate. Typical values are "RSA" or "ECDSA".
     * 
     * @var string
     */
    public $ca_type;

    /**
     * any additional info about the certificate. Expected to be a JSON string.
     * 
     * @var string
     */
    public $annotation;

    /**
     * Certificate is valid at the current point in time.
     */
    const CERTSTATUS_VALID = 1;

    /**
     * Certificate has expired. This status is set regardless whether it has 
     * also been revoked before; once the expiry date is over, it is just
     * expired.
     * 
     */
    const CERTSTATUS_EXPIRED = 2;

    /**
     * Certificate is within its validity time, but has been revoked.
     */
    const CERTSTATUS_REVOKED = 3;

    /**
     * This is not a certificate we know about.
     */
    const CERTSTATUS_INVALID = 4;

    /**
     * instantiates an existing certificate, identified either by its serial
     * number or the username. 
     * 
     * Use static issueCertificate() to generate a whole new cert.
     * 
     * @param int|string $identifier identify certificate either by CN or by serial
     * @param string     $certtype   RSA or ECDSA?
     */
    public function __construct($identifier, $certtype = NULL)
    {
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
        $this->ca_type = $certtype;
        $this->status = SilverbulletCertificate::CERTSTATUS_INVALID;
        $this->annotation = NULL;

        $incoming = FALSE;
        if (is_numeric($identifier)) {
            $incoming = $this->databaseHandle->exec("SELECT `id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`, `issued`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp`, `ca_type`, `extrainfo` FROM `silverbullet_certificate` WHERE serial_number = ? AND ca_type = ?", "is", $identifier, $certtype);
        } else { // it's a string instead
            $incoming = $this->databaseHandle->exec("SELECT `id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`, `issued`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp`, `ca_type`, `extrainfo` FROM `silverbullet_certificate` WHERE cn = ?", "s", $identifier);
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
            $this->ca_type = $oneResult->ca_type;
            $this->annotation = $oneResult->extrainfo;
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
     * retrieve basic information about the certificate
     * 
     * @return array of basic certificate details
     */
    public function getBasicInfo()
    {
        $returnArray = []; // unnecessary because the iterator below is never empty, but Scrutinizer gets excited nontheless
        foreach (['status', 'serial', 'username', 'issued', 'expiry', 'ca_type', 'annotation'] as $key) {
            $returnArray[$key] = $this->$key;
        }
        $returnArray['device'] = \devices\Devices::listDevices()[$this->device]['display'] ?? $this->device;
        return $returnArray;
    }

    /**
     * adds extra information about the certificate to the DB
     * 
     * @param array $annotation information to be stored
     * @return void
     */
    public function annotate($annotation)
    {
        $encoded = json_encode($annotation);
        $this->annotation = $encoded;
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET extrainfo = ? WHERE serial_number = ?", "si", $encoded, $this->serial);
    }

    /**
     * we don't use caching in SB, so this function does nothing
     * 
     * @return void
     */
    public function updateFreshness()
    {
        // nothing to be done here.
    }

    /**
     * find out what the CA engine to use is
     * 
     * @param string $type which engine to use
     * @return CertificationAuthorityInterface engine to use
     * @throws Exception
     */
    public static function getCaEngine($type)
    {
        switch ($type) {
            case \devices\Devices::SUPPORT_EMBEDDED_RSA:
                $caEngine = new CertificationAuthorityEmbeddedRSA();
                break;
            case \devices\Devices::SUPPORT_EDUPKI:
                $caEngine = new CertificationAuthorityEduPki();
                break;
            case \devices\Devices::SUPPORT_EMBEDDED_ECDSA:
                $caEngine = new CertificationAuthorityEmbeddedECDSA();
                break;
            default:
                throw new Exception("Unknown certificate backend!");
        }
        return $caEngine;
    }

    /**
     * issue a certificate based on a token
     *
     * @param string $token          the token string
     * @param string $importPassword the PIN
     * @param string $certtype       is this for the RSA or ECDSA CA?
     * @return array
     * @throws Exception
     */
    public static function issueCertificate($token, $importPassword, $certtype)
    {
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
        $caEngine = SilverbulletCertificate::getCaEngine($certtype);
        $username = SilverbulletCertificate::findUniqueUsername($profile->getAttributes("internal:realm")[0]['value'], $certtype);
        $privateKey = $caEngine->generateCompatiblePrivateKey();
        $csr = $caEngine->generateCompatibleCsr($privateKey, strtoupper($inst->federation), $username);

        $loggerInstance->debug(5, "generateCertificate: proceeding to sign cert.\n");

        $certMeta = $caEngine->signRequest($csr, $expiryDays);
        $cert = $certMeta["CERT"];
        $issuingCaPem = $certMeta["ISSUER"];
        $rootCaPem = $certMeta["ROOT"];
        $serial = $certMeta["SERIAL"];

        if ($cert === FALSE) {
            throw new Exception("The CA did not generate a certificate.");
        }
        $loggerInstance->debug(5, "generateCertificate: post-processing certificate.\n");

        // with the cert, our private key and import password, make a PKCS#12 container out of it
        $exportedCertProt = "";
        openssl_pkcs12_export($cert, $exportedCertProt, $privateKey, $importPassword, ['extracerts' => [$issuingCaPem /* , $rootCaPem */]]);
        // and without intermediate, to keep EAP conversation short where possible
        $exportedNoInterm = "";
        openssl_pkcs12_export($cert, $exportedNoInterm, $privateKey, $importPassword, []);
        $exportedCertClear = "";
        openssl_pkcs12_export($cert, $exportedCertClear, $privateKey, "", ['extracerts' => [$issuingCaPem, $rootCaPem]]);
        // store resulting cert CN and expiry date in separate columns into DB - do not store the cert data itself as it contains the private key!
        // we need the *real* expiry date, not just the day-approximation
        $x509 = new \core\common\X509();
        $certString = "";
        openssl_x509_export($cert, $certString);
        $parsedCert = $x509->processCertificate($certString);
        $loggerInstance->debug(5, "CERTINFO: " . /** @scrutinizer ignore-type */ print_r($parsedCert['full_details'], true));
        $realExpiryDate = date_create_from_format("U", $parsedCert['full_details']['validTo_time_t'])->format("Y-m-d H:i:s");

        // store new cert info in DB
        $databaseHandle->exec("INSERT INTO `silverbullet_certificate` (`profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn` ,`expiry`, `ca_type`) VALUES (?, ?, ?, ?, ?, ?, ?)", "iiissss", $invitationObject->profile, $invitationObject->userId, $invitationObject->identifier, $serial, $csr["USERNAME"], $realExpiryDate, $certtype);
        // newborn cert immediately gets its "valid" OCSP response
        $certObject = new SilverbulletCertificate($serial, $certtype);
        // the engine knows the format of its own serial numbers, no reason to get excited
        $caEngine->triggerNewOCSPStatement(/** @scrutinizer ignore-type */ $certObject->serial);
// return PKCS#12 data stream
        return [
            "certObject" => $certObject,
            "certdata" => $exportedCertProt,
            "certdata_nointermediate" => $exportedNoInterm,
            "certdataclear" => $exportedCertClear,
            // Scrutinizer thinks this needs to be a string, but a resource is just fine
            "sha1" => openssl_x509_fingerprint(/** @scrutinizer ignore-type */$cert, "sha1"),
            "sha256" => openssl_x509_fingerprint(/** @scrutinizer ignore-type */$cert, "sha256"),
            'importPassword' => $importPassword,
            'GUID' => common\Entity::uuid("", $exportedCertProt),
            'CN' => $csr["USERNAME"],
        ];
    }

    /**
     * revokes a certificate
     * 
     * @return void
     * @throws Exception
     */
    public function revokeCertificate()
    {
        $nowSql = (new \DateTime())->format("Y-m-d H:i:s");
        // regardless if embedded or not, always keep local state in our own DB
        $this->databaseHandle->exec("UPDATE silverbullet_certificate SET revocation_status = 'REVOKED', revocation_time = ? WHERE serial_number = ? AND ca_type = ?", "sis", $nowSql, $this->serial, $this->ca_type);
        $this->loggerInstance->debug(2, "Certificate revocation status for $this->serial updated, about to call triggerNewOCSPStatement().\n");
        // newly instantiate us, DB content has changed...
        $certObject = new SilverbulletCertificate((string) $this->serial, $this->ca_type);
        // embedded CA does "nothing special" for revocation: the DB change was the entire thing to do
        // but for external CAs, we need to notify explicitly that the cert is now revoked
        $caEngine = SilverbulletCertificate::getCaEngine($certObject->ca_type);
        // the engine knows the format of its own serial numbers, no reason to get excited
        $caEngine->revokeCertificate(/** @scrutinizer ignore-type */ $certObject->serial);
    }

    /**
     * we need a unique CN for every certificate. This function generates a
     * random CN and verifies that it does not yet exist in the DB
     * 
     * @param string $realm    the realm for the username
     * @param string $certtype typically RSA or ECDSA
     * @return string the username, realm included
     */
    private static function findUniqueUsername($realm, $certtype)
    {
        $databaseHandle = DBConnection::handle("INST");
        $usernameIsUnique = FALSE;
        $username = "";
        while ($usernameIsUnique === FALSE) {
            $usernameLocalPart = common\Entity::randomString(64 - 1 - strlen($realm), "0123456789abcdefghijklmnopqrstuvwxyz");
            $username = $usernameLocalPart . "@" . $realm;
            $uniquenessQuery = $databaseHandle->exec("SELECT cn from silverbullet_certificate WHERE cn = ? AND ca_type = ?", "ss", $username, $certtype);
            // SELECT -> resource, not boolean
            if (mysqli_num_rows(/** @scrutinizer ignore-type */ $uniquenessQuery) == 0) {
                $usernameIsUnique = TRUE;
            }
        }
        return $username;
    }
}