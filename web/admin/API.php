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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

// no SAML auth on this page. The API key authenticates the entity

$mode = "API";

$adminApi = new \web\lib\admin\API();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

if (!isset(\config\ConfAssistant::CONSORTIUM['registration_API_keys']) || count(\config\ConfAssistant::CONSORTIUM['registration_API_keys']) == 0) {
    $adminApi->returnError(web\lib\admin\API::ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}

$inputRaw = file_get_contents('php://input');
$inputDecoded = json_decode($inputRaw, TRUE);
if (!is_array($inputDecoded)) {
    $adminApi->returnError(web\lib\admin\API::ERROR_MALFORMED_REQUEST, "Unable to decode JSON POST data." . json_last_error_msg() . $inputRaw);
    exit(1);
}

if (!isset($inputDecoded['APIKEY'])) {
    $adminApi->returnError(web\lib\admin\API::ERROR_NO_APIKEY, "JSON request structure did not contain an APIKEY");
    exit(1);
}

$checkval = "FAIL";
foreach (\config\ConfAssistant::CONSORTIUM['registration_API_keys'] as $key => $fed_name) {
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
        $typeRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_INSTTYPE);
        if ($typeRaw === FALSE) {
            throw new Exception("We did not receive a valid participant type!");
        }
        $type = $validator->partType($typeRaw);
        $idp = new \core\IdP($fed->newIdP($type, "PENDING", "API"));
        // now add all submitted attributes
        $inputs = $adminApi->uglify($scrubbedParameters);
        $optionParser->processSubmittedFields($idp, $inputs["POST"], $inputs["FILES"]);
        $adminApi->returnSuccess([web\lib\admin\API::AUXATTRIB_CAT_INST_ID => $idp->identifier]);
        break;
    case web\lib\admin\API::ACTION_DELINST:
        try {
            $idp = $validator->existingIdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID), NULL, $fed);
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        $idp->destroy();
        $adminApi->returnSuccess([]);
        break;
    case web\lib\admin\API::ACTION_ADMIN_LIST:
        try {
            $idp = $validator->existingIdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID), NULL, $fed);
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
            exit(1);
        }
        $adminApi->returnSuccess($idp->listOwners());
        break;
    case web\lib\admin\API::ACTION_ADMIN_ADD:
        // IdP in question
        try {
            $idp = $validator->existingIdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID), NULL, $fed);
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
        $newtokens = $mgmt->createTokens(true, [$admin], $idp);
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=" . array_keys($newtokens)[0];
        $success = ["TOKEN URL" => $URL, "TOKEN" => array_keys($newtokens)[0]];
        // done with the essentials - display in response. But if we also have an email address, send it there
        $email = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TARGETMAIL);
        if ($email !== FALSE) {
            $sent = \core\common\OutsideComm::adminInvitationMail($email, "EXISTING-FED", array_keys($newtokens)[0], $idp->name, $fed, $idp->type);
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
            $idp = $validator->existingIdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID), NULL, $fed);
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
    case \web\lib\admin\API::ACTION_FEDERATION_LISTIDP:
        $retArray = [];
        $idpIdentifier = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID);
        if ($idpIdentifier === FALSE) {
            $allIdPs = $fed->listIdentityProviders(0);
            foreach ($allIdPs as $instanceId => $oneIdP) {
                $theIdP = $oneIdP["instance"];
                $retArray[$instanceId] = $theIdP->getAttributes();
            }
        } else {
            try {
                $thisIdP = $validator->existingIdP($idpIdentifier, NULL, $fed);
            } catch (Exception $e) {
                $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "IdP identifier does not exist!");
                exit(1);
            }
            $retArray[$idpIdentifier] = $thisIdP->getAttributes();
            foreach ($thisIdP->listProfiles() as $oneProfile) {
                $retArray[$idpIdentifier]["PROFILES"][$oneProfile->identifier] = $oneProfile->getAttributes();
            }
        }
        foreach ($retArray as $instNumber => $oneInstData) {
            foreach ($oneInstData as $attribNumber => $oneAttrib) {
                if ($oneAttrib['name'] == "general:logo_file") {
                    // JSON doesn't cope well with raw binary data, so b64 it
                    $retArray[$instNumber][$attribNumber]['value'] = base64_encode($oneAttrib['value']);
                }
            }
        }
        $adminApi->returnSuccess($retArray);
        break;
    case \web\lib\admin\API::ACTION_NEWPROF_RADIUS:
    // fall-through intended: both get mostly identical treatment
    case web\lib\admin\API::ACTION_NEWPROF_SB:
        try {
            $idp = $validator->existingIdP($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_INST_ID), NULL, $fed);
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
                $profile->addAttribute("hiddenprofile:tou_accepted", NULL, 1);
            }
            // we're done at this point
            $adminApi->returnSuccess([\web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID => $profile->identifier]);
            break;
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
    // fall-through intentional, those two actions are doing nearly identical things
    case web\lib\admin\API::ACTION_ENDUSER_CHANGEEXPIRY:
        $prof_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($prof_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $prof_id);
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $user = $validator->string($adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERNAME));
        $expiryRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_EXPIRY);
        if ($expiryRaw === FALSE) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "The expiry date wasn't found in the request.");
            break;
        }
        $expiry = new DateTime($expiryRaw);
        try {
            switch ($inputDecoded['ACTION']) {
                case web\lib\admin\API::ACTION_ENDUSER_NEW:
                    $retval = $profile->addUser($user, $expiry);
                    break;
                case web\lib\admin\API::ACTION_ENDUSER_CHANGEEXPIRY:
                    $retval = 0;
                    $userlist = $profile->listAllUsers();
                    $userId = array_keys($userlist, $user);
                    if (isset($userId[0])) {
                        $profile->setUserExpiryDate($userId[0], $expiry);
                        $retval = 1; // function doesn't have any failure vectors not raising an Exception and doesn't return a value
                    }
                    break;
            }
        } catch (Exception $e) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INTERNAL_ERROR, "The operation failed. Maybe a duplicate username, or malformed expiry date?");
            exit(1);
        }
        if ($retval == 0) {// that didn't work, it seems
            $adminApi->returnError(web\lib\admin\API::ERROR_INTERNAL_ERROR, "The operation failed subtly. Contact the administrators.");
            break;
        }
        $adminApi->returnSuccess([web\lib\admin\API::AUXATTRIB_SB_USERNAME => $user, \web\lib\admin\API::AUXATTRIB_SB_USERID => $retval]);
        break;
    case \web\lib\admin\API::ACTION_ENDUSER_DEACTIVATE:
    // fall-through intended: both actions are very similar
    case \web\lib\admin\API::ACTION_TOKEN_NEW:
        $profile_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($profile_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $profile_id);
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
                    if (is_string($email)) { // it's a valid address
                        $retval = $invitation->sendByMail($email);
                        $additionalInfo["EMAIL SENT"] = $retval["SENT"];
                        if ($retval["SENT"]) {
                            $additionalInfo["EMAIL TRANSPORT SECURE"] = $retval["TRANSPORT"];
                        }
                    }
                }
                $smsRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TARGETSMS);
                if ($smsRaw !== FALSE) {
                    $sms = $validator->sms($smsRaw);
                    if (is_string($sms)) {
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
    case \web\lib\admin\API::ACTION_ENDUSER_IDENTIFY:
        $profile_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($profile_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $profile_id);
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $userId = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERID);
        $userName = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERNAME);
        $certSerial = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_CERTSERIAL);
		$certCN = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_CERTCN);
        if ($userId === FALSE && $userName === FALSE && $certSerial === FALSE && $certCN === FALSE) {
            // we need at least one of those
            $adminApi->returnError(\web\lib\admin\API::ERROR_MISSING_PARAMETER, "At least one of User ID, Username, certificate serial, or certificate CN is required.");
            break;
        }
        if ($certSerial !== FALSE) { // we got a cert serial
            $serial = explode(":", $certSerial);
            $cert = new \core\SilverbulletCertificate($serial[1], $serial[0]);
            }
        if ($certCN !== FALSE) { // we got a cert CN
            $cert = new \core\SilverbulletCertificate($certCN);
        }
        if ($cert !== NULL) { // we found a cert; verify it and extract userId
            if ($cert->status == \core\SilverbulletCertificate::CERTSTATUS_INVALID) {
                return $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Certificate not found.");
            }
            if ($cert->profileId != $profile->identifier) {
                return $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Certificate does not belong to this profile.");
            }
            $userId = $cert->userId;
        }
        if ($userId !== FALSE) {
            $userList = $profile->getUserById($userId);
        }
        if ($userName !== FALSE) {
            $userList = $profile->getUserByName($userName);
        }
        if (count($userList) === 1) {
            foreach ($userList as $oneUserId => $oneUserName) {
                return $adminApi->returnSuccess([web\lib\admin\API::AUXATTRIB_SB_USERNAME => $oneUserName, \web\lib\admin\API::AUXATTRIB_SB_USERID => $oneUserId]);
            }
        }
        $adminApi->returnError(\web\lib\admin\API::ERROR_INVALID_PARAMETER, "No matching user found in this profile.");
        break;
    case \web\lib\admin\API::ACTION_ENDUSER_LIST:
    // fall-through: those two are similar
    case \web\lib\admin\API::ACTION_TOKEN_LIST:
        $profile_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($profile_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $profile_id);
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
                // reduce to important subset of information
                $infoSet = [];
                foreach ($tokens as $oneTokenObject) {
                    $infoSet[$oneTokenObject->userId] = [\web\lib\admin\API::AUXATTRIB_TOKEN => $oneTokenObject->invitationTokenString, "STATUS" => $oneTokenObject->invitationTokenStatus];
                }
                $adminApi->returnSuccess($infoSet);
        }
        break;
    case \web\lib\admin\API::ACTION_TOKEN_REVOKE:
        $tokenRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_TOKEN);
        if ($tokenRaw === FALSE) {
            exit(1);
        }
        $token = new core\SilverbulletInvitation($tokenRaw);
        if ($token->invitationTokenStatus !== core\SilverbulletInvitation::SB_TOKENSTATUS_VALID && $token->invitationTokenStatus !== core\SilverbulletInvitation::SB_TOKENSTATUS_PARTIALLY_REDEEMED) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "This is not a currently valid token.");
            exit(1);
        }
        $token->revokeInvitation();
        $adminApi->returnSuccess([]);
        break;
    case \web\lib\admin\API::ACTION_CERT_LIST:
        $prof_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        $user_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_USERID);
        if ($prof_id === FALSE || !is_int($user_id)) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $prof_id);
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        $invitations = $profile->userStatus($user_id);
        // now pull out cert information from the object
        $certs = [];
        foreach ($invitations as $oneInvitation) {
            $certs = array_merge($certs, $oneInvitation->associatedCertificates);
        }
        // extract relevant subset of information from cert objects
        $certDetails = [];
        foreach ($certs as $cert) {
            $certDetails[$cert->ca_type . ":" . $cert->serial] = ["ISSUED" => $cert->issued, "EXPIRY" => $cert->expiry, "STATUS" => $cert->status, "DEVICE" => $cert->device, "CN" => $cert->username, "ANNOTATION" => $cert->annotation];
        }
        $adminApi->returnSuccess($certDetails);
        break;
    case \web\lib\admin\API::ACTION_CERT_REVOKE:
        $prof_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($prof_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $prof_id);
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        // tear apart the serial
        $serialRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_CERTSERIAL);
        if ($serialRaw === FALSE) {
            exit(1);
        }
        $serial = explode(":", $serialRaw);
        $cert = new \core\SilverbulletCertificate($serial[1], $serial[0]);
        if ($cert->status == \core\SilverbulletCertificate::CERTSTATUS_INVALID) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Serial not found.");
        }
        if ($cert->profileId != $profile->identifier) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Serial does not belong to this profile.");
        }
        $cert->revokeCertificate();
        $adminApi->returnSuccess([]);
        break;
    case \web\lib\admin\API::ACTION_CERT_ANNOTATE:
        $prof_id = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_CAT_PROFILE_ID);
        if ($prof_id === FALSE) {
            exit(1);
        }
        $evaluation = $adminApi->commonSbProfileChecks($fed, $prof_id);
        if ($evaluation === FALSE) {
            exit(1);
        }
        list($idp, $profile) = $evaluation;
        // tear apart the serial
        $serialRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_CERTSERIAL);
        if ($serialRaw === FALSE) {
            exit(1);
        }
        $serial = explode(":", $serialRaw);
        $cert = new \core\SilverbulletCertificate($serial[1], $serial[0]);
        if ($cert->status == \core\SilverbulletCertificate::CERTSTATUS_INVALID) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Serial not found.");
        }
        if ($cert->profileId != $profile->identifier) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Serial does not belong to this profile.");
        }
        $annotationRaw = $adminApi->firstParameterInstance($scrubbedParameters, web\lib\admin\API::AUXATTRIB_SB_CERTANNOTATION);
        if ($annotationRaw === FALSE) {
            $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_PARAMETER, "Unable to extract annotation.");
            break;
        }
        $annotation = json_decode($annotationRaw, TRUE);
        $cert->annotate($annotation);
        $adminApi->returnSuccess([]);

        break;

    default:
        $adminApi->returnError(web\lib\admin\API::ERROR_INVALID_ACTION, "Not implemented yet.");
}