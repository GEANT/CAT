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
 * This file contains code for testing EAP servers
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 *
 * @package Developer
 * 
 */

namespace core\diag;

use \Exception;

/**
 * Test suite to verify that an EAP setup is actually working as advertised in
 * the real world. Can only be used if \config\Diagnostics::RADIUSTESTS is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RADIUSTests extends AbstractTest {

    /**
     * Was the reachability check executed already?
     * 
     * @var integer
     */
    private $UDP_reachability_executed;

    /**
     * the issues we found
     * 
     * @var array 
     */
    private $errorlist;

    /**
     * This private variable contains the realm to be checked. Is filled in the
     * class constructor.
     * 
     * @var string
     */
    private $realm;

    /**
     * which username to use as outer identity
     * 
     * @var string
     */
    private $outerUsernameForChecks;

    /**
     * list of CAs we expect incoming server certs to be from
     * 
     * @var array
     */
    private $expectedCABundle;

    /**
     * list of expected server names
     * 
     * @var array
     */
    private $expectedServerNames;

    /**
     * the list of EAP types which the IdP allegedly supports.
     * 
     * @var array
     */
    private $supportedEapTypes;

    /**
     * Do we run throrough or shallow checks?
     * 
     * @var integer
     */
    private $opMode;

    /**
     * result of the reachability tests
     * 
     * @var array
     */
    public $UDP_reachability_result;

    const RADIUS_TEST_OPERATION_MODE_SHALLOW = 1;
    const RADIUS_TEST_OPERATION_MODE_THOROUGH = 2;

    /**
     * Constructor for the EAPTests class. The single mandatory parameter is the
     * realm for which the tests are to be carried out.
     * 
     * @param string $realm                  the realm to check
     * @param string $outerUsernameForChecks outer username to use
     * @param array  $supportedEapTypes      array of integer representations of EAP types
     * @param array  $expectedServerNames    array of strings
     * @param array  $expectedCABundle       array of PEM blocks
     * @throws Exception
     */
    public function __construct($realm, $outerUsernameForChecks, $supportedEapTypes = [], $expectedServerNames = [], $expectedCABundle = []) {
        parent::__construct();

        $this->realm = $realm;
        $this->outerUsernameForChecks = $outerUsernameForChecks;
        $this->expectedCABundle = $expectedCABundle;
        $this->expectedServerNames = $expectedServerNames;
        $this->supportedEapTypes = $supportedEapTypes;

        $this->opMode = self::RADIUS_TEST_OPERATION_MODE_SHALLOW;

        $caNeeded = FALSE;
        $serverNeeded = FALSE;
        foreach ($supportedEapTypes as $oneEapType) {
            if ($oneEapType->needsServerCACert()) {
                $caNeeded = TRUE;
            }
            if ($oneEapType->needsServerName()) {
                $serverNeeded = TRUE;
            }
        }

        if ($caNeeded) {
            // we need to have info about at least one CA cert and server names
            if (count($this->expectedCABundle) == 0) {
                throw new Exception("Thorough checks for an EAP type needing CAs were requested, but the required parameters were not given.");
            } else {
                $this->opMode = self::RADIUS_TEST_OPERATION_MODE_THOROUGH;
            }
        }

        if ($serverNeeded) {
            if (count($this->expectedServerNames) == 0) {
                throw new Exception("Thorough checks for an EAP type needing server names were requested, but the required parameter was not given.");
            } else {
                $this->opMode = self::RADIUS_TEST_OPERATION_MODE_THOROUGH;
            }
        }

        $this->loggerInstance->debug(4, "RADIUSTests is in opMode " . $this->opMode . ", parameters were: $realm, $outerUsernameForChecks, " . /** @scrutinizer ignore-type */ print_r($supportedEapTypes, true));
        $this->loggerInstance->debug(4, /** @scrutinizer ignore-type */ print_r($expectedServerNames, true));
        $this->loggerInstance->debug(4, /** @scrutinizer ignore-type */ print_r($expectedCABundle, true));

        $this->UDP_reachability_result = [];
        $this->errorlist = [];
    }

    /**
     * creates a string with the DistinguishedName (comma-separated name=value fields)
     * 
     * @param array $distinguishedName the components of the DN
     * @return string
     */
    private function printDN($distinguishedName) {
        $out = '';
        foreach (array_reverse($distinguishedName) as $nameType => $nameValue) { // to give an example: "CN" => "some.host.example" 
            if (!is_array($nameValue)) { // single-valued: just a string
                $nameValue = ["$nameValue"]; // convert it to a multi-value attrib with just one value :-) for unified processing later on
            }
            foreach ($nameValue as $oneValue) {
                if ($out) {
                    $out .= ',';
                }
                $out .= "$nameType=$oneValue";
            }
        }
        return($out);
    }

    /**
     * prints a timestamp in gmdate formatting
     * 
     * @param int $time time in UNIX timestamp
     * @return string
     */
    private function printTm($time) {
        return(gmdate(\DateTime::COOKIE, $time));
    }

    /**
     * This function parses a X.509 server cert and checks if it finds client device incompatibilities
     * 
     * @param array $servercert the properties of the certificate as returned by
     *                          processCertificate(), $servercert is modified, 
     *                          if CRL is defied, it is downloaded and added to
     *                          the array incoming_server_names, sAN_DNS and CN 
     *                          array values are also defined
     * @return array of oddities; the array is empty if everything is fine
     */
    private function propertyCheckServercert(&$servercert) {
// we share the same checks as for CAs when it comes to signature algorithm and basicconstraints
// so call that function and memorise the outcome
        $returnarray = $this->propertyCheckIntermediate($servercert, TRUE);
        $sANdns = [];
        if (!isset($servercert['full_details']['extensions'])) {
            $returnarray[] = RADIUSTests::CERTPROB_NO_TLS_WEBSERVER_OID;
            $returnarray[] = RADIUSTests::CERTPROB_NO_CDP_HTTP;
        } else { // Extensions are present...
            if (!isset($servercert['full_details']['extensions']['extendedKeyUsage']) || !preg_match("/TLS Web Server Authentication/", $servercert['full_details']['extensions']['extendedKeyUsage'])) {
                $returnarray[] = RADIUSTests::CERTPROB_NO_TLS_WEBSERVER_OID;
            }
            if (isset($servercert['full_details']['extensions']['subjectAltName'])) {
                $sANlist = explode(", ", $servercert['full_details']['extensions']['subjectAltName']);
                foreach ($sANlist as $subjectAltName) {
                    if (preg_match("/^DNS:/", $subjectAltName)) {
                        $sANdns[] = substr($subjectAltName, 4);
                    }
                }
            }
        }

        // often, there is only one name, so we store it in an array of one member
        $commonName = [$servercert['full_details']['subject']['CN']];
        // if we got an array of names instead, then that is already an array, so override
        if (isset($servercert['full_details']['subject']['CN']) && is_array($servercert['full_details']['subject']['CN'])) {
            $commonName = $servercert['full_details']['subject']['CN'];
            $returnarray[] = RADIUSTests::CERTPROB_MULTIPLE_CN;
        }
        $allnames = array_values(array_unique(array_merge($commonName, $sANdns)));
// check for wildcards
// check for real hostnames, and whether there is a wildcard in a name
        foreach ($allnames as $onename) {
            if (preg_match("/\*/", $onename)) {
                $returnarray[] = RADIUSTests::CERTPROB_WILDCARD_IN_NAME;
                continue; // otherwise we'd ALSO complain that it's not a real hostname
            }
            if ($onename != "" && filter_var("foo@" . idn_to_ascii($onename), FILTER_VALIDATE_EMAIL) === FALSE) {
                $returnarray[] = RADIUSTests::CERTPROB_NOT_A_HOSTNAME;
            }
        }
        $servercert['incoming_server_names'] = $allnames;
        $servercert['sAN_DNS'] = $sANdns;
        $servercert['CN'] = $commonName;
        return $returnarray;
    }

    /**
     * This function parses a X.509 intermediate CA cert and checks if it finds client device incompatibilities
     * 
     * @param array   $intermediateCa the properties of the certificate as returned by processCertificate()
     * @param boolean $serverCert     treat as servercert?
     * @return array of oddities; the array is empty if everything is fine
     */
    private function propertyCheckIntermediate(&$intermediateCa, $serverCert = FALSE) {
        $returnarray = [];
        if (preg_match("/md5/i", $intermediateCa['full_details']['signatureTypeSN'])) {
            $returnarray[] = RADIUSTests::CERTPROB_MD5_SIGNATURE;
        }
        if (preg_match("/sha1/i", $intermediateCa['full_details']['signatureTypeSN'])) {
            $probValue = RADIUSTests::CERTPROB_SHA1_SIGNATURE;
            $returnarray[] = $probValue;
        }
        $this->loggerInstance->debug(4, "CERT IS: " . /** @scrutinizer ignore-type */ print_r($intermediateCa, TRUE));
        if ($intermediateCa['basicconstraints_set'] == 0) {
            $returnarray[] = RADIUSTests::CERTPROB_NO_BASICCONSTRAINTS;
        }
        if ($intermediateCa['full_details']['public_key_algorithm'] == \core\common\X509::KNOWN_PUBLIC_KEY_ALGORITHMS[0] && $intermediateCa['full_details']['public_key_length'] < 2048) {
            $returnarray[] = RADIUSTests::CERTPROB_LOW_KEY_LENGTH;
        }
        if (!in_array($intermediateCa['full_details']['public_key_algorithm'], \core\common\X509::KNOWN_PUBLIC_KEY_ALGORITHMS)) {
            $returnarray[] = RADIUSTests::CERTPROB_UNKNOWN_PUBLIC_KEY_ALGORITHM;
        }
        $validFrom = $intermediateCa['full_details']['validFrom_time_t'];
        $now = time();
        $validTo = $intermediateCa['full_details']['validTo_time_t'];
        if ($validFrom > $now || $validTo < $now) {
            $returnarray[] = RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD;
        }
        $addCertCrlResult = $this->addCrltoCert($intermediateCa);
        if ($addCertCrlResult !== 0 && $serverCert) {
            $returnarray[] = $addCertCrlResult;
        }

        return $returnarray;
    }

    /**
     * This function returns an array of errors which were encountered in all the tests.
     * 
     * @return array all the errors
     */
    public function listerrors() {
        return $this->errorlist;
    }

    /**
     * This function performs actual authentication checks with MADE-UP credentials.
     * Its purpose is to check if a RADIUS server is reachable and speaks EAP.
     * The function fills array RADIUSTests::UDP_reachability_result[$probeindex] with all check detail
     * in case more than the return code is needed/wanted by the caller
     * 
     * @param int     $probeindex  refers to the specific UDP-host in the config that should be checked
     * @param boolean $opnameCheck should we check choking on Operator-Name?
     * @param boolean $frag        should we cause UDP fragmentation? (Warning: makes use of Operator-Name!)
     * @return integer returncode
     * @throws Exception
     */
    public function udpReachability($probeindex, $opnameCheck = TRUE, $frag = TRUE) {
        // for EAP-TLS to be a viable option, we need to pass a random client cert to make eapol_test happy
        // the following PEM data is one of the SENSE EAPLab client certs (not secret at all)
        $clientcert = file_get_contents(dirname(__FILE__) . "/clientcert.p12");
        if ($clientcert === FALSE) {
            throw new Exception("A dummy client cert is part of the source distribution, but could not be loaded!");
        }
        // if we are in thorough opMode, use our knowledge for a more clever check
        // otherwise guess
        if ($this->opMode == self::RADIUS_TEST_OPERATION_MODE_THOROUGH) {
            return $this->udpLogin($probeindex, $this->supportedEapTypes[0]->getArrayRep(), $this->outerUsernameForChecks, 'eaplab', $opnameCheck, $frag, $clientcert);
        }
        return $this->udpLogin($probeindex, \core\common\EAP::EAPTYPE_ANY, "cat-connectivity-test@" . $this->realm, 'eaplab', $opnameCheck, $frag, $clientcert);
    }

    /**
     * There is a CRL Distribution Point URL in the certificate. So download the
     * CRL and attach it to the cert structure so that we can later find out if
     * the cert was revoked
     * @param array $cert by-reference: the cert data we are writing into
     * @return integer result code whether we were successful in retrieving the CRL
     * @throws Exception
     */
    private function addCrltoCert(&$cert) {
        $crlUrl = [];
        $returnresult = 0;
        if (!isset($cert['full_details']['extensions']['crlDistributionPoints'])) {
            return RADIUSTests::CERTPROB_NO_CDP;
        }
        if (!preg_match("/^.*URI\:(http)(.*)$/", str_replace(["\r", "\n"], ' ', $cert['full_details']['extensions']['crlDistributionPoints']), $crlUrl)) {
            return RADIUSTests::CERTPROB_NO_CDP_HTTP;
        }
        // first and second sub-match is the full URL... check it
        $crlcontent = \core\common\OutsideComm::downloadFile(trim($crlUrl[1] . $crlUrl[2]));
        if ($crlcontent === FALSE) {
            return RADIUSTests::CERTPROB_NO_CRL_AT_CDP_URL;
        }
        /* CRLs are always in DER form, so need encoding
         * note that what we ACTUALLY got can be arbitrary junk; we just deposit
         * it on the filesystem and let openssl figure out if it is usable or not
         *
         * Unfortunately, that freaks out Scrutinizer because we write unvetted
         * data to the filesystem. Let's see if we can make things better.
         */

        // $pem = chunk_split(base64_encode($crlcontent), 64, "\n");
        // inspired by https://stackoverflow.com/questions/2390604/how-to-pass-variables-as-stdin-into-command-line-from-php

        $proc = \config\Master::PATHS['openssl'] . " crl -inform der";
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];
        $process = proc_open($proc, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Unable to execute openssl cmdline for CRL conversion!");
        }
        fwrite($pipes[0], $crlcontent);
        fclose($pipes[0]);
        $pem = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $retval = proc_close($process);
        if ($retval != 0 || !preg_match("/BEGIN X509 CRL/", $pem)) {
            // this was not a real CRL
            return RADIUSTests::CERTPROB_NO_CRL_AT_CDP_URL;
        }
        $cert['CRL'] = [];
        $cert['CRL'][] = $pem;
        return $returnresult;
    }

    /**
     * We don't want to write passwords of the live login test to our logs. Filter them out
     * @param string $stringToRedact what should be redacted
     * @param array  $inputarray     array of strings (outputs of eapol_test command)
     * @return string[] the output of eapol_test with the password redacted
     */
    private function redact($stringToRedact, $inputarray) {
        $temparray = preg_replace("/^.*$stringToRedact.*$/", "LINE CONTAINING PASSWORD REDACTED", $inputarray);
        $hex = bin2hex($stringToRedact);
        $spaced = "";
        $origLength = strlen($hex);
        for ($i = 1; $i < $origLength; $i++) {
            if ($i % 2 == 1 && $i != strlen($hex)) {
                $spaced .= $hex[$i] . " ";
            } else {
                $spaced .= $hex[$i];
            }
        }
        return preg_replace("/$spaced/", " HEX ENCODED PASSWORD REDACTED ", $temparray);
    }

    /**
     * Filters eapol_test output and finds out the packet codes out of which the conversation was comprised of
     * 
     * @param array $inputarray array of strings (outputs of eapol_test command)
     * @return array the packet codes which were exchanged, in sequence
     */
    private function filterPackettype($inputarray) {
        $retarray = [];
        foreach ($inputarray as $line) {
            if (preg_match("/RADIUS message:/", $line)) {
                $linecomponents = explode(" ", $line);
                $packettypeExploded = explode("=", $linecomponents[2]);
                $packettype = $packettypeExploded[1];
                $retarray[] = $packettype;
            }
        }
        return $retarray;
    }

    const LINEPARSE_CHECK_REJECTIGNORE = 1;
    const LINEPARSE_CHECK_691 = 2;
    const LINEPARSE_EAPACK = 3;
    const LINEPARSE_TLSVERSION = 4;
    const TLS_VERSION_ANCIENT = "OTHER";
    const TLS_VERSION_1_0 = "TLSv1";
    const TLS_VERSION_1_1 = "TLSv1.1";
    const TLS_VERSION_1_2 = "TLSv1.2";
    const TLS_VERSION_1_3 = "TLSv1.3";

    /**
     * this function checks for various special conditions which can be found 
     * only by parsing eapol_test output line by line. Checks currently 
     * implemented are:
     * * if the ETLRs sent back an Access-Reject because there appeared to
     *   be a timeout further downstream
     * * did the server send an MSCHAP Error 691 - Retry Allowed in a Challenge
     *   instead of an outright reject?
     * * was an EAP method ever acknowledged by both sides during the EAP
     *   conversation
     * 
     * @param array   $inputarray   array of strings (outputs of eapol_test command)
     * @param integer $desiredCheck which test should be run (see constants above)
     * @return boolean|string returns TRUE if ETLR Reject logic was detected; FALSE if not; strings are returned for TLS versions
     * @throws Exception
     */
    private function checkLineparse($inputarray, $desiredCheck) {
        foreach ($inputarray as $lineid => $line) {
            switch ($desiredCheck) {
                case self::LINEPARSE_CHECK_REJECTIGNORE:
                    if (preg_match("/Attribute 18 (Reply-Message)/", $line) && preg_match("/Reject instead of Ignore at eduroam.org/", $inputarray[$lineid + 1])) {
                        return TRUE;
                    }
                    break;
                case self::LINEPARSE_CHECK_691:
                    if (preg_match("/MSCHAPV2: error 691/", $line) && preg_match("/MSCHAPV2: retry is allowed/", $inputarray[$lineid + 1])) {
                        return TRUE;
                    }
                    break;
                case self::LINEPARSE_EAPACK:
                    if (preg_match("/CTRL-EVENT-EAP-PROPOSED-METHOD/", $line) && !preg_match("/NAK$/", $line)) {
                        return TRUE;
                    }
                    break;
                case self::LINEPARSE_TLSVERSION:
                    break;
                default:
                    throw new Exception("This lineparse test does not exist.");
            }
        }
        // for TLS version checks, we need to search from bottom to top 
        // eapol_test will always try its highest version first, and can be
        // pursuaded later on to do less. So look at the end result.
        for ($counter = count($inputarray); $counter > 0; $counter--) {
            switch ($desiredCheck) {
                case self::LINEPARSE_TLSVERSION:
                    $version = [];
                    if (preg_match("/Using TLS version (.*)$/", $inputarray[$counter], $version)) {
                        switch (trim($version[1])) {
                            case self::TLS_VERSION_1_3:
                                return self::TLS_VERSION_1_3;
                            case self::TLS_VERSION_1_2:
                                return self::TLS_VERSION_1_2;
                            case self::TLS_VERSION_1_1:
                                return self::TLS_VERSION_1_1;
                            case self::TLS_VERSION_1_0:
                                return self::TLS_VERSION_1_0;
                            default:
                                return self::TLS_VERSION_ANCIENT;
                        }
                    }
                    break;
                case self::LINEPARSE_CHECK_691:
                /* fall-through intentional */
                case self::LINEPARSE_CHECK_REJECTIGNORE:
                /* fall-through intentional */
                case self::LINEPARSE_EAPACK:
                    /* fall-through intentional */
                    break;
                default:
                    throw new Exception("This lineparse test does not exist.");
            }
        }
        return FALSE;
    }

    /**
     * 
     * @param array  $eaptype  array representation of the EAP type
     * @param string $inner    inner username
     * @param string $outer    outer username
     * @param string $password the password
     * @return string[] [0] is the actual config for wpa_supplicant, [1] is a redacted version for logs
     */
    private function wpaSupplicantConfig(array $eaptype, string $inner, string $outer, string $password) {
        $eapText = \core\common\EAP::eapDisplayName($eaptype);
        $config = '
network={
  ssid="' . \config\Master::APPEARANCE['productname'] . ' testing"
  key_mgmt=WPA-EAP
  proto=WPA2
  pairwise=CCMP
  group=CCMP
  ';
// phase 1
        $config .= 'eap=' . $eapText['OUTER'] . "\n";
        $logConfig = $config;
// phase 2 if applicable; all inner methods have passwords
        if (isset($eapText['INNER']) && $eapText['INNER'] != "") {
            $config .= '  phase2="auth=' . $eapText['INNER'] . "\"\n";
            $logConfig .= '  phase2="auth=' . $eapText['INNER'] . "\"\n";
        }
// all methods set a password, except EAP-TLS
        if ($eaptype != \core\common\EAP::EAPTYPE_TLS) {
            $config .= "  password=\"$password\"\n";
            $logConfig .= "  password=\"not logged for security reasons\"\n";
        }
// for methods with client certs, add a client cert config block
        if ($eaptype == \core\common\EAP::EAPTYPE_TLS || $eaptype == \core\common\EAP::EAPTYPE_ANY) {
            $config .= "  private_key=\"./client.p12\"\n";
            $logConfig .= "  private_key=\"./client.p12\"\n";
            $config .= "  private_key_passwd=\"$password\"\n";
            $logConfig .= "  private_key_passwd=\"not logged for security reasons\"\n";
        }

// inner identity
        $config .= '  identity="' . $inner . "\"\n";
        $logConfig .= '  identity="' . $inner . "\"\n";
// outer identity, may be equal
        $config .= '  anonymous_identity="' . $outer . "\"\n";
        $logConfig .= '  anonymous_identity="' . $outer . "\"\n";
// done
        $config .= "}";
        $logConfig .= "}";

        return [$config, $logConfig];
    }

    /**
     * Checks whether the packets received are as expected in numbers
     * 
     * @param array $testresults by-reference array of the testresults so far
     *                           function adds its own findings to that array
     * @param array $packetcount the count of incoming packets
     * @return int
     */
    private function packetCountEvaluation(&$testresults, $packetcount) {
        $reqs = $packetcount[1] ?? 0;
        $accepts = $packetcount[2] ?? 0;
        $rejects = $packetcount[3] ?? 0;
        $challenges = $packetcount[11] ?? 0;
        $testresults['packetflow_sane'] = TRUE;
        if ($reqs - $accepts - $rejects - $challenges != 0 || $accepts > 1 || $rejects > 1) {
            $testresults['packetflow_sane'] = FALSE;
        }

        $this->loggerInstance->debug(5, "XYZ: Counting req, acc, rej, chal: $reqs, $accepts, $rejects, $challenges");

// calculate the main return values that this test yielded

        $finalretval = RADIUSTests::RETVAL_INVALID;
        if ($accepts + $rejects == 0) { // no final response. hm.
            if ($challenges > 0) { // but there was an Access-Challenge
                $finalretval = RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM;
            } else {
                $finalretval = RADIUSTests::RETVAL_NO_RESPONSE;
            }
        } else // either an accept or a reject
// rejection without EAP is fishy
        if ($rejects > 0) {
            if ($challenges == 0) {
                $finalretval = RADIUSTests::RETVAL_IMMEDIATE_REJECT;
            } else { // i.e. if rejected with challenges
                $finalretval = RADIUSTests::RETVAL_CONVERSATION_REJECT;
            }
        } else if ($accepts > 0) {
            $finalretval = RADIUSTests::RETVAL_OK;
        }

        return $finalretval;
    }

    /**
     * generate an eapol_test command-line config for the fixed config filename 
     * ./udp_login_test.conf
     * @param int     $probeindex number of the probe to check against
     * @param boolean $opName     include Operator-Name in request?
     * @param boolean $frag       make request so large that fragmentation is needed?
     * @return string the command-line for eapol_test
     */
    private function eapolTestConfig($probeindex, $opName, $frag) {
        $cmdline = \config\Diagnostics::PATHS['eapol_test'] .
                " -a " . \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$probeindex]['ip'] .
                " -s " . \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$probeindex]['secret'] .
                " -o serverchain.pem" .
                " -c ./udp_login_test.conf" .
                " -M 22:44:66:CA:20:" . sprintf("%02d", $probeindex) . " " .
                " -t " . \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$probeindex]['timeout'] . " ";
        if ($opName) {
            $cmdline .= '-N126:s:"1cat.eduroam.org" ';
        }
        if ($frag) {
            for ($i = 0; $i < 6; $i++) { // 6 x 250 bytes means UDP fragmentation will occur - good!
                $cmdline .= '-N26:x:0000625A0BF961616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161 ';
            }
        }
        return $cmdline;
    }

    /**
     * collects CA certificates, both from the incoming EAP chain and from CAT
     * config. Writes the root CAs into a trusted root CA dir and intermediate 
     * and first server cert into a PEM file for later chain validation
     * 
     * @param string $tmpDir              working directory
     * @param array  $intermOdditiesCAT   by-reference array of already found 
     *                                    oddities; adds its own
     * @param array  $servercert          the servercert to validate
     * @param array  $eapIntermediates    list of intermediate CA certs that came
     *                                    in via EAP
     * @param array  $eapIntermediateCRLs list of CRLs for the EAP-supplied
     *                                    intermediate CAs
     * @return string
     * @throws Exception
     */
    private function createCArepository($tmpDir, &$intermOdditiesCAT, $servercert, $eapIntermediates, $eapIntermediateCRLs) {
        if (!mkdir($tmpDir . "/root-ca-allcerts/", 0700, true)) {
            throw new Exception("unable to create root CA directory (RADIUS Tests): $tmpDir/root-ca-allcerts/\n");
        }
        if (!mkdir($tmpDir . "/root-ca-eaponly/", 0700, true)) {
            throw new Exception("unable to create root CA directory (RADIUS Tests): $tmpDir/root-ca-eaponly/\n");
        }
// make a copy of the EAP-received chain and add the configured intermediates, if any
        $catIntermediates = [];
        $catRoots = [];
        foreach ($this->expectedCABundle as $oneCA) {
            $x509 = new \core\common\X509();
            $decoded = $x509->processCertificate($oneCA);
            if (is_bool($decoded)) {
                throw new Exception("Unable to parse an expected CA certificate.");
            }
            if ($decoded['ca'] == 1) {
                if ($decoded['root'] == 1) { // save CAT roots to the root directory
                    file_put_contents($tmpDir . "/root-ca-eaponly/configuredroot" . count($catRoots) . ".pem", $decoded['pem']);
                    file_put_contents($tmpDir . "/root-ca-allcerts/configuredroot" . count($catRoots) . ".pem", $decoded['pem']);
                    $catRoots[] = $decoded['pem'];
                } else { // save the intermediates to allcerts directory
                    file_put_contents($tmpDir . "/root-ca-allcerts/cat-intermediate" . count($catIntermediates) . ".pem", $decoded['pem']);
                    $intermOdditiesCAT = array_merge($intermOdditiesCAT, $this->propertyCheckIntermediate($decoded));
                    if (isset($decoded['CRL']) && isset($decoded['CRL'][0])) {
                        $this->loggerInstance->debug(4, "got an intermediate CRL; adding them to the chain checks. (Remember: checking end-entity cert only, not the whole chain");
                        file_put_contents($tmpDir . "/root-ca-allcerts/crl_cat" . count($catIntermediates) . ".pem", $decoded['CRL'][0]);
                    }
                    $catIntermediates[] = $decoded['pem'];
                }
            }
        }
        // save all intermediate certificates and CRLs to separate files in 
        // both root-ca directories
        foreach ($eapIntermediates as $index => $onePem) {
            file_put_contents($tmpDir . "/root-ca-eaponly/intermediate$index.pem", $onePem);
            file_put_contents($tmpDir . "/root-ca-allcerts/intermediate$index.pem", $onePem);
        }
        foreach ($eapIntermediateCRLs as $index => $onePem) {
            file_put_contents($tmpDir . "/root-ca-eaponly/intermediateCRL$index.pem", $onePem);
            file_put_contents($tmpDir . "/root-ca-allcerts/intermediateCRL$index.pem", $onePem);
        }

        $checkstring = "";
        if (isset($servercert['CRL']) && isset($servercert['CRL'][0])) {
            $this->loggerInstance->debug(4, "got a server CRL; adding them to the chain checks. (Remember: checking end-entity cert only, not the whole chain");
            $checkstring = "-crl_check_all";
            file_put_contents($tmpDir . "/root-ca-eaponly/crl-server.pem", $servercert['CRL'][0]);
            file_put_contents($tmpDir . "/root-ca-allcerts/crl-server.pem", $servercert['CRL'][0]);
        }


// now c_rehash the root CA directory ...
        system(\config\Diagnostics::PATHS['c_rehash'] . " $tmpDir/root-ca-eaponly/ > /dev/null");
        system(\config\Diagnostics::PATHS['c_rehash'] . " $tmpDir/root-ca-allcerts/ > /dev/null");
        return $checkstring;
    }

    /**
     * for checks which have a known trust root CA (i.e. a valid CAT profile 
     * exists), check against those known-good roots
     * 
     * @param array  $testresults         by-reference list of testresults so far
     *                                    Function adds its own.
     * @param array  $intermOdditiesCAT   by-reference list of oddities in the CA
     *                                    certs which are configured in CAT
     * @param string $tmpDir              working directory
     * @param array  $servercert          the server certificate to validate
     * @param array  $eapIntermediates    list of intermediate CA certs that came
     *                                    in via EAP
     * @param array  $eapIntermediateCRLs list of CRLs for the EAP-supplied
     *                                    intermediate CAs
     * @return int
     * @throws Exception
     */
    private function thoroughChainChecks(&$testresults, &$intermOdditiesCAT, $tmpDir, $servercert, $eapIntermediates, $eapIntermediateCRLs) {

        $crlCheckString = $this->createCArepository($tmpDir, $intermOdditiesCAT, $servercert, $eapIntermediates, $eapIntermediateCRLs);

// ... and run the verification test
        $verifyResultEaponly = [];
        $verifyResultAllcerts = [];
// the error log will complain if we run this test against an empty file of certs
// so test if there's something PEMy in the file at all
// serverchain.pem is the output from eapol_test; incomingserver.pem is written by extractIncomingCertsfromEAP() if there was at least one server cert.
        if (filesize("$tmpDir/serverchain.pem") > 10 && filesize("$tmpDir/incomingserver.pem") > 10) {
            exec(\config\Master::PATHS['openssl'] . " verify $crlCheckString -CApath $tmpDir/root-ca-eaponly/ -purpose any $tmpDir/incomingserver.pem", $verifyResultEaponly);
            $this->loggerInstance->debug(4, \config\Master::PATHS['openssl'] . " verify $crlCheckString -CApath $tmpDir/root-ca-eaponly/ -purpose any $tmpDir/serverchain.pem\n");
            $this->loggerInstance->debug(4, "Chain verify pass 1: " . /** @scrutinizer ignore-type */ print_r($verifyResultEaponly, TRUE) . "\n");
            exec(\config\Master::PATHS['openssl'] . " verify $crlCheckString -CApath $tmpDir/root-ca-allcerts/ -purpose any $tmpDir/incomingserver.pem", $verifyResultAllcerts);
            $this->loggerInstance->debug(4, \config\Master::PATHS['openssl'] . " verify $crlCheckString -CApath $tmpDir/root-ca-allcerts/ -purpose any $tmpDir/serverchain.pem\n");
            $this->loggerInstance->debug(4, "Chain verify pass 2: " . /** @scrutinizer ignore-type */ print_r($verifyResultAllcerts, TRUE) . "\n");
        }

// now we do certificate verification against the collected parents
// this is done first for the server and then for each of the intermediate CAs
// any oddities observed will 
// openssl should havd returned exactly one line of output,
// and it should have ended with the string "OK", anything else is fishy
// The result can also be an empty array - this means there were no
// certificates to check. Don't complain about chain validation errors
// in that case.
// we have the following test result possibilities:
// 1. test against allcerts failed
// 2. test against allcerts succeded, but against eaponly failed - warn admin
// 3. test against eaponly succeded, in this case critical errors about expired certs
//    need to be changed to notices, since these certs obviously do tot participate
//    in server certificate validation.
        if (count($verifyResultAllcerts) == 0 || count($verifyResultEaponly) == 0) {
            throw new Exception("No output at all from openssl?");
        }
        if (!preg_match("/OK$/", $verifyResultAllcerts[0])) { // case 1
            if (preg_match("/certificate revoked$/", $verifyResultAllcerts[1])) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_SERVER_CERT_REVOKED;
            } elseif (preg_match("/unable to get certificate CRL/", $verifyResultAllcerts[1])) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_UNABLE_TO_GET_CRL;
            } else {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_TRUST_ROOT_NOT_REACHED;
            }
            return 1;
        }
        if (!preg_match("/OK$/", $verifyResultEaponly[0])) { // case 2
            if (preg_match("/certificate revoked$/", $verifyResultEaponly[1])) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_SERVER_CERT_REVOKED;
            } elseif (preg_match("/unable to get certificate CRL/", $verifyResultEaponly[1])) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_UNABLE_TO_GET_CRL;
            } else {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES;
            }
            return 2;
        }
        return 3;
    }

    /**
     * check the incoming hostname (both Subject:CN and subjectAltName:DNS
     * against what is configured in the profile; it's a significant error
     * if there is no match!
     * 
     * FAIL if none of the configured names show up in the server cert
     * WARN if the configured name is only in either CN or sAN:DNS
     * 
     * @param array $servercert  the server certificate to check
     * @param array $testresults by-reference the existing testresults. Function
     *                           adds its own findings.
     * @return void
     */
    private function thoroughNameChecks($servercert, &$testresults) {
        // Strategy for checks: we are TOTALLY happy if any one of the
        // configured names shows up in both the CN and a sAN
        // This is the primary check.
        // If that was not the case, we are PARTIALLY happy if any one of
        // the configured names was in either of the CN or sAN lists.
        // we are UNHAPPY if no names match!
        $happiness = "UNHAPPY";
        foreach ($this->expectedServerNames as $expectedName) {
            $this->loggerInstance->debug(4, "Managing expectations for $expectedName: " . /** @scrutinizer ignore-type */ print_r($servercert['CN'], TRUE) . /** @scrutinizer ignore-type */ print_r($servercert['sAN_DNS'], TRUE));
            if (array_search($expectedName, $servercert['CN']) !== FALSE && array_search($expectedName, $servercert['sAN_DNS']) !== FALSE) {
                $this->loggerInstance->debug(4, "Totally happy!");
                $happiness = "TOTALLY";
                break;
            } else {
                if (array_search($expectedName, $servercert['CN']) !== FALSE || array_search($expectedName, $servercert['sAN_DNS']) !== FALSE) {
                    $happiness = "PARTIALLY";
// keep trying with other expected names! We could be happier!
                }
            }
        }
        switch ($happiness) {
            case "UNHAPPY":
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_SERVER_NAME_MISMATCH;
                return;
            case "PARTIALLY":
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_SERVER_NAME_PARTIAL_MATCH;
                return;
            default: // nothing to complain about!
                return;
        }
    }

    /**
     * run eapol_test
     * 
     * @param string  $tmpDir      working directory
     * @param int     $probeindex  number of the probe this test should run through
     * @param array   $eaptype     EAP type in array representation
     * @param string  $innerUser   EAP method inner username to use
     * @param string  $password    password to use
     * @param boolean $opnameCheck inject Operator-Name?
     * @param boolean $frag        provoke UDP fragmentation?
     * @return array timing information of the executed eapol_test run
     * @throws Exception
     */
    private function executeEapolTest($tmpDir, $probeindex, $eaptype, $outerUser, $innerUser, $password, $opnameCheck, $frag) {
        $finalInner = $innerUser;
        $finalOuter = $outerUser;

        $theconfigs = $this->wpaSupplicantConfig($eaptype, $finalInner, $finalOuter, $password);
        // the config intentionally does not include CA checking. We do this
        // ourselves after getting the chain with -o.
        file_put_contents($tmpDir . "/udp_login_test.conf", $theconfigs[0]);

        $cmdline = $this->eapolTestConfig($probeindex, $opnameCheck, $frag);
        $this->loggerInstance->debug(4, "Shallow reachability check cmdline: $cmdline\n");
        $this->loggerInstance->debug(4, "Shallow reachability check config: $tmpDir\n" . $theconfigs[1] . "\n");
        $time_start = microtime(true);
        $pflow = [];
        exec($cmdline, $pflow);
        if ($pflow === NULL) {
            throw new Exception("The output of an exec() call really can't be NULL!");
        }
        $time_stop = microtime(true);
        $output = print_r($this->redact($password, $pflow), TRUE);
        file_put_contents($tmpDir . "/eapol_test_output_redacted_$probeindex.txt", $output);
        $this->loggerInstance->debug(5, "eapol_test output saved to eapol_test_output_redacted_$probeindex.txt\n");
        return [
            "time" => ($time_stop - $time_start) * 1000,
            "output" => $pflow,
        ];
    }

    /**
     * checks if the RADIUS packets were coming in in the order they are 
     * expected. The function massages the raw result for some known oddities.
     * 
     * @param array $testresults     by-reference array of test results so far.
     *                               function adds its own.
     * @param array $packetflow_orig original flow of packets
     * @return int
     */
    private function checkRadiusPacketFlow(&$testresults, $packetflow_orig) {

        $packetflow = $this->filterPackettype($packetflow_orig);

// when MS-CHAPv2 allows retry, we never formally get a reject (just a 
// Challenge that PW was wrong but and we should try a different one; 
// but that effectively is a reject
// so change the flow results to take that into account
        if ($packetflow[count($packetflow) - 1] == 11 && $this->checkLineparse($packetflow_orig, self::LINEPARSE_CHECK_691)) {
            $packetflow[count($packetflow) - 1] = 3;
        }
// also, the ETLRs sometimes send a reject when the server is not 
// responding. This should not be considered a real reject; it's a middle
// box unduly altering the end-to-end result. Do not consider this final
// Reject if it comes from ETLR
        if ($packetflow[count($packetflow) - 1] == 3 && $this->checkLineparse($packetflow_orig, self::LINEPARSE_CHECK_REJECTIGNORE)) {
            array_pop($packetflow);
        }
        $this->loggerInstance->debug(5, "Packetflow: " . /** @scrutinizer ignore-type */ print_r($packetflow, TRUE));
        $packetcount = array_count_values($packetflow);
        $testresults['packetcount'] = $packetcount;
        $testresults['packetflow'] = $packetflow;

// calculate packet counts and see what the overall flow was
        return $this->packetCountEvaluation($testresults, $packetcount);
    }

    /**
     * parses the eapol_test output to determine whether we got to a point where
     * an EAP type was mutually agreed
     * 
     * @param array $testresults     by-reference, we add our findings if 
     *                               something is noteworthy
     * @param array $packetflow_orig the array of text output from eapol_test
     * @return bool
     * @throws Exception
     */
    private function wasEapTypeNegotiated(&$testresults, $packetflow_orig) {
        $negotiatedEapType = $this->checkLineparse($packetflow_orig, self::LINEPARSE_EAPACK);
        if (!is_bool($negotiatedEapType)) {
            throw new Exception("checkLineparse should only ever return a boolean in this case!");
        }
        if (!$negotiatedEapType) {
            $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_NO_COMMON_EAP_METHOD;
        }

        return $negotiatedEapType;
    }

    /**
     * parses eapol_test to find the TLS version used during the EAP conversation
     * @param array $testresults     by-reference, we add our findings if 
     *                               something is noteworthy
     * @param array $packetflow_orig the array of text output from eapol_test
     * @return string|bool the version as a string or FALSE if TLS version could not be determined
     */
    private function wasModernTlsNegotiated(&$testresults, $packetflow_orig) {
        $negotiatedTlsVersion = $this->checkLineparse($packetflow_orig, self::LINEPARSE_TLSVERSION);
        $this->loggerInstance->debug(4, "TLS version found is: $negotiatedTlsVersion" . "\n");
        if ($negotiatedTlsVersion === FALSE) {
            $testresults['cert_oddities'][] = RADIUSTests::TLSPROB_UNKNOWN_TLS_VERSION;
        } elseif ($negotiatedTlsVersion != self::TLS_VERSION_1_2 && $negotiatedTlsVersion != self::TLS_VERSION_1_3) {
            $testresults['cert_oddities'][] = RADIUSTests::TLSPROB_DEPRECATED_TLS_VERSION;
        }

        return $negotiatedTlsVersion;
    }

    const SERVER_NO_CA_EXTENSION = 1;
    const SERVER_CA_SELFSIGNED = 2;
    const CA_INTERMEDIATE = 3;
    const CA_ROOT = 4;

    /**
     * what is the incoming certificate - root, intermediate, or server?
     * @param array $cert           the certificate to check
     * @param int   $totalCertCount number of certs in total in chain
     * @return int
     */
    private function determineCertificateType(&$cert, $totalCertCount) {
        if ($cert['ca'] == 0 && $cert['root'] == 0) {
            return RADIUSTests::SERVER_NO_CA_EXTENSION;
        }
        if ($cert['ca'] == 1 && $cert['root'] == 1) {
            if ($totalCertCount == 1) {
                $cert['full_details']['type'] = 'totally_selfsigned';
                return RADIUSTests::SERVER_CA_SELFSIGNED;
            } else {
                return RADIUSTests::CA_ROOT;
            }
        }
        return RADIUSTests::CA_INTERMEDIATE;
    }

    /**
     * pull out the certificates that were sent during the EAP conversation
     * 
     * @param array  $testresults by-reference, add our findings if any
     * @param string $tmpDir      working directory
     * @return array|FALSE an array with all the certs, CRLs and oddities, or FALSE if the EAP conversation did not yield a certificate at all
     * @throws Exception
     */
    private function extractIncomingCertsfromEAP(&$testresults, $tmpDir) {

        /*
         *  EAP's house rules:
         * 1) it is unnecessary to include the root CA itself (adding it has
         *    detrimental effects on performance)
         * 2) TLS Web Server OID presence (Windows OSes need that)
         * 3) MD5 signature algorithm disallowed (iOS barks if so)
         * 4) CDP URL (Windows Phone 8 barks if not present)
         * 5) there should be exactly one server cert in the chain
         */

        $x509 = new \core\common\X509();
// $eap_certarray holds all certs received in EAP conversation
        $incomingData = file_get_contents($tmpDir . "/serverchain.pem");
        if ($incomingData !== FALSE && strlen($incomingData) > 0) {
            $eapCertArray = $x509->splitCertificate($incomingData);
        } else {
            $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_NO_CERTIFICATE_IN_CONVERSATION;
            return FALSE;
        }
        $rootIncluded = [];
        $eapIntermediates = [];
        $eapIntermediateCRLs = [];
        $servercert = [];
        $intermOdditiesEAP = [];

        $testresults['certdata'] = [];

        foreach ($eapCertArray as $certPem) {
            $cert = $x509->processCertificate($certPem);
            if ($cert === FALSE) {
                continue;
            }
// consider the certificate a server cert 
// a) if it is not a CA and is not a self-signed root
// b) if it is a CA, and self-signed, and it is the only cert in
//    the incoming cert chain
//    (meaning the self-signed is itself the server cert)
            switch ($this->determineCertificateType($cert, count($eapCertArray))) {
                case RADIUSTests::SERVER_NO_CA_EXTENSION: // both are handled same, fall-through
                case RADIUSTests::SERVER_CA_SELFSIGNED:
                    $servercert[] = $cert;
                    if (count($servercert) == 1) {
                        if (file_put_contents($tmpDir . "/incomingserver.pem", $cert['pem'] . "\n") === FALSE) {
                            $this->loggerInstance->debug(4, "The (first) server certificate could not be written to $tmpDir/incomingserver.pem!\n");
                        }
                        $this->loggerInstance->debug(4, "This is the (first) server certificate, with CRL content if applicable: " . /** @scrutinizer ignore-type */ print_r($servercert[0], true));
                    } elseif (!in_array(RADIUSTests::CERTPROB_TOO_MANY_SERVER_CERTS, $testresults['cert_oddities'])) {
                        $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_TOO_MANY_SERVER_CERTS;
                    }
                    break;
                case RADIUSTests::CA_ROOT:
                    if (!in_array(RADIUSTests::CERTPROB_ROOT_INCLUDED, $testresults['cert_oddities'])) {
                        $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_ROOT_INCLUDED;
                    }
// chain checks need to be against the UPLOADED CA of the
// IdP/profile, not against an EAP-discovered CA
                    // save it anyway, but only for feature "root CA autodetection" is executed
                    $rootIncluded[] = $cert['pem'];
                    break;
                case RADIUSTests::CA_INTERMEDIATE:
                    $intermOdditiesEAP = array_merge($intermOdditiesEAP, $this->propertyCheckIntermediate($cert));
                    $eapIntermediates[] = $cert['pem'];

                    if (isset($cert['CRL']) && isset($cert['CRL'][0])) {
                        $eapIntermediateCRLs[] = $cert['CRL'][0];
                    }
                    break;
                default:
                    throw new Exception("Status of certificate could not be determined!");
            }
            $testresults['certdata'][] = $cert['full_details'];
        }
        switch (count($servercert)) {
            case 0:
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_NO_SERVER_CERT;
                break;
            default:
// check (first) server cert's properties
                $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $this->propertyCheckServercert($servercert[0]));
                $testresults['incoming_server_names'] = $servercert[0]['incoming_server_names'];
        }
        return [
            "SERVERCERT" => $servercert,
            "INTERMEDIATE_CA" => $eapIntermediates,
            "INTERMEDIATE_CRL" => $eapIntermediateCRLs,
            "INTERMEDIATE_OBSERVED_ODDITIES" => $intermOdditiesEAP,
            "UNTRUSTED_ROOT_INCLUDED" => $rootIncluded,
        ];
    }

    private function udpLoginPreliminaries($probeindex, $eaptype, $clientcertdata) {
        /** preliminaries */
        $eapText = \core\common\EAP::eapDisplayName($eaptype);
        // no host to send probes to? Nothing to do then
        if (!isset(\config\Diagnostics::RADIUSTESTS['UDP-hosts'][$probeindex])) {
            $this->UDP_reachability_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }

        // if we need client certs but don't have one, return
        if (($eaptype == \core\common\EAP::EAPTYPE_ANY || $eaptype == \core\common\EAP::EAPTYPE_TLS) && $clientcertdata === NULL) {
            $this->UDP_reachability_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }
        // if we don't have a string for outer EAP method name, give up
        if (!isset($eapText['OUTER'])) {
            $this->UDP_reachability_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }
        return TRUE;
    }

    public function autodetectCAWithProbe($outerId) {
        // for EAP-TLS to be a viable option, we need to pass a random client cert to make eapol_test happy
        // the following PEM data is one of the SENSE EAPLab client certs (not secret at all)
        $clientcert = file_get_contents(dirname(__FILE__) . "/clientcert.p12");
        if ($clientcert === FALSE) {
            throw new Exception("A dummy client cert is part of the source distribution, but could not be loaded!");
        }
        // which probe should we use? First is probably okay...
        $probeindex = 0;
        $preliminaries = $this->udpLoginPreliminaries($probeindex, \core\common\EAP::EAPTYPE_ANY, $clientcert);
        if ($preliminaries !== TRUE) {
            return $preliminaries;
        }
        // we will need a config blob for wpa_supplicant, in a temporary directory
        $temporary = \core\common\Entity::createTemporaryDirectory('test');
        $tmpDir = $temporary['dir'];
        chdir($tmpDir);
        $this->loggerInstance->debug(4, "temp dir: $tmpDir\n");
        file_put_contents($tmpDir . "/client.p12", $clientcert);
        $testresults = ['cert_oddities' => []];
        $runtime_results = $this->executeEapolTest($tmpDir, $probeindex, \core\common\EAP::EAPTYPE_ANY, $outerId, $outerId, "eaplab", FALSE, FALSE);
        $packetflow_orig = $runtime_results['output'];
        $radiusResult = $this->checkRadiusPacketFlow($testresults, $packetflow_orig);
        $negotiatedEapType = FALSE;
        if ($radiusResult != RADIUSTests::RETVAL_IMMEDIATE_REJECT) {
            $negotiatedEapType = $this->wasEapTypeNegotiated($testresults, $packetflow_orig);
            $testresults['negotiated_eaptype'] = $negotiatedEapType;
            $negotiatedTlsVersion = $this->wasModernTlsNegotiated($testresults, $packetflow_orig);
            $testresults['tls_version_eap'] = $negotiatedTlsVersion;
        }
        // now let's look at the server cert+chain, if we got a cert at all
        // that's not the case if we do EAP-pwd or could not negotiate an EAP method at
        // all
        // in that case: no server CA guess possible
        if (!
                ($radiusResult == RADIUSTests::RETVAL_CONVERSATION_REJECT && $negotiatedEapType) || $radiusResult == RADIUSTests::RETVAL_OK
        ) {
            return RADIUSTests::RETVAL_INVALID;
        }
        $bundle = $this->extractIncomingCertsfromEAP($testresults, $tmpDir);
        // we need to check if we know the issuer of the server cert
        // assume we have only one server cert - anything else is a 
        // misconfiguration on the EAP server side
        $previousHighestKnownIssuer = [];
        $currentHighestKnownIssuer = $bundle['SERVERCERT'][0]['full_details']['issuer'];
        $serverName = $bundle['SERVERCERT'][0]['CN'][0];
        // maybe there is an intermediate and the EAP server sent it. If so,
        // go and look at that, going one level higher
        $x509 = new \core\common\X509();
        $allCACerts = array_merge($bundle['INTERMEDIATE_CA'], $bundle['UNTRUSTED_ROOT_INCLUDED']);
        while ($previousHighestKnownIssuer != $currentHighestKnownIssuer) {
            $previousHighestKnownIssuer = $currentHighestKnownIssuer;
            foreach ($allCACerts as $oneCACert) {
                $certDetails = $x509->processCertificate($oneCACert);
                if ($certDetails['full_details']['subject'] == $previousHighestKnownIssuer) {
                    $currentHighestKnownIssuer = $certDetails['full_details']['issuer'];
                }
                if ($certDetails['full_details']['subject'] == $certDetails['full_details']['issuer']) {
                    // if we see a subject == issuer, then the EAP server even
                    // sent a root certificate. We'll propose that then.
                    return [
                        "NAME" => $serverName,
                        "INTERMEDIATE_CA" => $bundle['INTERMEDIATE_CA'],
                        "HIGHEST_ISSUER" => $currentHighestKnownIssuer,
                        "ROOT_CA" => $certDetails['pem'],
                    ];
                }
            }
        }
        // we now know the "highest" issuer name we got from the EAP 
        // conversation - ideally the name of a root CA we know. Let's look at 
        // our own system store to get a list of all commercial CAs with browser
        // trust, and custom ones we may have configured
        $ourRoots = file_get_contents(\config\ConfAssistant::PATHS['trust-store-custom']);
        $mozillaRoots = file_get_contents(\config\ConfAssistant::PATHS['trust-store-mozilla']);
        $allRoots = $x509->splitCertificate($ourRoots . "\n" . $mozillaRoots);
        foreach ($allRoots as $oneRoot) {
            $processedRoot = $x509->processCertificate($oneRoot);
            if ($processedRoot['full_details']['subject'] == $currentHighestKnownIssuer) {
                return [
            "NAME" => $serverName,
            "INTERMEDIATE_CA" => $bundle['INTERMEDIATE_CA'],
            "HIGHEST_ISSUER" => $currentHighestKnownIssuer,
            "ROOT_CA" => $oneRoot,
        ];
            }
        }
        return [
            "NAME" => $serverName,
            "INTERMEDIATE_CA" => $bundle['INTERMEDIATE_CA'],
            "HIGHEST_ISSUER" => $currentHighestKnownIssuer,
            "ROOT_CA" => NULL,
        ];
    }

    /**
     * The big Guy. This performs an actual login with EAP and records how far 
     * it got and what oddities were observed along the way
     * @param int     $probeindex     the probe we are connecting to (as set in product config)
     * @param array   $eaptype        EAP type to use for connection
     * @param string  $innerUser      inner username to try
     * @param string  $password       password to try
     * @param boolean $opnameCheck    whether or not we check with Operator-Name set
     * @param boolean $frag           whether or not we check with an oversized packet forcing fragmentation
     * @param string  $clientcertdata client certificate credential to try
     * @return int overall return code of the login test
     * @throws Exception
     */
    public function udpLogin($probeindex, $eaptype, $innerUser, $password, $opnameCheck = TRUE, $frag = TRUE, $clientcertdata = NULL) {
        $preliminaries = $this->udpLoginPreliminaries($probeindex, $eaptype, $clientcertdata);
        if ($preliminaries !== TRUE) {
            return $preliminaries;
        }
        // we will need a config blob for wpa_supplicant, in a temporary directory
        $temporary = \core\common\Entity::createTemporaryDirectory('test');
        $tmpDir = $temporary['dir'];
        chdir($tmpDir);
        $this->loggerInstance->debug(4, "temp dir: $tmpDir\n");
        if ($clientcertdata !== NULL) {
            file_put_contents($tmpDir . "/client.p12", $clientcertdata);
        }
        $testresults = [];
        // initialise the sub-array for cleaner parsing
        $testresults['cert_oddities'] = [];
        // execute RADIUS/EAP converation
        $runtime_results = $this->executeEapolTest($tmpDir, $probeindex, $eaptype, $this->outerUsernameForChecks, $innerUser, $password, $opnameCheck, $frag);
        $testresults['time_millisec'] = $runtime_results['time'];
        $packetflow_orig = $runtime_results['output'];
        $radiusResult = $this->checkRadiusPacketFlow($testresults, $packetflow_orig);
        // if the RADIUS conversation was immediately rejected, it is trivially
        // true that no EAP type was negotiated, and that TLS didn't negotiate
        // a version. Don't get excited about that then.
        $negotiatedEapType = FALSE;
        if ($radiusResult != RADIUSTests::RETVAL_IMMEDIATE_REJECT) {
            $negotiatedEapType = $this->wasEapTypeNegotiated($testresults, $packetflow_orig);
            $testresults['negotiated_eaptype'] = $negotiatedEapType;
            $negotiatedTlsVersion = $this->wasModernTlsNegotiated($testresults, $packetflow_orig);
            $testresults['tls_version_eap'] = $negotiatedTlsVersion;
        }
        // now let's look at the server cert+chain, if we got a cert at all
        // that's not the case if we do EAP-pwd or could not negotiate an EAP method at
        // all
        if (
                $eaptype != \core\common\EAP::EAPTYPE_PWD &&
                (($radiusResult == RADIUSTests::RETVAL_CONVERSATION_REJECT && $negotiatedEapType) || $radiusResult == RADIUSTests::RETVAL_OK)
        ) {
            $bundle = $this->extractIncomingCertsfromEAP($testresults, $tmpDir);
// FOR OWN REALMS check:
// 1) does the incoming chain have a root in one of the configured roots
//    if not, this is a signficant configuration error
// return this with one or more of the CERTPROB_ constants (see defs)
// TRUST_ROOT_NOT_REACHED
// TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES
// then check the presented names
// check intermediate ca cert properties
// check trust chain for completeness
// works only for thorough checks, not shallow, so:
            $intermOdditiesCAT = [];
            $verifyResult = 0;

            if ($this->opMode == self::RADIUS_TEST_OPERATION_MODE_THOROUGH && $bundle !== FALSE && !in_array(RADIUSTests::CERTPROB_NO_SERVER_CERT, $testresults['cert_oddities'])) {
                $verifyResult = $this->thoroughChainChecks($testresults, $intermOdditiesCAT, $tmpDir, $bundle["SERVERCERT"], $bundle["INTERMEDIATE_CA"], $bundle["INTERMEDIATE_CRL"]);
                $this->thoroughNameChecks($bundle["SERVERCERT"][0], $testresults);
            }

            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $bundle["INTERMEDIATE_OBSERVED_ODDITIES"] ?? []);
            if (in_array(RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD, $intermOdditiesCAT) && $verifyResult == 3) {
                $key = array_search(RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD, $intermOdditiesCAT);
                $intermOdditiesCAT[$key] = RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN;
            }

            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $intermOdditiesCAT);

// mention trust chain failure only if no expired cert was in the chain; otherwise path validation will trivially fail
            if (in_array(RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD, $testresults['cert_oddities'])) {
                $this->loggerInstance->debug(4, "Deleting trust chain problem report, if present.");
                if (($key = array_search(RADIUSTests::CERTPROB_TRUST_ROOT_NOT_REACHED, $testresults['cert_oddities'])) !== false) {
                    unset($testresults['cert_oddities'][$key]);
                }
                if (($key = array_search(RADIUSTests::CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES, $testresults['cert_oddities'])) !== false) {
                    unset($testresults['cert_oddities'][$key]);
                }
            }
        }
        $this->UDP_reachability_result[$probeindex] = $testresults;
        $this->UDP_reachability_executed = $radiusResult;
        return $radiusResult;
    }

    /**
     * sets the outer identity to use in the checks
     * 
     * @param string $id the outer ID to use
     * @return void
     */
    public function setOuterIdentity($id) {
        $this->outerUsernameForChecks = $id;
    }

    /**
     * pull together all sub tests into a cohesive test result
     * @param int $host index of the probe for which the results are collated
     * @return array
     */
    public function consolidateUdpResult($host) {
        \core\common\Entity::intoThePotatoes();
        $ret = [];
        $serverCert = [];
        $udpResult = $this->UDP_reachability_result[$host];
        if (isset($udpResult['certdata']) && count($udpResult['certdata'])) {
            foreach ($udpResult['certdata'] as $certdata) {
                if ($certdata['type'] != 'server' && $certdata['type'] != 'totally_selfsigned') {
                    continue;
                }
                if (isset($certdata['extensions'])) {
                    foreach ($certdata['extensions'] as $k => $v) {
                        $certdata['extensions'][$k] = iconv('UTF-8', 'UTF-8//IGNORE', $certdata['extensions'][$k]);
                    }
                }
                $serverCert = [
                    'subject' => $this->printDN($certdata['subject']),
                    'issuer' => $this->printDN($certdata['issuer']),
                    'validFrom' => $this->printTm($certdata['validFrom_time_t']),
                    'validTo' => $this->printTm($certdata['validTo_time_t']),
                    'serialNumber' => $certdata['serialNumber'] . sprintf(" (0x%X)", $certdata['serialNumber']),
                    'sha1' => $certdata['sha1'],
                    'extensions' => $certdata['extensions']
                ];
            }
        }
        $ret['server_cert'] = $serverCert;
        $ret['server'] = 0;
        if (isset($udpResult['incoming_server_names'][0])) {
            $ret['server'] = sprintf(_("Connected to %s."), $udpResult['incoming_server_names'][0]);
        }
        $ret['level'] = \core\common\Entity::L_OK;
        $ret['time_millisec'] = sprintf("%d", $udpResult['time_millisec']);
        if (empty($udpResult['cert_oddities'])) {
            $ret['message'] = _("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.");
            \core\common\Entity::outOfThePotatoes();
            return $ret;
        }

        $ret['message'] = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned. Some properties of the connection attempt were sub-optimal; the list is below.");
        $ret['cert_oddities'] = [];
        foreach ($udpResult['cert_oddities'] as $oddity) {
            $o = [];
            $o['code'] = $oddity;
            $o['message'] = isset($this->returnCodes[$oddity]["message"]) && $this->returnCodes[$oddity]["message"] ? $this->returnCodes[$oddity]["message"] : $oddity;
            $o['level'] = $this->returnCodes[$oddity]["severity"];
            $ret['level'] = max($ret['level'], $this->returnCodes[$oddity]["severity"]);
            $ret['cert_oddities'][] = $o;
        }
        \core\common\Entity::outOfThePotatoes();
        return $ret;
    }

}
