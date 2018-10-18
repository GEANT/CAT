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

function commonSbProfileChecks($fed, $id) {
    $validator = new \web\lib\common\InputValidation();
    $adminApi = new \web\lib\admin\API();
    try {
        $profile = $validator->Profile($id);
    } catch (Exception $e) {
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Profile identifier does not exist!");
        return FALSE;
    }
    if (!$profile instanceof core\ProfileSilverbullet) {
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Profile identifier is not SB!");
        return FALSE;
    }
    $idp = new \core\IdP($profile->institution);
    if (strtoupper($idp->federation) != strtoupper($fed->tld)) {
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Profile is not in the federation for this APIKEY!");
        return FALSE;
    }
    if (count($profile->getAttributes("hiddenprofile:tou_accepted")) < 1) {
        $adminApi->returnError(web\lib\admin\API::ERROR_NO_TOU, "The terms of use have not yet been accepted for this profile!");
        return FALSE;
    }
    return [$idp, $profile];
}

// no SAML auth on this page. The API key authenticates the entity

$mode = "API";

$adminApi = new \web\lib\admin\API();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

if (!isset(CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys']) || count(CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys']) == 0) {
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
foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys'] as $key => $fed_name) {
    if ($inputDecoded['APIKEY'] == $key) {
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
$fed = new \core\Federation($federation);
// it's a valid admin; what does he want to do?
if (!array_key_exists($inputDecoded['ACTION'], web\lib\admin\API::ACTIONS)) {
    $adminApi->returnError(web\lib\admin\API::ERROR_NO_ACTION, "JSON request structure did not contain a valid ACTION");
    exit(1);
}
// it's a valid ACTION, so let's sanitise the input parameters
$scrubbedParameters = $adminApi->scrub($inputDecoded, $fed);
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

switch ($inputDecoded['ACTION']) {
    case web\lib\admin\API::ACTION_NEWINST:
        // create the inst, no admin, no attributes
        $idp = new \core\IdP($fed->newIdP("PENDING", "API"));
        // now add all submitted attributes
        $inputs = $adminApi->uglify($scrubbedParameters);
        $optionParser->processSubmittedFields($idp, $inputs["POST"], $inputs["FILES"]);
        $adminApi->returnSuccess([web\lib\admin\API::AUXATTRIB_CAT_INST_ID => $idp->identifier]);
        break;
    case web\lib\admin\API::ACTION_DELINST:
        try {
            $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        $idp->destroy();
        $adminApi->returnSuccess([]);
        break;
    case web\lib\admin\API::ACTION_ADMIN_LIST:
        try {
            $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        $adminApi->returnSuccess($idp->listOwners());
        break;
    case web\lib\admin\API::ACTION_ADMIN_ADD:
        // IdP in question
        try {
            $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        // here is the token
        $mgmt = new core\UserManagement();
        // we know we have an admin ID but scrutinizer wants this checked more explicitly
        $admin = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_ADMINID);
        if ($admin === FALSE) {
            throw new Exception("A required parameter is missing, and this wasn't caught earlier?!");
        }
        $newtokens = $mgmt->createTokens(true, [ $admin ], $idp);
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=".array_keys($newtokens)[0];
        $success = ["TOKEN URL" => $URL, "TOKEN" => array_keys($newtokens)[0]];
        // done with the essentials - display in response. But if we also have an email address, send it there
        $email = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TARGETMAIL);
        if ($email !== FALSE) {
            $sent = \core\common\OutsideComm::adminInvitationMail($email, "EXISTING-FED", array_keys($newtokens)[0], $idp->name, $fed);
            $success["EMAIL SENT"] = $sent["SENT"];
            if ($sent["SENT"] === TRUE) {
                $success["EMAIL TRANSPORT SECURE"] = $sent["TRANSPORT"];
            }
        }
        $adminApi->returnSuccess($success);
        break;
    case web\lib\admin\API::ACTION_ADMIN_DEL:
        // IdP in question
        try {
            $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        $currentAdmins = $idp->listOwners();
        $toBeDeleted = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_ADMINID);
        if ($toBeDeleted === FALSE) {
            throw new Exception("A required parameter is missing, and this wasn't caught earlier?!");
        }
        $found = FALSE;
        foreach ($currentAdmins as $oneAdmin) {
            if ($oneAdmin['MAIL'] == $toBeDeleted) {
                $found = TRUE;
                $mgmt = new core\UserManagement();
                $mgmt->removeAdminFromIdP($idp, $oneAdmin['ID']);
            }
        }
        if ($found) {
            $adminApi->returnSuccess([]);
        }
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "The admin with ID $toBeDeleted is not associated to IdP " . $idp->identifier);
        break;
    case web\lib\admin\API::ACTION_STATISTICS_FED:
        $adminApi->returnSuccess($fed->downloadStats("array"));
        break;
    case \web\lib\admin\API::ACTION_NEWPROF_RADIUS:
    // fall-through intended: both get mostly identical treatment
    case web\lib\admin\API::ACTION_NEWPROF_SB:
        try {
            $idp = $validator->IdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID));
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        if ($inputDecoded['ACTION'] == web\lib\admin\API::ACTION_NEWPROF_RADIUS) {
            $type = "RADIUS";
        } else {
            $type = "SILVERBULLET";
        }
        $profile = $idp->newProfile($type);
        if ($profile === NULL) {
            $adminApi->returnError(\web\lib\admin\API::ERROR_INTERNAL_ERROR, "Unable to create a new Profile, for no apparent reason. Please contact support.");
            exit(1);
        }
        $inputs = $adminApi->uglify($scrubbedParameters);
        $optionParser->processSubmittedFields($profile, $inputs["POST"], $inputs["FILES"]);
        if ($inputDecoded['ACTION'] == web\lib\admin\API::ACTION_NEWPROF_SB) {
            // auto-accept ToU?
            if ($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_TOU) !== FALSE) {
                $profile->addAttribute("hiddenprofile:tou_accepted", NULL, TRUE);
            }
            // we're done at this point
            $adminApi->returnSuccess([\web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID => $profile->identifier]);
            continue;
        }
        if (!$profile instanceof core\ProfileRADIUS) {
            throw new Exception("Can't be. This is only here to convince Scrutinizer that we're really talking RADIUS.");
        }
        /* const AUXATTRIB_PROFILE_REALM = 'ATTRIB-PROFILE-REALM';
          const AUXATTRIB_PROFILE_OUTERVALUE = 'ATTRIB-PROFILE-OUTERVALUE'; */
        $realm = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_PROFILE_REALM);
        $outer = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_PROFILE_OUTERVALUE);
        if ($realm !== FALSE) {
            if ($outer === FALSE) {
                $outer = "";
                $profile->setAnonymousIDSupport(FALSE);
            } else {
                $outer = $outer . "@";
                $profile->setAnonymousIDSupport(TRUE);
            }
            $profile->setRealm($outer . $realm);
        }
        /* const AUXATTRIB_PROFILE_TESTUSER = 'ATTRIB-PROFILE-TESTUSER'; */
        $testuser = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_PROFILE_TESTUSER);
        if ($testuser !== FALSE) {
            $profile->setRealmCheckUser(TRUE, $testuser);
        }
        /* const AUXATTRIB_PROFILE_INPUT_HINT = 'ATTRIB-PROFILE-HINTREALM';
          const AUXATTRIB_PROFILE_INPUT_VERIFY = 'ATTRIB-PROFILE-VERIFYREALM'; */
        $hint = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_PROFILE_INPUT_HINT);
        $enforce = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_PROFILE_INPUT_VERIFY);
        if ($enforce !== FALSE) {
            $profile->setInputVerificationPreference($enforce, $hint);
        }
        /* const AUXATTRIB_PROFILE_EAPTYPE */
        $iterator = 1;
        foreach ($scrubbedParameters as $oneParam) {
            if ($oneParam['NAME'] == web\lib\admin\API::AUXATTRIB_PROFILE_EAPTYPE && is_int($oneParam["VALUE"])) {
                $type = new \core\common\EAP($oneParam["VALUE"]);
                $profile->addSupportedEapMethod($type, $iterator);
                $iterator = $iterator + 1;
            }
        }
        // reinstantiate $profile freshly from DB - it was updated in the process
        $profileFresh = new core\ProfileRADIUS($profile->identifier);
        $profileFresh->prepShowtime();
        $adminApi->returnSuccess([\web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID => $profileFresh->identifier]);
        break;
    case web\lib\admin\API::ACTION_ENDUSER_NEW:
        $evaluation = commonSbProfileChecks($fed, $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID));
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $user = $validator->string($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERNAME));
        $expiryRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_EXPIRY);
        if ($expiryRaw === FALSE) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "The expiry date wasn't found in the request.");
            exit(1);
        }
        $expiry = new DateTime($expiryRaw);
        try {
            $retval = $profile->addUser($user, $expiry);
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INTERNAL_ERROR, "The operation failed. Maybe a duplicate username, or malformed expiry date?");
            exit(1);
        }
        if ($retval == 0) {// that didn't work, it seems
            $adminApi->returnError(web\lib\admin\API::ERROR_INTERNAL_ERROR, "The operation failed subtly. Contact the administrators.");
            exit(1);
        }
        $adminApi->returnSuccess([web\lib\admin\API::AUXATTRIB_SB_USERNAME => $user, \web\lib\admin\API::AUXATTRIB_SB_USERID => $retval]);
        break;
    case \web\lib\admin\API::ACTION_ENDUSER_DEACTIVATE:
    // fall-through intended: both actions are very similar
    case \web\lib\admin\API::ACTION_TOKEN_NEW:
        $evaluation = commonSbProfileChecks($fed, $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID));
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $userId = $validator->integer($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERID));
        if ($userId === FALSE) {
            $adminApi->returnError(\web\lib\admin\API::ERROR_INVALID_PARAMETER, "User ID is not an integer.");
            exit(1);
        }
        $additionalInfo = [];
        switch ($inputDecoded['ACTION']) { // this is where the two differ
            case \web\lib\admin\API::ACTION_ENDUSER_DEACTIVATE:
                $result = $profile->deactivateUser($userId);
                break;
            case \web\lib\admin\API::ACTION_TOKEN_NEW:
                $counter = $validator->integer($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TOKEN_ACTIVATIONS));
                if (!is_integer($counter)) {
                    $counter = 1;
                }
                $invitation = core\SilverbulletInvitation::createInvitation($profile->identifier, $userId, $counter);
                $result = TRUE;
                $additionalInfo[\web\lib\admin\API::AUXATTRIB_TOKENURL] = $invitation->link();
                $additionalInfo[\web\lib\admin\API::AUXATTRIB_TOKEN] = $invitation->invitationTokenString;
                $emailRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TARGETMAIL);
                if ($emailRaw) { // an email parameter was specified
                    $email = $validator->email($emailRaw);
                    if ($email) { // it's a valid address
                        $retval = $invitation->sendByMail($email);
                        $additionalInfo["EMAIL SENT"] = $retval["SENT"];
                        if ($retval["SENT"]) {
                            $additionalInfo["EMAIL TRANSPORT SECURE"] = $retval["TRANSPORT"];
                        }
                    }
                }
                $smsRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TARGETSMS);
                if ($smsRaw) {
                    $sms = $validator->sms($smsRaw);
                    if ($sms) {
                        $wasSent = $invitation->sendBySms($sms);
                        $additionalInfo["SMS SENT"] = $wasSent == core\common\OutsideComm::SMS_SENT ? TRUE : FALSE;
                    }
                }
                break;
        }

        if ($result !== TRUE) {
            $adminApi->returnError(\web\lib\admin\API::ERROR_INVALID_PARAMETER, "These parameters did not lead to an existing, active user.");
            exit(1);
        }
        $adminApi->returnSuccess($additionalInfo);
        break;
    case \web\lib\admin\API::ACTION_ENDUSER_LIST:
        // fall-through: those two are similar
            case \web\lib\admin\API::ACTION_TOKEN_LIST:
        $evaluation = commonSbProfileChecks($fed, $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID));
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $allUsers = $profile->listAllUsers();
        // this is where they differ
        switch ($inputDecoded['ACTION']) {
            case \web\lib\admin\API::ACTION_ENDUSER_LIST:
                $adminApi->returnSuccess($allUsers);
                break;
            case \web\lib\admin\API::ACTION_TOKEN_LIST:
                $user = $validator->integer($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERID));
                if ($user !== FALSE) {
                    $allUsers = [$user];
                }
                $tokens = [];
                foreach ($allUsers as $oneUser) {
                    $tokens = array_merge($tokens, $profile->userStatus($oneUser));
                }
                $adminApi->returnSuccess($tokens);
        }
        break;
    case \web\lib\admin\API::ACTION_TOKEN_REVOKE:
        $token = new core\SilverbulletInvitation($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TOKEN));
        if ($token->invitationTokenStatus !== core\SilverbulletInvitation::SB_TOKENSTATUS_VALID && $token->invitationTokenStatus !== core\SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "This is not a currently valid token.");
            exit(1);
        }
        $token->revokeInvitation();
        $adminApi->returnSuccess([]);
        break;
    default:
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_ACTION, "Not implemented yet.");
}