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

require_once(dirname(dirname(__DIR__)) . "/config/_config.php");

/**
 * Test suite to verify that an EAP setup is actually working as advertised in
 * the real world. Can only be used if CONFIG_DIAGNOSTICS['RADIUSTESTS'] is configured.
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
     * The variables below maintain state of the result of previous checks.
     * 
     */
    private $UDP_reachability_executed;
    private $errorlist;

    /**
     * This private variable contains the realm to be checked. Is filled in the
     * class constructor.
     * 
     * @var string
     */
    private $realm;
    private $outerUsernameForChecks;
    private $expectedCABundle;
    private $expectedServerNames;
    
    /**
     * the list of EAP types which the IdP allegedly supports.
     * 
     * @var array
     */
    private $supportedEapTypes;
    private $opMode;
    public $UDP_reachability_result;

    const RADIUS_TEST_OPERATION_MODE_SHALLOW = 1;
    const RADIUS_TEST_OPERATION_MODE_THOROUGH = 2;

    /**
     * Constructor for the EAPTests class. The single mandatory parameter is the
     * realm for which the tests are to be carried out.
     * 
     * @param string $realm
     * @param string $outerUsernameForChecks
     * @param array $supportedEapTypes (array of integer representations of EAP types)
     * @param array $expectedServerNames (array of strings)
     * @param array $expectedCABundle (array of PEM blocks)
     */
    public function __construct($realm, $outerUsernameForChecks, $supportedEapTypes = [], $expectedServerNames = [], $expectedCABundle = []) {
        parent::__construct();
        $oldlocale = $this->languageInstance->setTextDomain('diagnostics');

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
                Throw new Exception("Thorough checks for an EAP type needing CAs were requested, but the required parameters were not given.");
            } else {
                $this->opMode = self::RADIUS_TEST_OPERATION_MODE_THOROUGH;
            }
        }

        if ($serverNeeded) {
            if (count($this->expectedServerNames) == 0) {
                Throw new Exception("Thorough checks for an EAP type needing server names were requested, but the required parameter was not given.");
            } else {
                $this->opMode = self::RADIUS_TEST_OPERATION_MODE_THOROUGH;
            }
        }

        $this->loggerInstance->debug(4, "RADIUSTests is in opMode " . $this->opMode . ", parameters were: $realm, $outerUsernameForChecks, " . print_r($supportedEapTypes, true));
        $this->loggerInstance->debug(4, print_r($expectedServerNames, true));
        $this->loggerInstance->debug(4, print_r($expectedCABundle, true));

        $this->UDP_reachability_result = [];
        $this->errorlist = [];
        $this->languageInstance->setTextDomain($oldlocale);
    }

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

    private function printTm($time) {
        return(gmdate(\DateTime::COOKIE, $time));
    }

    /**
     * This function parses a X.509 server cert and checks if it finds client device incompatibilities
     * 
     * @param array $servercert the properties of the certificate as returned by processCertificate(), 
     *    $servercert is modified, if CRL is defied, it is downloaded and added to the array
     *    incoming_server_names, sAN_DNS and CN array values are also defined
     * @return array of oddities; the array is empty if everything is fine
     */
    private function propertyCheckServercert(&$servercert) {
        $this->loggerInstance->debug(5, "SERVER CERT IS: " . print_r($servercert, TRUE));
// we share the same checks as for CAs when it comes to signature algorithm and basicconstraints
// so call that function and memorise the outcome
        $returnarray = $this->propertyCheckIntermediate($servercert, TRUE);

        if (!isset($servercert['full_details']['extensions'])) {
            $returnarray[] = RADIUSTests::CERTPROB_NO_TLS_WEBSERVER_OID;
            $returnarray[] = RADIUSTests::CERTPROB_NO_CDP_HTTP;
        } else {
            if (!isset($servercert['full_details']['extensions']['extendedKeyUsage']) || !preg_match("/TLS Web Server Authentication/", $servercert['full_details']['extensions']['extendedKeyUsage'])) {
                $returnarray[] = RADIUSTests::CERTPROB_NO_TLS_WEBSERVER_OID;
            }
        }
// check for wildcards
        $commonName = [];
        if (isset($servercert['full_details']['subject']['CN'])) {
            if (is_array($servercert['full_details']['subject']['CN'])) {
                $commonName = $servercert['full_details']['subject']['CN'];
            } else {
                $commonName = [$servercert['full_details']['subject']['CN']];
            }
        }

        $sANlist = [];
        if (isset($servercert['full_details']['extensions']) && isset($servercert['full_details']['extensions']['subjectAltName'])) {
            $sANlist = explode(", ", $servercert['full_details']['extensions']['subjectAltName']);
        }

        $sANdns = [];
        foreach ($sANlist as $subjectAltName) {
            if (preg_match("/^DNS:/", $subjectAltName)) {
                $sANdns[] = substr($subjectAltName, 4);
            }
        }

        $allnames = array_unique(array_merge($commonName, $sANdns));

        if (preg_match("/\*/", implode($allnames))) {
            $returnarray[] = RADIUSTests::CERTPROB_WILDCARD_IN_NAME;
        }

// is there more than one CN? None or one is okay, more is asking for trouble.
        if (count($commonName) > 1) {
            $returnarray[] = RADIUSTests::CERTPROB_MULTIPLE_CN;
        }

// check for real hostname
        foreach ($allnames as $onename) {
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
     * @param array $intermediateCa the properties of the certificate as returned by processCertificate()
     * @param boolean complain_about_cdp_existence: for intermediates, not having a CDP is less of an issue than for servers. Set the REMARK (..._INTERMEDIATE) flag if not complaining; and _SERVER if so
     * @return array of oddities; the array is empty if everything is fine
     */
    private function propertyCheckIntermediate(&$intermediateCa, $serverCert = FALSE) {
        $returnarray = [];
        if (preg_match("/md5/i", $intermediateCa['full_details']['signatureTypeSN'])) {
            $returnarray[] = RADIUSTests::CERTPROB_MD5_SIGNATURE;
        }
        if (preg_match("/sha1/i", $intermediateCa['full_details']['signatureTypeSN'])) {
            $returnarray[] = RADIUSTests::CERTPROB_SHA1_SIGNATURE;
        }
        $this->loggerInstance->debug(4, "CERT IS: " . print_r($intermediateCa, TRUE));
        if ($intermediateCa['basicconstraints_set'] == 0) {
            $returnarray[] = RADIUSTests::CERTPROB_NO_BASICCONSTRAINTS;
        }
        if ($intermediateCa['full_details']['public_key_length'] < 1024) {
            $returnarray[] = RADIUSTests::CERTPROB_LOW_KEY_LENGTH;
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
     * @param string $probeindex refers to the specific UDP-host in the config that should be checked
     * @param boolean $opnameCheck should we check choking on Operator-Name?
     * @param boolean $frag should we cause UDP fragmentation? (Warning: makes use of Operator-Name!)
     * @return int returncode
     */
    public function UDP_reachability($probeindex, $opnameCheck = TRUE, $frag = TRUE) {
        // for EAP-TLS to be a viable option, we need to pass a random client cert to make eapol_test happy
        // the following PEM data is one of the SENSE EAPLab client certs (not secret at all)
        $clientcert = file_get_contents(dirname(__FILE__) . "/clientcert.p12");
        // if we are in thorough opMode, use our knowledge for a more clever check
        // otherwise guess
        if ($this->opMode == self::RADIUS_TEST_OPERATION_MODE_THOROUGH) {
            return $this->UDP_login($probeindex, $this->supportedEapTypes[0]->getArrayRep(), $this->outerUsernameForChecks, 'eaplab', $opnameCheck, $frag, $clientcert);
        }
        return $this->UDP_login($probeindex, \core\common\EAP::EAPTYPE_ANY, "cat-connectivity-test@" . $this->realm, 'eaplab', $opnameCheck, $frag, $clientcert);
    }

    /**
     * There is a CRL Distribution Point URL in the certificate. So download the
     * CRL and attach it to the cert structure so that we can later find out if
     * the cert was revoked
     * @param array $cert by-reference: the cert data we are writing into
     * @return int result code whether we were successful in retrieving the CRL
     */
    private function addCrltoCert(&$cert) {
        $crlUrl = [];
        $returnresult = 0;
        if (!isset($cert['full_details']['extensions']['crlDistributionPoints'])) {
            $returnresult = RADIUSTests::CERTPROB_NO_CDP;
        } else if (!preg_match("/^.*URI\:(http)(.*)$/", str_replace(["\r", "\n"], ' ', $cert['full_details']['extensions']['crlDistributionPoints']), $crlUrl)) {
            $returnresult = RADIUSTests::CERTPROB_NO_CDP_HTTP;
        } else { // first and second sub-match is the full URL... check it
            $crlcontent = \core\common\OutsideComm::downloadFile(trim($crlUrl[1] . $crlUrl[2]));
            if ($crlcontent === FALSE) {
                $returnresult = RADIUSTests::CERTPROB_NO_CRL_AT_CDP_URL;
            }
            $crlBegin = strpos($crlcontent, "-----BEGIN X509 CRL-----");
            if ($crlBegin === FALSE) {
                $pem = chunk_split(base64_encode($crlcontent), 64, "\n");
                $crlcontent = "-----BEGIN X509 CRL-----\n" . $pem . "-----END X509 CRL-----\n";
            }
            $cert['CRL'] = [];
            $cert['CRL'][] = $crlcontent;
        }
        return $returnresult;
    }

    /**
     * We don't want to write passwords of the live login test to our logs. Filter them out
     * @param string $stringToRedact what should be redacted
     * @param array $inputarray array of strings (outputs of eapol_test command)
     * @return string[] the output of eapol_test with the password redacted
     */
    private function redact($stringToRedact, $inputarray) {
        $temparray = preg_replace("/^.*$stringToRedact.*$/", "LINE CONTAINING PASSWORD REDACTED", $inputarray);
        $hex = bin2hex($stringToRedact);
        $spaced = "";
        for ($i = 1; $i < strlen($hex); $i++) {
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
     * @param array $inputarray array of strings (outputs of eapol_test command)
     * @param int $desiredCheck which test should be run (see constants above)
     * @return boolean returns TRUE if ETLR Reject logic was detected; FALSE if not
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
                default:
                    throw new Exception("This lineparse test does not exist.");
            }
        }
        return FALSE;
    }

    /**
     * 
     * @param array $eaptype array representation of the EAP type
     * @param string $inner inner username
     * @param string $outer outer username
     * @param string $password the password
     * @return string[] [0] is the actual config for wpa_supplicant, [1] is a redacted version for logs
     */
    private function wpaSupplicantConfig(array $eaptype, string $inner, string $outer, string $password) {
        $eapText = \core\common\EAP::eapDisplayName($eaptype);
        $config = '
network={
  ssid="' . CONFIG['APPEARANCE']['productname'] . ' testing"
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

    private function packetCountEvaluation(&$testresults, $packetcount) {
        $reqs = $packetcount[1] ?? 0;
        $accepts = $packetcount[2] ?? 0;
        $rejects = $packetcount[3]?? 0;
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
     * @param int $probeindex number of the probe to check against
     * @param boolean $opName include Operator-Name in request?
     * @param boolean $frag make request so large that fragmentation is needed?
     * @return string the command-line for eapol_test
     */
    private function eapolTestConfig($probeindex, $opName, $frag) {
        $cmdline = CONFIG_DIAGNOSTICS['PATHS']['eapol_test'] .
                " -a " . CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$probeindex]['ip'] .
                " -s " . CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$probeindex]['secret'] .
                " -o serverchain.pem" .
                " -c ./udp_login_test.conf" .
                " -M 22:44:66:CA:20:" . sprintf("%02d", $probeindex) . " " .
                " -t " . CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$probeindex]['timeout'] . " ";
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

    private function thoroughChainChecks(&$testresults, &$intermOdditiesCAT, $tmpDir, $servercert, $eapIntermediates, $eapIntermediateCRLs) {

        // collect CA certificates, both the incoming EAP chain and from CAT config
        // Write the root CAs into a trusted root CA dir
        // and intermediate and first server cert into a PEM file
        // for later chain validation

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
            if ($decoded === FALSE) {
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
        system(CONFIG_DIAGNOSTICS['PATHS']['c_rehash'] . " $tmpDir/root-ca-eaponly/ > /dev/null");
        system(CONFIG_DIAGNOSTICS['PATHS']['c_rehash'] . " $tmpDir/root-ca-allcerts/ > /dev/null");

// ... and run the verification test
        $verifyResultEaponly = [];
        $verifyResultAllcerts = [];
// the error log will complain if we run this test against an empty file of certs
// so test if there's something PEMy in the file at all
        if (filesize("$tmpDir/incomingserver.pem") > 10) {
            exec(CONFIG['PATHS']['openssl'] . " verify $checkstring -CApath $tmpDir/root-ca-eaponly/ -purpose any $tmpDir/incomingserver.pem", $verifyResultEaponly);
            $this->loggerInstance->debug(4, CONFIG['PATHS']['openssl'] . " verify $checkstring -CApath $tmpDir/root-ca-eaponly/ -purpose any $tmpDir/incomingserver.pem\n");
            $this->loggerInstance->debug(4, "Chain verify pass 1: " . print_r($verifyResultEaponly, TRUE) . "\n");
            exec(CONFIG['PATHS']['openssl'] . " verify $checkstring -CApath $tmpDir/root-ca-allcerts/ -purpose any $tmpDir/incomingserver.pem", $verifyResultAllcerts);
            $this->loggerInstance->debug(4, CONFIG['PATHS']['openssl'] . " verify $checkstring -CApath $tmpDir/root-ca-allcerts/ -purpose any $tmpDir/incomingserver.pem\n");
            $this->loggerInstance->debug(4, "Chain verify pass 2: " . print_r($verifyResultAllcerts, TRUE) . "\n");
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

    private function thoroughNameChecks($servercert, &$testresults) {
        // check the incoming hostname (both Subject:CN and subjectAltName:DNS
        // against what is configured in the profile; it's a significant error
        // if there is no match!
        // FAIL if none of the configured names show up in the server cert
        // WARN if the configured name is only in either CN or sAN:DNS
        // Strategy for checks: we are TOTALLY happy if any one of the
        // configured names shows up in both the CN and a sAN
        // This is the primary check.
        // If that was not the case, we are PARTIALLY happy if any one of
        // the configured names was in either of the CN or sAN lists.
        // we are UNHAPPY if no names match!

        $happiness = "UNHAPPY";
        foreach ($this->expectedServerNames as $expectedName) {
            $this->loggerInstance->debug(4, "Managing expectations for $expectedName: " . print_r($servercert['CN'], TRUE) . print_r($servercert['sAN_DNS'], TRUE));
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

    private function executeEapolTest($tmpDir, $probeindex, $eaptype, $innerUser, $password, $opnameCheck, $frag) {
        $finalInner = $innerUser;
        $finalOuter = $this->outerUsernameForChecks;

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
        $this->loggerInstance->debug(5, print_r($this->redact($password, $pflow), TRUE));
        return [
            "time" => ($time_stop - $time_start) * 1000,
            "output" => $pflow,
        ];
    }

    /**
     * The big Guy. This performs an actual login with EAP and records how far 
     * it got and what oddities were observed along the way
     * @param int $probeindex the probe we are connecting to (as set in product config)
     * @param array $eaptype EAP type to use for connection
     * @param string $innerUser inner username to try
     * @param string $password password to try
     * @param boolean $opnameCheck whether or not we check with Operator-Name set
     * @param boolean $frag whether or not we check with an oversized packet forcing fragmentation
     * @param string $clientcertdata client certificate credential to try
     * @return int overall return code of the login test
     * @throws Exception
     */
    public function UDP_login($probeindex, $eaptype, $innerUser, $password, $opnameCheck = TRUE, $frag = TRUE, $clientcertdata = NULL) {

        /** preliminaries */
        $eapText = \core\common\EAP::eapDisplayName($eaptype);
        // no host to send probes to? Nothing to do then
        if (!isset(CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$probeindex])) {
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
        // we will need a config blob for wpa_supplicant, in a temporary directory
        $temporary = $this->createTemporaryDirectory('test');
        $tmpDir = $temporary['dir'];
        chdir($tmpDir);
        $this->loggerInstance->debug(4, "temp dir: $tmpDir\n");
        if ($clientcertdata !== NULL) {
            file_put_contents($tmpDir . "/client.p12", $clientcertdata);
        }

        /** execute RADIUS/EAP converation */
        $testresults = [];
        $runtime_results = $this->executeEapolTest($tmpDir, $probeindex, $eaptype, $innerUser, $password, $opnameCheck, $frag);

        $testresults['time_millisec'] = $runtime_results['time'];
        $packetflow_orig = $runtime_results['output'];

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
        $this->loggerInstance->debug(5, "Packetflow: " . print_r($packetflow, TRUE));
        $packetcount = array_count_values($packetflow);
        $testresults['packetcount'] = $packetcount;
        $testresults['packetflow'] = $packetflow;

// calculate packet counts and see what the overall flow was
        $finalretval = $this->packetCountEvaluation($testresults, $packetcount);

// only to make sure we've defined this in all code paths
// not setting it has no real-world effect, but Scrutinizer mocks
        $ackedmethod = FALSE;
        $testresults['cert_oddities'] = [];
        if ($finalretval == RADIUSTests::RETVAL_CONVERSATION_REJECT) {
            $ackedmethod = $this->checkLineparse($packetflow_orig, self::LINEPARSE_EAPACK);
            if (!$ackedmethod) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_NO_COMMON_EAP_METHOD;
            }
        }


        // now let's look at the server cert+chain, if we got a cert at all
        // that's not the case if we do EAP-pwd or could not negotiate an EAP method at
        // all
        if (
                $eaptype != \core\common\EAP::EAPTYPE_PWD &&
                (($finalretval == RADIUSTests::RETVAL_CONVERSATION_REJECT && $ackedmethod) || $finalretval == RADIUSTests::RETVAL_OK)
        ) {


// ALWAYS check: 
// 1) it is unnecessary to include the root CA itself (adding it has
//    detrimental effects on performance)
// 2) TLS Web Server OID presence (Windows OSes need that)
// 3) MD5 signature algorithm (iOS barks if so)
// 4) CDP URL (Windows Phone 8 barks if not present)
// 5) there should be exactly one server cert in the chain
// FOR OWN REALMS check:
// 1) does the incoming chain have a root in one of the configured roots
//    if not, this is a signficant configuration error
// return this with one or more of the CERTPROB_ constants (see defs)
// TRUST_ROOT_NOT_REACHED
// TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES
// then check the presented names
            $x509 = new \core\common\X509();
// $eap_certarray holds all certs received in EAP conversation
            $eapCertarray = $x509->splitCertificate(fread(fopen($tmpDir . "/serverchain.pem", "r"), "1000000"));
// we want no root cert, and exactly one server cert
            $numberRoot = 0;
            $numberServer = 0;
            $eapIntermediates = [];
            $eapIntermediateCRLs = [];
            $servercert = FALSE;
            $totallySelfsigned = FALSE;
            $intermOdditiesEAP = [];

            $testresults['certdata'] = [];

            $serverFile = fopen($tmpDir . "/incomingserver.pem", "w");
            foreach ($eapCertarray as $certPem) {
                $cert = $x509->processCertificate($certPem);
                if ($cert == FALSE) {
                    continue;
                }
// consider the certificate a server cert 
// a) if it is not a CA and is not a self-signed root
// b) if it is a CA, and self-signed, and it is the only cert in
//    the incoming cert chain
//    (meaning the self-signed is itself the server cert)
                if (($cert['ca'] == 0 && $cert['root'] != 1) || ($cert['ca'] == 1 && $cert['root'] == 1 && count($eapCertarray) == 1)) {
                    if ($cert['ca'] == 1 && $cert['root'] == 1 && count($eapCertarray) == 1) {
                        $totallySelfsigned = TRUE;
                        $cert['full_details']['type'] = 'totally_selfsigned';
                    }
                    $numberServer++;

                    $servercert = $cert;
                    if ($numberServer == 1) {
                        fwrite($serverFile, $certPem . "\n");
                        $this->loggerInstance->debug(4, "This is the (first) server certificate, with CRL content if applicable: " . print_r($servercert, true));
                    }
                } else
                if ($cert['root'] == 1) {
                    $numberRoot++;
// do not save the root CA, it serves no purpose
// chain checks need to be against the UPLOADED CA of the
// IdP/profile, not against an EAP-discovered CA
                } else {
                    $intermOdditiesEAP = array_merge($intermOdditiesEAP, $this->propertyCheckIntermediate($cert));
                    $eapIntermediates[] = $certPem;

                    if (isset($cert['CRL']) && isset($cert['CRL'][0])) {
                        $eapIntermediateCRLs[] = $cert['CRL'][0];
                    }
                }
                $testresults['certdata'][] = $cert['full_details'];
            }
            fclose($serverFile);
            if ($numberRoot > 0 && !$totallySelfsigned) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_ROOT_INCLUDED;
            }
            if ($numberServer > 1) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_TOO_MANY_SERVER_CERTS;
            }
            if ($numberServer == 0) {
                $testresults['cert_oddities'][] = RADIUSTests::CERTPROB_NO_SERVER_CERT;
            }
// check server cert properties
            if ($numberServer > 0) {
                if ($servercert === FALSE) {
                    throw new Exception("We incremented the numberServer counter and added a certificate. Now it's gone?!");
                }
                $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $this->propertyCheckServercert($servercert));
                $testresults['incoming_server_names'] = $servercert['incoming_server_names'];
            }

// check intermediate ca cert properties
// check trust chain for completeness
// works only for thorough checks, not shallow, so:
            $intermOdditiesCAT = [];
            $verifyResult = 0;

            if ($this->opMode == self::RADIUS_TEST_OPERATION_MODE_THOROUGH) {
                $verifyResult = $this->thoroughChainChecks($testresults, $intermOdditiesCAT, $tmpDir, $servercert, $eapIntermediates, $eapIntermediateCRLs);
                $this->thoroughNameChecks($servercert, $testresults);
            }

            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $intermOdditiesEAP);
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
        $this->loggerInstance->debug(4, "UDP_LOGIN\n");
        $this->loggerInstance->debug(4, $testresults);
        $this->loggerInstance->debug(4, "\nEND\n");
        $this->UDP_reachability_result[$probeindex] = $testresults;
        $this->UDP_reachability_executed = $finalretval;
        return $finalretval;
    }

    public function consolidateUdpResult($host) {
        $ret = [];
        $serverCert = [];
        $udpResult = $this->UDP_reachability_result[$host];
        if (isset($udpResult['certdata']) && count($udpResult['certdata'])) {
            foreach ($udpResult['certdata'] as $certdata) {
                if ($certdata['type'] != 'server' && $certdata['type'] != 'totally_selfsigned') {
                    continue;
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

        return $ret;
    }

}
