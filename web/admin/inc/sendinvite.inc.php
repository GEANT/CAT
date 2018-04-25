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

require_once(dirname(dirname(dirname(__DIR__))) . "/config/_config.php");

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

/**
 * aborts code execution if a required mail address is invalid
 * 
 * @param mixed $newmailaddress input string, possibly a mail address
 * @param string $redirect_destination destination to send user to if validation failed
 * @return string mail address if validation passed
 */
function abortOnBogusMail($newmailaddress, $redirect_destination) {
    if ($newmailaddress === FALSE) {
        header("Location: $redirect_destination" . "invitation=INVALIDSYNTAX");
        exit;
    } else {
        return $newmailaddress;
    }
}

// check if the user is authenticated, and we have a valid mail address
if (!isset($_SESSION['user']) || !isset($_POST['mailaddr'])) {
    throw new Exception("sendinvite: called either without authentication or without target mail address!");
}

$newmailaddress = $validator->email(filter_input(INPUT_POST, 'mailaddr', FILTER_SANITIZE_STRING));
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

// what did we actually get?
if (isset($_GET['inst_id'])) {
    $operationMode = OPERATION_MODE_EDIT;
}

if (isset($_POST['creation']) && $_POST['creation'] == "new" && isset($_POST['name']) && isset($_POST['country'])) {
    $operationMode = OPERATION_MODE_NEWUNLINKED;
}

if (isset($_POST['creation']) && ($_POST['creation'] == "existing") && isset($_POST['externals']) && ($_POST['externals'] != "FREETEXT")) {
    $operationMode = OPERATION_MODE_NEWFROMDB;
}

switch ($operationMode) {
    case OPERATION_MODE_EDIT:
        $idp = $validator->IdP($_GET['inst_id']);
        // editing IdPs is done from within the popup. When we're done, send the 
        // user back to the popup (append the result of the operation later)
        $redirect_destination = "manageAdmins.inc.php?inst_id=" . $idp->identifier . "&";
        $mailaddress = abortOnBogusMail($newmailaddress, $redirect_destination);
        // is the user primary admin of this IdP?
        $is_owner = $idp->isPrimaryOwner($_SESSION['user']);
        // check if he is (also) federation admin for the federation this IdP is in. His invitations have more blessing then.
        $fedadmin = $userObject->isFederationAdmin($idp->federation);
        // check if he is either one, if not, complain
        if (!$is_owner && !$fedadmin) {
            echo "<p>" . sprintf(_("Something's wrong... you are a %s admin, but not for the %s the requested %s belongs to!"), $uiElements->nomenclature_fed, $uiElements->nomenclature_fed, $uiElements->nomenclature_inst) . "</p>";
            exit(1);
        }

        $prettyprintname = $idp->name;
        $newtoken = $mgmt->createToken($fedadmin, $mailaddress, $idp);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP " . $idp->identifier . " - Token created for " . $mailaddress);
        $introtext = "CO-ADMIN";
        break;
    case OPERATION_MODE_NEWUNLINKED:
        $redirect_destination = "../overview_federation.php?";
        $mailaddress = abortOnBogusMail($newmailaddress, $redirect_destination);
        // run an input check and conversion of the raw inputs... just in case
        $newinstname = $validator->string($_POST['name']);
        $newcountry = $validator->string($_POST['country']);
        $new_idp_authorized_fedadmin = $userObject->isFederationAdmin($newcountry);
        if ($new_idp_authorized_fedadmin !== TRUE) {
            throw new Exception("Something's wrong... you want to create a new " . $uiElements->nomenclature_inst . ", but are not a " . $uiElements->nomenclature_fed . " admin for the " . $uiElements->nomenclature_fed . " it should be in!");
        }
        $federation = $validator->Federation($newcountry);
        $prettyprintname = $newinstname;
        $introtext = "NEW-FED";
        // send the user back to his federation overview page, append the result of the operation later
        // do the token creation magic
        $newtoken = $mgmt->createToken(TRUE, $mailaddress, $newinstname, 0, $newcountry);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP FUTURE  - Token created for " . $mailaddress);
        break;
    case OPERATION_MODE_NEWFROMDB:
        $redirect_destination = "../overview_federation.php?";
        $mailaddress = abortOnBogusMail($newmailaddress, $redirect_destination);
        // a real external DB entry was submitted and all the required parameters are there
        $newexternalid = $validator->string($_POST['externals']);
        $extinfo = $catInstance->getExternalDBEntityDetails($newexternalid);
        $new_idp_authorized_fedadmin = $userObject->isFederationAdmin($extinfo['country']);
        if ($new_idp_authorized_fedadmin !== TRUE) {
            throw new Exception("Something's wrong... you want to create a new " . $uiElements->nomenclature_inst . ", but are not a " . $uiElements->nomenclature_fed . " admin for the " . $uiElements->nomenclature_fed . " it should be in!");
        }
        $federation = $validator->Federation($extinfo['country']);
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
        // fill the rest of the text
        $introtext = "EXISTING-FED";
        // do the token creation magic
        $newtoken = $mgmt->createToken(TRUE, $mailaddress, $prettyprintname, $newexternalid);
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP FUTURE  - Token created for " . $mailaddress);
        break;
    default: // includes OPERATION_MODE_INVALID
        $wrongcontent = print_r($_POST, TRUE);
        echo "<pre>Wrong parameters in POST:
" . htmlspecialchars($wrongcontent) . "
</pre>";
        exit(1);
}

// send, and invalidate the token immediately if the mail could not be sent!
$sent = \core\common\OutsideComm::adminInvitationMail($mailaddress, $introtext, $newtoken, $prettyprintname, $federation);
if ($sent["SENT"] === FALSE ) {
    $mgmt->invalidateToken($newtoken);
    header("Location: $redirect_destination" . "invitation=FAILURE");
    exit;
}

header("Location: $redirect_destination" . "invitation=SUCCESS&transportsecurity=" . ($sent["TRANSPORT"] ? "ENCRYPTED" : "CLEAR"));
