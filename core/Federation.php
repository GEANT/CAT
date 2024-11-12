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
 * This file contains the Federation class.
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
 * This class represents an consortium federation.
 * 
 * It is semantically a country(!). Do not confuse this with a TLD; a federation
 * may span more than one TLD, and a TLD may be distributed across multiple federations.
 *
 * Example: a federation "fr" => "France" may also contain other TLDs which
 *              belong to France in spite of their different TLD
 * Example 2: Domains ending in .edu are present in multiple different
 *              federations
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class Federation extends EntityWithDBProperties
{

    /**
     * the handle to the FRONTEND database (only needed for some stats access)
     * 
     * @var DBConnection
     */
    private $frontendHandle;

    /**
     * the top-level domain of the Federation
     * 
     * @var string
     */
    public $tld;

    private $idpArray = [];
    /**
     * retrieve the statistics from the database in an internal array representation
     * 
     * @param string $detail
     * @return array
     */
    private function downloadStatsCore($detail = '')
    {
        if ($detail !== 'ORGANISATIONS' && $detail !== 'PROFILES' && $detail !== 'FEDERATION') {
            $detail = 'NONE';
        }
        
        $grossAdmin = 0;
        $grossUser = 0;
        $grossSilverbullet = 0;
        $dataArray = [];
        $cohesionQuery = [
            'ORGANISATIONS' => "SELECT profile.inst_id AS inst_id, downloads.device_id AS dev_id, sum(downloads.downloads_user) AS dl_user, sum(downloads.downloads_silverbullet) as dl_sb, sum(downloads.downloads_admin) AS dl_admin FROM downloads JOIN profile ON downloads.profile_id=profile.profile_id JOIN institution ON profile.inst_id=institution.inst_id WHERE institution.country = ? GROUP BY profile.inst_id, downloads.device_id",
            'PROFILES' => "SELECT profile.inst_id AS inst_id, profile.profile_id AS profile_id, downloads.device_id AS dev_id, sum(downloads.downloads_user) AS dl_user, sum(downloads.downloads_silverbullet) as dl_sb, sum(downloads.downloads_admin) AS dl_admin FROM downloads JOIN profile ON downloads.profile_id=profile.profile_id JOIN institution ON profile.inst_id=institution.inst_id WHERE institution.country = ? GROUP BY profile.inst_id, profile.profile_id, downloads.device_id",
            'FEDERATION' => "SELECT downloads.device_id AS dev_id, sum(downloads.downloads_user) AS dl_user, sum(downloads.downloads_silverbullet) AS dl_sb, sum(downloads.downloads_admin) AS dl_admin FROM profile JOIN institution ON profile .inst_id = institution.inst_id JOIN downloads ON profile.profile_id = downloads.profile_id WHERE institution.country = ? AND profile.profile_id = downloads.profile_id GROUP BY device_id",
            'NONE' => "SELECT downloads.device_id as dev_id, sum(downloads.downloads_user) as dl_user, sum(downloads.downloads_silverbullet) AS dl_sb, sum(downloads.downloads_admin) as dl_admin FROM profile JOIN institution ON profile .inst_id = institution.inst_id JOIN downloads ON profile.profile_id = downloads.profile_id WHERE profile.inst_id = institution.inst_id AND institution.country = ? AND profile.profile_id = downloads.profile_id group by device_id"
        ];
        // first, find out which profiles belong to this federation
        $downloadsList = $this->databaseHandle->exec($cohesionQuery[$detail], "s", $this->tld);
        $deviceArray = \devices\Devices::listDevices();
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $downloadsList)) {
            if ($detail === 'NONE' || $detail === 'FEDERATION') {
                $grossAdmin = $grossAdmin + $queryResult->dl_admin;
                $grossSilverbullet = $grossSilverbullet + $queryResult->dl_sb;
                $grossUser = $grossUser + $queryResult->dl_user;
                if ($detail === 'NONE') {
                    continue;
                }
            }    
            if (isset($deviceArray[$queryResult->dev_id])) {
                $displayName = $deviceArray[$queryResult->dev_id]['display'];
            } else { // this device has stats, but doesn't exist in current config. We don't even know its display name, so display its raw representation
                $displayName = sprintf(_("(discontinued) %s"), $queryResult->dev_id);
            }
            if ($detail === 'FEDERATION') {
                $dataArray[$displayName] = ["ADMIN" => $queryResult->dl_admin, "SILVERBULLET" => $queryResult->dl_sb, "USER" => $queryResult->dl_user];
            } else {
                $inst_id = $queryResult->inst_id;            
                if (!isset($dataArray[$inst_id])) {
                    $dataArray[$inst_id] = [];
                }            
                if ($detail === 'ORGANISATIONS') {       
                    $dataArray[$inst_id][$displayName] = ["ADMIN" => $queryResult->dl_admin, "SILVERBULLET" => $queryResult->dl_sb, "USER" => $queryResult->dl_user];
                }
                if ($detail === 'PROFILES') {
                    $profile_id = $queryResult->profile_id;
                    if (!isset($dataArray[$inst_id][$profile_id])) {
                        $dataArray[$inst_id][$profile_id] = [];
                    }
                    $dataArray[$inst_id][$profile_id][$displayName] = ["ADMIN" => $queryResult->dl_admin, "SILVERBULLET" => $queryResult->dl_sb, "USER" => $queryResult->dl_user];
                }
            }
        }
        if ($detail === 'NONE' || $detail === 'FEDERATION') {
            $dataArray["TOTAL"] = ["ADMIN" => $grossAdmin, "SILVERBULLET" => $grossSilverbullet, "USER" => $grossUser];
        }
        return $dataArray;
    }

    /**
     * when a Federation attribute changes, invalidate caches of all IdPs 
     * in that federation (e.g. change of fed logo changes the actual 
     * installers)
     * 
     * @return void
     */
    public function updateFreshness()
    {
        $idplist = $this->listIdentityProviders();
        foreach ($idplist as $idpDetail) {
            $idpDetail['instance']->updateFreshness();
        }
    }

    /**
     * gets the download statistics for the federation
     * @param string $format either as an html *table* or *XML* or *JSON*
     * @return string|array
     * @throws Exception
     */
    public function downloadStats($format, $detail = '')
    {
        $data = $this->downloadStatsCore($detail);
        $retstring = "";

        switch ($format) {
            case "table":
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "<tr><td>$device</td><td>".$numbers['ADMIN']."</td><td>".$numbers['SILVERBULLET']."</td><td>".$numbers['USER']."</td></tr>";
                }
                $retstring .= "<tr><td><strong>TOTAL</strong></td><td><strong>".$data['TOTAL']['ADMIN']."</strong></td><td><strong>".$data['TOTAL']['SILVERBULLET']."</strong></td><td><strong>".$data['TOTAL']['USER']."</strong></td></tr>";
                break;
            case "XML":
                // the calls to date() operate on current date, so there is no chance for a FALSE to be returned. Silencing scrutinizer.
                $retstring .= "<federation id='$this->tld' ts='"./** @scrutinizer ignore-type */ date("Y-m-d")."T"./** @scrutinizer ignore-type */ date("H:i:s")."'>\n";
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "  <device name='".$device."'>\n    <downloads group='admin'>".$numbers['ADMIN']."</downloads>\n    <downloads group='managed_idp'>".$numbers['SILVERBULLET']."</downloads>\n    <downloads group='user'>".$numbers['USER']."</downloads>\n  </device>";
                }
                $retstring .= "<total>\n  <downloads group='admin'>".$data['TOTAL']['ADMIN']."</downloads>\n  <downloads group='managed_idp'>".$data['TOTAL']['SILVERBULLET']."</downloads>\n  <downloads group='user'>".$data['TOTAL']['USER']."</downloads>\n</total>\n";
                $retstring .= "</federation>";
                break;
            case "array":
                return $data;
            default:
                throw new Exception("Statistics can be requested only in 'table' or 'XML' format!");
        }
        return $retstring;
    }

    /**
     *
     * Constructs a Federation object.
     *
     * @param string $fedname textual representation of the Federation object
     *                        Example: "lu" (for Luxembourg)
     * @throws Exception
     */
    public function __construct($fedname)
    {
        // initialise the superclass variables

        $this->databaseType = "INST";
        $this->entityOptionTable = "federation_option";
        $this->entityIdColumn = "federation_id";

        $cat = new CAT();
        if (!isset($cat->knownFederations[$fedname])) {
            throw new Exception("This federation is not known to the system!");
        }
        $this->identifier = 0; // we do not use the numeric ID of a federation
        // $fedname is unvetted input. We do know it's correct because of the 
        // knownFederations check above - so no security issue - but Scrutinizer
        // doesn't realise it because we assign the literal incoming value. 
        // Let's make this assignment more dumb so that it passes the SC checks.
        // Equivalent to the following line, but assigning processed indexes
        // instead of the identical user input.
        // $this->tld = $fedname;
        $fedIdentifiers = array_keys($cat->knownFederations);
        $this->tld = $fedIdentifiers[array_search(strtoupper($fedname), $fedIdentifiers)];
        $this->name = $cat->knownFederations[$this->tld]['name'];
        // end of spoon-feed
        
        parent::__construct(); // we now have access to our database handle

        $handle = DBConnection::handle("FRONTEND");
        if ($handle instanceof DBConnection) {
            $this->frontendHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
        $this->loggerInstance->debug(4, $fedname, "Creating federation:", " \n");
        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row_id 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = ?
                                            ORDER BY option_name", "FED");


        $this->attributes[] = array("name" => "internal:country",
            "lang" => NULL,
            "value" => $this->tld,
            "level" => Options::LEVEL_FED,
            "row_id" => 0,
            "flag" => NULL);

        if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] != 'LOCAL' && \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == 'LOCAL') {
            // this instance exclusively does SB, so it is not necessary to ask
            // fed ops whether they want to enable it or not. So always add it
            // to the list of fed attributes
            $this->attributes[] = array("name" => "fed:silverbullet",
                "lang" => NULL,
                "value" => "on",
                "level" => Options::LEVEL_FED,
                "row_id" => 0,
                "flag" => NULL);
        }

        $this->idpListActive = [];
        $this->idpListAll = [];
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $type          type of institution - IdP, SP or IdPSP
     * @param string $ownerId       Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level         Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail          e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @param string $bestnameguess name of the IdP, if already known, in the best-match language
     * @return integer identifier of the new IdP
     * @throws Exception
     */
    public function newIdP($type, $ownerId, $level, $mail = NULL, $bestnameguess = NULL)
    {
        $this->databaseHandle->exec("INSERT INTO institution (country, type) VALUES('$this->tld', '$type')");
        $identifier = $this->databaseHandle->lastID();

        if ($identifier == 0 || !$this->loggerInstance->writeAudit($ownerId, "NEW", "Organisation $identifier")) {
            $text = "<p>Could not create a new ".common\Entity::$nomenclature_participant."!</p>";
            echo $text;
            throw new Exception($text);
        }

        if ($ownerId != "PENDING") {
            if ($mail === NULL) {
                throw new Exception("New IdPs in a federation need a mail address UNLESS created by API without OwnerId");
            }
            $this->databaseHandle->exec("INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES(?,?,?,?)", "siss", $ownerId, $identifier, $level, $mail);
        }
        if ($bestnameguess === NULL) {
            $bestnameguess = "(no name yet, identifier $identifier)";
        }
        $admins = $this->listFederationAdmins();

        switch ($type) {
            case IdP::TYPE_IDP:
                $prettyPrintType = common\Entity::$nomenclature_idp;
                break;
            case IdP::TYPE_SP:
                $prettyPrintType = common\Entity::$nomenclature_hotspot;
                break;
            default:
                /// IdP and SP
                $prettyPrintType = sprintf(_("%s and %s"), common\Entity::$nomenclature_idp, common\Entity::$nomenclature_hotspot);
        }

        $consortium = \config\ConfAssistant::CONSORTIUM['display_name'];
        $productShort = \config\Master::APPEARANCE['productname'];
        $productLong = \config\Master::APPEARANCE['productname_long'];
        // notify the fed admins...

        foreach ($admins as $id) {
            $user = new User($id);
            /// arguments are: 1. nomenclature for the type of organisation being created (IdP/SP/both)
            ///                2. IdP name; 
            ///                3. consortium name (e.g. eduroam); 
            ///                4. federation shortname, e.g. "LU"; 
            ///                5. nomenclature for "institution"
            ///                6. product name (e.g. eduroam CAT); 
            ///                7. product long name (e.g. eduroam Configuration Assistant Tool)
            $message = sprintf(_("Hi,

the invitation for the new %s %s in your %s federation %s has been used and the %s was created in %s.

We thought you might want to know.

Best regards,

%s"),
                    $prettyPrintType,
                    $bestnameguess,
                    $consortium,
                    strtoupper($this->tld),
                    common\Entity::$nomenclature_participant,
                    $productShort,
                    $productLong);
            /// organisation
            if (\config\Master::MAILSETTINGS['notify_nro']) {
                $retval = $user->sendMailToUser(sprintf(_("%s in your federation was created"), common\Entity::$nomenclature_participant), $message);
                if ($retval === FALSE) {
                    $this->loggerInstance->debug(2, "Mail to federation admin was NOT sent!\n");
                }
            }
        }

        return $identifier;
    }

    /**
     * list of all institutions. Fetched once from the DB and then stored in
     * this variable
     * 
     * @var array
     */
    private $idpListAll;

    /**
     * list of all active institutions. Fetched once from the DB and then stored
     * in this variable
     * 
     * @var array
     */
    private $idpListActive;

    /**
     * fetches all known certificate information for RADIUS/TLS certs from the DB
     * 
     * @return array
     */
    public function listTlsCertificates()
    {
        $certQuery = "SELECT ca_name, request_serial, distinguished_name, status, expiry, certificate, revocation_pin FROM federation_servercerts WHERE federation_id = ?";
        $upperTld = strtoupper($this->tld);
        $certList = $this->databaseHandle->exec($certQuery, "s", $upperTld);
        $retArray = [];
        // SELECT -> resource, not boolean
        while ($certListResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $certList)) {
            $retArray[] = [
                'CA' => $certListResult->ca_name,
                'REQSERIAL' => $certListResult->request_serial,
                'DN' => $certListResult->distinguished_name,
                'STATUS' => $certListResult->status,
                'EXPIRY' => $certListResult->expiry,
                'CERT' => $certListResult->certificate,
                'REVPIN' => $certListResult->revocation_pin,
            ];
        }
        return$retArray;
    }

    /**
     * requests a new certificate
     * 
     * @param string $user       the user ID requesting the certificate
     * @param array  $csr        the CSR with some metainfo in an array
     * @param int    $expiryDays how long should the cert be valid, in days
     * @return void
     */
    public function requestCertificate($user, $csr, $expiryDays)
    {
        $revocationPin = common\Entity::randomString(10, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");
        $newReq = new CertificationAuthorityEduPkiServer();
        $reqserial = $newReq->sendRequestToCa($csr, $revocationPin, $expiryDays);
        $this->loggerInstance->writeAudit($user, "NEW", "Certificate request - NRO: ".$this->tld." - serial: ".$reqserial." - subject: ".$csr['SUBJECT']);
        $reqQuery = "INSERT INTO federation_servercerts "
                ."(federation_id, ca_name, request_serial, distinguished_name, status, revocation_pin) "
                ."VALUES (?, 'eduPKI', ?, ?, 'REQUESTED', ?)";
        $this->databaseHandle->exec($reqQuery, "siss", $this->tld, $reqserial, $csr['SUBJECT'], $revocationPin);
    }

    /**
     * fetches new cert info from the CA
     * 
     * @param int $reqSerial the request serial number that is to be updated
     * @return void
     */
    public function updateCertificateStatus($reqSerial)
    {
        $ca = new CertificationAuthorityEduPkiServer();
        $entryInQuestion = $ca->pickupFinalCert($reqSerial, FALSE);
        if ($entryInQuestion === FALSE) {
            return; // no update to fetch
        }
        $certDetails = openssl_x509_parse($entryInQuestion['CERT']);
        $expiry = "20".$certDetails['validTo'][0].$certDetails['validTo'][1]."-".$certDetails['validTo'][2].$certDetails['validTo'][3]."-".$certDetails['validTo'][4].$certDetails['validTo'][5];
        openssl_x509_export($entryInQuestion['CERT'], $pem);
        $updateQuery = "UPDATE federation_servercerts SET status = 'ISSUED', certificate = ?, expiry = ? WHERE ca_name = 'eduPKI' AND request_serial = ?";
        $this->databaseHandle->exec($updateQuery, "ssi", $pem, $expiry, $reqSerial);
    }

    /**
     * revokes a certificate.
     * 
     * @param int $reqSerial the request serial whose associated cert is to be revoked
     * @return void
     */
    public function triggerRevocation($reqSerial)
    {
        // revocation at the CA side works with the serial of the certificate, not the request
        // so find that out first
        // This is a select, so tell Scrutinizer about the type-safety of the result
        $certInfoResource = $this->databaseHandle->exec("SELECT certificate FROM federation_servercerts WHERE ca_name = 'eduPKI' AND request_serial = ?", "i", $reqSerial);
        $certInfo = mysqli_fetch_row(/** @scrutinizer ignore-type */ $certInfoResource);
        if ($certInfo === NULL) {
            return; // cert not found, nothing to revoke
        }
        $certData = openssl_x509_parse($certInfo[0]);
        $serial = $certData['full_details']['serialNumber'];
        $eduPki = new CertificationAuthorityEduPkiServer();
        $eduPki->revokeCertificate($serial);
        $this->databaseHandle->exec("UPDATE federation_servercerts SET status = 'REVOKED' WHERE ca_name = 'eduPKI' AND request_serial = ?", "i", $reqSerial);
    }
    /**
     * Gets an array of certificate status (as most critical) from all profiles
     * per IdP - passing over the non-active profiles
     */
    public function getIdentityProvidersCertStatus() {
        $query = "SELECT distinct profile.profile_id  FROM profile JOIN profile_option ON profile.profile_id = profile_option.profile_id WHERE option_name='profile:production' AND profile.sufficient_config = 1";
        $activeProfiles = [];
        $result = $this->databaseHandle->exec($query);
        $rows = $result->fetch_all();
        foreach ($rows as $row) {
           $activeProfiles[] = $row[0];
        }
        $query = "SELECT institution.inst_id AS inst_id, profile.profile_id AS profile_id, profile_option.option_value AS cert FROM profile_option JOIN profile ON profile_option.profile_id=profile.profile_id JOIN institution ON profile.inst_id=institution.inst_id WHERE profile_option.option_name='eap:ca_file' and institution.country='".$this->tld."'";        
        $result = $this->databaseHandle->exec($query);
        $rows = $result->fetch_all();
        $x509 = new \core\common\X509();
        $certsStatus = [];
        $idpCertStatus = [];
        foreach ($rows as $row) {
            $inst = $row[0];
            $profile = $row[1];
            // pass any rofile which is not active
            if (!in_array($profile, $activeProfiles)) {
                continue;
            }
            $encodedCert = $row[2];
            if (!isset($idpCertStatus[$inst])) {
                $idpCertStatus[$inst] = \core\AbstractProfile::CERT_STATUS_OK;
            }
            
            // check if we have already seen this cert if not, continue analysis
            if (!isset($certsStatus[$encodedCert])) {
                $tm = $x509->processCertificate(base64_decode($encodedCert))['full_details']['validTo_time_t'] - time();
                if ($tm < \config\ConfAssistant::CERT_WARNINGS['expiry_critical']) {
                    $certsStatus[$encodedCert] = \core\AbstractProfile::CERT_STATUS_ERROR;
                } elseif ($tm < \config\ConfAssistant::CERT_WARNINGS['expiry_warning']) {
                    $certsStatus[$encodedCert] = \core\AbstractProfile::CERT_STATUS_WARN;
                } else {
                    $certsStatus[$encodedCert] = \core\AbstractProfile::CERT_STATUS_OK;
                }
            }
            $idpCertStatus[$inst] = max($idpCertStatus[$inst], $certsStatus[$encodedCert]);
        }
        return $idpCertStatus;
    }
    
    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $activeOnly if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($activeOnly = 0)
    {
        // maybe we did this exercise before?
        if ($activeOnly != 0 && count($this->idpListActive) > 0) {
            return $this->idpListActive;
        }
        if ($activeOnly == 0 && count($this->idpListAll) > 0) {
            return $this->idpListAll;
        }
        // the one for activeOnly is much more complex:
        if ($activeOnly != 0) {
            $allIDPs = $this->databaseHandle->exec("SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->tld' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        } else {         // default query is:
        $allIDPs = $this->databaseHandle->exec("SELECT institution.inst_id AS inst_id,
            GROUP_CONCAT(DISTINCT REGEXP_REPLACE(profile.realm, '.*@', '') SEPARATOR '===') AS realms
            FROM institution LEFT JOIN profile ON institution.inst_id = profile.inst_id
               WHERE country = '$this->tld' GROUP BY institution.inst_id ORDER BY inst_id");

        }
        $returnarray = [];
        // SELECT -> resource, not boolean
        while ($idpQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allIDPs)) {
            if (isset($this->idpArray[$idpQuery->inst_id])) {
                $idp = $this->idpArray[$idpQuery->inst_id];
            } else {
                $idp = new IdP($idpQuery->inst_id);
                $this->idpArray[$idpQuery->inst_id] = $idp;
            }
            if (!isset($idpQuery->realms)) {
                $idpQuery->realms = '';
            }
            $name = $idp->name;
            $idpInfo = ['entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp,
                'realms' => $idpQuery->realms]
                 ;
            $returnarray[$idp->identifier] = $idpInfo;
        }
        if ($activeOnly != 0) { // we're only doing this once.
            $this->idpListActive = $returnarray;
        } else {
            $this->idpListAll = $returnarray;
        }
        return $returnarray;
    }

    /**
     * returns an array with information about the authorised administrators of the federation
     * 
     * @return array list of the admins of this federation
     */
    public function listFederationAdmins()
    {
        $returnarray = [];
        $query = "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = ?";
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = ?";
        }
        $userHandle = DBConnection::handle("USER"); // we need something from the USER database for a change
        $upperFed = strtoupper($this->tld);
        // SELECT -> resource, not boolean
        $admins = $userHandle->exec($query, "s", $upperFed);

        while ($fedAdminQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $admins)) {
            $returnarray[] = $fedAdminQuery->user_id;
        }
        return $returnarray;
    }

    /**
     * cross-checks in the EXTERNAL customer DB which institutions exist there for the federations
     * 
     * @param bool   $unmappedOnly if set to TRUE, only returns those which do not have a known mapping to our internally known institutions
     * @param string $type         which type of entity to search for
     * @return array
     */
    public function listExternalEntities($unmappedOnly, $type = NULL)
    {
        $allExternals = [];
        $usedarray = [];
        $returnarray = [];
        if ($unmappedOnly) { // find out which entities are already mapped
            $syncstate = IdP::EXTERNAL_DB_SYNCSTATE_SYNCED;
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = ?", "i", $syncstate);
            $pendingInvite = $this->databaseHandle->exec("SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
            // SELECT -> resource, no boolean
            while ($alreadyUsedQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }
            // SELECT -> resource, no boolean
            while ($pendingInviteQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $pendingInvite)) {
                if (!in_array($pendingInviteQuery->external_db_uniquehandle, $usedarray)) {
                    $usedarray[] = $pendingInviteQuery->external_db_uniquehandle;
                }
            }
        }

        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $externalDB = CAT::determineExternalConnection();
            // need to convert our internal notion of participant types to those of eduroam DB
            $allExternals = $externalDB->listExternalEntities($this->tld, $type);
        }
        foreach ($allExternals as $oneExternal) {
            if (!in_array($oneExternal["ID"], $usedarray)) {
                $returnarray[] = $oneExternal;
            }
        }
        return $returnarray;
    }

    const UNKNOWN_IDP = -1;
    const AMBIGUOUS_IDP = -2;

    /**
     * for a MySQL list of institutions, find an institution or find out that
     * there is no single best match
     * 
     * @param \mysqli_result $dbResult the query object to work with
     * @param string         $country  used to return the country of the inst, if can be found out
     * @return int the identifier of the inst, or one of the special return values if unsuccessful
     */
    private static function findCandidates(\mysqli_result $dbResult, &$country)
    {
        $retArray = [];
        while ($row_id = mysqli_fetch_object($dbResult)) {
            if (!in_array($row_id->id, $retArray)) {
                $retArray[] = $row_id->id;
                $country = strtoupper($row_id->country);
            }
        }
        if (count($retArray) <= 0) {
            return Federation::UNKNOWN_IDP;
        }
        if (count($retArray) > 1) {
            return Federation::AMBIGUOUS_IDP;
        }

        return array_pop($retArray);
    }

    /**
     * If we are running diagnostics, our input from the user is the realm. We
     * need to find out which IdP this realm belongs to.
     * @param string $realm the realm to search for
     * @return array an array with two entries, CAT ID and DB ID, with either the respective ID of the IdP in the system, or UNKNOWN_IDP or AMBIGUOUS_IDP
     */
    public static function determineIdPIdByRealm($realm)
    {
        $country = NULL;
        $candidatesExternalDb = Federation::UNKNOWN_IDP;
        $dbHandle = DBConnection::handle("INST");
        $realmSearchStringCat = "%@$realm";
        $candidateCatQuery = $dbHandle->exec("SELECT p.profile_id as id, i.country as country FROM profile p, institution i WHERE p.inst_id = i.inst_id AND p.realm LIKE ?", "s", $realmSearchStringCat);
        // this is a SELECT returning a resource, not a boolean
        $candidatesCat = Federation::findCandidates(/** @scrutinizer ignore-type */ $candidateCatQuery, $country);

        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED        
            $externalHandle = DBConnection::handle("EXTERNAL");
            $realmSearchStringDb1 = "$realm";
            $realmSearchStringDb2 = "%,$realm";
            $realmSearchStringDb3 = "$realm,%";
            $realmSearchStringDb4 = "%,$realm,%";
            $candidateExternalQuery = $externalHandle->exec("SELECT id_institution as id, country FROM view_active_idp_institution WHERE inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ? or inst_realm LIKE ?", "ssss", $realmSearchStringDb1, $realmSearchStringDb2, $realmSearchStringDb3, $realmSearchStringDb4);
            // SELECT -> resource, not boolean
            $candidatesExternalDb = Federation::findCandidates(/** @scrutinizer ignore-type */ $candidateExternalQuery, $country);
        }

        return ["CAT" => $candidatesCat, "EXTERNAL" => $candidatesExternalDb, "FEDERATION" => $country];
    }
}