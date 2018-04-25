<?php

/*
 * ******************************************************************************
 * Copyright 2011-2018 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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
    const ACTION_NEWINST_BY_REF = "NEWINST-BY-REF";
    const ACTION_NEWINST = "NEWINST";
    const ACTION_DELINST = "DELINST";
    const ACTION_ADMIN_LIST = "ADMIN-LIST";
    const ACTION_ADMIN_ADD = "ADMIN-ADD";
    const ACTION_ADMIN_DEL = "ADMIN-DEL";
    const ACTION_STATISTICS_INST = "STATISTICS-INST";
    const ACTION_STATISTICS_FED = "STATISTICS-FED";
    const ACTION_NEWPROF_RADIUS = "NEWPROF-RADIUS";
    const ACTION_NEWPROF_SB = "NEWPROF-SB";
    const ACTION_ENDUSER_NEW = "ENDUSER-NEW";
    const ACTION_ENDUSER_DEACTIVATE = "ENDUSER-DEACTIVATE";
    const ACTION_ENDUSER_LIST = "ENDUSER-LIST";
    const ACTION_TOKEN_NEW = "TOKEN-NEW";
    const ACTION_TOKEN_REVOKE = "TOKEN-REVOKE";
    const ACTION_TOKEN_LIST = "TOKEN-LIST";
    const ACTION_CERT_LIST = "CERT-LIST";
    const ACTION_CERT_REVOKE = "CERT-REVOKE";
    const AUXATTRIB_ADMINID = "ATTRIB-ADMINID";
    const AUXATTRIB_ADMINEMAIL = "ATTRIB-ADMINEMAIL";
    const AUXATTRIB_EXTERNALID = "ATTRIB-EXTERNALID";
    const AUXATTRIB_CAT_INST_ID = "ATTRIB-CAT-INSTID";

    /*
     * ACTIONS consists of a list of keywords, and associated REQuired and OPTional parameters
     * 
     */
    const ACTIONS = [
        # inst-level actions
        API::ACTION_NEWINST_BY_REF => [
            "REQ" => [API::AUXATTRIB_EXTERNALID,],
            "OPT" => ['general:geo_coordinates', 'general:logo_file', 'media:SSID', 'media:SSID_with_legacy', 'media:wired', 'media:remove_SSID', 'media:consortium_OI', 'media:force_proxy', 'support:email', 'support:info_file', 'support:phone', 'support:url'],
        ],
        API::ACTION_NEWINST => [
            "REQ" => [],
            "OPT" => ['general:instname', 'general:geo_coordinates', 'general:logo_file', 'media:SSID', 'media:SSID_with_legacy', 'media:wired', 'media:remove_SSID', 'media:consortium_OI', 'media:force_proxy', 'support:email', 'support:info_file', 'support:phone', 'support:url'],
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
            "REQ" => [API::AUXATTRIB_ADMINID, API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [API::AUXATTRIB_ADMINEMAIL]
        ],
        API::ACTION_ADMIN_DEL => [
            "REQ" => [API::AUXATTRIB_ADMINID, API::AUXATTRIB_CAT_INST_ID],
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
            "REQ" => [],
            "OPT" => []
        ],
        # Silverbullet profile actions
        API::ACTION_NEWPROF_SB => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_ENDUSER_NEW => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_ENDUSER_DEACTIVATE => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_ENDUSER_LIST => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_TOKEN_NEW => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_TOKEN_REVOKE => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_TOKEN_LIST => [
            "REQ" => [],
            "OPT" => []
        ],
        API::ACTION_CERT_LIST => [
            "REQ" => [],
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
                    case API::AUXATTRIB_ADMINEMAIL:
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

    public function return_error($code, $description) {
        echo json_encode(["result" => "ERROR", "details" => ["errorcode" => $code, "description" => $description]], JSON_PRETTY_PRINT);
        exit(1);
    }

    public function return_success($details) {
        echo json_encode(["result" => "SUCCESS", "details" => $details], JSON_PRETTY_PRINT);
        exit(0);
    }

}
