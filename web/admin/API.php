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


/*
 * FIX for v2.2.1 : introduce better type-safety for admin API - reported by: Nahit (Github: https://github.com/Dogru-Isim) 
 */

require_once dirname(dirname(dirname(__FILE__)))."/config/_config.php";

// no SAML auth on this page. The API key authenticates the entity

$mode = "API";
$adminApi = new \web\lib\admin\API();

if (!isset(\config\ConfAssistant::CONSORTIUM['registration_API_keys']) || count(\config\ConfAssistant::CONSORTIUM['registration_API_keys']) == 0) {
    $adminApi->returnError(web\lib\admin\API::ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}
$inputRaw = file_get_contents('php://input');

$inputDecoded = json_decode($inputRaw, TRUE);
if (!is_array($inputDecoded)) {
    $adminApi->returnError(web\lib\admin\API::ERROR_MALFORMED_REQUEST, "Unable to decode JSON POST data.".json_last_error_msg().$inputRaw);
    exit(1);
}

if (!isset($inputDecoded['APIKEY'])) {
    $adminApi->returnError(web\lib\admin\API::ERROR_NO_APIKEY, "JSON request structure did not contain an APIKEY");
    exit(1);
}

$checkval = "FAIL";
foreach (\config\ConfAssistant::CONSORTIUM['registration_API_keys'] as $key => $fed_name) {
    if ($inputDecoded['APIKEY'] === $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }
}

if ($checkval == "FAIL") {
    $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_APIKEY, "APIKEY is invalid");
    exit(1);
}

// let's instantiate the fed, we will need it later
$adminApi->fed = new \core\Federation($federation);
// it's a valid admin; what does he want to do?
if (!array_key_exists($inputDecoded['ACTION'], web\lib\admin\API::ACTIONS)) {
    $adminApi->returnError(web\lib\admin\API::ERROR_NO_ACTION, "JSON request structure did not contain a valid ACTION");
    exit(1);
}

$actionMethodName = 'action'.str_replace('-','',ucwords(strtolower($inputDecoded['ACTION']), '-'));
// it's a valid ACTION, so let's sanitise the input parameters
$scrubbedParameters = $adminApi->scrub($inputDecoded);
$paramNames = [];
foreach ($scrubbedParameters as $oneParam) {
    $paramNames[] = $oneParam['NAME'];
}
// are all the required parameters (still) in the request?
foreach (web\lib\admin\API::ACTIONS[$inputDecoded['ACTION']]['REQ'] as $oneRequiredAttribute) {
    if (!in_array($oneRequiredAttribute, $paramNames)) {
        $adminApi->returnError(web\lib\admin\API::ERROR_MISSING_PARAMETER, "At least one required parameter for this ACTION is missing: $oneRequiredAttribute");
        exit(1);
    }
}
foreach ($scrubbedParameters as $oneParam) {
    if ($oneParam['VERIFY_RESULT'] === false) {
        $adminApi->returnError(web\lib\admin\API::ERROR_MISSING_PARAMETER, $oneParam['VERIFY_DESC']);
        exit(1);
    }
}

$adminApi->$actionMethodName();