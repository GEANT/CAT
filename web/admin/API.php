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
    exit(1);
}

function return_success($details) {
    echo json_encode(["result" => "SUCCESS", "details" => $details], JSON_PRETTY_PRINT);
    exit(0);
}

if (!isset(CONFIG['registration_API_keys']) || count(CONFIG['registration_API_keys']) == 0) {
    return_error(web\lib\admin\API::ERROR_API_DISABLED, "API is disabled in this instance of CAT");
}

$inputRaw = file_get_contents('php://input');
$inputDecoded = json_decode($inputRaw, TRUE);
if (!is_array($inputDecoded)) {
    return_error(web\lib\admin\API::ERROR_MALFORMED_REQUEST, "Unable to decode JSON POST data.");
}

if (!isset($inputDecoded['APIKEY'])) {
    return_error(web\lib\admin\API::ERROR_NO_APIKEY, "JSON request structure did not contain an APIKEY");
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
}

// let's instantiate the fed, we will need it later
$fed = new \core\Federation($federation);
// it's a valid admin; what does he want to do?
if (!array_key_exists($inputDecoded['ACTION'], web\lib\admin\API::ACTIONS)) {
    return_error(web\lib\admin\API::ERROR_NO_ACTION, "JSON request structure did not contain a valid ACTION");
}
// it's a valid ACTION, so let's sanitise the input parameters
$scrubbedParameters = $adminApi->scrub($inputDecoded);
$paramNames = [];
foreach ($scrubbedParameters as $oneParam) {
    $paramNames[] = $oneParam['NAME'];
}
// are all the required parameters (still) in the request?
foreach (web\lib\admin\API::ACTIONS[$inputDecoded['ACTION']]['REQ'] as $oneRequiredAttribute) {
    if (!in_array($oneRequiredAttribute, $paramNames)) {
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
        return_success([web\lib\admin\API::AUXATTRIB_CAT_INST_ID => $idp->identifier]);
        break;
    case web\lib\admin\API::ACTION_DELINST:
        try {
        $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch(Exception $e) {
            return_error(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
        }
        $idp->destroy();
        return_success([]);
        break;
    case web\lib\admin\API::ACTION_ADMIN_ADD:
        // IdP in question
        try {
        $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch(Exception $e) {
            return_error(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
        }
        // here is the token
        $mgmt = new core\UserManagement();
        // we know we have an admin ID but scrutinizer wants this checked more explicitly
        $admin = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_ADMINID);
        if ($admin === FALSE) {
            throw new Exception("A required parameter is missing, and this wasn't caught earlier?!");
        }
        $newtoken = $mgmt->createToken(true, $admin, $idp);
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=$newtoken";
        $success = ["TOKEN URL" => $URL];
        // done with the essentials - display in response. But if we also have an email address, send it there
        $email = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_ADMINEMAIL);
        if ($email !== FALSE) {
            $sent = \core\common\OutsideComm::adminInvitationMail($email, "EXISTING-FED", $newtoken, $idp->name, $fed);
            $success["EMAIL SENT"] = $sent["SENT"];
            if ($sent["SENT"] === TRUE) {
                $success["EMAIL TRANSPORT SECURE"] = $sent["TRANSPORT"];
            }
        }
        return_success($success);
        break;
    default:
        return_error(web\lib\admin\API::ERROR_INVALID_ACTION, "Not implemented yet.");
}
