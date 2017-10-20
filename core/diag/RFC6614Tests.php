<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace core\diag;

use \Exception;

require_once(dirname(dirname(__DIR__)) . "/config/_config.php");

/**
 * Test suite to verify that a given NAI realm has NAPTR records according to
 * consortium-agreed criteria
 * Can only be used if CONFIG_DIAGNOSTICS['RADIUSTESTS'] is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RFC6614Tests extends AbstractTest {

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
     * Sets up the instance for testing of a number of candidate IPs
     * 
     * @param array $listOfIPs candidates to test
     */
    public function __construct($listOfIPs) {
        parent::__construct();
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
    }

    /**
     * run all checks on all candidates
     */
    public function allChecks() {
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
    public function cApathCheck(string $host) {
        if (!isset($this->TLS_CA_checks_result[$host])) {
            $this->TLS_CA_checks_result[$host] = [];
        }
        $opensslbabble = $this->openssl_s_client($host, '', $this->TLS_CA_checks_result[$host]);
        return $this->opensslCAResult($host, $opensslbabble, $this->TLS_CA_checks_result);
    }

    /**
     * This function executes openssl s_client command to check if a server accepts a client certificate
     * 
     * @param string $host IP:port
     * @return int returncode
     */
    public function tlsClientSideCheck(string $host) {
        $res = RADIUSTests::RETVAL_OK;
        if (!is_array(CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-clientcerts']) || count(CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-clientcerts']) == 0) {
            return RADIUSTests::RETVAL_SKIPPED;
        }
        if (preg_match("/\[/", $host)) {
            return RADIUSTests::RETVAL_INVALID;
        }
        foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-clientcerts'] as $type => $tlsclient) {
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
                $opensslbabble = $this->openssl_s_client($host, $add, $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]);
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
                        echo "koniec zabawy2<br>";
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
     * @param string $host IP address
     * @param string $arg arguments to add to the openssl command 
     * @param array $testresults by-reference: the testresults array we are writing into
     * @return array result of openssl s_client ...
     */
    private function openssl_s_client($host, $arg, &$testresults) {
// we got the IP address either from DNS (guaranteeing well-formedness)
// or from filter_var'ed user input. So it is always safe as an argument
// but code analysers want this more explicit, so here is this extra
// call to escapeshellarg()
        $escapedHost = escapeshellarg($host);
        $this->loggerInstance->debug(4, CONFIG['PATHS']['openssl'] . " s_client -connect " . $escapedHost . " -tls1 -CApath " . ROOT . "/config/ca-certs/ $arg 2>&1\n");
        $time_start = microtime(true);
        $opensslbabble = [];
        $result = 999; // likely to become zero by openssl; don't want to initialise to zero, could cover up exec failures
        exec(CONFIG['PATHS']['openssl'] . " s_client -connect " . $escapedHost . " -tls1 -CApath " . ROOT . "/config/ca-certs/ $arg 2>&1", $opensslbabble, $result);
        if ($opensslbabble === NULL) {
            throw new Exception("The output of an exec() call really can't be NULL!");
        }
        $time_stop = microtime(true);
        $testresults['time_millisec'] = floor(($time_stop - $time_start) * 1000);
        $testresults['returncode'] = $result;
        return $opensslbabble;
    }

    /**
     * This function parses openssl s_client result
     * 
     * @param string $host IP:port
     * @param array $opensslbabble openssl command output
     * @param array $testresults by-reference: pointer to results array we write into
     * @return int return code
     */
    private function opensslCAResult($host, $opensslbabble, &$testresults) {
        $res = RADIUSTests::RETVAL_OK;
        if (preg_match('/connect: Connection refused/', implode($opensslbabble))) {
            $testresults[$host]['status'] = RADIUSTests::RETVAL_CONNECTION_REFUSED;
            $res = RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
            $testresults[$host]['cert_oddity'] = RADIUSTests::CERTPROB_UNKNOWN_CA;
            $testresults[$host]['status'] = RADIUSTests::RETVAL_INVALID;
            $res = RADIUSTests::RETVAL_INVALID;
        }
        if (preg_match('/verify return:1/', implode($opensslbabble))) {
            $testresults[$host]['status'] = RADIUSTests::RETVAL_OK;
            $servercertStage1 = implode("\n", $opensslbabble);
            $servercert = preg_replace("/.*(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\n).*/s", "$1", $servercertStage1);
            $data = openssl_x509_parse($servercert);
            $testresults[$host]['certdata']['subject'] = $data['name'];
            $testresults[$host]['certdata']['issuer'] = $this->getCertificateIssuer($data);
            if (($altname = $this->getCertificatePropertyField($data, 'subjectAltName'))) {
                $testresults[$host]['certdata']['extensions']['subjectaltname'] = $altname;
            }
            $oids = $this->propertyCheckPolicy($data);
            if (!empty($oids)) {
                foreach ($oids as $resultArrayKey => $o) {
                    $testresults[$host]['certdata']['extensions']['policyoid'][] = " $o ($resultArrayKey)";
                }
            }
            if (($crl = $this->getCertificatePropertyField($data, 'crlDistributionPoints'))) {
                $testresults[$host]['certdata']['extensions']['crlDistributionPoint'] = $crl;
            }
            if (($ocsp = $this->getCertificatePropertyField($data, 'authorityInfoAccess'))) {
                $testresults[$host]['certdata']['extensions']['authorityInfoAccess'] = $ocsp;
            }
        }
        return $res;
    }

    /**
     * This function parses openssl s_client result
     * 
     * @param string $host IP:port
     * @param array $opensslbabble openssl command output
     * @param array $testresults by-reference: pointer to results array we write into
     * @param string $type type of certificate
     * @param int $resultArrayKey results array key
     * @return int return code
     */
    private function opensslClientsResult($host, $opensslbabble, &$testresults, $type = '', $resultArrayKey = 0) {
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
        return $res;
    }

        /**
     * This function parses a X.509 cert and returns all certificatePolicies OIDs
     * 
     * @param array $cert (returned from openssl_x509_parse) 
     * @return array of OIDs
     */
    private function propertyCheckPolicy($cert) {
        $oids = [];
        if ($cert['extensions']['certificatePolicies']) {
            foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-acceptableOIDs'] as $key => $oid) {
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
    private function getCertificateIssuer($cert) {
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
     * @param array $cert (returned from openssl_x509_parse) 
     * @param string $field 
     * @return string value of the extention named $field or ''
     */
    private function getCertificatePropertyField($cert, $field) {
        if ($cert['extensions'][$field]) {
            return $cert['extensions'][$field];
        }
        return '';
    }

}
