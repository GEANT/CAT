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
     * An internal error occured and the requested action could not be
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

    /*
     * ACTIONS consists of a list of keywords, and associated REQuired and OPTional parameters
     * 
     */
    const ACTIONS = [
        // Inst-level actions.
        API::ACTION_NEWINST_BY_REF => [
            "REQ" => [API::AUXATTRIB_EXTERNALID,],
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
            "RETVAL" => [
                API::AUXATTRIB_CAT_INST_ID, // New inst ID.
            ],
        ],
        API::ACTION_DELINST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [],
            "RETVAL" => [],
        ],
        // Inst administrator management.
        API::ACTION_ADMIN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [
            ],
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
            "RETVAL" => [],
        ],
        // Statistics.
        API::ACTION_STATISTICS_INST => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => []
        ],
        API::ACTION_STATISTICS_FED => [
            "REQ" => [],
            "OPT" => [],
            "RETVAL" => [
                ["device_id" => ["ADMIN", "SILVERBULLET", "USER"]] // Plus "TOTAL".
            ],
        ],
        API::ACTION_FEDERATION_LISTIDP => [
            "REQ" => [],
            "OPT" => [API::AUXATTRIB_CAT_INST_ID],
            "RETVAL" => [API::AUXATTRIB_CAT_INST_ID => "JSON_DATA"],
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
            "RETVAL" => API::AUXATTRIB_CAT_PROFILE_ID,
        ],
        // Silverbullet profile actions.
        API::ACTION_NEWPROF_SB => [
            "REQ" => [API::AUXATTRIB_CAT_INST_ID],
            "OPT" => [API::AUXATTRIB_SB_TOU],
            "RETVAL" => API::AUXATTRIB_CAT_PROFILE_ID,
        ],
        API::ACTION_ENDUSER_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_EXPIRY],
            "OPT" => [],
            "RETVAL" => [API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_USERID],
        ],
        API::ACTION_ENDUSER_CHANGEEXPIRY => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_EXPIRY],
            "OPT" => [],
            "RETVAL" => [],
        ],
        API::ACTION_ENDUSER_DEACTIVATE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [],
            "RETVAL" => [],
        ],
        API::ACTION_ENDUSER_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [],
            "RETVAL" => [
                [API::AUXATTRIB_SB_USERID => API::AUXATTRIB_SB_USERNAME],
            ],
        ],
        API::ACTION_ENDUSER_IDENTIFY => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [API::AUXATTRIB_SB_USERID, API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_CERTSERIAL, API::AUXATTRIB_SB_CERTCN],
            "RETVAL" => [API::AUXATTRIB_SB_USERNAME, API::AUXATTRIB_SB_USERID],
        ],
        API::ACTION_TOKEN_NEW => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [API::AUXATTRIB_TOKEN_ACTIVATIONS, API::AUXATTRIB_TARGETMAIL, API::AUXATTRIB_TARGETSMS],
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
            "RETVAL" => [],
        ],
        API::ACTION_TOKEN_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID],
            "OPT" => [API::AUXATTRIB_SB_USERID],
            "RETVAL" => [
                [API::AUXATTRIB_SB_USERID => [API::AUXATTRIB_TOKEN, "STATUS"]],
            ]
        ],
        API::ACTION_CERT_LIST => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_USERID],
            "OPT" => [],
            "RETVAL" => [
                [API::AUXATTRIB_SB_CERTSERIAL => ["ISSUED", "EXPIRY", "STATUS", "DEVICE", "CN"]]
            ]
        ],
        API::ACTION_CERT_REVOKE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_CERTSERIAL],
            "OPT" => [],
            "RETVAL" => [],
        ],
        API::ACTION_CERT_ANNOTATE => [
            "REQ" => [API::AUXATTRIB_CAT_PROFILE_ID, API::AUXATTRIB_SB_CERTSERIAL, API::AUXATTRIB_SB_CERTANNOTATION],
            "OPT" => [],
            "RETVAL" => [],
        ]
    ];

    /**
     *
     * @var \web\lib\common\InputValidation
     */
    private $validator;

    /**
     * construct the API class
     */
    public function __construct() {
        $this->validator = new \web\lib\common\InputValidation();
    }

    /**
     * Only leave attributes in the request which are related to the ACTION.
     * Also sanitise by enforcing LANG attribute in multi-lang attributes.
     * 
     * @param array            $inputJson the incoming JSON request
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
                            $inst = $this->validator->existingIdP($oneIncomingParam['VALUE']);
                        } catch (Exception $e) {
                            continue 2;
                        }
                        if (strtoupper($inst->federation) != strtoupper($fedObject->tld)) {
                            // IdP in different fed, scrub it.
                            continue 2;
                        }
                        break;
                    case API::AUXATTRIB_TARGETMAIL:
                        if ($this->validator->email($oneIncomingParam['VALUE']) === FALSE) {
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
                    default:
                        break;
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
     * @param array  $inputs   incoming set of arrays
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
        $validator = new \web\lib\common\InputValidation();
        $adminApi = new \web\lib\admin\API();
        try {
            $profile = $validator->existingProfile($id);
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

}
