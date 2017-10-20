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

/**
 * base class of the various test classes.
 * 
 * Its main purpose is to initialise some error messages.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class AbstractTest extends \core\common\Entity {

    /**
     * generic return codes
     * 
     * @var array
     */
    public $returnCodes;

    /**
     * Test was executed and the result was as expected.
     */
    const RETVAL_OK = 0;

    /**
     * Test could not be run because CAT software isn't configured for it
     */
    const RETVAL_NOTCONFIGURED = -100;

    /**
     * Test skipped because there was nothing to be done
     */
    const RETVAL_SKIPPED = -101;

    /**
     * test executed, and there were errors
     */
    const RETVAL_INVALID = -103;
// return codes specific to authentication checks
    /**
     * no reply at all from remote RADIUS server
     */
    const RETVAL_NO_RESPONSE = -106;

    /**
     * auth flow stopped somewhere in the middle of a conversation
     */
    const RETVAL_SERVER_UNFINISHED_COMM = -107;

    /**
     * a RADIUS server did not want to talk EAP with us, but at least replied with a Reject
     */
    const RETVAL_IMMEDIATE_REJECT = -108;

    /**
     * a RADIUS server talked EAP with us, but didn't like us in the end
     */
    const RETVAL_CONVERSATION_REJECT = -109;

    /**
     * a RADIUS server refuses connection
     */
    const RETVAL_CONNECTION_REFUSED = -110;

    /**
     * not enough data provided to perform an authentication
     */
    const RETVAL_INCOMPLETE_DATA = -111;

    /**
     * PKCS12 password does not match the certificate file
     */
    const RETVAL_WRONG_PKCS12_PASSWORD = -112;
// certificate property errors
    /**
     * The root CA certificate was sent by the EAP server.
     */
    const CERTPROB_ROOT_INCLUDED = -200;

    /**
     * There was more than one server certificate in the EAP server's chain.
     */
    const CERTPROB_TOO_MANY_SERVER_CERTS = -201;

    /**
     * There was no server certificate in the EAP server's chain.
     */
    const CERTPROB_NO_SERVER_CERT = -202;

    /**
     * The/a server certificate was signed with an MD5 signature.
     */
    const CERTPROB_MD5_SIGNATURE = -204;

    /**
     * The/a server certificate was signed with an MD5 signature.
     */
    const CERTPROB_SHA1_SIGNATURE = -227;

    /**
     * one of the keys in the cert chain was smaller than 1024 bits
     */
    const CERTPROB_LOW_KEY_LENGTH = -220;

    /**
     * The server certificate did not contain the TLS Web Server OID, creating compat problems with many Windows versions.
     */
    const CERTPROB_NO_TLS_WEBSERVER_OID = -205;

    /**
     * The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8.
     */
    const CERTPROB_NO_CDP = -206;

    /**
     * The server certificate did a CRL Distribution Point, but not to a HTTP/HTTPS URL. Possible compat problems.
     */
    const CERTPROB_NO_CDP_HTTP = -207;

    /**
     * The server certificate's CRL Distribution Point URL couldn't be accessed and/or did not contain a CRL.
     */
    const CERTPROB_NO_CRL_AT_CDP_URL = -208;

    /**
     * certificate is not currently valid (expired/not yet valid)
     */
    const CERTPROB_SERVER_CERT_REVOKED = -222;

    /**
     * The received server certificate is revoked.
     */
    const CERTPROB_OUTSIDE_VALIDITY_PERIOD = -221;

    /**
     * At least one certificate is outside its validity period (not yet valid, or already expired)!
     */
    const CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN = -225;

    /**
     * At least one certificate is outside its validity period, but this certificate does not take part in servder validation 
     */
    const CERTPROB_TRUST_ROOT_NOT_REACHED = -209;

    /**
     * The received certificate chain did not carry the necessary intermediate CAs in the EAP conversation. Only the CAT Intermediate CA installation can complete the chain.
     */
    const CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES = -216;

    /**
     * The received server certificate's name did not match the configured name in the profile properties.
     */
    const CERTPROB_SERVER_NAME_MISMATCH = -210;

    /**
     * The received server certificate's name did not match the configured name in the profile properties.
     */
    const CERTPROB_SERVER_NAME_PARTIAL_MATCH = -217;

    /**
     * One of the names in the cert was not a hostname.
     */
    const CERTPROB_NOT_A_HOSTNAME = -218;

    /**
     * One of the names contained a wildcard character.
     */
    const CERTPROB_WILDCARD_IN_NAME = -219;

    /**
     * The certificate does not set any BasicConstraints; particularly no CA = TRUE|FALSE
     */
    const CERTPROB_NO_BASICCONSTRAINTS = -211;

    /**
     * The server presented a certificate which is from an unknown authority
     */
    const CERTPROB_UNKNOWN_CA = -212;

    /**
     * The server accepted this client certificate, but should not have
     */
    const CERTPROB_WRONGLY_ACCEPTED = -213;

    /**
     * The server does not accept this client certificate, but should have
     */
    const CERTPROB_WRONGLY_NOT_ACCEPTED = -214;

    /**
     * The server does accept this client certificate
     */
    const CERTPROB_NOT_ACCEPTED = -215;

    /**
     * the CRL of a certificate could not be found
     */
    const CERTPROB_UNABLE_TO_GET_CRL = 223;

    /**
     * no EAP method could be agreed on, certs could not be extraced
     */
    const CERTPROB_NO_COMMON_EAP_METHOD = -224;

    /**
     * Diffie-Hellman groups need to be 1024 bit at least, starting with OS X 10.11
     */
    const CERTPROB_DH_GROUP_TOO_SMALL = -225;

    /**
     * There is more than one CN in the certificate
     */
    const CERTPROB_MULTIPLE_CN = -226;

    /**
     * initialises the error messages.
     */
    public function __construct() {
        parent::__construct();
        $oldlocale = $this->languageInstance->setTextDomain('diagnostics');
        $this->returnCodes = [];
        /**
         * Test was executed and the result was as expected.
         */
        $code1 = RADIUSTests::RETVAL_OK;
        $this->returnCodes[$code1]["message"] = _("Completed");
        $this->returnCodes[$code1]["severity"] = \core\common\Entity::L_OK;

        /**
         * Test could not be run because CAT software isn't configured for it
         */
        $code2 = RADIUSTests::RETVAL_NOTCONFIGURED;
        $this->returnCodes[$code2]["message"] = _("Product is not configured to run this check.");
        $this->returnCodes[$code2]["severity"] = \core\common\Entity::L_OK;
        /**
         * Test skipped because there was nothing to be done
         */
        $code3 = RADIUSTests::RETVAL_SKIPPED;
        $this->returnCodes[$code3]["message"] = _("This check was skipped.");
        $this->returnCodes[$code3]["severity"] = \core\common\Entity::L_OK;

        /**
         * test executed, and there were errors
         */
        $code4 = RADIUSTests::RETVAL_INVALID;
        $this->returnCodes[$code4]["message"] = _("There were errors during the test.");
        $this->returnCodes[$code4]["severity"] = \core\common\Entity::L_OK;

// return codes specific to authentication checks
        /**
         * no reply at all from remote RADIUS server
         */
        $code7 = RADIUSTests::RETVAL_NO_RESPONSE;
        $this->returnCodes[$code7]["message"] = _("There was no reply at all from the RADIUS server.");
        $this->returnCodes[$code7]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * auth flow stopped somewhere in the middle of a conversation
         */
        $code8 = RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM;
        $this->returnCodes[$code8]["message"] = _("There was a bidirectional communication with the RADIUS server, but it ended halfway through.");
        $this->returnCodes[$code8]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * a RADIUS server did not want to talk EAP with us, but at least replied with a Reject
         */
        $code9 = RADIUSTests::RETVAL_IMMEDIATE_REJECT;
        $this->returnCodes[$code9]["message"] = _("The RADIUS server immediately rejected the authentication request in its first reply.");
        $this->returnCodes[$code9]["severity"] = \core\common\Entity::L_WARN;

        /**
         * a RADIUS server talked EAP with us, but didn't like us in the end
         */
        $code10 = RADIUSTests::RETVAL_CONVERSATION_REJECT;
        $this->returnCodes[$code10]["message"] = _("The RADIUS server rejected the authentication request after an EAP conversation.");
        $this->returnCodes[$code10]["severity"] = \core\common\Entity::L_WARN;

        /**
         * a RADIUS server refuses connection
         */
        $code11 = RADIUSTests::RETVAL_CONNECTION_REFUSED;
        $this->returnCodes[$code11]["message"] = _("Connection refused");
        $this->returnCodes[$code11]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * not enough data provided to perform an authentication
         */
        $code12 = RADIUSTests::RETVAL_INCOMPLETE_DATA;
        $this->returnCodes[$code12]["message"] = _("Not enough data provided to perform an authentication");
        $this->returnCodes[$code12]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * PKCS12 password does not match the certificate file
         */
        $code13 = RADIUSTests::RETVAL_WRONG_PKCS12_PASSWORD;
        $this->returnCodes[$code13]["message"] = _("The certificate password you provided does not match the certificate file.");
        $this->returnCodes[$code13]["severity"] = \core\common\Entity::L_ERROR;

// certificate property errors
        /**
         * The root CA certificate was sent by the EAP server.
         */
        $code14 = RADIUSTests::CERTPROB_ROOT_INCLUDED;
        $this->returnCodes[$code14]["message"] = _("The certificate chain includes the root CA certificate. This does not serve any useful purpose but inflates the packet exchange, possibly leading to more round-trips and thus slower authentication.");
        $this->returnCodes[$code14]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * There was more than one server certificate in the EAP server's chain.
         */
        $code15 = RADIUSTests::CERTPROB_TOO_MANY_SERVER_CERTS;
        $this->returnCodes[$code15]["message"] = _("There is more than one server certificate in the chain.");
        $this->returnCodes[$code15]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * There was no server certificate in the EAP server's chain.
         */
        $code16 = RADIUSTests::CERTPROB_NO_SERVER_CERT;
        $this->returnCodes[$code16]["message"] = _("There is no server certificate in the chain.");
        $this->returnCodes[$code16]["severity"] = \core\common\Entity::L_WARN;

        /**
         * A certificate was signed with an MD5 signature.
         */
        $code17 = RADIUSTests::CERTPROB_MD5_SIGNATURE;
        $this->returnCodes[$code17]["message"] = _("At least one certificate in the chain is signed with the MD5 signature algorithm. Many Operating Systems, including Apple iOS, will fail to validate this certificate.");
        $this->returnCodes[$code17]["severity"] = \core\common\Entity::L_WARN;

        /**
         * A certificate was signed with an SHA1 signature.
         */
        $code17a = RADIUSTests::CERTPROB_SHA1_SIGNATURE;
        $this->returnCodes[$code17a]["message"] = _("At least one certificate in the chain is signed with the SHA-1 signature algorithm. Many Operating Systems, including Apple iOS, will fail to validate this certificate.");
        $this->returnCodes[$code17a]["severity"] = \core\common\Entity::L_WARN;

        /**
         * Low public key length (<1024)
         */
        $code18 = RADIUSTests::CERTPROB_LOW_KEY_LENGTH;
        $this->returnCodes[$code18]["message"] = _("At least one certificate in the chain had a public key of less than 1024 bits. Many recent operating systems consider this unacceptable and will fail to validate the server certificate.");
        $this->returnCodes[$code18]["severity"] = \core\common\Entity::L_WARN;

        /**
         * The server certificate did not contain the TLS Web Server OID, creating compat problems with many Windows versions.
         */
        $code19 = RADIUSTests::CERTPROB_NO_TLS_WEBSERVER_OID;
        $this->returnCodes[$code19]["message"] = _("The server certificate does not have the extension 'extendedKeyUsage: TLS Web Server Authentication'. Most Microsoft Operating Systems will fail to validate this certificate.");
        $this->returnCodes[$code19]["severity"] = \core\common\Entity::L_WARN;

        /**
         * The server certificate did not include a CRL Distribution Point, creating compat problems with Windows Phone 8.
         */
        $code20 = RADIUSTests::CERTPROB_NO_CDP;
        $this->returnCodes[$code20]["message"] = _("The server certificate did not include a CRL Distribution Point, creating compatibility problems with Windows Phone 8.");
        $this->returnCodes[$code20]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * The server certificate did a CRL Distribution Point, but not to a HTTP/HTTPS URL. Possible compat problems.
         */
        $code21 = RADIUSTests::CERTPROB_NO_CDP_HTTP;
        $this->returnCodes[$code21]["message"] = _("The server certificate's 'CRL Distribution Point' extension does not point to an HTTP/HTTPS URL. Some Operating Systems may fail to validate this certificate. Checking server certificate validity against a CRL will not be possible.");
        $this->returnCodes[$code21]["severity"] = \core\common\Entity::L_WARN;

        /**
         * The server certificate's CRL Distribution Point URL couldn't be accessed and/or did not contain a CRL.
         */
        $code22 = RADIUSTests::CERTPROB_NO_CRL_AT_CDP_URL;
        $this->returnCodes[$code22]["message"] = _("The extension 'CRL Distribution Point' in the server certificate points to a non-existing location. Some Operating Systems check certificate validity by consulting the CRL and will fail to validate the certificate. Checking server certificate validity against a CRL will not be possible.");
        $this->returnCodes[$code22]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The server certificate has been revoked by its CA.
         */
        $code23 = RADIUSTests::CERTPROB_SERVER_CERT_REVOKED;
        $this->returnCodes[$code23]["message"] = _("The server certificate was revoked by the CA!");
        $this->returnCodes[$code23]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The server certificate's names contained at least which was not a hostname.
         */
        $code24 = RADIUSTests::CERTPROB_NOT_A_HOSTNAME;
        $this->returnCodes[$code24]["message"] = _("The certificate contained a CN or subjectAltName:DNS which does not parse as a hostname. This can be problematic on some supplicants. If the certificate also contains names which are a proper hostname, and you only use those for your supplicant configuration, then you can safely ignore this notice.");
        $this->returnCodes[$code24]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * The server certificate's names contained at least one wildcard name.
         */
        $code25 = RADIUSTests::CERTPROB_WILDCARD_IN_NAME;
        $this->returnCodes[$code25]["message"] = _("The certificate contained a CN or subjectAltName:DNS which contains a wildcard ('*'). This can be problematic on some supplicants. If the certificate also contains names which are wildcardless, and you only use those for your supplicant configuration, then you can safely ignore this notice.");
        $this->returnCodes[$code25]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * cert is not yet, or not any more, valid
         */
        $code26 = RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD;
        $this->returnCodes[$code26]["message"] = _("At least one certificate is outside its validity period (not yet valid, or already expired)!");
        $this->returnCodes[$code26]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * cert is not yet, or not any more, valid but is not taking part in server validation
         */
        $code27 = RADIUSTests::CERTPROB_OUTSIDE_VALIDITY_PERIOD_WARN;
        $this->returnCodes[$code27]["message"] = sprintf(_("At least one intermediate certificate in your CAT profile is outside its validity period (not yet valid, or already expired), but this certificate was not used for server validation. Consider removing it from your %s configuration."), CONFIG['APPEARANCE']['productname']);
        $this->returnCodes[$code27]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * The received certificate chain did not end in any of the trust roots configured in the profile properties.
         */
        $code28 = RADIUSTests::CERTPROB_TRUST_ROOT_NOT_REACHED;
        $this->returnCodes[$code28]["message"] = _("The server certificate could not be verified to the root CA you configured in your profile!");
        $this->returnCodes[$code28]["severity"] = \core\common\Entity::L_ERROR;

        $code29 = RADIUSTests::CERTPROB_TRUST_ROOT_REACHED_ONLY_WITH_OOB_INTERMEDIATES;
        $this->returnCodes[$code29]["message"] = _("The certificate chain as received in EAP was not sufficient to verify the certificate to the root CA in your profile. It was verified using the intermediate CAs in your profile though. You should consider sending the required intermediate CAs inside the EAP conversation.");
        $this->returnCodes[$code29]["severity"] = \core\common\Entity::L_REMARK;
        /**
         * The received server certificate's name did not match the configured name in the profile properties.
         */
        $code30 = RADIUSTests::CERTPROB_SERVER_NAME_MISMATCH;
        $this->returnCodes[$code30]["message"] = _("The EAP server name does not match any of the configured names in your profile!");
        $this->returnCodes[$code30]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The received server certificate's name only matched either CN or subjectAltName, but not both
         */
        $code31 = RADIUSTests::CERTPROB_SERVER_NAME_PARTIAL_MATCH;
        $this->returnCodes[$code31]["message"] = _("The configured EAP server name matches either the CN or a subjectAltName:DNS of the incoming certificate; best current practice is that the certificate should contain the name in BOTH places.");
        $this->returnCodes[$code31]["severity"] = \core\common\Entity::L_REMARK;

        /**
         * The certificate does not set any BasicConstraints; particularly no CA = TRUE|FALSE
         */
        $code32 = RADIUSTests::CERTPROB_NO_BASICCONSTRAINTS;
        $this->returnCodes[$code32]["message"] = _("At least one certificate did not contain any BasicConstraints extension; which makes it unclear if it's a CA certificate or end-entity certificate. At least Mac OS X 10.8 (Mountain Lion) will not validate this certificate for EAP purposes!");
        $this->returnCodes[$code32]["severity"] = \core\common\Entity::L_WARN;

        /**
         * The server presented a certificate which is from an unknown authority
         */
        $code33 = RADIUSTests::CERTPROB_UNKNOWN_CA;
        $this->returnCodes[$code33]["message"] = _("The server presented a certificate from an unknown authority.");
        $this->returnCodes[$code33]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The server accepted this client certificate, but should not have
         */
        $code34 = RADIUSTests::CERTPROB_WRONGLY_ACCEPTED;
        $this->returnCodes[$code34]["message"] = _("The server accepted the INVALID client certificate.");
        $this->returnCodes[$code34]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The server does not accept this client certificate, but should have
         */
        $code35 = RADIUSTests::CERTPROB_WRONGLY_NOT_ACCEPTED;
        $this->returnCodes[$code35]["message"] = _("The server rejected the client certificate, even though it was valid.");
        $this->returnCodes[$code35]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * The server does not accept this client certificate
         */
        $code36 = RADIUSTests::CERTPROB_NOT_ACCEPTED;
        $this->returnCodes[$code36]["message"] = _("The server rejected the client certificate as expected.");
        $this->returnCodes[$code36]["severity"] = \core\common\Entity::L_OK;

        /**
         * the CRL of a certificate could not be found
         */
        $code37 = RADIUSTests::CERTPROB_UNABLE_TO_GET_CRL;
        $this->returnCodes[$code37]["message"] = _("The CRL of a certificate could not be found.");
        $this->returnCodes[$code37]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * the CRL of a certificate could not be found
         */
        $code38 = RADIUSTests::CERTPROB_NO_COMMON_EAP_METHOD;
        $this->returnCodes[$code38]["message"] = _("EAP method negotiation failed!");
        $this->returnCodes[$code38]["severity"] = \core\common\Entity::L_ERROR;

        /**
         * DH group too small
         */
        $code39 = RADIUSTests::CERTPROB_DH_GROUP_TOO_SMALL;
        $this->returnCodes[$code39]["message"] = _("The server offers Diffie-Hellman (DH) ciphers with a DH group smaller than 1024 bit. Mac OS X 10.11 'El Capitan' is known to refuse TLS connections under these circumstances!");
        $this->returnCodes[$code39]["severity"] = \core\common\Entity::L_WARN;

        /**
         * The server certificate's names contained at least which was not a hostname.
         */
        $code40 = RADIUSTests::CERTPROB_MULTIPLE_CN;
        $this->returnCodes[$code40]["message"] = _("The certificate contains more than one CommonName (CN) field. This is reportedly problematic on many supplicants.");
        $this->returnCodes[$code40]["severity"] = \core\common\Entity::L_WARN;
        
        $this->languageInstance->setTextDomain($oldlocale);
    }

}
