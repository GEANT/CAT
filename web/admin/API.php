<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");


// no SAML auth on this page. The API key authenticates the entity

$mode = "API";

$adminApi = new \web\lib\admin\API();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

function return_error($code, $description) {
    echo json_encode(["result" => "ERROR", "details" => ["errorcode" => $code, "description" => $description]], JSON_PRETTY_PRINT);
}

if (!isset(CONFIG['registration_API_keys']) || count(CONFIG['registration_API_keys']) == 0) {
    return_error(web\lib\admin\API::ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}

$inputRaw = file_get_contents('php://input');
$inputDecoded = json_decode($inputRaw, TRUE);
if (!is_array($inputDecoded)) {
    return_error(web\lib\admin\API::ERROR_MALFORMED_REQUEST, "Unable to decode JSON POST data.");
    exit(1);
}

if (!isset($inputDecoded['APIKEY'])) {
    return_error(web\lib\admin\API::ERROR_NO_APIKEY, "JSON request structure did not contain an APIKEY");
    exit(1);
}

$checkval = "FAIL";
foreach (CONFIG['registration_API_keys'] as $key => $fed_name) {
    if ($inputDecoded['APIKEY'] == $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }
}

if ($checkval == "FAIL") {
    return_error(web\lib\admin\API::ERROR_INVALID_APIKEY, "APIKEY is invalid");
    exit(1);
}

// let's instantiate the fed, we will need it later
$fed = new \core\Federation($federation);
// it's a valid admin; what does he want to do?
if (!array_key_exists($inputDecoded['ACTION'], web\lib\admin\API::ACTIONS)) {
    return_error(ERROR_NO_ACTION, "JSON request structure did not contain a valid ACTION");
    exit(1);
}
// it's a valid ACTION, so let's sanitise the input parameters
$scrubbedParameters = $adminApi->scrub($inputDecoded);
// are all the required parameters (still) in the request?
foreach (web\lib\admin\API::ACTIONS[$inputDecoded['ACTION']]['REQ'] as $oneRequiredAttribute) {
    if (!in_array($oneRequiredAttribute, $scrubbedParameters)) {
        return_error(web\lib\admin\API::ERROR_MISSING_PARAMETER, "At least one required parameter for this ACTION is missing: $oneRequiredAttribute");
    }
}

switch ($inputDecoded['ACTION']) {
    case web\lib\admin\API::ACTION_NEWINST:
        // create the inst, no admin, no attributes
        $idp = new \core\IdP($fed->newIdP("PENDING", "API"));
        // now add all submitted attributes
        $inputs = $adminApi->uglify($scrubbedParameters);
        $optionParser->processSubmittedFields($idp, $inputs["POST"], $inputs["FILES"]);
        break;
    case web\lib\admin\API::ACTION_ADMIN_ADD:
        // generate the token
        $newtoken = $mgmt->createToken(true, $validator->string($_POST['NEWINST_PRIMARYADMIN']), $idp);
        // and send it back to the caller
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=$newtoken";
        echo "<CAT-API-Response>\n";
        echo "  <success action='NEWINST'>\n    <enrollment_URL>$URL</enrollment_URL>\n    <inst_unique_id>" . $idp->identifier . "</inst_unique_id>\n  </success>\n";
        echo "</CAT-API-Response>\n";
        exit(0);
        break;
    default:
        return_error(web\lib\admin\API::ERROR_INVALID_ACTION, "Not implemented yet.");
        exit(1);
}
