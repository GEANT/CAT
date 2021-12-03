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

namespace core\diag;

use \Exception;

/**
 * Test suite to verify that a given NAI realm has NAPTR records according to
 * consortium-agreed criteria
 * Can only be used if \config\Diagnostics::RADIUSTESTS is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RFC6614Tests extends AbstractTest
{

    /**
     * dictionary of translatable texts around the certificates we check
     * 
     * @var array
     */
    private $TLS_certkeys = [];

    /**
     * list of IP addresses which are candidates for dynamic discovery targets
     * 
     * @var array
     */
    private $candidateIPs;

    /**
     * the hostname which should show up in the certificate when establishing
     * a connection to the RADIUS/TLS server (hostname is an intermediary result
     * of the RFC7585 DNS resolution algorithm, in SRV response)
     * 
     * @var string
     */
    private $expectedName;

    /**
     * associative array holding the server-side cert test results for a given IP (IP is the key)
     * 
     * @var array
     */
    public $TLS_CA_checks_result;

    /**
     * associative array holding the client-side cert test results for a given IP (IP is the key)
     * 
     * @var array
     */
    public $TLS_clients_checks_result;

    /**
     * which consortium are we testing against?
     * 
     * @var string
     */
    private $consortium;
    /**
     * Sets up the instance for testing of a number of candidate IPs
     * 
     * @param array  $listOfIPs    candidates to test
     * @param string $expectedName expected server name to test against
     * @param string $consortium   which consortium to test against
     */
    public function __construct($listOfIPs, $expectedName, $consortium = "eduroam")
    {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->TLS_certkeys = [
            'eduPKI' => _('eduPKI'),
            'NCU' => _('Nicolaus Copernicus University'),
            'ACCREDITED' => _('accredited'),
            'NONACCREDITED' => _('non-accredited'),
            'CORRECT' => _('correct certificate'),
            'WRONGPOLICY' => _('certificate with wrong policy OID'),
            'EXPIRED' => _('expired certificate'),
            'REVOKED' => _('revoked certificate'),
            'PASS' => _('pass'),
            'FAIL' => _('fail'),
            'non-eduPKI-accredited' => _("eduroam-accredited CA (now only for tests)"),
        ];
        $this->TLS_CA_checks_result = [];
        $this->TLS_clients_checks_result = [];

        $this->candidateIPs = $listOfIPs;
        $this->expectedName = $expectedName;

        switch ($consortium) {
            case "eduroam":
                // fall-through intended
            case "openroaming":
                $this->consortium = $consortium;
                break;
            default:
                throw new Exception("Certificate checks against unknown consortium identifier requested!");
        }

        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * run all checks on all candidates
     * 
     * @return void
     */
    public function allChecks()
    {
        foreach ($this->candidateIPs as $oneIP) {
            $this->cApathCheck($oneIP);
            $this->tlsClientSideCheck($oneIP);
        }
    }

    /**
     * This function executes openssl s_clientends command to check if a server accepts a CA
     * 
     * @param string $host IP:port
     * @return int returncode
     */
    public function cApathCheck(string $host)
    {
        if (!isset($this->TLS_CA_checks_result[$host])) {
            $this->TLS_CA_checks_result[$host] = [];
        }
        $opensslbabble = $this->execOpensslClient($host, '', $this->TLS_CA_checks_result[$host]);
        $overallRetval = $this->opensslCAResult($host, $opensslbabble);
        if ($overallRetval == AbstractTest::RETVAL_OK) {
            $this->checkServerName($host);
        }
        return $overallRetval;
    }

    /**
     * checks whether the received servername matches the expected server name
     * 
     * @param string $host IP:port
     * @return bool yes or no
     */
    private function checkServerName($host)
    {
        // it could match CN or sAN:DNS, we don't care which
        if (isset($this->TLS_CA_checks_result[$host]['certdata']['subject'])) {
            $this->loggerInstance->debug(4, "Checking expected server name " . $this->expectedName . " against Subject: ");
            $this->loggerInstance->debug(4, $this->TLS_CA_checks_result[$host]['certdata']['subject']);
            // we are checking against accidental misconfig, not attacks, so loosely checking against end of string is appropriate
            if (preg_match("/CN=" . $this->expectedName . "/", $this->TLS_CA_checks_result[$host]['certdata']['subject']) === 1) {
                return TRUE;
            }
        }
        if (isset($this->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'])) {
            $this->loggerInstance->debug(4, "Checking expected server name " . $this->expectedName . " against sANs: ");
            $this->loggerInstance->debug(4, $this->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname']);
            $testNames = $this->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'];
            if (!is_array($testNames)) {
                $testNames = [$testNames];
            }
            foreach ($testNames as $oneName) {
                if (preg_match("/" . $this->expectedName . "/", $oneName) === 1) {
                    return TRUE;
                }
            }
        }
        $this->loggerInstance->debug(3, "Tried to check expected server name " . $this->expectedName . " but neither CN nor sANs matched.");

        $this->TLS_CA_checks_result[$host]['cert_oddity'] = RADIUSTests::CERTPROB_DYN_SERVER_NAME_MISMATCH;
        return FALSE;
    }

    /**
     * This function executes openssl s_client command to check if a server accepts a client certificate
     * 
     * @param string $host       IP:port
     * @return int returncode
     */
    public function tlsClientSideCheck(string $host)
    {
        $res = RADIUSTests::RETVAL_OK;
        if (!is_array(\config\Diagnostics::RADIUSTESTS['TLS-clientcerts']) || count(\config\Diagnostics::RADIUSTESTS['TLS-clientcerts']) == 0) {
            return RADIUSTests::RETVAL_SKIPPED;
        }
        if (preg_match("/\[/", $host)) {
            return RADIUSTests::RETVAL_INVALID;
        }
        foreach (\config\Diagnostics::RADIUSTESTS['TLS-clientcerts'] as $type => $tlsclient) {
            $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['from'] = $type;
            $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['status'] = $tlsclient['status'];
            $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['message'] = $this->TLS_certkeys[$tlsclient['status']];
            $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['issuer'] = $tlsclient['issuerCA'];
            foreach ($tlsclient['certificates'] as $k => $cert) {
                $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['status'] = $cert['status'];
                $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['message'] = $this->TLS_certkeys[$cert['status']];
                $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['expected'] = $cert['expected'];
                $add = ' -cert ' . ROOT . '/config/cli-certs/' . $cert['public'] . ' -key ' . ROOT . '/config/cli-certs/' . $cert['private'];
                if (!isset($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k])) {
                    $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k] = [];
                }
                $opensslbabble = $this->execOpensslClient($host, $add, $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]);
                $res = $this->opensslClientsResult($host, $opensslbabble, $this->TLS_clients_checks_result, $type, $k);
                if ($cert['expected'] == 'PASS') {
                    if (!$this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['connected']) {
                        if (($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) {
                            $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['returncode'] = RADIUSTests::CERTPROB_NOT_ACCEPTED;
                            $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['finalerror'] = 1;
                            break;
                        }
                    }
                } else {
                    if ($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['connected']) {
                        $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['returncode'] = RADIUSTests::CERTPROB_WRONGLY_ACCEPTED;
                    }

                    if (($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['reason'] == RADIUSTests::CERTPROB_UNKNOWN_CA) && ($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) {
                        $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['finalerror'] = 1;
                        break;
                    }
                }
            }
        }
        return $res;
    }

    /**
     * This function executes openssl s_client command
     * 
     * @param string $host        IP address
     * @param string $consortium  which consortium to check against
     * @param string $arg         arguments to add to the openssl command 
     * @param array  $testresults by-reference: the testresults array we are writing into
     * @return array result of openssl s_client ...
     */
    private function execOpensslClient($host, $arg, &$testresults)
    {
// we got the IP address either from DNS (guaranteeing well-formedness)
// or from filter_var'ed user input. So it is always safe as an argument
// but code analysers want this more explicit, so here is this extra
// call to escapeshellarg()
        $escapedHost = escapeshellarg($host);
        $this->loggerInstance->debug(4, \config\Master::PATHS['openssl'] . " s_client -connect " . $escapedHost . " -tls1 -CApath " . ROOT . "/config/ca-certs/$this->consortium/ $arg 2>&1\n");
        $time_start = microtime(true);
        $opensslbabble = [];
        $result = 999; // likely to become zero by openssl; don't want to initialise to zero, could cover up exec failures
        exec(\config\Master::PATHS['openssl'] . " s_client -connect " . $escapedHost . " -no_ssl3 -CApath " . ROOT . "/config/ca-certs/$this->consortium/ $arg 2>&1", $opensslbabble, $result);
        $time_stop = microtime(true);
        $testresults['time_millisec'] = floor(($time_stop - $time_start) * 1000);
        $testresults['returncode'] = $result;
        return $opensslbabble;
    }

    /**
     * This function parses openssl s_client result
     * 
     * @param string $host          IP:port
     * @param array  $opensslbabble openssl command output
     * @return int return code
     */
    private function opensslCAResult($host, $opensslbabble)
    {
        if (preg_match('/connect: Connection refused/', implode($opensslbabble))) {
            $this->TLS_CA_checks_result[$host]['status'] = RADIUSTests::RETVAL_CONNECTION_REFUSED;
            return RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/no peer certificate available/', implode($opensslbabble))) {
            $this->TLS_CA_checks_result[$host]['status'] = RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM;
            return RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
            $this->TLS_CA_checks_result[$host]['cert_oddity'] = RADIUSTests::CERTPROB_UNKNOWN_CA;
            $this->TLS_CA_checks_result[$host]['status'] = RADIUSTests::RETVAL_INVALID;
            return RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/Cipher is (NONE)/', implode($opensslbabble))) {
            $this->TLS_CA_checks_result[$host]['status'] = RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM;
            return RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/verify return:1/', implode($opensslbabble))) {
            $this->TLS_CA_checks_result[$host]['status'] = RADIUSTests::RETVAL_OK;
            $servercertStage1 = implode("\n", $opensslbabble);
            $servercert = preg_replace("/.*(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\n).*/s", "$1", $servercertStage1);
            $data = openssl_x509_parse($servercert);
            $this->TLS_CA_checks_result[$host]['certdata']['subject'] = $data['name'];
            $this->TLS_CA_checks_result[$host]['certdata']['issuer'] = $this->getCertificateIssuer($data);
            if (($altname = $this->getCertificatePropertyField($data, 'subjectAltName'))) {
                $this->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'] = $altname;
            }

            $oids = $this->propertyCheckPolicy($data);
            if (!empty($oids)) {
                foreach ($oids as $resultArrayKey => $o) {
                    $this->TLS_CA_checks_result[$host]['certdata']['extensions']['policyoid'][] = " $o ($resultArrayKey)";
                }
            }
            if (($crl = $this->getCertificatePropertyField($data, 'crlDistributionPoints'))) {
                $this->TLS_CA_checks_result[$host]['certdata']['extensions']['crlDistributionPoint'] = $crl;
            }
            if (($ocsp = $this->getCertificatePropertyField($data, 'authorityInfoAccess'))) {
                $this->TLS_CA_checks_result[$host]['certdata']['extensions']['authorityInfoAccess'] = $ocsp;
            }
            return RADIUSTests::RETVAL_OK;
        }
        // we should have been caught somewhere along the way. If we got here,
        // something seriously unexpected happened. Let's talk about it.
        return RADIUSTests::RETVAL_INVALID;
    }

    /**
     * This function parses openssl s_client result
     * 
     * @param string $host           IP:port
     * @param array  $opensslbabble  openssl command output
     * @param array  $testresults    by-reference: pointer to results array we write into
     * @param string $type           type of certificate
     * @param int    $resultArrayKey results array key
     * @return int return code
     */
    private function opensslClientsResult($host, $opensslbabble, &$testresults, $type = '', $resultArrayKey = 0)
    {
        \core\common\Entity::intoThePotatoes();
        $res = RADIUSTests::RETVAL_OK;
        $ret = $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['returncode'];
        $output = implode($opensslbabble);
        if ($ret == 0) {
            $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['connected'] = 1;
        } else {
            $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['connected'] = 0;
            if (preg_match('/connect: Connection refused/', implode($opensslbabble))) {
                $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['returncode'] = RADIUSTests::RETVAL_CONNECTION_REFUSED;
                $resComment = _("No TLS connection established: Connection refused");
            } elseif (preg_match('/sslv3 alert certificate expired/', $output)) {
                $resComment = _("certificate expired");
            } elseif (preg_match('/sslv3 alert certificate revoked/', $output)) {
                $resComment = _("certificate was revoked");
            } elseif (preg_match('/SSL alert number 46/', $output)) {
                $resComment = _("bad policy");
            } elseif (preg_match('/tlsv1 alert unknown ca/', $output)) {
                $resComment = _("unknown authority");
                $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['reason'] = RADIUSTests::CERTPROB_UNKNOWN_CA;
            } else {
                $resComment = _("unknown authority or no certificate policy or another problem");
            }
            $testresults[$host]['ca'][$type]['certificate'][$resultArrayKey]['resultcomment'] = $resComment;
        }
        \core\common\Entity::outOfThePotatoes();
        return $res;
    }

    /**
     * This function parses a X.509 cert and returns all certificatePolicies OIDs
     * 
     * @param array $cert (returned from openssl_x509_parse) 
     * @return array of OIDs
     */
    private function propertyCheckPolicy($cert)
    {
        $oids = [];
        if ($cert['extensions']['certificatePolicies']) {
            foreach (\config\Diagnostics::RADIUSTESTS['TLS-acceptableOIDs'] as $key => $oid) {
                if (preg_match("/Policy: $oid/", $cert['extensions']['certificatePolicies'])) {
                    $oids[$key] = $oid;
                }
            }
        }
        return $oids;
    }

    /**
     * This function parses a X.509 cert and returns the value of $field
     * 
     * @param array $cert (returned from openssl_x509_parse) 
     * @return string value of the issuer field or ''
     */
    private function getCertificateIssuer($cert)
    {
        $issuer = '';
        foreach ($cert['issuer'] as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    $issuer .= "/$key=$v";
                }
            } else {
                $issuer .= "/$key=$val";
            }
        }
        return $issuer;
    }

    /**
     * This function parses a X.509 cert and returns the value of $field
     * 
     * @param array  $cert  (returned from openssl_x509_parse) 
     * @param string $field the field to search for
     * @return string value of the extention named $field or ''
     */
    private function getCertificatePropertyField($cert, $field)
    {
        if ($cert['extensions'][$field]) {
            return $cert['extensions'][$field];
        }
        return '';
    }
}