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

namespace web\lib\admin;

use Exception;

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

class API {

    const ERROR_API_DISABLED = 1;
    const ERROR_NO_APIKEY = 2;
    const ERROR_INVALID_APIKEY = 3;
    const ERROR_MISSING_PARAMETER = 4;
    const ERROR_INVALID_PARAMETER = 5;
    const ERROR_NO_ACTION = 6;
    const ERROR_INVALID_ACTION = 7;
    const ERROR_MALFORMED_REQUEST = 8;
    const ERROR_INTERNAL_ERROR = 9;
    const ERROR_NO_TOU = 10;
    const ACTION_NEWINST_BY_REF = "NEWINST-BY-REF";
    const ACTION_NEWINST = "NEWINST";
    const ACTION_DELINST = "DELINST";
    const ACTION_ADMIN_LIST = "ADMIN-LIST";
    const ACTION_ADMIN_ADD = "ADMIN-ADD";
    const ACTION_ADMIN_DEL = "ADMIN-DEL";
    const ACTION_STATISTICS_INST = "STATISTICS-INST";
    const ACTION_STATISTICS_FED = "STATISTICS-FED";
    const ACTION_NEWPROF_RADIUS = "NEWPROF-RADIUS";
    const ACTION_NEWPROF_SB = "NEWPROF-MANAGED";
    const ACTION_ENDUSER_NEW = "ENDUSER-NEW";
    const ACTION_ENDUSER_DEACTIVATE = "ENDUSER-DEACTIVATE";
    const ACTION_ENDUSER_LIST = "ENDUSER-LIST";
    const ACTION_TOKEN_NEW = "TOKEN-NEW";
    const ACTION_TOKEN_REVOKE = "TOKEN-REVOKE";
    const ACTION_TOKEN_LIST = "TOKEN-LIST";
    const ACTION_CERT_LIST = "CERT-LIST";
    const ACTION_CERT_REVOKE = "CERT-REVOKE";
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
    const AUXATTRIB_SB_EXPIRY = "ATTRIB-MANAGED-EXPIRY"; /* MySQL timestamp format */
    const AUXATTRIB_TOKEN = "ATTRIB-TOKEN";
    const AUXATTRIB_TOKENURL = "ATTRIB-TOKENURL";
    const AUXATTRIB_TOKEN_ACTIVATIONS = "ATTRIB-TOKEN-ACTIVATIONS";

    /*
     * ACTIONS consists of a list of keywords, and associated REQuired and OPTional parameters
     * 
     */
    const ACTIONS = [
        # inst-level actions
        API::ACTION_NEWINST_BY_REF => [
            "REQ" => [API::AUXATTRIB_EXTERNALID,],
            "OPT" => [
                'general:geo_coordinates',
                'general:logo_file',
                'media:SSID',
                'media:SSID_with_legacy',
                'media:wired',
                'media:remove_SSID',
                'media:consortium_OI',
                'media:force_proxy',
                'support:email',
                'support:info_file',
                'support:phone',
                'support:url'
            ],
        ],
        API::ACTION_NEWINST => [
            "REQ" => [],
            "OPT" => [
                'general:instname',
                'general:geo_coordinates',
                'general:logo_file',
                'media:SSID',
                'media:SSID_with_legacy',
                'media:wired',
                'media:remove_SSID',
                'media:consortium_OI',
                'media:force_proxy',
                'support:email',
                'support:info_file',
                'support:phone',
                'support:url'
            ],
        ],
        API::ACTION_DELINST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => []
        ],
        # inst administrator management
        API::ACTION_ADMIN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => []
        ],
        API::ACTION_ADMIN_ADD => [
            "REQ" => [
                API::AUXATTRIB_ADMINID,
                API::AUXATTRIB_CAT_INST_ID
            ],
            "OPT" => [API::AUXATTRIB_TARGETMAIL]
        ],
        API::ACTION_ADMIN_DEL => [
            "REQ" => [
                API::AUXATTRIB_ADMINID,
                API::AUXATTRIB_CAT_INST_ID
            ],
            "OPT" => []
        ],
        # statistics
        API::ACTION_STATISTICS_INST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => []
        ],
        API::ACTION_STATISTICS_FED => [
            "REQ" => [],
            "OPT" => []
        ],
        # RADIUS profile actions
        API::ACTION_NEWPROF_RADIUS => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [
                'eap:ca_file',
                'eap:server_name',
                'media:SSID',
                'media:SSID_with_legacy',
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
                API::AUXATTRIB_PROFILE_INPUT_HINT,
                API::AUXATTRIB_PROFILE_INPUT_VERIFY,
                API::AUXATTRIB_PROFILE_OUTERVALUE,
                API::AUXATTRIB_PROFILE_REALM,
                API::AUXATTRIB_PROFILE_TESTUSER,
                API::AUXATTRIB_PROFILE_EAPTYPE,
            ]
        ],
        # Silverbullet profile actions
        API::ACTION_NEWPROF_SB => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [API::AUXATTRIB_SB_TOU]
        ],
        API::ACTION_ENDUSER_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_EXPIRY],
            "OPT" => []
        ],
        API::ACTION_ENDUSER_DEACTIVATE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => []
        ],
        API::ACTION_ENDUSER_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => []
        ],
        API::ACTION_TOKEN_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [API::AUXATTRIB_TOKEN_ACTIVATIONS, API::AUXATTRIB_TARGETMAIL, API::AUXATTRIB_TARGETSMS]
        ],
        API::ACTION_TOKEN_REVOKE => [
            "REQ" => [API::AUXATTRIB_TOKEN],
            "OPT" => []
        ],
        API::ACTION_TOKEN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [API::AUXATTRIB_SB_USERID]
        ],
        API::ACTION_CERT_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => []
        ],
        API::ACTION_CERT_REVOKE => [
            "REQ" => [],
            "OPT" => []
        ],
    ];

    /**
     *
     * @var \web\lib\common\InputValidation
     */
    private $validator;

    public function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
    }

    /**
     * Only leave attributes in the request which are related to the ACTION.
     * Also sanitise by enforcing LANG attribute in multi-lang attributes.
     * 
     * @param array $inputJson the incoming JSON request
     * @param \core\Federation $fedObject the federation the user is acting within
     * @return array the scrubbed attributes
     */
    public function scrub($inputJson, $fedObject) {
        $optionInstance = \core\Options::instance();
        $parameters = [];
        $allPossibleAttribs = array_merge(API::ACTIONS[$inputJson['ACTION']]['REQ'], API::ACTIONS[$inputJson['ACTION']]['OPT']);
        // some actions don't need parameters. Don't get excited when there aren't any.
        if (!isset($inputJson['PARAMETERS'])) {
            $inputJson['PARAMETERS'] = [];
        }
        foreach ($inputJson['PARAMETERS'] as $number => $oneIncomingParam) {
            // index has to be an integer
            if (!is_int($number)) {
                continue;
            }
            // do we actually have a value?
            if (!array_key_exists("VALUE", $oneIncomingParam)) {
                continue;
            }
            // is this multi-lingual, and not an AUX attrib? Then check for presence of LANG and CONTENT before considering to add
            if (!preg_match("/^ATTRIB-/", $oneIncomingParam['NAME'])) {
                $optionProperties = $optionInstance->optionType($oneIncomingParam['NAME']);
                if ($optionProperties["flag"] == "ML" && !array_key_exists("LANG", $oneIncomingParam)) {
                    continue;
                }
            } else { // sanitise the AUX attr 
                switch ($oneIncomingParam['NAME']) {
                    case API::AUXATTRIB_CAT_INST_ID:
                        try {
                            $inst = $this->validator->IdP($oneIncomingParam['VALUE']);
                        } catch (Exception $e) {
                            continue;
                        }
                        if (strtoupper($inst->federation) != strtoupper($fedObject->tld)) {
                            // IdP in different fed, scrub it.
                            continue;
                        }
                        break;
                    case API::AUXATTRIB_TARGETMAIL:
                        if ($this->validator->email($oneIncomingParam['VALUE']) === FALSE) {
                            continue;
                        }
                        break;
                    case API::AUXATTRIB_ADMINID:
                        try {
                            $oneIncomingParam['VALUE'] = $this->validator->string($oneIncomingParam['VALUE']);
                        } catch (Exception $e) {
                            continue;
                        }
                        break;
                    default:
                        continue;
                }
            }
            if (in_array($oneIncomingParam['NAME'], $allPossibleAttribs)) {
                $parameters[$number] = $oneIncomingParam;
            }
        }
        return $parameters;
    }

    /**
     * extracts the first occurence of a given parameter name from the set of inputs
     * 
     * @param array $inputs incoming set of arrays
     * @param string $expected attribute that is to be extracted
     * @return mixed the value, or FALSE if none was found
     */
    public function firstParameterInstance($inputs, $expected) {
        foreach ($inputs as $attrib) {
            if ($attrib['NAME'] == $expected) {
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
     * @param array $parameters
     */
    public function uglify($parameters) {
        $coercedInline = [];
        $coercedFile = [];
        $optionObject = \core\Options::instance();
        $cat = new \core\CAT();
        $dir = $cat->createTemporaryDirectory('test');
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
                // fall-through: they all get the same treatment
                case \core\Options::TYPECODE_BOOLEAN:
                // fall-through: they all get the same treatment
                case \core\Options::TYPECODE_STRING:
                // fall-through: they all get the same treatment
                case \core\Options::TYPECODE_INTEGER:
                    $extension = $optionInfo['type'];
                    $coercedInline["option"][$basename] = $oneAttrib['NAME'] . "#";
                    $coercedInline["value"][$basename . "-" . $extension] = $oneAttrib['VALUE'];
                    if ($optionInfo['flag'] == "ML") {
                        $coercedInline["value"][$basename . "-lang"] = $oneAttrib['LANG'];
                    }
                    break;
                case \core\Options::TYPECODE_FILE:
                    // binary data is expected in base64 encoding. This is true
                    // also for PEM files!
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

    public function returnError($code, $description) {
        echo json_encode(["result" => "ERROR", "details" => ["errorcode" => $code, "description" => $description]], JSON_PRETTY_PRINT);
    }

    public function returnSuccess($details) {
        echo json_encode(["result" => "SUCCESS", "details" => $details], JSON_PRETTY_PRINT);
    }

}
