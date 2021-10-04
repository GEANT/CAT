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

require_once dirname(dirname(dirname(__DIR__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$auth->authenticate();

$catInstance = new \core\CAT();
$loggerInstance = new \core\common\Logging();
$validator = new \web\lib\common\InputValidation();
$uiElements = new \web\lib\admin\UIElements();
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");

$mgmt = new \core\UserManagement;
$new_idp_authorized_fedadmin = FALSE;

// check if the user is authenticated, and we have a valid mail address
if (!isset($_SESSION['user']) || !isset($_POST['mailaddr'])) {
    throw new Exception("sendinvite: called either without authentication or without target mail address!");
}

$newmailaddress = filter_input(INPUT_POST, 'mailaddr', FILTER_SANITIZE_STRING);
$totalSegments = explode(",", $newmailaddress);
$validAddresses = core\common\OutsideComm::exfiltrateValidAddresses($newmailaddress);
$newcountry = "";

// fed admin stuff
// we are either inviting to co-manage an existing inst ...

$userObject = new \core\User($_SESSION['user']);
$federation = NULL;

const OPERATION_MODE_INVALID = 0;
const OPERATION_MODE_EDIT = 1;
const OPERATION_MODE_NEWFROMDB = 2;
const OPERATION_MODE_NEWUNLINKED = 3;

$operationMode = OPERATION_MODE_INVALID;

// filter all input to make Scrutinizer unnecessarily happy

// what did we actually get?
if (isset($_GET['inst_id'])) {
    $operationMode = OPERATION_MODE_EDIT;
}

if (isset($_POST['creation'])) {
    $filteredCreation = filter_input(INPUT_POST, 'creation', FILTER_SANITIZE_STRING);
} else {
    $filteredCreation = NULL;
}

if (isset($_POST['name'])) {
    $filteredName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
} else {
    $filteredName = NULL;
}

if (isset($_POST['country'])) {
    $filteredCountry = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
} else {
    $filteredCountry = NULL;
}

if (isset($_POST['externals'])) {
    $filteredExternals = filter_input(INPUT_POST, 'externals', FILTER_SANITIZE_STRING);
} else {
    $filteredExternals = NULL;
}

if ($filteredCreation == "new" && $filteredName !== NULL && $filteredCountry !== NULL) {
    $operationMode = OPERATION_MODE_NEWUNLINKED;
}

if ($filteredCreation == "existing" && $filteredExternals !== NULL && $filteredExternals != "FREETEXT") {
    $operationMode = OPERATION_MODE_NEWFROMDB;
}

switch ($operationMode) {
    case OPERATION_MODE_EDIT:
        $idp = $validator->existingIdP($_GET['inst_id']);
        // editing IdPs is done from within the popup. When we're done, send the 
        // user back to the popup (append the result of the operation later)
        $redirectDestination = "manageAdmins.inc.php?inst_id=" . $idp->identifier . "&";
        if (count($validAddresses) == 0) {
            header("Location: $redirectDestination" . "invitation=INVALIDSYNTAX");
            exit(1);
        }
        // is the user primary admin of this IdP?
        $is_owner = $idp->isPrimaryOwner($_SESSION['user']);
        // check if he is (also) federation admin for the federation this IdP is in. His invitations have more blessing then.
        $fedadmin = $userObject->isFederationAdmin($idp->federation);
        // check if he is either one, if not, complain
        if (!$is_owner && !$fedadmin) {
            echo "<p>" . sprintf(_("Something's wrong... you are a %s admin, but not for the %s the requested %s belongs to!"), $uiElements->nomenclatureFed, $uiElements->nomenclatureFed, $uiElements->nomenclatureParticipant) . "</p>";
            exit(1);
        }

        $prettyprintname = $idp->name;
        $newtokens = $mgmt->createTokens($fedadmin, $validAddresses, $idp);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP " . $idp->identifier . " - Token created for " . implode(",", $validAddresses));
        $introtext = "CO-ADMIN";
        $participant_type = $idp->type;
        break;
    case OPERATION_MODE_NEWUNLINKED:
        $redirectDestination = "../overview_federation.php?";
        if (count($validAddresses) == 0) {
            header("Location: $redirectDestination"."invitation=INVALIDSYNTAX");
            exit(1);
        }
        // run an input check and conversion of the raw inputs... just in case
        $newinstname = $validator->string($_POST['name']);
        $newcountry = $validator->string($_POST['country']);
        $participant_type = $validator->partType($_POST['participant_type']);
        $new_idp_authorized_fedadmin = $userObject->isFederationAdmin($newcountry);
        if ($new_idp_authorized_fedadmin !== TRUE) {
            throw new Exception("Something's wrong... you want to create a new " . $uiElements->nomenclatureParticipant . ", but are not a " . $uiElements->nomenclatureFed . " admin for the " . $uiElements->nomenclatureFed . " it should be in!");
        }
        $federation = $validator->existingFederation($newcountry);
        $prettyprintname = $newinstname;
        $introtext = "NEW-FED";
        // send the user back to his federation overview page, append the result of the operation later
        // do the token creation magic
        $newtokens = $mgmt->createTokens(TRUE, $validAddresses, $newinstname, 0, $newcountry, $participant_type);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "ORG FUTURE  - Token created for $participant_type " . implode(",", $validAddresses));
        break;
    case OPERATION_MODE_NEWFROMDB:
        $redirectDestination = "../overview_federation.php?";
        if (count($validAddresses) == 0) {
            header("Location: $redirectDestination"."invitation=INVALIDSYNTAX");
            exit(1);
        }
        // a real external DB entry was submitted and all the required parameters are there
        $newexternalid = $validator->string($_POST['externals']);
        $extinfo = $catInstance->getExternalDBEntityDetails($newexternalid);
        $new_idp_authorized_fedadmin = $userObject->isFederationAdmin($extinfo['country']);
        if ($new_idp_authorized_fedadmin !== TRUE) {
            throw new Exception("Something's wrong... you want to create a new " . $uiElements->nomenclatureParticipant . ", but are not a " . $uiElements->nomenclatureFed . " admin for the " . $uiElements->nomenclatureFed . " it should be in!");
        }
        $federation = $validator->existingFederation($extinfo['country']);
        $newcountry = $extinfo['country'];
        // see if the inst name is defined in the currently set language; if not, pick its English name; if N/A, pick the last in the list
        $prettyprintname = "";
        foreach ($extinfo['names'] as $lang => $name) {
            if ($lang == $languageInstance->getLang()) {
                $prettyprintname = $name;
            }
        }
        if ($prettyprintname == "" && isset($extinfo['names']['en'])) {
            $prettyprintname = $extinfo['names']['en'];
        }
        if ($prettyprintname == "") {
            foreach ($extinfo['names'] as $name) {
                $prettyprintname = $name;
            }
        }
        $participant_type = $extinfo['type'];
        // fill the rest of the text
        $introtext = "EXISTING-FED";
        // do the token creation magic
        $newtokens = $mgmt->createTokens(TRUE, $validAddresses, $prettyprintname, $newexternalid);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP FUTURE  - Token created for " . implode(",", $validAddresses));
        break;
    default: // includes OPERATION_MODE_INVALID
        // second param is TRUE, so the variable *will* contain a string
        // i.e. ignore Scrutinizer type warning later
        $wrongcontent = print_r($_POST, TRUE);
        echo "<pre>Wrong parameters in POST:
" . htmlspecialchars(/** @scrutinizer ignore-type */ $wrongcontent) . "
</pre>";
        exit(1);
}

// send, and invalidate the token immediately if the mail could not be sent!

$status = [];
$allEncrypted = TRUE;
$allClear = TRUE;
foreach ($newtokens as $onetoken => $oneDest) {
    $sent = \core\common\OutsideComm::adminInvitationMail($oneDest, $introtext, $onetoken, $prettyprintname, $federation, $participant_type);
    if ($sent["SENT"] === FALSE) {
        $mgmt->invalidateToken($onetoken);
    } else {
        $status[$onetoken] = $sent["TRANSPORT"];
        if (!$sent["TRANSPORT"]) {
            $allEncrypted = FALSE;
        } else {
            $allClear = FALSE;
        }
    }
}

if (count($status) == 0) {
    header("Location: $redirectDestination" . "invitation=FAILURE");
    exit;
}
$finalDestParams = "invitation=SUCCESS";
if (count($status) < count($totalSegments)) { // only a subset of mails was sent, update status
    $finalDestParams = "invitation=PARTIAL";
}
$finalDestParams .= "&successcount=" . count($status);
if ($allEncrypted === TRUE) {
    $finalDestParams .= "&transportsecurity=ENCRYPTED";
} elseif ($allClear === TRUE) {
    $finalDestParams .= "&transportsecurity=CLEAR";
} else {
    $finalDestParams .= "&transportsecurity=PARTIAL";
}

header("Location: $redirectDestination" . $finalDestParams);
