<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
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


// generic return codes

/**
 * Test was executed and the result was as expected.
 */
define("RETVAL_OK", 0);
/**
 * Test could not be run because CAT software isn't configured for it
 */
define("RETVAL_NOTCONFIGURED", -100);
define("RETVAL_NOT_CONFIGURED", -100);
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

/**
  * PKCS12 password does not match the certificate file
  */

define("RETVAL_WRONG_PKCS12_PASSWORD", -112);

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
define("CERTPROB_MD5_SIGNATURE", -204);
/**
 * one of the keys in the cert chain was smaller than 1024 bits
 */
define("CERTPROB_LOW_KEY_LENGTH", -220);
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
 * certificate is not currently valid (expired/not yet valid)
 */
define("CERTPROB_SERVER_CERT_REVOKED", -222);
/**
 * The received server certificate is revoked.
 */
define("CERTPROB_OUTSIDE_VALIDITY_PERIOD", -221);
/**
 * At least one certificate is outside its validity period (not yet valid, or already expired)!
 */
define("CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN", -225);
/**
 * At least one certificate is outside its validity period, but this certificate does not take part in servder validation 
 */
define("CERTPROB_TRUST_ROOT_NOT_REACHED", -209);
/**
 * The received certificate chain did not carry the necessary intermediate CAs in the EAP conversation. Only the CAT Intermediate CA installation can complete the chain.
 */
define("CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES", -216);
/**
 * The received server certificate's name did not match the configured name in the profile properties.
 */
define("CERTPROB_SERVER_NAME_MISMATCH", -210);
/**
 * The received server certificate's name did not match the configured name in the profile properties.
 */
define("CERTPROB_SERVER_NAME_PARTIAL_MATCH", -217);
/**
 * One of the names in the cert was not a hostname.
 */
define("CERTPROB_NOT_A_HOSTNAME", -218);
/**
 * One of the names contained a wildcard character.
 */
define("CERTPROB_WILDCARD_IN_NAME", -219);
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
define("CERTPROB_WRONGLY_ACCEPTED", -213);
/**
 * The server does not accept this client certificate, but should have
 */
define("CERTPROB_WRONGLY_NOT_ACCEPTED", -214);
/**
 * The server does accept this client certificate
 */
define("CERTPROB_NOT_ACCEPTED", -215);
/**
  * the CRL of a certificate could not be found
  */
define("CERTPROB_UNABLE_TO_GET_CRL",223);
/**
 * no EAP method could be agreed on, certs could not be extraced
 */
define("CERTPROB_NO_COMMON_EAP_METHOD", -224);
/**
 * Diffie-Hellman groups need to be 1024 bit at least, starting with OS X 10.11
 */
define("CERTPROB_DH_GROUP_TOO_SMALL", -225);
/**
 * There is more than one CN in the certificate
 */
define("CERTPROB_MULTIPLE_CN", -226);



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
    public $TLS_certkeys = [];

    /**
     * Constructor for the EAPTests class. The single mandatory parameter is the
     * realm for which the tests are to be carried out.
     * 
     * @param string $realm
     * @param int $profile_id
     */
    public function __construct($realm, $profile_id = 0) {
        $this->realm = $realm;
        $this->UDP_reachability_result = [];
        $this->TLS_CA_checks_result = [];
        $this->TLS_clients_checks_result = [];
        $this->NAPTR_executed = FALSE;
        $this->NAPTR_compliance_executed = FALSE;
        $this->NAPTR_SRV_executed = FALSE;
        $this->NAPTR_hostname_executed = FALSE;
        $this->NAPTR_records = [];
        $this->NAPTR_SRV_records = [];
        $this->NAPTR_hostname_records = [];
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
        $this->errorlist = [];
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
            $NAPTRs_consortium = [];
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
        $format_errors = [];
        // format of NAPTRs is consortium specific. eduroam below; others need
        // their own code
        if (Config::$CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
            foreach ($this->NAPTR_records as $edupointer) {
                // must be "s" type for SRV
                if ($edupointer["flags"] != "s" && $edupointer["flags"] != "S")
                    $format_errors[] = ["TYPE" => "NAPTR-FLAG", "TARGET" => $edupointer['flag']];
                // no regex
                if ($edupointer["regex"] != "")
                    $format_errors[] = ["TYPE" => "NAPTR-REGEX", "TARGET" => $edupointer['regex']];
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
        $this->return_codes = [];
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
        $this->return_codes[$code]["message"] = _("There were errors during the test.");
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
        $this->return_codes[$code]["message"] = _("NAPTR records were found, but all of them refer to unrelated services.");
        $this->return_codes[$code]["severity"] = L_OK;

// return codes specific to authentication checks
        /**
         * no reply at all from remote RADIUS server
         */
        $code = RETVAL_NO_RESPONSE;
        $this->return_codes[$code]["message"] = _("There was no reply at all from the RADIUS server.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * auth flow stopped somewhere in the middle of a conversation
         */
        $code = RETVAL_SERVER_UNFINISHED_COMM;
        $this->return_codes[$code]["message"] = _("There was a bidirectional communication with the RADIUS server, but it ended halfway through.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * a RADIUS server did not want to talk EAP with us, but at least replied with a Reject
         */
        $code = RETVAL_IMMEDIATE_REJECT;
        $this->return_codes[$code]["message"] = _("The RADIUS server immediately rejected the authentication request in its first reply.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * a RADIUS server talked EAP with us, but didn't like us in the end
         */
        $code = RETVAL_CONVERSATION_REJECT;
        $this->return_codes[$code]["message"] = _("The RADIUS server rejected the authentication request after an EAP conversation.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * a RADIUS server refuses connection
         */
        $code = RETVAL_CONNECTION_REFUSED;
        $this->return_codes[$code]["message"] = _("Connection refused");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * not enough data provided to perform an authentication
         */
        $code = RETVAL_INCOMPLETE_DATA;
        $this->return_codes[$code]["message"] = _("Not enough data provided to perform an authentication");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
          * PKCS12 password does not match the certificate file
          */

        $code = RETVAL_WRONG_PKCS12_PASSWORD;
        $this->return_codes[$code]["message"] = _("The certificate password you provided does not match the certificate file.");
        $this->return_codes[$code]["severity"] = L_ERROR;

// certificate property errors
        /**
         * The root CA certificate was sent by the EAP server.
         */
        $code = CERTPROB_ROOT_INCLUDED;
        $this->return_codes[$code]["message"] = _("The certificate chain includes the root CA certificate. This does not serve any useful purpose but inflates the packet exchange, possibly leading to more round-trips and thus slower authentication.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * There was more than one server certificate in the EAP server's chain.
         */
        $code = CERTPROB_TOO_MANY_SERVER_CERTS;
        $this->return_codes[$code]["message"] = _("There is more than one server certificate in the chain.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * There was no server certificate in the EAP server's chain.
         */
        $code = CERTPROB_NO_SERVER_CERT;
        $this->return_codes[$code]["message"] = _("There is no server certificate in the chain.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * A certificate was signed with an MD5 signature.
         */
        $code = CERTPROB_MD5_SIGNATURE;
        $this->return_codes[$code]["message"] = _("At least one certificate in the chain is signed with the MD5 signature algorithm. Many Operating Systems, including Apple iOS, will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * Low public key length (<1024)
         */
        $code = CERTPROB_LOW_KEY_LENGTH;
        $this->return_codes[$code]["message"] = _("At least one certificate in the chain had a public key of less than 1024 bits. Many recent operating systems consider this unacceptable and will fail to validate the server certificate.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * The server certificate did not contain the TLS Web Server OID, creating compat problems with many Windows versions.
         */
        $code = CERTPROB_NO_TLS_WEBSERVER_OID;
        $this->return_codes[$code]["message"] = _("The server certificate does not have the extension 'extendedKeyUsage: TLS Web Server Authentication'. Most Microsoft Operating Systems will fail to validate this certificate.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8.
         */
        $code = CERTPROB_NO_CDP;
        $this->return_codes[$code]["message"] = _("The server certificate did not include a CRL Distribution Point, creating compatibility problems with Windows Phone 8.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * The server certificate did a CRL Distribution Point, but not to a HTTP/HTTPS URL. Possible compat problems.
         */
        $code = CERTPROB_NO_CDP_HTTP;
        $this->return_codes[$code]["message"] = _("The server certificate's 'CRL Distribution Point' extension does not point to an HTTP/HTTPS URL. Some Operating Systems may fail to validate this certificate. Checking server certificate validity against a CRL will not be possible.");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * The server certificate's CRL Distribution Point URL couldn't be accessed and/or did not contain a CRL.
         */
        $code = CERTPROB_NO_CRL_AT_CDP_URL;
        $this->return_codes[$code]["message"] = _("The extension 'CRL Distribution Point' in the server certificate points to a non-existing location. Some Operating Systems check certificate validity by consulting the CRL and will fail to validate the certificate. Checking server certificate validity against a CRL will not be possible.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The server certificate has been revoked by its CA.
         */
        $code = CERTPROB_SERVER_CERT_REVOKED;
        $this->return_codes[$code]["message"] = _("The server certificate was revoked by the CA!");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The server certificate's names contained at least which was not a hostname.
         */
        $code = CERTPROB_NOT_A_HOSTNAME;
        $this->return_codes[$code]["message"] = _("The certificate contained a CN or subjectAltName:DNS which does not parse as a hostname. This can be problematic on some supplicants. If the certificate also contains names which are a proper hostname, and you only use those for your supplicant configuration, then you can safely ignore this notice.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * The server certificate's names contained at least one wildcard name.
         */
        $code = CERTPROB_WILDCARD_IN_NAME;
        $this->return_codes[$code]["message"] = _("The certificate contained a CN or subjectAltName:DNS which contains a wildcard ('*'). This can be problematic on some supplicants. If the certificate also contains names which are wildcardless, and you only use those for your supplicant configuration, then you can safely ignore this notice.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * cert is not yet, or not any more, valid
         */
        $code = CERTPROB_OUTSIDE_VALIDITY_PERIOD;
        $this->return_codes[$code]["message"] = _("At least one certificate is outside its validity period (not yet valid, or already expired)!");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * cert is not yet, or not any more, valid but is not taking part in server validation
         */
        $code = CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN;
        $this->return_codes[$code]["message"] = sprintf(_("At least one intermediate certificate in your CAT profile is outside its validity period (not yet valid, or already expired), but this certificate was not used for server validation. Consider removing it from your %s configuration."), Config::$APPEARANCE['productname']);
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * The received certificate chain did not end in any of the trust roots configured in the profile properties.
         */
        $code = CERTPROB_TRUST_ROOT_NOT_REACHED;
        $this->return_codes[$code]["message"] = _("The server certificate could not be verified to the root CA you configured in your profile!");
        $this->return_codes[$code]["severity"] = L_ERROR;

        $code = CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES;
        $this->return_codes[$code]["message"] = _("The certificate chain as received in EAP was not sufficient to verify the certificate to the root CA in your profile. It was verified using the intermediate CAs in your profile though. You should consider sending the required intermediate CAs inside the EAP conversation.");
        $this->return_codes[$code]["severity"] = L_REMARK;
        /**
         * The received server certificate's name did not match the configured name in the profile properties.
         */
        $code = CERTPROB_SERVER_NAME_MISMATCH;
        $this->return_codes[$code]["message"] = _("The EAP server name does not match any of the configured names in your profile!");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The received server certificate's name only matched either CN or subjectAltName, but not both
         */
        $code = CERTPROB_SERVER_NAME_PARTIAL_MATCH;
        $this->return_codes[$code]["message"] = _("The configured EAP server name matches either the CN or a subjectAltName:DNS of the incoming certificate; best current practice is that the certificate should contain the name in BOTH places.");
        $this->return_codes[$code]["severity"] = L_REMARK;

        /**
         * The certificate does not set any BasicConstraints; particularly no CA = TRUE|FALSE
         */
        $code = CERTPROB_NO_BASICCONSTRAINTS;
        $this->return_codes[$code]["message"] = _("At least one certificate did not contain any BasicConstraints extension; which makes it unclear if it's a CA certificate or end-entity certificate. At least Mac OS X 10.8 (Mountain Lion) will not validate this certificate for EAP purposes!");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * The server presented a certificate which is from an unknown authority
         */
        $code = CERTPROB_UNKNOWN_CA;
        $this->return_codes[$code]["message"] = _("The server presented a certificate from an unknown authority.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The server accepted this client certificate, but should not have
         */
        $code = CERTPROB_WRONGLY_ACCEPTED;
        $this->return_codes[$code]["message"] = _("The server accepted the INVALID client certificate.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The server does not accept this client certificate, but should have
         */
        $code = CERTPROB_WRONGLY_NOT_ACCEPTED;
        $this->return_codes[$code]["message"] = _("The server rejected the client certificate, even though it was valid.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * The server does not accept this client certificate
         */
        $code = CERTPROB_NOT_ACCEPTED;
        $this->return_codes[$code]["message"] = _("The server rejected the client certificate as expected.");
        $this->return_codes[$code]["severity"] = L_OK;

        /**
         * the CRL of a certificate could not be found
         */
        $code = CERTPROB_UNABLE_TO_GET_CRL;
        $this->return_codes[$code]["message"] = _("The CRL of a certificate could not be found.");
        $this->return_codes[$code]["severity"] = L_ERROR;

        /**
         * the CRL of a certificate could not be found
         */
        $code = CERTPROB_NO_COMMON_EAP_METHOD;
        $this->return_codes[$code]["message"] = _("EAP method negotiation failed!");
        $this->return_codes[$code]["severity"] = L_ERROR;
        
        /** 
         * DH group too small
         */
        $code = CERTPROB_DH_GROUP_TOO_SMALL;
        $this->return_codes[$code]["message"] = _("The server offers Diffie-Hellman (DH) ciphers with a DH group smaller than 1024 bit. Mac OS X 10.11 'El Capitan' is known to refuse TLS connections under these circumstances!");
        $this->return_codes[$code]["severity"] = L_WARN;

        /**
         * The server certificate's names contained at least which was not a hostname.
         */
        $code = CERTPROB_MULTIPLE_CN;
        $this->return_codes[$code]["message"] = _("The certificate contains more than one CommonName (CN) field. This is reportedly problematic on many supplicants.");
        $this->return_codes[$code]["severity"] = L_WARN;
        
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

        $SRV_errors = [];
        $SRV_targets = [];

        foreach ($this->NAPTR_records as $edupointer) {
            $temp_result = dns_get_record($edupointer["replacement"], DNS_SRV);
            if ($temp_result === FALSE || count($temp_result) == 0) {
                $SRV_errors[] = ["TYPE" => "SRV_NOT_RESOLVING", "TARGET" => $edupointer['replacement']];
            } else
                foreach ($temp_result as $res)
                    $SRV_targets[] = ["hostname" => $res["target"], "port" => $res["port"]];
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

        $ip_addresses = [];
        $resolution_errors = [];

        foreach ($this->NAPTR_SRV_records as $server) {
            $host_resolution_6 = dns_get_record($server["hostname"], DNS_AAAA);
            $host_resolution_4 = dns_get_record($server["hostname"], DNS_A);
            $host_resolution = array_merge($host_resolution_6, $host_resolution_4);
            if ($host_resolution === FALSE || count($host_resolution) == 0) {
                $resolution_errors[] = ["TYPE" => "HOST_NO_ADDRESS", "TARGET" => $server['hostname']];
            } else
                foreach ($host_resolution as $address)
                    if (isset($address["ip"]))
                        $ip_addresses[] = ["family" => "IPv4", "IP" => $address["ip"], "port" => $server["port"], "status" => ""];
                    else
                        $ip_addresses[] = ["family" => "IPv6", "IP" => $address["ipv6"], "port" => $server["port"], "status" => ""];
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
     * @param array $servercert the properties of the certificate as returned by processCertificate(), 
     *    $servercert is modified, if CRL is defied, it is downloaded and added to the array
     *    incoming_server_names, sAN_DNS and CN array values are also defined
     * @return array of oddities; the array is empty if everything is fine
     */
    public function property_check_servercert(&$servercert) {
        // debug(4, "SERVER CERT IS: " . print_r($servercert, TRUE));
        // we share the same checks as for CAs when it comes to signature algorithm and basicconstraints
        // so call that function and memorise the outcome
        $returnarray = $this->property_check_intermediate($servercert, TRUE);

        if (!isset($servercert['full_details']['extensions'])) {
            $returnarray[] = CERTPROB_NO_TLS_WEBSERVER_OID;
            $returnarray[] = CERTPROB_NO_CDP_HTTP;
        } else {
            if (!isset($servercert['full_details']['extensions']['extendedKeyUsage']) || !preg_match("/TLS Web Server Authentication/", $servercert['full_details']['extensions']['extendedKeyUsage'])) {
                $returnarray[] = CERTPROB_NO_TLS_WEBSERVER_OID;
            }
        }
        // check for wildcards

        if (isset($servercert['full_details']['subject']['CN'])) {
            if (is_array($servercert['full_details']['subject']['CN']))
                $CN = $servercert['full_details']['subject']['CN'];
            else $CN = [$servercert['full_details']['subject']['CN']];
        }
        else {
            $CN = [""];
        }
        
        if (isset($servercert['full_details']['extensions']) && isset($servercert['full_details']['extensions']['subjectAltName']))
            $sAN_list = explode(", ", $servercert['full_details']['extensions']['subjectAltName']);
        else
            $sAN_list = [];

        $sAN_DNS = [];
        foreach ($sAN_list as $san_name)
            if (preg_match("/^DNS:/", $san_name))
                $sAN_DNS[] = substr($san_name, 4);

        $allnames = array_unique(array_merge($CN, $sAN_DNS));
        
        echo "<pre>".print_r($CN,true)."</pre>";
        echo "<pre>".print_r($sAN_DNS,true)."</pre>";
        echo "<pre>".print_r($allnames,true)."</pre>";
        
        if (preg_match("/\*/", implode($allnames)))
            $returnarray[] = CERTPROB_WILDCARD_IN_NAME;

        // is there more than one CN? None or one is okay, more is asking for trouble.
        if (count($CN) > 1)
            $returnarray[] = CERTPROB_MULTIPLE_CN;
        
        // check for real hostname
        foreach ($allnames as $onename) {
            if ($onename != "" && filter_var("foo@" . idn_to_ascii($onename), FILTER_VALIDATE_EMAIL) === FALSE)
                $returnarray[] = CERTPROB_NOT_A_HOSTNAME;
        }
        $servercert['incoming_server_names'] = $allnames;
        $servercert['sAN_DNS'] = $sAN_DNS;
        $servercert['CN'] = $CN;
        return $returnarray;
    }

    /**
     * This function parses a X.509 intermediate CA cert and checks if it finds client device incompatibilities
     * 
     * @param array $intermediate_ca the properties of the certificate as returned by processCertificate()
     * @param boolean complain_about_cdp_existence: for intermediates, not having a CDP is less of an issue than for servers. Set the REMARK (..._INTERMEDIATE) flag if not complaining; and _SERVER if so
     * @return array of oddities; the array is empty if everything is fine
     */
    public function property_check_intermediate(&$intermediate_ca,$server_cert=FALSE) {
        $returnarray = [];
        if (preg_match("/md5/i", $intermediate_ca['full_details']['signature_algorithm'])) {
            $returnarray[] = CERTPROB_MD5_SIGNATURE;
        }
        debug(4, "CERT IS: " . print_r($intermediate_ca, TRUE));
        if ($intermediate_ca['basicconstraints_set'] == 0) {
            $returnarray[] = CERTPROB_NO_BASICCONSTRAINTS;
        }
        if ($intermediate_ca['full_details']['public_key_length'] < 1024)
            $returnarray[] = CERTPROB_LOW_KEY_LENGTH;
        $from = $intermediate_ca['full_details']['validFrom_time_t'];
        $now = time();
        $to = $intermediate_ca['full_details']['validTo_time_t'];
        if ($from > $now || $to < $now)
            $returnarray[] = CERTPROB_OUTSIDE_VALIDITY_PERIOD;
        $add_cert_crl_result = $this->add_cert_crl($intermediate_ca);
        if( $add_cert_crl_result !== 0  && $server_cert)
            $returnarray[] = $add_cert_crl_result;

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
        // for EAP-TLS to be a viable option, we need to pass a random client cert to make eapol_test happy
        // the following PEM data is one of the SENSE EAPLab client certs (not secret at all)
        $clientcerthandle = fopen(dirname(__FILE__) . "/clientcert.p12", "r");
        debug(4, "Tried to get a useless client cert from" . dirname(__FILE__) . "/clientcert.p12");
        $clientcert = fread($clientcerthandle, filesize(dirname(__FILE__) . "/clientcert.p12"));
        fclose($clientcerthandle);
        return $this->UDP_login($probeindex, EAP::$EAP_ANY, "cat-connectivity-test@" . $this->realm, "eaplab", '',  $opname_check, $frag, $clientcert);
    }

    private function add_cert_crl(&$cert) {
        $crl_url = [];
        $returnresult = 0;
        if (!isset($cert['full_details']['extensions']['crlDistributionPoints'])) {
            $returnresult = CERTPROB_NO_CDP;
        } else if (!preg_match("/^.*URI\:(http)(.*)$/", str_replace(["\r", "\n"], ' ', $cert['full_details']['extensions']['crlDistributionPoints']), $crl_url)) {
            $returnresult = CERTPROB_NO_CDP_HTTP;
        } else { // first and second sub-match is the full URL... check it
            $crlcontent = downloadFile($crl_url[1] . $crl_url[2]);
            if ($crlcontent === FALSE)
                $returnresult = CERTPROB_NO_CRL_AT_CDP_URL;
            $begin_crl = strpos($crlcontent,"-----BEGIN X509 CRL-----");
            if($begin_crl === FALSE) {
                $pem = chunk_split(base64_encode($crlcontent), 64, "\n");
                $crlcontent = "-----BEGIN X509 CRL-----\n".$pem."-----END X509 CRL-----\n";
            }
            $cert['CRL'] = [];
            $cert['CRL'][] = $crlcontent;
        }
        return $returnresult;
    }

    private function redact($string_to_redact, $inputarray) {
        $temparray = preg_replace("/^.*$string_to_redact.*$/", "LINE CONTAINING PASSWORD REDACTED", $inputarray);
        $hex = bin2hex($string_to_redact);
        debug(5, $hex[2]);
        $spaced = "";
        for ($i = 1; $i < strlen($hex); $i++) {
            if ($i % 2 == 1 && $i != strlen($hex)) {
                $spaced .= $hex[$i] . " ";
            } else {
                $spaced .= $hex[$i];
            };
        }
        debug(5, $hex . " HEX " . $spaced);
        return preg_replace("/$spaced/", " HEX ENCODED PASSWORD REDACTED ", $temparray);
    }

    private function filter_packettype($inputarray) {
        $retarray = [];
        foreach ($inputarray as $line) {
            if (preg_match("/RADIUS message:/", $line)) {
                $linecomponents = explode(" ", $line);
                $packettype_exploded = explode("=", $linecomponents[2]);
                $packettype = $packettype_exploded[1];
                $retarray[] = $packettype;
            }
        }
        return $retarray;
    }

    private function check_mschap_691_r($inputarray) {
        foreach ($inputarray as $lineid => $line) {
            if (preg_match("/MSCHAPV2: error 691/", $line) && preg_match("/MSCHAPV2: retry is allowed/", $inputarray[$lineid + 1])) {
                return TRUE;
            }
        }
        return FALSE;
    }

    // this function assumes that there was an EAP conversation; calling it on other packet flows gets undefined results
    // it checks if the flow contained at least one method proposition which was not NAKed
    // returns TRUE if method was ACKed; false if only NAKs in the flow
    private function check_conversation_eap_method_ack($inputarray) {        
        foreach ($inputarray as $lineid => $line) {
            if (preg_match("/CTRL-EVENT-EAP-PROPOSED-METHOD/", $line) && ! preg_match("/NAK$/", $line)) {
                return TRUE;
            }
        }
        return FALSE;
    }
    
    public function UDP_login($probeindex, $eaptype, $user, $password, $outer_user = '',  $opname_check = TRUE, $frag = TRUE, $clientcertdata = NULL) {
        if (!isset(Config::$RADIUSTESTS['UDP-hosts'][$probeindex])) {
            $this->UDP_reachability_executed = RETVAL_NOTCONFIGURED;
            return RETVAL_NOTCONFIGURED;
        }

        if($outer_user == "") {
            $anon_id = ""; // our default of last resort. Will check if servers choke on the IETF-recommended anon ID format.
            if ($this->profile instanceof Profile) { // take profile's anon ID if known
                $foo = $this->profile;
                if ($foo->use_anon_outer == TRUE && $foo->realm = $this->realm) {
                    $the_id = $foo->getAttributes("internal:anon_local_value");
                    $anon_id = $the_id[0]['value'];
                }
            }
        } 

        // we will need a config blob for wpa_supplicant, in a temporary directory
        // code is copy&paste from DeviceConfig.php

        $T = createTemporaryDirectory('test');
        $tmp_dir = $T['dir'];
        chdir($tmp_dir);
        debug(4, "temp dir: $tmp_dir\n");
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
            if ($eaptype == EAP::$EAP_ANY) { // add a junk client cert
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
        if( ($outer_user != "" && preg_match("/@/", $outer_user))) {
            $config .= '  anonymous_identity="';
            $log_config .= '  anonymous_identity="';
            $config .= $outer_user . "\"\n";
            $log_config .= $outer_user . "\"\n";
        }
        // done
        $config .= "}";
        $log_config .= "}";
        // the config intentionally does not include CA checking. We do this
        // ourselves after getting the chain with -o.

        fwrite($wpa_supplicant_config, $config);
        fclose($wpa_supplicant_config);

        $testresults = [];
        $testresults['cert_oddities'] = [];
        $packetflow_orig = [];
        $packetflow = [];
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
                $cmdline .= '-N26:x:0000625A0BF961616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161616161 ';

        debug(4, "Shallow reachability check cmdline: $cmdline\n");
        debug(4, "Shallow reachability check config: $tmp_dir\n$log_config\n");
        $time_start = microtime(true);
        exec($cmdline, $packetflow_orig);
        $time_stop = microtime(true);
        debug(5, print_r($this->redact($password, $packetflow_orig), TRUE));
        $packetflow = $this->filter_packettype($packetflow_orig);
        if ($packetflow[count($packetflow) - 1] == 11 && $this->check_mschap_691_r($packetflow_orig))
            $packetflow[count($packetflow) - 1] = 3;
        debug(5, "Packetflow: " . print_r($packetflow, TRUE));
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

        // calculate the main return values that this test yielded

        $finalretval = RETVAL_INVALID;
        if ($accepts + $rejects == 0) { // no final response. hm.
            if ($challenges > 0) { // but there was an Access-Challenge
                $finalretval = RETVAL_SERVER_UNFINISHED_COMM;
            } else {
                $finalretval = RETVAL_NO_RESPONSE;
            }
        } else // either an accept or a reject
        // rejection without EAP is fishy
        if ($rejects > 0) {
            if ($challenges == 0) {
                $finalretval = RETVAL_IMMEDIATE_REJECT;
            } else { // i.e. if rejected with challenges
                $finalretval = RETVAL_CONVERSATION_REJECT;
            }
        } else if ($accepts > 0) {
            $finalretval = RETVAL_OK;
        }

        if ($finalretval == RETVAL_CONVERSATION_REJECT) {
            $ackedmethod = $this->check_conversation_eap_method_ack($packetflow_orig);            
            if (!$ackedmethod)
                $testresults['cert_oddities'][] = CERTPROB_NO_COMMON_EAP_METHOD;
        };

            
        // now let's look at the server cert+chain
        // if we got a cert at all
        // TODO: also only do this if EAP types all mismatched; we won't have a
        // cert in that case
        if (
           $eaptype != EAP::$PWD && 
           (($finalretval == RETVAL_CONVERSATION_REJECT && $ackedmethod) || $finalretval == RETVAL_OK)
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
            $x509 = new X509();
            // $eap_certarray holds all certs received in EAP conversation
            $eap_certarray = $x509->splitCertificate(fread(fopen($tmp_dir . "/serverchain.pem", "r"), "1000000"));
            // we want no root cert, and exactly one server cert
            $number_root = 0;
            $number_server = 0;
            $eap_number_intermediate = 0;
            $cat_number_intermediate = 0;
            $servercert;
            $totally_selfsigned = FALSE;


            // Write the root CAs into a trusted root CA dir
            // and intermediate and first server cert into a PEM file
            // for later chain validation
            $CRLs = []; // if one is missing, set to FALSE

            $server_file = fopen($tmp_dir . "/incomingserver.pem", "w");

            if (!mkdir($tmp_dir . "/root-ca-allcerts/", 0700, true)) {
                error("unable to create root CA directory (RADIUS Tests): $tmp_dir/root-ca-allcerts/\n");
                exit;
            }

            if (!mkdir($tmp_dir . "/root-ca-eaponly/", 0700, true)) {
                error("unable to create root CA directory (RADIUS Tests): $tmp_dir/root-ca-eaponly/\n");
                exit;
            }

            $eap_intermediate_oddities = [];
            $testresults['certdata']=[];
            
            foreach ($eap_certarray as $cert_pem) {
                $cert = $x509->processCertificate($cert_pem);
                if ($cert == FALSE)
                    continue;
                // consider the certificate a server cert 
                // a) if it is not a CA and is not a self-signed root
                // b) if it is a CA, and self-signed, and it is the only cert in
                //    the incoming cert chain
                //    (meaning the self-signed is itself the server cert)
                if (($cert['ca'] == 0 && $cert['root'] != 1) || ($cert['ca'] == 1 && $cert['root'] == 1 && count($eap_certarray)==1)) {
                    if ($cert['ca'] == 1 && $cert['root'] == 1 && count($eap_certarray)==1) {
                        $totally_selfsigned = TRUE;
                    }
                    $number_server++;
                    $servercert = $cert;
                    if ($number_server == 1) {
                        fwrite($server_file, $cert_pem."\n");
                    }
                } else
                if ($cert['root'] == 1) {
                    $number_root++;
                    // do not save the root CA, it serves no purpose
                    // chain checks need to be against the UPLOADED CA of the
                    // IdP/profile, not against an EAP-discovered CA
                } else {
                    $eap_intermediate_oddities = array_merge($eap_intermediate_oddities, $this->property_check_intermediate($cert));
                    $intermediate_file = fopen($tmp_dir . "/root-ca-eaponly/incomingintermediate$eap_number_intermediate.pem", "w");
                    fwrite($intermediate_file, $cert_pem."\n");
                    fclose($intermediate_file);
                    $intermediate_file = fopen($tmp_dir . "/root-ca-allcerts/incomingintermediate$eap_number_intermediate.pem", "w");
                    fwrite($intermediate_file, $cert_pem."\n");
                    fclose($intermediate_file);


                    if (isset($cert['CRL']) && isset($cert['CRL'][0])) {
                        debug(4, "got an intermediate CRL; adding them to the chain checks. (Remember: checking end-entity cert only, not the whole chain");
                        $CRL_file = fopen($tmp_dir . "/root-ca-eaponly/crl$eap_number_intermediate.pem", "w"); // this is where the root CAs go
                        fwrite($CRL_file, $cert['CRL'][0]);
                        fclose($CRL_file);
                        $CRL_file = fopen($tmp_dir . "/root-ca-allcerts/crl$eap_number_intermediate.pem", "w"); // this is where the root CAs go
                        fwrite($CRL_file, $cert['CRL'][0]);
                        fclose($CRL_file);
                    }
                    $eap_number_intermediate++;
                }
                $testresults['certdata'][] = $cert['full_details'];
            }
            fclose($server_file);
            if ($number_root > 0 && !$totally_selfsigned)
                $testresults['cert_oddities'][] = CERTPROB_ROOT_INCLUDED;
            if ($number_server > 1)
                $testresults['cert_oddities'][] = CERTPROB_TOO_MANY_SERVER_CERTS;
            if ($number_server == 0)
                $testresults['cert_oddities'][] = CERTPROB_NO_SERVER_CERT;
            // check server cert properties
            if ($number_server > 0) {
                $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'], $this->property_check_servercert($servercert));
                $testresults['incoming_server_names'] = $servercert['incoming_server_names'];
            }

            // check intermediate ca cert properties
            // check trust chain for completeness
            // works only for thorough checks, not shallow, so:
            $cat_intermediate_oddities = [];
            $verify_result = 0;
            if ($this->profile) {
                $number_configured_roots = 0;
                $my_profile = $this->profile;
                // $ca_store contains certificates configured in the CAT profile
                $ca_store = $my_profile->getAttributes("eap:ca_file");
                // make a copy of the EAP-received chain and add the configured intermediates, if any
                foreach ($ca_store as $one_ca) {
                    $x509 = new X509();
                    $decoded = $x509->processCertificate($one_ca['value']);
                    if ($decoded['ca'] == 1) {
                        if ($decoded['root'] == 1) { // save CAT roots to the root directory
                            $root_CA = fopen($tmp_dir . "/root-ca-eaponly/configuredroot$number_configured_roots.pem", "w"); // this is where the root CAs go
                            fwrite($root_CA, $one_ca['value']);
                            fclose($root_CA);
                            $root_CA = fopen($tmp_dir . "/root-ca-allcerts/configuredroot$number_configured_roots.pem", "w"); // this is where the root CAs go
                            fwrite($root_CA, $one_ca['value']);
                            fclose($root_CA);
                            $number_configured_roots = $number_configured_roots + 1;
                        } else { // save the intermadiates to allcerts directory
                            $intermediate_file = fopen($tmp_dir . "/root-ca-allcerts/cat-intermediate$cat_number_intermediate.pem", "w");
                            fwrite($intermediate_file, $one_ca['value']);
                            fclose($intermediate_file);

                            $cat_intermediate_oddities = array_merge($cat_intermediate_oddities, $this->property_check_intermediate($decoded));
                            if (isset($decoded['CRL']) && isset($decoded['CRL'][0])) {
                                debug(4, "got an intermediate CRL; adding them to the chain checks. (Remember: checking end-entity cert only, not the whole chain");
                                $CRL_file = fopen($tmp_dir . "/root-ca-allcerts/crl_cat$cat_number_intermediate.pem", "w"); // this is where the root CAs go
                                fwrite($CRL_file, $decoded['CRL'][0]);
                                fclose($CRL_file);
                            }
                            $cat_number_intermediate++;
                        }
                    }
                }
                if ($number_server > 0)
                    debug(4, "This is the server certificate, with CRL content if applicable: " . print_r($servercert, true));
                $checkstring = "";
                if (isset($servercert['CRL']) && isset($servercert['CRL'][0])) {
                    debug(4, "got a server CRL; adding them to the chain checks. (Remember: checking end-entity cert only, not the whole chain");
                    $checkstring = "-crl_check_all";
                    $CRL_file = fopen($tmp_dir . "/root-ca-eaponly/crl$crlindex.pem", "w"); // this is where the root CAs go
                    fwrite($CRL_file, $servercert['CRL'][0]);
                    fclose($CRL_file);
                    $CRL_file = fopen($tmp_dir . "/root-ca-allcerts/crl$crlindex.pem", "w"); // this is where the root CAs go
                    fwrite($CRL_file, $servercert['CRL'][0]);
                    fclose($CRL_file);
                }

                // save all intermediate certificate CRLs to separate files in root-ca directory

                // now c_rehash the root CA directory ...
                system(Config::$PATHS['c_rehash'] . " $tmp_dir/root-ca-eaponly/ > /dev/null");
                system(Config::$PATHS['c_rehash'] . " $tmp_dir/root-ca-allcerts/ > /dev/null");

                // ... and run the verification test
                $verify_result_eaponly = [];
                // the error log will complain if we run this test against an empty file of certs
                // so test if there's something PEMy in the file at all
                if (filesize("$tmp_dir/incomingserver.pem") > 10) {
                    exec(Config::$PATHS['openssl'] . " verify $checkstring -CApath $tmp_dir/root-ca-eaponly/ -purpose any $tmp_dir/incomingserver.pem", $verify_result_eaponly);
                    debug(4,Config::$PATHS['openssl'] . " verify $checkstring -CApath $tmp_dir/root-ca-eaponly/ -purpose any $tmp_dir/incomingserver.pem\n");
                debug(4, "Chain verify pass 1: " . print_r($verify_result_eaponly, TRUE) . "\n");
                    exec(Config::$PATHS['openssl'] . " verify $checkstring -CApath $tmp_dir/root-ca-allcerts/ -purpose any $tmp_dir/incomingserver.pem", $verify_result_allcerts);
                    debug(4,Config::$PATHS['openssl'] . " verify $checkstring -CApath $tmp_dir/root-ca-allcerts/ -purpose any $tmp_dir/incomingserver.pem\n");
                debug(4, "Chain verify pass 2: " . print_r($verify_result_allcerts, TRUE) . "\n");
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
                if (count($verify_result_allcerts) > 0) {
                    if (!preg_match("/OK$/", $verify_result_allcerts[0])) { // case 1
                        $verify_result = 1;
                        if (preg_match("/certificate revoked$/", $verify_result_allcerts[1])) {
                            $testresults['cert_oddities'][] = CERTPROB_SERVER_CERT_REVOKED;
                        } elseif(preg_match("/unable to get certificate CRL/", $verify_result_allcerts[1])) {
                            $testresults['cert_oddities'][] = CERTPROB_UNABLE_TO_GET_CRL;
                        } else {
                            $testresults['cert_oddities'][] = CERTPROB_TRUST_ROOT_NOT_REACHED;
                        }
                    } else if (!preg_match("/OK$/", $verify_result_eaponly[0])) { // case 2
                        $verify_result = 2;
                        if (preg_match("/certificate revoked$/", $verify_result_eaponly[1])) {
                            $testresults['cert_oddities'][] = CERTPROB_SERVER_CERT_REVOKED;
                        } elseif(preg_match("/unable to get certificate CRL/", $verify_result_eaponly[1])) {
                            $testresults['cert_oddities'][] = CERTPROB_UNABLE_TO_GET_CRL;
                        } else {
                            $testresults['cert_oddities'][] = CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES;
                        }
                    } else { // case 3
                        $verify_result = 3;
                    }
                }

                // check the incoming hostname (both Subject:CN and subjectAltName:DNS
                // against what is configured in the profile; it's a significant error
                // if there is no match!
                // FAIL if none of the configured names show up in the server cert
                // WARN if the configured name is only in either CN or sAN:DNS
                $confnames = $my_profile->getAttributes("eap:server_name");
                $expected_names = [];
                foreach ($confnames as $tuple)
                    $expected_names[] = $tuple['value'];

                // Strategy for checks: we are TOTALLY happy if any one of the
                // configured names shows up in both the CN and a sAN
                // This is the primary check.
                // If that was not the case, we are PARTIALLY happy if any one of
                // the configured names was in either of the CN or sAN lists.
                // we are UNHAPPY if no names match!
                $happiness = "UNHAPPY";
                foreach ($expected_names as $expected_name) {
                    debug(4, "Managing expectations for $expected_name: " . print_r($servercert['CN'], TRUE) . print_r($servercert['sAN_DNS'], TRUE));
                    if (array_search($expected_name, $servercert['CN']) !== FALSE && array_search($expected_name, $servercert['sAN_DNS']) !== FALSE) {
                        debug(4, "Totally happy!");
                        $happiness = "TOTALLY";
                        break;
                    } else {
                        if (array_search($expected_name, $servercert['CN']) !== FALSE || array_search($expected_name, $servercert['sAN_DNS']) !== FALSE) {
                            $happiness = "PARTIALLY";
                            // keep trying with other expected names! We could be happier!
                        }
                    }
                }
                switch ($happiness) {
                    case "UNHAPPY":
                        $testresults['cert_oddities'][] = CERTPROB_SERVER_NAME_MISMATCH;
                        break;
                    case "PARTIALLY":
                        $testresults['cert_oddities'][] = CERTPROB_SERVER_NAME_PARTIAL_MATCH;
                        break;
                    default: // nothing to complain about!
                        break;
                }

                // TODO: dump the details in a class variable in case someone cares
            }
            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'],$eap_intermediate_oddities); 
            if (in_array(CERTPROB_OUTSIDE_VALIDITY_PERIOD, $cat_intermediate_oddities) && $verify_result == 3) {
                 $key = array_search(CERTPROB_OUTSIDE_VALIDITY_PERIOD, $cat_intermediate_oddities);
                 $cat_intermediate_oddities[$key] = CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN;
            }

            $testresults['cert_oddities'] = array_merge($testresults['cert_oddities'],$cat_intermediate_oddities); 

            // mention trust chain failure only if no expired cert was in the chain; otherwise path validation will trivially fail
            if (in_array(CERTPROB_OUTSIDE_VALIDITY_PERIOD, $testresults['cert_oddities'])) {
                debug(4, "Deleting trust chain problem report, if present.");
                if (($key = array_search(CERTPROB_TRUST_ROOT_NOT_REACHED, $testresults['cert_oddities'])) !== false) {
                    unset($testresults['cert_oddities'][$key]);
                }
                if (($key = array_search(CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES, $testresults['cert_oddities'])) !== false) {
                    unset($testresults['cert_oddities'][$key]);
                }
            }
        }
debug(4,"UDP_LOGIN\n");
debug(4,$testresults);
debug(4,"\nEND\n");
        $this->UDP_reachability_result[$probeindex] = $testresults;
        $this->UDP_reachability_executed = $finalretval;
        return $finalretval;
    }

    /**
     * This function parses a X.509 cert and returns all certificatePolicies OIDs
     * 
     * @param structure $cert (returned from openssl_x509_parse) 
     * @return array of OIDs
     */
    function property_check_policy($cert) {
        $oids = [];
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
    function openssl_s_client($host, $arg, &$testresults) {
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
    function openssl_result($host, $testtype, $opensslbabble, &$testresults, $type = '', $k = 0) {
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
        if (!isset($this->TLS_CA_checks_result[$host]))
            $this->TLS_CA_checks_result[$host] = [];
        $opensslbabble = $this->openssl_s_client($host, '', $this->TLS_CA_checks_result[$host]);
        fputs($f, serialize($this->TLS_CA_checks_result) . "\n");
        $res = $this->openssl_result($host, 'capath', $opensslbabble, $this->TLS_CA_checks_result);
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
                    if (!isset($this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]))
                        $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k] = [];
                    $opensslbabble = $this->openssl_s_client($host, $add, $this->TLS_clients_checks_result[$host]['ca'][$type]['certificate'][$k]);
                    $res = $this->openssl_result($host, 'clients', $opensslbabble, $this->TLS_clients_checks_result, $type, $k);
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
