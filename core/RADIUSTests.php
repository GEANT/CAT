<?php

/* * ********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

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
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("EAP.php");
require_once("X509.php");
require_once("Helper.php");

define("L_OK", 0);
define("L_WARN", 1);
define("L_ERROR", 2);
define("L_REMARK", 3);


// generic return codes

/**
 * Test was executed and the result was as expected.
 */
define("RETVAL_OK", 0);
/**
 * Test could not be run because CAT software isn't configured for it
 */
define("RETVAL_NOTCONFIGURED", -100);
/**
 * Test skipped because there was nothing to be done
 */
define("RETVAL_SKIPPED", -101);
/**
 * test executed, and there were errors
 */
define("RETVAL_INVALID", -103);

// return codes specific to NAPTR existence checks
/**
 * no NAPTRs for domain; this is not an error, simply means that realm is not doing dynamic discovery for any service
 */
define("RETVAL_NONAPTR", -104);
/**
 * no eduroam NAPTR for domain; this is not an error, simply means that realm is not doing dynamic discovery for eduroam
 */
define("RETVAL_ONLYUNRELATEDNAPTR", -105);

// return codes specific to authentication checks
/**
 * no reply at all from remote RADIUS server
 */
define("RETVAL_NO_RESPONSE", -106);
/**
 * auth flow stopped somewhere in the middle of a conversation
 */
define("RETVAL_SERVER_UNFINISHED_COMM", -107);
/**
 * a RADIUS server did not want to talk EAP with us, but at least replied with a Reject
 */
define("RETVAL_IMMEDIATE_REJECT", -108);
/**
 * a RADIUS server talked EAP with us, but didn't like us in the end
 */
define("RETVAL_CONVERSATION_REJECT", -109);
/**
 * a RADIUS server refuses connection
 */
define("RETVAL_CONNECTION_REFUSED", -110);
/**
 * not enough data provided to perform an authentication
 */
define("RETVAL_INCOMPLETE_DATA", -111);

// certificate property errors
/**
 * The root CA certificate was sent by the EAP server.
 */
define("CERTPROB_ROOT_INCLUDED", -200);
/**
 * There was more than one server certificate in the EAP server's chain.
 */
define("CERTPROB_TOO_MANY_SERVER_CERTS", -201);
/**
 * There was no server certificate in the EAP server's chain.
 */
define("CERTPROB_NO_SERVER_CERT", -202);
/**
 * The/a server certificate was signed with an MD5 signature.
 */
define("CERTPROB_MD5_SIGNATURE_SERVER", -203);
/**
 * An intermediate CA certificate was signed with an MD5 signature.
 */
define("CERTPROB_MD5_SIGNATURE_INTERMEDIATE", -204);
/**
 * The server certificate did not contain the TLS Web Server OID, creating compat problems with many Windows versions.
 */
define("CERTPROB_NO_TLS_WEBSERVER_OID", -205);
/**
 * The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8.
 */
define("CERTPROB_NO_CDP", -206);
/**
 * The server certificate did a CRL Distribution Point, but not to a HTTP/HTTPS URL. Possible compat problems.
 */
define("CERTPROB_NO_CDP_HTTP", -207);
/**
 * The server certificate's CRL Distribution Point URL couldn't be accessed and/or did not contain a CRL.
 */
define("CERTPROB_NO_CRL_AT_CDP_URL", -208);
/**
 * The received certificate chain did not end in any of the trust roots configured in the profile properties.
 */
define("CERTPROB_TRUST_ROOT_NOT_REACHED", -209);
/**
 * The received server certificate's name did not match the configured name in the profile properties.
 */
define("CERTPROB_SERVER_NAME_MISMATCH", -210);
/**
 * The certificate does not set any BasicConstraints; particularly no CA = TRUE|FALSE
 */
define("CERTPROB_NO_BASICCONSTRAINTS", -211);
/**
 * The server presented a certificate which is from an unknown authority
 */
define("CERTPROB_UNKNOWN_CA", -212);
/**
 * The server accepted this client certificate, but should not have
 */
define("CERTPROB_WRONGLY_ACCEPT", -213);
/**
 * The server does not accept this client certificate, but should have
 */
define("CERTPROB_WRONGLY_NOT_ACCEPTED", -214);
/**
 * The server does accept this client certificate
 */
define("CERTPROB_NOT_ACCEPTED", -215);

/**
 * Test suite to verify that an EAP setup is actually working as advertised in
 * the real world. Can only be used if Config::$RADIUSTESTS is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RADIUSTests {

    /**
     * This private variable contains the realm to be checked. Is filled in the
     * class constructor.
     * 
     * @var string
     */
    private $realm;
    private $profile;

    /**
     * The variables below maintain state of the result of previous checks.
     * 
     */
    private $NAPTR_executed;
    private $NAPTR_compliance_executed;
    private $NAPTR_SRV_executed;
    private $NAPTR_hostname_executed;
    private $NAPTR_records;
    private $NAPTR_SRV_records;
    private $UDP_reachability_executed;
    private $errorlist;
    public $return_codes;
    public $UDP_reachability_result;
    public $TLS_CA_checks_result;
    public $TLS_clients_checks_result;
    public $NAPTR_hostname_records;

    /**
     */
    public $TLS_certkeys = array();

    /**
     * Constructor for the EAPTests class. The single mandatory parameter is the
     * realm for which the tests are to be carried out.
     * 
     * @param string $realm
     * @param int $profile_id
     */
    public function __construct($realm, $profile_id = 0) {
        $this->realm = $realm;
        $this->UDP_reachability_result = array();
        $this->TLS_CA_checks_result = array();
        $this->TLS_clients_checks_result = array();
        $this->NAPTR_executed = FALSE;
        $this->NAPTR_compliance_executed = FALSE;
        $this->NAPTR_SRV_executed = FALSE;
        $this->NAPTR_hostname_executed = FALSE;
        $this->NAPTR_records = array();
        $this->NAPTR_SRV_records = array();
        $this->NAPTR_hostname_records = array();
        $this->TLS_certkeys = array(
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
        );
        $this->errorlist = array();
        $this->initialise_errors();
        if ($profile_id !== 0)
            $this->profile = new Profile($profile_id);
        else
            $this->profile = FALSE;
    }

    /**
     * Tests if this realm exists in DNS and has NAPTR records matching the
     * configured consortium NAPTR target.
     * 
     * possible RETVALs:
     * - RETVAL_NOT_CONFIGURED; needs Config::$RADIUSTESTS['TLS-discoverytag']
     * - RETVAL_ONLYUNRELATEDNAPTR
     * - RETVAL_NONAPTR
     * 
     * @return int Either a RETVAL constant or a positive number (count of relevant NAPTR records)
     */
    public function NAPTR() {
        if (Config::$RADIUSTESTS['TLS-discoverytag'] == "") {
            $this->NAPTR_executed = RETVAL_NOTCONFIGURED;
            return RETVAL_NOTCONFIGURED;
        }

        $NAPTRs = dns_get_record($this->realm . ".", DNS_NAPTR);
        if ($NAPTRs !== FALSE && count($NAPTRs) > 0) {
            $NAPTRs_consortium = array();
            foreach ($NAPTRs as $naptr) {
                if ($naptr["services"] == Config::$RADIUSTESTS['TLS-discoverytag'])
                    $NAPTRs_consortium[] = $naptr;
            }

            if (count($NAPTRs_consortium) > 0) {
                $this->NAPTR_records = $NAPTRs_consortium;
                $this->NAPTR_executed = count($NAPTRs_consortium);
                return count($NAPTRs_consortium);
            } else {
                $this->NAPTR_executed = RETVAL_ONLYUNRELATEDNAPTR;
                return RETVAL_ONLYUNRELATEDNAPTR;
            }
        } else {
            $this->NAPTR_executed = RETVAL_NONAPTR;
            return RETVAL_NONAPTR;
        }
    }

    /**
     * Tests if all the dicovered NAPTR entries conform to the consortium's requirements
     * 
     * possible RETVALs:
     * - RETVAL_NOT_CONFIGURED; needs Config::$RADIUSTESTS['TLS-discoverytag']
     * - RETVAL_INVALID (at least one format error)
     * - RETVAL_OK (all fine)

     * @return int one of two RETVALs above
     */
    public function NAPTR_compliance() {
        // did we query DNS for the NAPTRs yet? If not, do so now.
        if ($this->NAPTR_executed === FALSE)
            $this->NAPTR();
        // if the NAPTR checks aren't configured, tell the caller
        if ($this->NAPTR_executed === RETVAL_NOTCONFIGURED) {
            $this->NAPTR_compliance_executed = RETVAL_NOTCONFIGURED;
            return RETVAL_NOTCONFIGURED;
        }
        // if there were no relevant NAPTR records, we are compliant :-)
        if (count($this->NAPTR_records) == 0) {
            $this->NAPTR_compliance_executed = RETVAL_OK;
            return RETVAL_OK;
        }
        $format_errors = array();
        // format of NAPTRs is consortium specific. eduroam below; others need
        // their own code
        if (Config::$CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
            foreach ($this->NAPTR_records as $edupointer) {
                // must be "s" type for SRV
                if ($edupointer["flags"] != "s" && $edupointer["flags"] != "S")
                    $format_errors[] = array("TYPE" => "NAPTR-FLAG", "TARGET" => $edupointer['flag']);
                // no regex
                if ($edupointer["regex"] != "")
                    $format_errors[] = array("TYPE" => "NAPTR-REGEX", "TARGET" => $edupointer['regex']);
            }
        }
        if (count($format_errors) > 0) {
            $this->errorlist = array_merge($this->errorlist, $format_errors);
            $this->NAPTR_compliance_executed = RETVAL_INVALID;
            return RETVAL_INVALID;
        } else {
            $this->NAPTR_compliance_executed = RETVAL_OK;
            return RETVAL_OK;
        }
    }

// generic return codes
    function initialise_errors() {
        $oldlocale = CAT::set_locale('core');
        $this->return_codes = array();
        /**
         * Test was executed and the result was as expected.
         */
        $code = RETVAL_OK;
        $this->return_codes[$code]["message"] = _("Completed");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * Test could not be run because CAT software isn't configured for it
         */
        $code = RETVAL_NOTCONFIGURED;

        /**
         * Test skipped because there was nothing to be done
         */
        $code = RETVAL_SKIPPED;
        $this->return_codes[$code]["message"] = _("This check was skipped.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * test executed, and there were errors
         */
        $code = RETVAL_INVALID;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

// return codes specific to NAPTR existence checks
        /**
         * no NAPTRs for domain; this is not an error, simply means that realm is not doing dynamic discovery for any service
         */
        $code = RETVAL_NONAPTR;
        $this->return_codes[$code]["message"] = _("This realm has no NAPTR records.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * no eduroam NAPTR for domain; this is not an error, simply means that realm is not doing dynamic discovery for eduroam
         */
        $code = RETVAL_ONLYUNRELATEDNAPTR;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

// return codes specific to authentication checks
        /**
         * no reply at all from remote RADIUS server
         */
        $code = RETVAL_NO_RESPONSE;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * auth flow stopped somewhere in the middle of a conversation
         */
        $code = RETVAL_SERVER_UNFINISHED_COMM;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * a RADIUS server did not want to talk EAP with us, but at least replied with a Reject
         */
        $code = RETVAL_IMMEDIATE_REJECT;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * a RADIUS server talked EAP with us, but didn't like us in the end
         */
        $code = RETVAL_CONVERSATION_REJECT;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * a RADIUS server refuses connection
         */
        $code = RETVAL_CONNECTION_REFUSED;
        $this->return_codes[$code]["message"] = _("Conection refused");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * not enough data provided to perform an authentication
         */
        $code = RETVAL_INCOMPLETE_DATA;
        $this->return_codes[$code]["message"] = _("Not enough data provided to perform an authentication");
        $this->return_codes[$code]["severity"] = L_ERROR;

// certificate property errors
        /**
         * The root CA certificate was sent by the EAP server.
         */
        $code = CERTPROB_ROOT_INCLUDED;
        $this->return_codes[$code]["message"] = _("The certificate chain includes the root CA certificate. This does not serve any useful purpose but inflates the packet exchange, possibly leading to more round-trips and thus slower authentication.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * There was more than one server certificate in the EAP server's chain.
         */
        $code = CERTPROB_TOO_MANY_SERVER_CERTS;
        $this->return_codes[$code]["message"] = _("There is more than one server certificate in the chain.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * There was no server certificate in the EAP server's chain.
         */
        $code = CERTPROB_NO_SERVER_CERT;
        $this->return_codes[$code]["message"] = _("There is no server certificate in the chain.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The/a server certificate was signed with an MD5 signature.
         */
        $code = CERTPROB_MD5_SIGNATURE_SERVER;
        $this->return_codes[$code]["message"] = _("The server certificate is signed with the MD5 signature algorithm. Many Operating Systems, including Apple iOS, will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * An intermediate CA certificate was signed with an MD5 signature.
         */
        $code = CERTPROB_MD5_SIGNATURE_INTERMEDIATE;
        $this->return_codes[$code]["message"] = _("An intermediate CA is signed with the MD5 signature algorithm. Many Operating Systems, including Apple iOS, will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server certificate did not contain the TLS Web Server OID, creating compat problems with many Windows versions.
         */
        $code = CERTPROB_NO_TLS_WEBSERVER_OID;
        $this->return_codes[$code]["message"] = _("The server certificate does not have the extension 'extendedKeyUsage: TLS Web Server Authentication'. Most Microsoft Operating Systems will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8.
         */
        $code = CERTPROB_NO_CDP;
        $this->return_codes[$code]["message"] = _("The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server certificate did a CRL Distribution Point, but not to a HTTP/HTTPS URL. Possible compat problems.
         */
        $code = CERTPROB_NO_CDP_HTTP;
        $this->return_codes[$code]["message"] = _("The server certificate does not have the extension 'CRL Distribution Point' pointing to an HTTP/HTTPS URL. Some Operating Systems (currently only Windows Phone 8) will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server certificate's CRL Distribution Point URL couldn't be accessed and/or did not contain a CRL.
         */
        $code = CERTPROB_NO_CRL_AT_CDP_URL;
        $this->return_codes[$code]["message"] = _("The extension 'CRL Distribution Point' in the server certificate points to a non-existing location. Some Operating Systems check certificate validity by consulting the CRL and will fail to validate the certifice.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The received certificate chain did not end in any of the trust roots configured in the profile properties.
         */
        $code = CERTPROB_TRUST_ROOT_NOT_REACHED;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The received server certificate's name did not match the configured name in the profile properties.
         */
        $code = CERTPROB_SERVER_NAME_MISMATCH;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The certificate does not set any BasicConstraints; particularly no CA = TRUE|FALSE
         */
        $code = CERTPROB_NO_BASICCONSTRAINTS;
        $this->return_codes[$code]["message"] = _("At least one certificate did not contain any BasicConstraints extension; which makes it unclear if it's a CA certificate or end-entity certificate. At least Mac OS X 10.8 (Mountain Lion) will not validate this certificate for EAP purposes!");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server presented a certificate which is from an unknown authority
         */
        $code = CERTPROB_UNKNOWN_CA;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server accepted this client certificate, but should not have
         */
        $code = CERTPROB_WRONGLY_ACCEPT;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server does not accept this client certificate, but should have
         */
        $code = CERTPROB_WRONGLY_NOT_ACCEPTED;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * The server does accept this client certificate
         */
        $code = CERTPROB_NOT_ACCEPTED;
        $this->return_codes[$code]["message"] = _("");
        $this->return_codes[$code]["severity"] = L_OK;

        CAT::set_locale($oldlocale);
    }

    /**
     * Tests if NAPTR records can be resolved to SRVs. Will only run if NAPTR
     * checks completed without error.
     *
     * possible RETVALs:
     * - RETVAL_INVALID
     * - RETVAL_SKIPPED
     * 
     * @return int one of the RETVALs above or the number of SRV records which were resolved
     */
    function NAPTR_SRV() {
        // see if preceding checks have been run, and run them if not
        // compliance check will cascade NAPTR check on its own
        if ($this->NAPTR_compliance_executed === FALSE)
            $this->NAPTR_compliance();
        // we only run the SRV checks if all records are compliant and more than one relevant NAPTR exists
        if ($this->NAPTR_executed <= 0 || $this->NAPTR_compliance_executed == RETVAL_INVALID) {
            $this->NAPTR_SRV_executed = RETVAL_SKIPPED;
            return RETVAL_SKIPPED;
        }

        $SRV_errors = array();
        $SRV_targets = array();

        foreach ($this->NAPTR_records as $edupointer) {
            $temp_result = dns_get_record($edupointer["replacement"], DNS_SRV);
            if ($temp_result === FALSE || count($temp_result) == 0) {
                $SRV_errors[] = array("TYPE" => "SRV_NOT_RESOLVING", "TARGET" => $edupointer['replacement']);
            } else
                foreach ($temp_result as $res)
                    $SRV_targets[] = array("hostname" => $res["target"], "port" => $res["port"]);
        }
        $this->NAPTR_SRV_records = $SRV_targets;
        if (count($SRV_errors) > 0) {
            $this->NAPTR_SRV_executed = RETVAL_INVALID;
            $this->errorlist = array_merge($this->errorlist, $SRV_errors);
            return RETVAL_INVALID;
        }
        $this->NAPTR_SRV_executed = count($SRV_targets);
        return count($SRV_targets);
    }

    function NAPTR_hostnames() {
        // make sure the previous tests have been run before we go on
        // preceeding tests will cascade automatically if needed
        if ($this->NAPTR_SRV_executed === FALSE)
            $this->NAPTR_SRV();
        // if previous are SKIPPED, skip this one, too
        if ($this->NAPTR_SRV_executed == RETVAL_SKIPPED) {
            $this->NAPTR_hostname_executed = RETVAL_SKIPPED;
            return RETVAL_SKIPPED;
        }
        // the SRV check may have returned INVALID, but could have found a
        // a working subset of hosts anyway. We should continue checking all 
        // dicovered names.

        $ip_addresses = array();
        $resolution_errors = array();

        foreach ($this->NAPTR_SRV_records as $server) {
            $host_resolution_6 = dns_get_record($server["hostname"], DNS_AAAA);
            $host_resolution_4 = dns_get_record($server["hostname"], DNS_A);
            $host_resolution = array_merge($host_resolution_6, $host_resolution_4);
            if ($host_resolution === FALSE || count($host_resolution) == 0) {
                $resolution_errors[] = array("TYPE" => "HOST_NO_ADDRESS", "TARGET" => $server['hostname']);
            } else
                foreach ($host_resolution as $address)
                    if (isset($address["ip"]))
                        $ip_addresses[] = array("family" => "IPv4", "IP" => $address["ip"], "port" => $server["port"], "status" => "");
                    else
                        $ip_addresses[] = array("family" => "IPv6", "IP" => $address["ipv6"], "port" => $server["port"], "status" => "");
        }

        $this->NAPTR_hostname_records = $ip_addresses;

        if (count($resolution_errors) > 0) {
            $this->errorlist = array_merge($this->errorlist, $resolution_errors);
            $this->NAPTR_hostname_executed = RETVAL_INVALID;
            return RETVAL_INVALID;
        }
        $this->NAPTR_hostname_executed = count($this->NAPTR_hostname_records);
        return count($this->NAPTR_hostname_records);
    }

    /**
     * This function parses a X.509 server cert and checks if it finds client device incompatibilities
     * 
     * @param array $servercert the properties of the certificate as returned by processCertificate()
     * @return array of oddities; the array is empty if everything is fine
     */
    public function property_check_servercert($servercert) {
        debug(4, "SERVER CERT IS: " . print_r($servercert, TRUE));
        $returnarray = Array();
        // we share the same checks as for CAs when it comes to signature algorithm and basicconstraints
        // so call that function and memorise the outcome
        $returnarray = array_merge($this->property_check_intermediate($servercert));

        if (!isset($servercert['full_details']['extensions'])) {
            $returnarray[] = CERTPROB_NO_TLS_WEBSERVER_OID;
            $returnarray[] = CERTPROB_NO_CDP_HTTP;
        } else {
            if (!isset($servercert['full_details']['extensions']['extendedKeyUsage']) || !preg_match("/TLS Web Server Authentication/", $servercert['full_details']['extensions']['extendedKeyUsage'])) {
                $returnarray[] = CERTPROB_NO_TLS_WEBSERVER_OID;
            }
            $crl_url = array();
            if (!isset($servercert['full_details']['extensions']['crlDistributionPoints'])) {
                $returnarray[] = CERTPROB_NO_CDP;
            } else if (!preg_match("/^.*URI\:(http)(.*)$/", str_replace(array("\r", "\n"), ' ', $servercert['full_details']['extensions']['crlDistributionPoints']), $crl_url)) {
                $returnarray[] = CERTPROB_NO_CDP_HTTP;
            } else { // first and second sub-match is the full URL... check it
                $crlcontent = downloadFile($crl_url[1] . $crl_url[2]);
                if ($crlcontent === FALSE)
                    $returnarray[] = CERTPROB_NO_CRL_AT_CDP_URL;
            }
        }

        return $returnarray;
    }

    /**
     * This function parses a X.509 intermediate CA cert and checks if it finds client device incompatibilities
     * 
     * @param array $intermediate_ca the properties of the certificate as returned by processCertificate()
     * @return array of oddities; the array is empty if everything is fine
     */
    public function property_check_intermediate($intermediate_ca) {
        $returnarray = Array();
        if (preg_match("/md5/i", $intermediate_ca['full_details']['signature_algorithm'])) {
            $returnarray[] = CERTPROB_MD5_SIGNATURE_INTERMEDIATE;
        }
        debug(4, "CA CERT IS: " . print_r($intermediate_ca, TRUE));
        if ($intermediate_ca['basicconstraints_set'] == 0) {
            $returnarray[] = CERTPROB_NO_BASICCONSTRAINTS;
        }
        return $returnarray;
    }

    /**
     * This function returns an array of errors which were encountered in all the tests.
     * 
     * @return array
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
     * @param string $probeindex: refers to the specific UDP-host in the config that should be checked
     * @param boolean $opname_check: should we check choking on Operator-Name?
     * @param boolean $frag: should we cause UDP fragmentation? (Warning: makes use of Operator-Name!)
     * @return int returncode
     */
    public function UDP_reachability($probeindex, $opname_check = TRUE, $frag = TRUE) {
        return $this->UDP_login($probeindex, EAP::$EAP_ANY, "cat-connectivity-test@" . $this->realm, "ihavenopassword", $opname_check, $frag);
    }

    public function UDP_login($probeindex, $eaptype, $user, $password, $opname_check = TRUE, $frag = TRUE, $clientcertdata = NULL) {
        if (!isset(Config::$RADIUSTESTS['UDP-hosts'][$probeindex])) {
            $this->UDP_reachability_executed = RETVAL_NOTCONFIGURED;
            return RETVAL_NOTCONFIGURED;
        }

        $anon_id = ""; // our default of last resort. Will check if servers choke on the IETF-recommended anon ID format.
        if ($this->profile instanceof Profile) { // take profile's anon ID if known
            $foo = $this->profile;
            if ($foo->use_anon_outer == TRUE && $foo->realm = $this->realm) {
                $the_id = $foo->getAttributes("internal:anon_local_value");
                $anon_id = $the_id[0]['value'];
            }
        }

        // we will need a config blob for wpa_supplicant, in a temporary directory
        // code is copy&paste from DeviceConfig.php

        $pathname = 'downloads' . '/' . md5(time() . rand());
        $tmp_dir = dirname(dirname(__FILE__)) . '/web/' . $pathname;
        debug(4, "temp dir: $tmp_dir\n");
        if (!mkdir($tmp_dir, 0700, true)) {
            error("unable to create temporary directory (eap test): $tmp_dir\n");
            exit;
        }
        chdir($tmp_dir);
        $wpa_supplicant_config = fopen($tmp_dir . "/udp_login_test.conf", "w");
        $eap_text = EAP::eapDisplayName($eaptype);
        $config = '
network={
  ssid="' . Config::$APPEARANCE['productname'] . ' testing"
  key_mgmt=WPA-EAP
  proto=WPA2
  pairwise=CCMP
  group=CCMP
  ';
        // phase 1
        if (isset($eap_text['OUTER'])) {
            $config .= 'eap=' . $eap_text['OUTER'] . "\n";
        } else {// in case eapDisplayName didn't give an answer
            $this->UDP_reachability_executed = RETVAL_NOTCONFIGURED;
            return RETVAL_NOT_CONFIGURED;
        }
        $log_config = $config;
        // phase 2 if applicable; all inner methods have passwords
        if (isset($eap_text['INNER']) && $eap_text['INNER'] != "") {
            $config .= '  phase2="auth=' . $eap_text['INNER'] . "\"\n";
            $log_config .= '  phase2="auth=' . $eap_text['INNER'] . "\"\n";
            $config .= "  password=\"$password\"\n";
            $log_config .= "  password=\"not logged for security reasons\"\n";
        } else if ($eaptype == EAP::$PWD) { // PWD has a password, but no phase2
            $config .= "  password=\"$password\"\n";
            $log_config .= "  password=\"not logged for security reasons\"\n";
        } else if ($eaptype == EAP::$TLS) { // EAP-TLS has private credentials, no phase2
            if ($clientcertdata !== NULL) {
                $clientcertfile = fopen($tmp_dir . "/client.p12", "w");
                fwrite($clientcertfile, $clientcertdata);
                fclose($clientcertfile);
                $config .= "  private_key=\"./client.p12\"\n";
                $config .= "  private_key_passwd=\"$password\"\n";
                $log_config .= "  private_key_passwd=\"not logged for security reasons\"\n";
            } else {
                $this->UDP_reachability_executed = RETVAL_NOTCONFIGURED;
                return RETVAL_NOT_CONFIGURED;
            }
        }
        // outer/only identity
        $config .= '  identity="';
        $log_config .= '  identity="';
        if (preg_match("/@/", $user)) {
            $config .= $user . "\"\n";
            $log_config .= $user . "\"\n";
        } else {
            $config .= $anon_id . "@" . $user . "\"\n";
            $log_config .= $anon_id . "@" . $user . "\"\n";
        }
        // done
        $config .= "}";
        $log_config .= "}";
        // the config intentionally does not include CA checking. We do this
        // ourselves after getting the chain with -o.

        fwrite($wpa_supplicant_config, $config);
        fclose($wpa_supplicant_config);

        $testresults = array();
        $testresults['cert_oddities'] = array();
        $packetflow = array();
        $cmdline = Config::$PATHS['eapol_test'] .
                " -a " . Config::$RADIUSTESTS['UDP-hosts'][$probeindex]['ip'] .
                " -s " . Config::$RADIUSTESTS['UDP-hosts'][$probeindex]['secret'] .
                " -o serverchain.pem" .
                " -c ./udp_login_test.conf" .
                " -M 22:44:66:CA:20:00 " .
                " -t " . Config::$RADIUSTESTS['UDP-hosts'][$probeindex]['timeout'] . " ";
        if ($opname_check)
            $cmdline .= '-N126:s:"1cat.eduroam.org" ';
        if ($frag)
            for ($i = 0; $i < 6; $i++) // 6 x 250 bytes means UDP fragmentation will occur - good!
                $cmdline .= '-N126:s:"1cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat..cat.cat.cat.cat.cat.cat.cat.cat.cat.cat.cat..cat.cat.cat.cat.eduroam.org" ';
        $cmdline .= " | grep 'RADIUS message:' | cut -d ' ' -f 3 | cut -d '=' -f 2";
        debug(4, "Shallow reachability check cmdline: $cmdline\n");
        debug(4, "Shallow reachability check config: $tmp_dir\n$log_config\n");
        $time_start = microtime(true);
        exec($cmdline, $packetflow);
        $time_stop = microtime(true);
        $testresults['time_millisec'] = ($time_stop - $time_start) * 1000;
        $packetcount = array_count_values($packetflow);
        $testresults['packetcount'] = $packetcount;
        $testresults['packetflow'] = $packetflow;
        // first see if there was a packet count mismatch (retransmits?)
        $reqs = (isset($packetcount[1]) ? $packetcount[1] : 0);
        $accepts = (isset($packetcount[2]) ? $packetcount[2] : 0);
        $rejects = (isset($packetcount[3]) ? $packetcount[3] : 0);
        $challenges = (isset($packetcount[11]) ? $packetcount[11] : 0);
        if ($reqs - $accepts - $rejects - $challenges != 0 || $accepts > 1 || $rejects > 1)
            $testresults['packetflow_sane'] = FALSE;
        else
            $testresults['packetflow_sane'] = TRUE;

        // now let's look at the server cert+chain
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
        // TODO: TRUST_ROOT_NOT_REACHED
        //       have optional param to exclude potential knowledge about intermediates
        $x509 = new X509();
        $certarray = $x509->splitCertificate(fread(fopen($tmp_dir . "/serverchain.pem", "r"), "1000000"));
        // we want no root cert, and exactly one server cert
        $number_root = 0;
        $number_server = 0;
        $servercert;
        $intermediate_cas = array();


        $processed = array(); // eapol_test seems a bit buggy; dumps the same
        // cert multiple times into the file. We fill this array only once.
        // at the same time, write the root CAs into a trusted root CA dir
        // and intermediate and first server cert into a PEM file
        // for later chain validation

        $server_and_intermediate_file = fopen($tmp_dir . "/incomingchain.pem", "w");

        if (!mkdir($tmp_dir . "/root-ca/", 0700, true)) {
            error("unable to create root CA directory (RADIUS Tests): $tmp_dir/root-ca/\n");
            exit;
        }

        foreach ($certarray as $cert_pem)
            if (!in_array($cert_pem, $processed)) {
                $processed[] = $cert_pem;
                $cert = $x509->processCertificate($cert_pem);
                if ($cert == FALSE)
                    continue;
                if ($cert['ca'] == 0 && $cert['root'] != 1) {
                    $number_server++;
                    $servercert = $cert;
                    if ($number_server == 1)
                        fwrite($server_and_intermediate_file, $cert_pem);
                } else
                if ($cert['root'] == 1) {
                    $number_root++;
                    $newrootcafile = fopen($tmp_dir . "/root-ca/cert$number_root.pem", "w");
                    fwrite($newrootcafile, $cert_pem);
                    fclose($newrootcafile);
                } else {
                    $intermediate_cas[] = $cert;
                    fwrite($server_and_intermediate_file, $cert_pem);
                }
                $testresults['certdata'][] = $cert['full_details'];
            }
        fclose($server_and_intermediate_file);
        if ($number_root > 0)
            $testresults['cert_oddities'][] = CERTPROB_ROOT_INCLUDED;
        if ($number_server > 1)
            $testresults['cert_oddities'][] = CERTPROB_TOO_MANY_SERVER_CERTS;
        if ($number_server == 0)
            $testresults['cert_oddities'][] = CERTPROB_NO_SERVER_CERT;
        // check server cert properties
        if ($number_server > 0)
            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $this->property_check_servercert($servercert));
        // check intermediate ca cert properties
        foreach ($intermediate_cas as $intermediate_ca)
            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $this->property_check_intermediate($intermediate_ca));
        // check trust chain for completeness
        system(Config::$PATHS['c_rehash'] . " $tmp_dir/root-ca/ > /dev/null");
        system(Config::$PATHS['openssl'] . " verify -CApath $tmp_dir/root-ca/ -purpose any $tmp_dir/incomingchain.pem > /dev/null");
        rrmdir($tmp_dir);
        //
        // TODO evaluate the results of the openssl call
        // 
        // TODO check the hostname against ... subject? subjectAltName? both?
        // 
        // dump the details in a class variable in case someone cares
        $this->UDP_reachability_result[$probeindex] = $testresults;
        // if neither an Accept or Reject were generated, there is definitely a problem
        if ($accepts + $rejects == 0) { // no final response. hm.
            if ($challenges > 0) { // but there was an Access-Challenge
                $this->UDP_reachability_executed = RETVAL_SERVER_UNFINISHED_COMM;
                return RETVAL_SERVER_UNFINISHED_COMM;
            } else {
                $this->UDP_reachability_executed = RETVAL_NO_RESPONSE;
                return RETVAL_NO_RESPONSE;
            }
        } else // either an accept or a reject
        // rejection without EAP is fishy
        if ($rejects > 0) {
            if ($challenges == 0) {
                $this->UDP_reachability_executed = RETVAL_IMMEDIATE_REJECT;
                return RETVAL_IMMEDIATE_REJECT;
            } else { // i.e. if rejected with challenges
                $this->UDP_reachability_executed = RETVAL_CONVERSATION_REJECT;
                return RETVAL_CONVERSATION_REJECT;
            }
        } else if ($accepts > 0) {
            $this->UDP_reachability_executed = RETVAL_OK;
            return RETVAL_OK;
        }
    }

    /**
     * This function parses a X.509 cert and returns all certificatePolicies OIDs
     * 
     * @param structure $cert (returned from openssl_x509_parse) 
     * @return array of OIDs
     */
    function property_check_policy($cert) {
        $oids = array();
        if ($cert['extensions']['certificatePolicies']) {
            foreach (Config::$RADIUSTESTS['TLS-acceptableOIDs'] as $key => $oid)
                if (preg_match("/Policy: $oid/", $cert['extensions']['certificatePolicies']))
                    $oids[$key] = $oid;
        }
        return $oids;
    }

    /**
     * This function parses a X.509 cert and returns the value of $field
     * 
     * @param structure $cert (returned from openssl_x509_parse) 
     * @return string value of the issuer field or ''
     */
    function property_certificate_get_issuer($cert) {
        $issuer = '';
        foreach ($cert['issuer'] as $key => $val)
            if (is_array($val))
                foreach ($val as $v)
                    $issuer .= "/$key=$v";
            else
                $issuer .= "/$key=$val";
        return $issuer;
    }

    /**
     * This function parses a X.509 cert and returns the value of $field
     * 
     * @param structure $cert (returned from openssl_x509_parse) 
     * @param string $field 
     * @return string value of the extention named $field or ''
     */
    function property_certificate_get_field($cert, $field) {
        if ($cert['extensions'][$field]) {
            return $cert['extensions'][$field];
        }
        return '';
    }

    /**
     * This function executes openssl s_client command
     * 
     * @param string $key points NAPTR_hostname_records
     * @param string $bracketaddr IP address
     * @param int $port
     * @param string $arg arguments to add to the openssl command 
     * @return string result of oenssl s_client ...
     */
    function openssl_s_client($host, $arg, $testresults) {
        debug(4, Config::$PATHS['openssl'] . " s_client -connect " . $host . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ $arg 2>&1\n");
        $time_start = microtime(true);
        exec(Config::$PATHS['openssl'] . " s_client -connect " . $host . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ $arg 2>&1", $opensslbabble, $result);
        $time_stop = microtime(true);
        $testresults['time_millisec'] = floor(($time_stop - $time_start) * 1000);
        $testresults['returncode'] = $result;
        return $opensslbabble;
    }

    /**
     * This function parses openssl s_client result
     * 
     * @param string $host IP:port
     * @param string $testtype capath or clients
     * @param string $opensslbabble openssl command output
     * @param pointer to results array
     * @param string $type results array key
     * @param int $k results array key
     * @return int return code
     */
    function openssl_result($host, $testtype, $opensslbabble, $testresults, $type = '', $k = 0) {
        $res = RETVAL_OK;
        switch ($testtype) {
            case "capath":
                if (preg_match('/connect: Connection refused/', implode($opensslbabble))) {
                    $testresults[$host]['status'] = RETVAL_CONNECTION_REFUSED;
                    $res = RETVAL_INVALID;
                }
                if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
                    $testresults[$host]['cert_oddity'] = CERTPROB_UNKNOWN_CA;
                    $testresults[$host]['status'] = RETVAL_INVALID;
                    $res = RETVAL_INVALID;
                }
                if (preg_match('/verify return:1/', implode($opensslbabble))) {
                    $testresults[$host]['status'] = RETVAL_OK;
                    $servercert = implode("\n", $opensslbabble);
                    $servercert = preg_replace("/.*(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\n).*/s", "$1", $servercert);
                    $data = openssl_x509_parse($servercert);
                    $testresults[$host]['certdata']['subject'] = $data['name'];
                    $testresults[$host]['certdata']['issuer'] = $this->property_certificate_get_issuer($data);
                    if (($altname = $this->property_certificate_get_field($data, 'subjectAltName'))) {
                        $testresults[$host]['certdata']['extensions']['subjectaltname'] = $altname;
                    }
                    $oids = $this->property_check_policy($data);
                    if (!empty($oids)) {
                        foreach ($oids as $k => $o)
                            $testresults[$host]['certdata']['extensions']['policyoid'][] = " $o ($k)";
                    }
                    if (($crl = $this->property_certificate_get_field($data, 'crlDistributionPoints'))) {
                        $testresults[$host]['certdata']['extensions']['crlDistributionPoint'] = $crl;
                    }
                    if (($ocsp = $this->property_certificate_get_field($data, 'authorityInfoAccess'))) {
                        $testresults[$host]['certdata']['extensions']['authorityInfoAccess'] = $ocsp;
                    }
                }
                break;
            case "clients":
                $ret = $testresults[$host]['ca'][$type]['certificate'][$k]['returncode'];
                $output = implode($opensslbabble);
                $unknownca = 0;
                if ($ret == 0)
                    $testresults[$host]['ca'][$type]['certificate'][$k]['connected'] = 1;
                else {
                    $testresults[$host]['ca'][$type]['certificate'][$k]['connected'] = 0;
                    if (preg_match('/connect: Connection refused/', implode($opensslbabble))) {
                        $testresults[$host]['ca'][$type]['certificate'][$k]['returncode'] = RETVAL_CONNECTION_REFUSED;
                    } elseif (preg_match('/sslv3 alert certificate expired/', $output))
                        $res_comment = _("certificate expired");
                    elseif (preg_match('/sslv3 alert certificate revoked/', $output))
                        $res_comment = _("certificate was revoked");
                    elseif (preg_match('/SSL alert number 46/', $output))
                        $res_comment = _("bad policy");
                    elseif (preg_match('/tlsv1 alert unknown ca/', $output)) {
                        $res_comment = _("unknown authority");
                        $testresults[$host]['ca'][$type]['certificate'][$k]['reason'] = CERTPROB_UNKNOWN_CA;
                    } else
                        $res_comment = _("unknown authority or no certificate policy or another problem");
                    $testresults[$host]['ca'][$type]['certificate'][$k]['resultcomment'] = $res_comment;
                }
                break;
        }
        return $res;
    }

    /**
     * This function executes openssl s_clientends command to check if a server accept a CA
     * @param string $host IP:port
     * @return int returncode
     */
    public function CApath_check($host) {

        $res = RETVAL_OK;
        if (preg_match("/\[/", $host))
            return RETVAL_INVALID;
        $opensslbabble = $this->openssl_s_client($host, '', &$this->TLS_CA_checks_result[$host]);
        $res = $this->openssl_result($host, 'capath', $opensslbabble, &$this->TLS_CA_checks_result);
        return $res;
    }

    /**
     * This function performs 
     * This function performs executes openssl s_client command to check if a server accept a client certificate
     * @param string $host IP:port
     * @return int returncode
     */
    public function TLS_clients_side_check($host) {
        $res = RETVAL_OK;
        if (is_array(Config::$RADIUSTESTS['TLS-clientcerts']) && count(Config::$RADIUSTESTS['TLS-clientcerts']) > 0) {
            if (preg_match("/\[/", $host))
                return RETVAL_INVALID;
            foreach (Config::$RADIUSTESTS['TLS-clientcerts'] as $type => $tlsclient) {
                $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['from'] = $type;
                $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['status'] = $tlsclient['status'];
                $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['message'] = $this->TLS_certkeys[$tlsclient['status']];
                $this->TLS_clients_checks_result[$host]['ca'][$type]['clientcertinfo']['issuer'] = $tlsclient['issuerCA'];
                foreach ($tlsclient['certificates'] as $k => $cert) {
                    $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['status'] = $cert['status'];
                    $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['message'] = $this->TLS_certkeys[$cert['status']];
                    $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['expected'] = $cert['expected'];
                    $add = ' -cert ' . CAT::$root . '/config/cli-certs/' . $cert['public'] . ' -key ' . CAT::$root . '/config/cli-certs/' . $cert['private'];
                    $opensslbabble = $this->openssl_s_client($host, $add, &$this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]);
                    $res = $this->openssl_result($host, 'clients', $opensslbabble, &$this->TLS_clients_checks_result, $type, $k);
                    if ($cert['expected'] == 'PASS') {
                        if (!$this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['connected']) {
                            if (($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) {
                                $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['returncode'] = CERTPROB_NOT_ACCEPTED;
                                $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['finalerror'] = 1;
                                break;
                            }
                        }
                    } else {
                        if ($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['connected'])
                            $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['returncode'] = CERTPROB_WRONGLY_ACCEPTED;

                        if (($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['reason'] == CERTPROB_UNKNOWN_CA) && ($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) {
                            $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]['finalerror'] = 1;
                            echo "koniec zabawy2<br>";
                            break;
                        }
                    }
                }
            }
        } else {
            return RETVAL_SKIPPED;
        }
        return $res;
    }

}

?>
