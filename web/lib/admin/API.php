<?php

/**
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
 * 
 * @package AdminAPI
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @license https://github.com/GEANT/CAT/blob/master/web/copyright.inc.php GEANT Standard Open Source Software Outward Licence
 * @link API
 */

namespace web\lib\admin;

use Exception;

/**
 * This class defines the various actions doable with the admin API, the
 * parameters and return values.
 * 
 */
class API {

    /**
     * This error is returned if the API is globally disabled on a deployment.
     */
    const ERROR_API_DISABLED = 1;

    /**
     * This error is returned if the API request did not contain an API key.
     */
    const ERROR_NO_APIKEY = 2;

    /**
     * This error is returned if the API request contained an unknown API key.
     */
    const ERROR_INVALID_APIKEY = 3;

    /**
     * An action was requested, but one if its required parameters was missing.
     */
    const ERROR_MISSING_PARAMETER = 4;

    /**
     * An action was requested, but one if its required parameters was
     *  malformed.
     */
    const ERROR_INVALID_PARAMETER = 5;

    /**
     * The API request did not contain a requested action.
     */
    const ERROR_NO_ACTION = 6;

    /**
     * The action that was requested is not recognised.
     */
    const ERROR_INVALID_ACTION = 7;

    /**
     * The API request as a whole did not parse correctly.
     */
    const ERROR_MALFORMED_REQUEST = 8;

    /**
     * An internal error occurred and the requested action could not be
     *  performed.
     */
    const ERROR_INTERNAL_ERROR = 9;

    /**
     * An action for a Silverbullet profile was requested, but the profile admin
     * has not accepted the Terms of Use for Silverbullet yet.
     * 
     * Note: Silverbullet is currently marketed as "eduroam Managed IdP" on the
     * eduroam deployment of the code base.
     */
    const ERROR_NO_TOU = 10;

    /**
     * This action creates a new institution. The institution is identified by
     * a reference to the external DB.
     */
    const ACTION_NEWINST_BY_REF = "NEWINST-BY-REF";

    /**
     * This action creates a new institution. The institution is created in the
     * system, all institution properties are set by including optional
     * parameters.
     */
    const ACTION_NEWINST = "NEWINST";

    /**
     * This action deletes an existing institution.
     */
    const ACTION_DELINST = "DELINST";

    /**
     * This action lists all administrators of an institution.
     */
    const ACTION_ADMIN_LIST = "ADMIN-LIST";

    /**
     * This action creates a new invitation token for administering an existing
     * institution. The invitation can be sent directly via mail, or the sign-up
     * token can be returned in the API response for the caller to hand it out.
     */
    const ACTION_ADMIN_ADD = "ADMIN-ADD";

    /**
     * This action de-authorises an existing administrator from administering an
     * institution. The institution is not deleted. If the administrator is also
     * managing other institutions, that is not changed.
     */
    const ACTION_ADMIN_DEL = "ADMIN-DEL";

    /**
     * This action retrieves download statistics for a given institution.
     */
    const ACTION_STATISTICS_INST = "STATISTICS-INST";

    /**
     * This action retrieves cumulated statistics for the entire federation.
     */
    const ACTION_STATISTICS_FED = "STATISTICS-FED";

    /**
     * Dumps all configured information about IdPs in the federation
     */
    const ACTION_FEDERATION_LISTIDP = "DATADUMP-FED";

    /**
     * This action creates a new RADIUS profile (i.e. a classic profile for
     * institutions with their own RADIUS server, delivering one installer for
     * this profile).
     */
    const ACTION_NEWPROF_RADIUS = "NEWPROF-RADIUS";

    /**
     * This action creates a new Managed IdP profile (i.e. a profile where all
     * RADIUS is handled by our system and the administrator merely needs to
     * provision users via a web interface).
     */
    const ACTION_NEWPROF_SB = "NEWPROF-MANAGED";

    /**
     * This action creates a new end-user within a Managed IdP profile.
     */
    const ACTION_ENDUSER_NEW = "ENDUSER-NEW";

    /**
     * This action changes the end user expiry date
     */
    const ACTION_ENDUSER_CHANGEEXPIRY = "ENDUSER-CHANGEEXPIRY";

    /**
     * This action deactivates an existing end user in a Managed IdP profile.
     */
    const ACTION_ENDUSER_DEACTIVATE = "ENDUSER-DEACTIVATE";

    /**
     * This action lists all end users in a given Managed IdP profile.
     */
    const ACTION_ENDUSER_LIST = "ENDUSER-LIST";

    /**
     * This action identifies a user account from either his user ID, username
     * or any of their certificate CNs.
     */
    const ACTION_ENDUSER_IDENTIFY = "ENDUSER-IDENTIFY";

    /**
     * This action creates a new end-user voucher for eduroam credential
     * installation.
     */
    const ACTION_TOKEN_NEW = "TOKEN-NEW";

    /**
     * This action cancels a currently valid end-user voucher. Existing redeemed
     * credentials based on that voucher remain valid.
     */
    const ACTION_TOKEN_REVOKE = "TOKEN-REVOKE";

    /**
     * This action lists all vouchers for a given end-user.
     */
    const ACTION_TOKEN_LIST = "TOKEN-LIST";

    /**
     * This action lists all client certificate credentials issued to a given
     * end user.
     */
    const ACTION_CERT_LIST = "CERT-LIST";

    /**
     * This action revokes a specific client cert.
     */
    const ACTION_CERT_REVOKE = "CERT-REVOKE";

    /** This action calls diagnostics tests
     *
     */
    const ACTION_DIAG_TESTS = "DIAG-TESTS";
    /**
     * This action adds internal notes regarding this certificate. These notes
     * are included when retrieving certificate information with 
     * ACTION_CERT_LIST but are not actively used for anything.
     */
    const ACTION_CERT_ANNOTATE = "CERT-ANNOTATE";
    const AUXATTRIB_ADMINID = "ATTRIB-ADMINID";
    const AUXATTRIB_TARGETMAIL = "ATTRIB-TARGETMAIL";
    const AUXATTRIB_TARGETSMS = "ATTRIB-TARGETSMS";
    const AUXATTRIB_EXTERNALID = "ATTRIB-EXTERNALID";
    const AUXATTRIB_CAT_INST_ID = "ATTRIB-CAT-INSTID";
    const AUXATTRIB_CAT_PROFILE_ID = "ATTRIB-CAT-PROFILEID";
    const AUXATTRIB_PROFILE_REALM = 'ATTRIB-PROFILE-REALM';
    const AUXATTRIB_PROFILE_OUTERVALUE = 'ATTRIB-PROFILE-OUTERVALUE';
    const AUXATTRIB_PROFILE_TESTUSER = 'ATTRIB-PROFILE-TESTUSER';
    const AUXATTRIB_PROFILE_INPUT_HINT = 'ATTRIB-PROFILE-HINTREALM';
    const AUXATTRIB_PROFILE_INPUT_VERIFY = 'ATTRIB-PROFILE-VERIFYREALM';
    const AUXATTRIB_PROFILE_EAPTYPE = "ATTRIB-PROFILE-EAPTYPE";
    const AUXATTRIB_SB_TOU = "ATTRIB-MANAGED-TOU";
    const AUXATTRIB_SB_USERNAME = "ATTRIB-MANAGED-USERNAME";
    const AUXATTRIB_SB_USERID = "ATTRIB-MANAGED-USERID";
    const AUXATTRIB_SB_CERTSERIAL = "ATTRIB-MANAGED-CERTSERIAL";
    const AUXATTRIB_SB_CERTCN = "ATTRIB-MANAGED-CERTCN";
    const AUXATTRIB_SB_CERTANNOTATION = "ATTRIB-MANAGED-CERTANNOTATION";
    const AUXATTRIB_SB_EXPIRY = "ATTRIB-MANAGED-EXPIRY"; /* MySQL timestamp format */
    const AUXATTRIB_TOKEN = "ATTRIB-TOKEN";
    const AUXATTRIB_TOKENURL = "ATTRIB-TOKENURL";
    const AUXATTRIB_TOKEN_ACTIVATIONS = "ATTRIB-TOKEN-ACTIVATIONS";
    const AUXATTRIB_INSTTYPE = "ATTRIB-INSTITUTION-TYPE";
    const AUXATTRIB_DETAIL = "ATTRIB-DETAIL";
    const AUXATTRIB_DIAG_USERNAME = "ATTRIB-DIAG-USERNAME";
    const AUXATTRIB_DIAG_PASSWD = "ATTRIB-DIAG-PASSWD";
    const AUXATTRIB_DIAG_OUTERUSER = "ATTRIB-DIAG-OUTERUSER";
    const AUXATTRIB_DIAG_SCOPE = "ATTRIB-DIAG-SCOPE";
    /**
     * This section defines allowed flags for actions
     */
    const FLAG_NOLOGO = "FLAG-NO-LOGO"; // skip logos in attribute listings
    
    const DIAG_ALL = "ALL";
    const DIAG_LIVE_LOGIN = "LIVE-LOGIN";
    const DIAG_INFRASTRUCTURE = "INFRASTRUCTURE";
    const DIAG_DYNAMIC = "DYNAMIC";
    
    const DIAG_SCOPES = [API::DIAG_ALL, API::DIAG_LIVE_LOGIN, API::DIAG_INFRASTRUCTURE, API::DIAG_DYNAMIC];
    /*
     * ACTIONS consists of a list of keywords, and associated REQuired and OPTional parameters
     * 
     */
    const ACTIONS = [
        // Inst-level actions.
        API::ACTION_NEWINST_BY_REF => [
            "REQ" => [
                API::AUXATTRIB_EXTERNALID,
                API::AUXATTRIB_INSTTYPE,
                ],
            "OPT" => [
                'general:geo_coordinates',
                'general:logo_file',
                'media:SSID',
                'media:wired',
                'media:remove_SSID',
                'media:consortium_OI',
                'media:force_proxy',
                'support:email',
                'support:info_file',
                'support:phone',
                'support:url'
            ],
            "FLAG" => [],
            "RETVAL" => [
                API::AUXATTRIB_CAT_INST_ID, // New inst ID.
            ],
        ],
        API::ACTION_NEWINST => [
            "REQ" => [API::AUXATTRIB_INSTTYPE,], // "IdP", "SP" or "IdPSP"
            "OPT" => [
                'general:instname',
                'general:geo_coordinates',
                'general:logo_file',
                'media:SSID',
                'media:wired',
                'media:remove_SSID',
                'media:consortium_OI',
                'media:force_proxy',
                'support:email',
                'support:info_file',
                'support:phone',
                'support:url'
            ],
            "FLAG" => [],
            "RETVAL" => [
                API::AUXATTRIB_CAT_INST_ID, // New inst ID.
            ],            
        ],
        API::ACTION_DELINST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        // Inst administrator management.
        API::ACTION_ADMIN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [
                ["ID", "MAIL", "LEVEL"] // Array with all admins of inst.
            ]
        ],
        API::ACTION_ADMIN_ADD => [
            "REQ" => [
                API::AUXATTRIB_ADMINID,
                API::AUXATTRIB_CAT_INST_ID
            ],
            "OPT" => [API::AUXATTRIB_TARGETMAIL],
            "FLAG" => [],
            "RETVAL" => [
                ["TOKEN URL",
                    "EMAIL SENT", // Dependent on TARGETMAIL input.
                    "EMAIL TRANSPORT SECURE"], // Dependent on TARGETMAIL input.
            ]
        ],
        API::ACTION_ADMIN_DEL => [
            "REQ" => [
                API::AUXATTRIB_ADMINID,
                API::AUXATTRIB_CAT_INST_ID
            ],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        // Statistics.
        API::ACTION_STATISTICS_INST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [],
            "FLAG" => [],
        ],
        API::ACTION_STATISTICS_FED => [
            "REQ" => [],
            "OPT" => [API::AUXATTRIB_DETAIL],
            "FLAG" => [],
            "RETVAL" => [
                ["device_id" => ["ADMIN", "SILVERBULLET", "USER"]] // Plus "TOTAL".
            ],
        ],
        API::ACTION_FEDERATION_LISTIDP => [
            "REQ" => [],
            "OPT" => [API::AUXATTRIB_CAT_INST_ID],
            "RETVAL" => [API::AUXATTRIB_CAT_INST_ID => "JSON_DATA"],
            "FLAG" => [API::FLAG_NOLOGO],
        ],
        // RADIUS profile actions.
        API::ACTION_NEWPROF_RADIUS => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [
                'eap:ca_file',
                'eap:server_name',
                'media:SSID',
                'media:wired',
                'media:remove_SSID',
                'media:consortium_OI',
                'media:force_proxy',
                'profile:name',
                'profile:customsuffix',
                'profile:description',
                'profile:production',
                'support:email',
                'support:info_file',
                'support:phone',
                'support:url',
                'device-specific:redirect',
                API::AUXATTRIB_PROFILE_INPUT_HINT,
                API::AUXATTRIB_PROFILE_INPUT_VERIFY,
                API::AUXATTRIB_PROFILE_OUTERVALUE,
                API::AUXATTRIB_PROFILE_REALM,
                API::AUXATTRIB_PROFILE_TESTUSER,
                API::AUXATTRIB_PROFILE_EAPTYPE,
            ],
            "FLAG" => [],
            "RETVAL" => API::AUXATTRIB_CAT_PROFILE_ID,
        ],
        // Silverbullet profile actions.
        API::ACTION_NEWPROF_SB => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [API::AUXATTRIB_SB_TOU],
            "FLAG" => [],
            "RETVAL" => API::AUXATTRIB_CAT_PROFILE_ID,
        ],
        API::ACTION_ENDUSER_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_EXPIRY],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_USERID],
        ],
        API::ACTION_ENDUSER_CHANGEEXPIRY => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_EXPIRY],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        API::ACTION_ENDUSER_DEACTIVATE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        API::ACTION_ENDUSER_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [
                [API::AUXATTRIB_SB_USERID => API::AUXATTRIB_SB_USERNAME],
            ],
        ],
        API::ACTION_ENDUSER_IDENTIFY => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [API::AUXATTRIB_SB_USERID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_CERTSERIAL, API::AUXATTRIB_SB_CERTCN],
            "FLAG" => [],
            "RETVAL" => [API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_USERID],
        ],
        API::ACTION_TOKEN_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [API::AUXATTRIB_TOKEN_ACTIVATIONS, API::AUXATTRIB_TARGETMAIL, API::AUXATTRIB_TARGETSMS],
            "FLAG" => [],
            "RETVAL" => [
                API::AUXATTRIB_TOKENURL,
                API::AUXATTRIB_TOKEN,
                "EMAIL SENT", // Dependent on TARGETMAIL input.
                "EMAIL TRANSPORT SECURE", // Dependent on TARGETMAIL input.
                "SMS SENT", // Dependent on TARGETSMS input.
            ]
        ],
        API::ACTION_TOKEN_REVOKE => [
            "REQ" => [API::AUXATTRIB_TOKEN],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        API::ACTION_TOKEN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [API::AUXATTRIB_SB_USERID],
            "FLAG" => [],
            "RETVAL" => [
                [API::AUXATTRIB_SB_USERID => [API::AUXATTRIB_TOKEN, "STATUS"]],
            ]
        ],
        API::ACTION_CERT_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [
                [API::AUXATTRIB_SB_CERTSERIAL => ["ISSUED", "EXPIRY", "STATUS", "DEVICE", "CN"]]
            ]
        ],
        API::ACTION_CERT_REVOKE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_CERTSERIAL],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        API::ACTION_CERT_ANNOTATE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_CERTSERIAL, API::AUXATTRIB_SB_CERTANNOTATION],
            "OPT" => [],
            "FLAG" => [],
            "RETVAL" => [],
        ],
        API::ACTION_DIAG_TESTS => [
            "REQ" => [],
            "OPT" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_PROFILE_REALM, API::AUXATTRIB_DIAG_USERNAME,
                      API::AUXATTRIB_DIAG_PASSWD, API::AUXATTRIB_DIAG_OUTERUSER, API::AUXATTRIB_DIAG_SCOPE],
            "FLAG" => [],
            "RETVAL" => [],
        ]
    ];

    /**
     *
     * @var \web\lib\common\InputValidation
     */
    private $validator;
    private $optionParser;

    private $jsondir;
    private $catlink;
    private $token;
    /**
     * construct the API class
     */
    public function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
        $this->optionParser = new \web\lib\admin\OptionParser();
        $this->loggerInstance = new \core\common\Logging();
    }

    /**
     * Only leave attributes in the request which are related to the ACTION.
     * Also sanitise by enforcing LANG attribute in multi-lang attributes.
     * 
     * @param array            $inputJson the incoming JSON request
     * @return array the scrubbed attributes
     */
    public function scrub($inputJson) {
        $optionInstance = \core\Options::instance();
        $parameters = [];
        $allPossibleAttribs = array_merge(API::ACTIONS[$inputJson['ACTION']]['REQ'], API::ACTIONS[$inputJson['ACTION']]['OPT'],  API::ACTIONS[$inputJson['ACTION']]['FLAG']);
        // some actions don't need parameters. Don't get excited when there aren't any.
        if (!isset($inputJson['PARAMETERS'])) {
            return [];
        }
        \core\common\Logging::debug_s(4, $inputJson['PARAMETERS'], "JSON:\n","\n");
        foreach ($inputJson['PARAMETERS'] as $number => $oneIncomingParam) {
            // index has to be an integer
            if (!is_int($number)) {
                continue;
            }
            // do we actually have a value?
            if (!array_key_exists("VALUE", $oneIncomingParam)) {
                continue;
            }
            if (!in_array($oneIncomingParam['NAME'], $allPossibleAttribs)) {
                continue;
            }
            if (preg_match("/^ATTRIB-/", $oneIncomingParam['NAME'])) {// sanitise the AUX attr 
                switch ($oneIncomingParam['NAME']) {
                    case API::AUXATTRIB_CAT_INST_ID:
                        try {
                            $inst = $this->validator->existingIdP($oneIncomingParam['VALUE']);
                        } catch (Exception $e) {
                            // invalid IdP number
                            \core\common\Logging::debug_s(4, $oneIncomingParam['VALUE'], "No such IdP: ", "\n");
                            $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"No such IdP"]);
                            continue 2;
                        }
                        if (strtoupper($inst->federation) != strtoupper($this->fed->tld)) {
                            // IdP in different fed, scrub it
                            \core\common\Logging::debug_s(4, $oneIncomingParam['VALUE'], "IdP not in your fed: ", "\n");
                            $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"IdP not in your federation"]);
                            continue 2;
                        }
                        break;
                    case API::AUXATTRIB_EXTERNALID:
                        try {
                            $ROid = strtoupper($this->fed->tld)."01";
                            $extId = $this->validator->string($oneIncomingParam['VALUE']);
                            $extInst = $this->validator->existingExtInstitution($extId, 'API', $ROid);
                        } catch (Exception $ex) {

                        }
                        break;
                    case API::AUXATTRIB_TARGETMAIL:
                        if ($this->validator->email($oneIncomingParam['VALUE']) === FALSE) {
                            // invalid mail format
                            $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"Invalid mail format"]);
                            continue 2;
                        }
                        break;
                    case API::AUXATTRIB_ADMINID:
                        try {
                            $oneIncomingParam['VALUE'] = $this->validator->string($oneIncomingParam['VALUE']);
                        } catch (Exception $e) {
                            continue 2;
                        }
                        break;
                    case API::AUXATTRIB_DIAG_SCOPE:
                        if (!in_array($oneIncomingParam['VALUE'], API::DIAG_SCOPES)) {
                            $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"Invalid scope value"]);
                            continue 2;
                        }
                    default:
                        break;
                }   
            } elseif (preg_match("/^FLAG-/", $oneIncomingParam['NAME'])) {
                if ($oneIncomingParam['VALUE'] != "TRUE" && $oneIncomingParam['VALUE'] != "FALSE" ) {
                    // incorrect FLAG value
                    $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"Incorrect FLAG value"]);
                    continue;
                }
            } else {
            // is this multi-lingual, and not an AUX attrib? Then check for presence of LANG and CONTENT before considering to add                
                $optionProperties = $optionInstance->optionType($oneIncomingParam['NAME']);
                if ($optionProperties["flag"] == "ML" && !array_key_exists("LANG", $oneIncomingParam)) {
                    // LANG parameter missing
                    $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>false, 'VERIFY_DESC'=>"LANG parameter missing"]);
                    continue;
                }
            }
            $parameters[$number] = array_merge($oneIncomingParam, ['VERIFY_RESULT'=>true, 'VERIFY_DESC'=>""]);
        }
        $this->scrubbedParameters = $parameters;
        return $parameters;
    }
    
    /**
     * extracts the first occurrence of a given parameter name from the set of inputs
     * 
     * @param array  $inputs   incoming set of arrays
     * @param string $expected attribute that is to be extracted
     * @return mixed the value, or FALSE if none was found
     */
    public function firstParameterInstanceOld($inputs, $expected) {
        foreach ($inputs as $attrib) {
            if ($attrib['NAME'] === $expected) {
                return $attrib['VALUE'];
            }
        }
        return FALSE;
    }


    /**
     * extracts the first occurrence of a given parameter name from the set of inputs
     * 
     * @param array  $inputs   incoming set of arrays
     * @param string $expected attribute that is to be extracted
     * @return mixed the value, or FALSE if none was found
     */
    public function firstParameterInstance($expected) {
        foreach ($this->scrubbedParameters as $attrib) {
            if ($attrib['NAME'] === $expected) {
                return $attrib['VALUE'];
            }
        }
        return FALSE;
    }    
    /**
     * we are coercing the submitted JSON-style parameters into the same format
     * we use for the HTML POST user-interactively.
     * That's ugly, hence the function name.
     * 
     * @param array $parameters the parameters as provided by JSON input
     * @return array
     * @throws Exception
     */
    public function uglify($parameters) {
        $coercedInline = [];
        $coercedFile = [];
        $optionObject = \core\Options::instance();
        $dir = \core\common\Entity::createTemporaryDirectory('test');
        foreach ($parameters as $number => $oneAttrib) {
            if (preg_match("/^ATTRIB-/", $oneAttrib['NAME'])) {
                continue;
            }
            $optionInfo = $optionObject->optionType($oneAttrib['NAME']);
            $basename = "S$number";
            $extension = "";
            switch ($optionInfo['type']) {
                case \core\Options::TYPECODE_COORDINATES:
                    $extension = \core\Options::TYPECODE_TEXT;
                    $coercedInline["option"][$basename] = $oneAttrib['NAME'] . "#";
                    $coercedInline["value"][$basename . "-" . $extension] = $oneAttrib['VALUE'];
                    break;
                case \core\Options::TYPECODE_TEXT:
                // Fall-through: they all get the same treatment.
                case \core\Options::TYPECODE_BOOLEAN:
                // Fall-through: they all get the same treatment.
                case \core\Options::TYPECODE_STRING:
                // Fall-through: they all get the same treatment.
                case \core\Options::TYPECODE_INTEGER:
                    $extension = $optionInfo['type'];
                    $coercedInline["option"][$basename] = $oneAttrib['NAME'] . "#";
                    $coercedInline["value"][$basename . "-" . $extension] = $oneAttrib['VALUE'];
                    if ($optionInfo['flag'] == "ML") {
                        $coercedInline["value"][$basename . "-lang"] = $oneAttrib['LANG'];
                    }
                    break;
                case \core\Options::TYPECODE_FILE:
                    // Binary data is expected in base64 encoding. This is true also for PEM files!
                    $extension = $optionInfo['type'];
                    $coercedInline["option"][$basename] = $oneAttrib['NAME'] . "#";
                    file_put_contents($dir['dir'] . "/" . $basename . "-" . $extension, base64_decode($oneAttrib['VALUE']));
                    $coercedFile["value"]['tmp_name'][$basename . "-" . $extension] = $dir['dir'] . "/" . $basename . "-" . $extension;
                    break;
                default:
                    throw new Exception("We don't seem to know this type code!");
            }
        }
        return ["POST" => $coercedInline, "FILES" => $coercedFile];
    }

    /**
     * Returns a JSON construct detailing the error that happened
     * 
     * @param int    $code        error code to return
     * @param string $description textual description to return
     * @return string
     */
    public function returnError($code, $description) {
        echo json_encode(["result" => "ERROR", "details" => ["errorcode" => $code, "description" => $description]], JSON_PRETTY_PRINT);
    }

    /**
     * Returns a JSON construct with details of the successful API call
     * 
     * @param array $details details to return with the SUCCESS
     * @return string
     */
    public function returnSuccess($details) {
        $output = json_encode(["result" => "SUCCESS", "details" => $details], JSON_PRETTY_PRINT);
        if ($output === FALSE) {
            $this->returnError(API::ERROR_INTERNAL_ERROR, "Unable to JSON encode return data: ". json_last_error(). " - ". json_last_error_msg());
        }
        else {
            echo $output;
        }
    }

    /**
     * Checks if the profile is a valid SB profile belonging to the federation,
     * and fulfills all the prerequisites for being manipulated over API
     * 
     * @param \core\Federation $fed federation identifier
     * @param integer          $id  profile identifier
     * @return boolean|array
     */
    public function commonSbProfileChecks($fed, $id) {
        $adminApi = new \web\lib\admin\API();
        try {
            $profile = $this->validator->existingProfile($id);
        } catch (Exception $e) {
            $adminApi->returnError(self::ERROR_INVALID_PARAMETER, "Profile identifier does not exist!");
            return FALSE;
        }
        if (!$profile instanceof \core\ProfileSilverbullet) {
            $adminApi->returnError(self::ERROR_INVALID_PARAMETER, sprintf("Profile identifier is not %s!", \core\ProfileSilverbullet::PRODUCTNAME));
            return FALSE;
        }
        $idp = new \core\IdP($profile->institution);
        if (strtoupper($idp->federation) != strtoupper($fed->tld)) {
            $adminApi->returnError(self::ERROR_INVALID_PARAMETER, "Profile is not in the federation for this APIKEY!");
            return FALSE;
        }
        if (count($profile->getAttributes("hiddenprofile:tou_accepted")) < 1) {
            $adminApi->returnError(self::ERROR_NO_TOU, "The terms of use have not yet been accepted for this profile!");
            return FALSE;
        }
        return [$idp, $profile];
    }
    
    
    
    public function actionNewinstByRef() {
        $typeRaw = $this->firstParameterInstance(API::AUXATTRIB_INSTTYPE);
        if ($typeRaw === FALSE) {
            $this->returnError(API::ERROR_INVALID_PARAMETER, "We did not receive a valid participant type!");
            exit(1);
        }
        $type = $this->validator->partType($typeRaw);
        $ROid = strtoupper($this->fed->tld).'01';
        $extId = $this->firstParameterInstance(API::AUXATTRIB_EXTERNALID);
        \core\common\Logging::debug_s(3,$extId, "EXTID\n", "\n");
        if ($this->validator->existingExtInstitution($extId, 'API', $ROid) === 0) {
            $this->returnError(API::ERROR_INVALID_PARAMETER, "This is not a valid identifier in eduroam DB!");
            exit(1);
        }
        if (\core\Federation::isExternalInstInCAT($extId, $this->fed->tld)) {
            $this->returnError(API::ERROR_INVALID_PARAMETER, "This external identifier is already used in your federation!");
            exit(1);
        }        
        $details = $this->fed->newIdPFromAPI($type, $extId);
        $idp = new \core\IdP($details['new_idp']);
        $out = [];
        foreach ($details['names'] as $lang=>$name) {
            $out[] = ["LANG"=>$lang, "NAME"=>"general:instname", "VALUE"=>$name, "VERIFY_RESULT"=>true, "VERIFY_DESC"=>""];
            if ($lang === 'en') {
            $out[] = ["LANG"=>"C", "NAME"=>"general:instname", "VALUE"=>$name, "VERIFY_RESULT"=>true, "VERIFY_DESC"=>""];                
            }
        }
        if (isset($details['contacts'][0])) {
            foreach ($details['contacts'][0] as $contactType => $contactValue) {
                switch ($contactType) {
                    case 'name':
                        break;
                    case 'mail':
                        $contactType = 'email';
                    default:
                        $out[] = ["LANG"=>"C", "NAME"=>"support:$contactType", "VALUE"=>$contactValue, "VERIFY_RESULT"=>true, "VERIFY_DESC"=>""];
                        break;
                }
            }
        }
        $inputs = $this->uglify(array_merge($this->scrubbedParameters, $out));
        $this->optionParser->processSubmittedFields($idp, $inputs["POST"], $inputs["FILES"]);
        $this->returnSuccess([API::AUXATTRIB_CAT_INST_ID => $idp->identifier]);  
    }
    
    public function actionNewinst() {
        $typeRaw = $this->firstParameterInstance(API::AUXATTRIB_INSTTYPE);
        if ($typeRaw === FALSE) {
            throw new Exception("We did not receive a valid participant type!");
        }
        $type = $this->validator->partType($typeRaw);
        $idp = new \core\IdP($this->fed->newIdP('TOKEN', $type, "PENDING", "API"));
        // now add all submitted attributes
        $inputs = $this->uglify($this->scrubbedParameters);
        $this->optionParser->processSubmittedFields($idp, $inputs["POST"], $inputs["FILES"]);
        $this->returnSuccess([API::AUXATTRIB_CAT_INST_ID => $idp->identifier]);
    }
    
    public function actionAdminList() {
        $idp = $this->getIdpFromParams();
        $this->returnSuccess($idp->listOwners());
    }
    
    public function actionDelinst() {
        $idp = $this->getIdpFromParams();
        $idp->destroy();
        $this->returnSuccess([]);
    }
    
    public function actionAdminAdd() {
        $idp = $this->getIdpFromParams();
        // here is the token
        $mgmt = new \core\UserManagement();
        // we know we have an admin ID but scrutinizer wants this checked more explicitly
        $admin = $this->firstParameterInstance(API::AUXATTRIB_ADMINID);
        if ($admin === FALSE) {
            throw new Exception("A required parameter is missing, and this wasn't caught earlier?!");
        }
        $newtokens = $mgmt->createTokens("FED", [$admin], $idp);
        $URL = "https://".$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_NAME'])."/action_enrollment.php?token=".array_keys($newtokens)[0];
        $success = ["TOKEN URL" => $URL, "TOKEN" => array_keys($newtokens)[0]];
        // done with the essentials - display in response. But if we also have an email address, send it there
        $email = $this->firstParameterInstance(API::AUXATTRIB_TARGETMAIL);
        if ($email !== FALSE) {
            $sent = \core\common\OutsideComm::adminInvitationMail($email, "EXISTING-FED", array_keys($newtokens)[0], $idp->name, $this->fed, $idp->type);
            $success["EMAIL SENT"] = $sent["SENT"];
            if ($sent["SENT"] === TRUE) {
                $success["EMAIL TRANSPORT SECURE"] = $sent["TRANSPORT"];
            }
        }
        $this->returnSuccess($success);
    }
    
    public function actionFederationListip() {
        $retArray = [];
        $noLogo = null;
        $idpIdentifier = $this->firstParameterInstance(API::AUXATTRIB_CAT_INST_ID);
        $logoFlag = $this->firstParameterInstance(API::FLAG_NOLOGO);
//        $detail = $this->firstParameterInstance1(API::AUXATTRIB_DETAIL);
        if ($logoFlag === "TRUE") {
            $noLogo = 'general:logo_file';
        }
        if ($idpIdentifier === FALSE) {
            $allIdPs = $this->fed->listIdentityProviders(0);
            foreach ($allIdPs as $instanceId => $oneIdP) {
                $theIdP = $oneIdP["instance"];
                $retArray[$instanceId] = $theIdP->getAttributes(null, $noLogo);
            }
        } else {
            try {
                $thisIdP = $this->validator->existingIdP($idpIdentifier, NULL, $this_fed);
            } catch (Exception $e) {
                $this->returnError(API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
                exit(1);
            }
            $retArray[$idpIdentifier] = $thisIdP->getAttributes(null, $noLogo);
            foreach ($thisIdP->listProfiles() as $oneProfile) {
                $retArray[$idpIdentifier]["PROFILES"][$oneProfile->identifier] = $oneProfile->getAttributes(null, $noLogo);
            }
        }
        foreach ($retArray as $instNumber => $oneInstData) {
            foreach ($oneInstData as $attribNumber => $oneAttrib) {
                if ($oneAttrib['name'] == "general:logo_file") {
                    // JSON doesn't cope well with raw binary data, so b64 it
                    $retArray[$instNumber][$attribNumber]['value'] = base64_encode($oneAttrib['value']);
                }
                if ($attribNumber == "PROFILES") {
                    // scan for included fed:logo_file and b64 escape it, t2oo
                    foreach ($oneAttrib as $profileNumber => $profileContent) {
                        foreach ($profileContent as $oneProfileIterator => $oneProfileContent) {
                                if ($oneProfileContent['name'] == "fed:logo_file" || $oneProfileContent['name'] == "general:logo_file" || $oneProfileContent['name'] == "eap:ca_file") {
                                        $retArray[$instNumber]["PROFILES"][$profileNumber][$oneProfileIterator]['value'] = base64_encode($oneProfileContent['value']);
                                }
                        }
                    }
                }
            }
        }
        $this->returnSuccess($retArray);
    }
    
    public function actionStatisticsFed() {
        $detail = $this->firstParameterInstance(API::AUXATTRIB_DETAIL);
        $this->returnSuccess($this->fed->downloadStats("array", $detail));
    }
    
    public function actionAdminDel() {
        $idp = $this->getIdpFromParams();
        $currentAdmins = $idp->listOwners();
        $toBeDeleted = $this->firstParameterInstance(API::AUXATTRIB_ADMINID);
        if ($toBeDeleted === FALSE) {
            throw new Exception("A required parameter is missing, and this wasn't caught earlier?!");
        }
        $found = FALSE;
        foreach ($currentAdmins as $oneAdmin) {
            if ($oneAdmin['MAIL'] == $toBeDeleted) {
                $found = TRUE;
                $mgmt = new \core\UserManagement();
                $mgmt->removeAdminFromIdP($idp, $oneAdmin['ID']);
            }
        }
        if ($found) {
            $this->returnSuccess([]);
            return;
        }
        $this->returnError(API::ERROR_INVALID_PARAMETER, "The admin with ID $toBeDeleted is not associated to IdP ".$idp->identifier);
    }
    
    public function actionDiagTest() {
        $retArray = [];
        $this->token = bin2hex(openssl_random_pseudo_bytes(20));
        $realm = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_REALM);
        $profile_id = $this->firstParameterInstance(API::AUXATTRIB_CAT_PROFILE_ID);
        $scope = $this->firstParameterInstance(API::AUXATTRIB_DIAG_SCOPE);
        if ($realm === FALSE && $profile_id === FALSE) {
            $this->returnError(self::ERROR_INVALID_PARAMETER, "A profile identifier or a realm has to be provided!");
        }
        if ($scope === FALSE) {
            $scope = API::DIAG_SCOPES[0];
        } else {
            if (!in_array($scope, API::DIAG_SCOPES)) {
                $scope = API::DIAG_SCOPES[0];
            }
        }
        $live_tests = FALSE;
        if ($scope === API::DIAG_ALL || $scope === API::DIAG_LIVE_LOGIN) {
            $login_user = $this->firstParameterInstance(API::AUXATTRIB_DIAG_USERNAME);
            $login_pass = $this->firstParameterInstance(API::AUXATTRIB_DIAG_PASSWD);
            $login_outer = $this->firstParameterInstance(API::AUXATTRIB_DIAG_OUTERUSER);
            $live_tests = TRUE;
        }
        $this->jsondir = dirname(dirname(dirname(dirname(__FILE__))))."/var/json_cache";
        if (isset($_SERVER['HTTPS'])) {
            $this->catlink = 'https://';
        } else {
            $this->catlink = 'http://';
        }
        $this->catlink .= $_SERVER['SERVER_NAME'];
        $relPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
        if (substr($relPath, -1) == '/') {
            $relPath = substr($relPath, 0, -1);
            if ($relPath === FALSE) {
                throw new Exception("Uh. Something went seriously wrong with URL path mangling.");
            }
        }
        $this->catlink .= $relPath;
        
        $payload = ['token' => $this->token, 'addtest' => 1];
        if ($profile_id !== FALSE) {
            $payload['profile_id'] = $profile_id;
        }
        if ($realm !== FALSE) {
            $payload['realm'] = $realm;
        } else {
            $realm = '';
        }
        $this->diag_call($payload, $this->catlink."/diag/findRealm.php");
        $filename = $this->jsondir.'/'.$this->token.'/realm';
        if ($this->token && is_dir($this->jsondir.'/'.$this->token) && is_file($filename)) {  
            $data = json_decode(file_get_contents($filename), TRUE);
        }
        $retArray['realm'] = $data['realm'];
        $retArray['datetime'] = $data['datetime'];
        $retArray['outeruser'] = $data['outeruser'];
        $retArray['resultinCAT'] = $this->catlink."/diag/show_realmcheck.php?norefresh=1&token=".$this->token;
        if ($realm === '' && $data['realm'] !== NULL) {
            $realm = $data['realm'];
        }
        $retArray['radius_hosts_tests'] = [];
        if ($live_tests === TRUE && $login_user !== FALSE) {
            $retArray['live_login_tests'] = [];
        }
        if ($scope === API::DIAG_ALL || $scope === API::DIAG_INFRASTRUCTURE) {
            $retArray = array_merge($retArray, $this->infrastructure_test($profile_id, $realm));
        }
        if ($live_tests === TRUE && $login_user !== FALSE) {
            $retArray = array_merge($retArray, $this->live_login_test($profile_id, $realm, $login_outer, $login_user, $login_pass));
            
        }
        if ($scope === API::DIAG_ALL || $scope === API::DIAG_DYNAMIC) {
            if (isset($data['naptr']) && $data['naptr'] > 0 && isset($data['totest']) && count($data['totest']) > 0) {
                $retArray = array_merge($retArray, $this->dynamic_test($realm, $data['totest']));
            }
        }
        $this->returnSuccess($retArray);
    }
        
    public function actionStatisticsInst() {
        $retArray = [];
        $idp = $this->getIdpFromParams();
        $retArray[$idp->identifier] = [];
        foreach ($idp->listProfiles() as $oneProfile) {
            $retArray[$idp->identifier][$oneProfile->identifier] = $oneProfile->getUserDownloadStats();
        }
        $this->returnSuccess($retArray);
    }
    
    public function actionNewProfRadius() {
        $idp = $this->getIdpFromParams();
        $profile = $idp->newProfile("RADIUS");
        if ($profile === NULL) {
            $this->returnError(\web\lib\admin\API::ERROR_INTERNAL_ERROR, "Unable to create a new Profile, for no apparent reason. Please contact support.");
            exit(1);
        }
        $inputs = $this->uglify($this->scrubbedParameters);
        $this->optionParser->processSubmittedFields($profile, $inputs["POST"], $inputs["FILES"]);
        $realm = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_REALM);
        $outer = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_OUTERVALUE);
        if ($realm !== FALSE) {
            if ($outer === FALSE) {
                $outer = "";
                $profile->setAnonymousIDSupport(FALSE);
            } else {
                $outer = $outer."@";
                $profile->setAnonymousIDSupport(TRUE);
            }
            $profile->setRealm($outer.$realm);
        }
        /* const AUXATTRIB_PROFILE_TESTUSER = 'ATTRIB-PROFILE-TESTUSER'; */
        $testuser = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_TESTUSER);
        if ($testuser !== FALSE) {
            $profile->setRealmCheckUser(TRUE, $testuser);
        }
        /* const AUXATTRIB_PROFILE_INPUT_HINT = 'ATTRIB-PROFILE-HINTREALM';
          const AUXATTRIB_PROFILE_INPUT_VERIFY = 'ATTRIB-PROFILE-VERIFYREALM'; */
        $hint = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_INPUT_HINT);
        $enforce = $this->firstParameterInstance(API::AUXATTRIB_PROFILE_INPUT_VERIFY);
        if ($enforce !== FALSE) {
            $profile->setInputVerificationPreference($enforce, $hint);
        }
        /* const AUXATTRIB_PROFILE_EAPTYPE */
        $iterator = 1;
        foreach ($this->scrubbedParameters as $oneParam) {
            if ($oneParam['NAME'] == API::AUXATTRIB_PROFILE_EAPTYPE && is_int($oneParam["VALUE"])) {
                $type = new \core\common\EAP($oneParam["VALUE"]);
                $profile->addSupportedEapMethod($type, $iterator);
                $iterator = $iterator + 1;
            }
        }
        // reinstantiate $profile freshly from DB - it was updated in the process
        $profileFresh = new \core\ProfileRADIUS($profile->identifier);
        $profileFresh->prepShowtime();
        $this->returnSuccess([API::AUXATTRIB_CAT_PROFILE_ID => $profileFresh->identifier]);
    }
    

    public function actionNewProfSb() {
        $idp = $this->getIdpFromParams();
        $profile = $idp->newProfile("SILVERBULLET");
        $inputs = $this->uglify($this->scrubbedParameters);
        $this->optionParser->processSubmittedFields($profile, $inputs["POST"], $inputs["FILES"]);
        // auto-accept ToU?
        if ($this->firstParameterInstance(API::AUXATTRIB_SB_TOU) !== FALSE) {
            $profile->addAttribute("hiddenprofile:tou_accepted", NULL, 1);
        }
        // we're done at this point
        $this->returnSuccess([API::AUXATTRIB_CAT_PROFILE_ID => $profile->identifier]);
    }
    
    private function getIdpFromParams() {
        try {
            $idp = $this->validator->existingIdP($this->firstParameterInstance(API::AUXATTRIB_CAT_INST_ID), NULL, $this->fed);
        } catch (Exception $e) {
            $this->returnError(API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }   
        return $idp;
    }

    private function diag_call($payload, $url) {
        $params = http_build_query($payload);
        $ch = curl_init("$url?$params");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }
    
    private function infrastructure_test($profile_id, $realm) {
        $infrastructure_test = [];
        foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
            $radius = [];
            $radius['name'] = $host['display_name'];
            $radius["ip"] = $host['ip'];
            $payload = ['test_type' => 'udp', 'realm' => $realm, 'token' => $this->token, 'src' => $hostindex, 'hostindex' => $hostindex];
            if ($profile_id !== FALSE) {
                $payload['profile_id'] = $profile_id;
            }
            $this->diag_call($payload, $this->catlink."/diag/radius_tests.php");
            $filename = $this->jsondir.'/'.$this->token."/udp_$hostindex";
            if ($this->token && is_dir($this->jsondir.'/'.$this->token) && is_file($filename)) {  
                $testdata = json_decode(file_get_contents($filename), TRUE);
            }
            $radius['returncode'] = $testdata['returncode'][0];
            if ($radius['returncode'] == \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT) {
                $radius['returncode'] = "OK (REJECT)";
            }
            if ($radius['returncode'] == \core\diag\RADIUSTests::RETVAL_IMMEDIATE_REJECT) {
                $radius['returncode'] = "IMMEDIATE REJECT";
            }
            $radius['time_millisec'] = $testdata['result'][0]['time_millisec'];
            $radius['message'] = $testdata['result'][0]['message'];
            $radius['datetime'] = $testdata['datetime'];
            $infrastructure_test['radius_hosts_tests'][] = $radius;
        }
        return $infrastructure_test;
    }
    
    private function live_login_test($profile_id, $realm, $login_outer, $login_user, $login_pass) {
        $live_test = [];
        if ($login_outer === FALSE) {
            $login_outer = '';
        }
        if ($login_pass === FALSE) {
            $login_pass = '';
        }
        foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
            $payload = ['test_type' => 'udp_login', 'realm' => $realm, 'token' => $this->token, 'src' => $hostindex, 'hostindex' => $hostindex];
            if ($profile_id !== FALSE) {
                $payload['profile_id'] = $profile_id;
            }
            if ($profile_id === FALSE) {
                $live_login = [];
                $live_login['message'] = _("Live login test requires ATTRIB-CAT-PROFILEID value");
                $live_test['live_login_tests'][] = $live_login;
            } else {
                $payload['profile_id'] = $profile_id;
                $payload['username'] = $login_user;
                $payload['password'] = $login_pass;
                $payload['outer_username'] = "$login_outer@$realm";
                $this->diag_call($payload, $this->catlink."/diag/radius_tests.php");
                $testdata = NULL;
                $filename = $this->jsondir.'/'.$this->token."/udp_login_$hostindex";
                if ($this->token && is_dir($this->jsondir.'/'.$this->token) && is_file($filename)) {
                    $testdata = json_decode(file_get_contents($filename), TRUE);
                }
                if ($testdata !== NULL) {
                    $live_login['name'] = $host['display_name'];
                    $live_login['ip'] = $host["ip"];
                    $live_login['returncode'] = $testdata['returncode'][0];
                    if ($live_login['returncode'] == \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT) {
                        $live_login['returncode'] = "REJECT";
                    }
                    if ($live_login['returncode'] == \core\diag\RADIUSTests::RETVAL_OK) {
                        $live_login['returncode'] = "ACCEPT";
                    }
                    $live_login['time_millisec'] = $testdata["result"][0]["time_millisec"];
                    $live_login['message'] = $testdata["result"][0]["message"];
                    $live_login['eap_type'] = $testdata["result"][0]["eap"];
                    $live_test['live_login_tests'][] = $live_login;
                }
            }
        }
        return $live_test;
    }
    
    private function dynamic_test($realm, $totest) {
        $dynamic_test_res['dynamic_connectivity_tests'] = [];
        if (isset($totest) && count($totest) > 0) {
            foreach ($totest as $i=>$host) {
                $dynamic = [];
                $dynamic['host'] = $host['host'];
                $dynamic['name'] = $host['name'];
                $payload = ['test_type' => 'capath', 'realm' => $realm, 'token' => $this->token, 'src' => $host['host'], 
                            'hostindex' => $i, 'expectedname' => $host['name'], 'ssltest' => $host['ssltest']];
                $this->diag_call($payload, $this->catlink."/diag/radius_tests.php");
                $payload['test_type'] = 'clients';
                $this->diag_call($payload, $this->catlink."/diag/radius_tests.php");
                $filename = $this->jsondir.'/'.$this->token."/capath_$i";
                if ($this->token && is_dir($this->jsondir.'/'.$this->token) && is_file($filename)) {  
                    $testdata = json_decode(file_get_contents($filename), TRUE);
                    $dynamic['time_millisec'] = $testdata['time_millisec'];
                    if ($testdata['result'] === 0) {
                        $dynamic['check_ca'] = 'PASSED';
                    } else {
                        $dynamic['check_ca'] = 'FAILED';
                    }
                }
                $dynamic_test_res['dynamic_connectivity_tests'][] = $dynamic;
            }
        }
        return $dynamic_test_res;
    }
        
    public $loggerInstance;
    public $scrubbedParameters = [];
    public $fed;

}
