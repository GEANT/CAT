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
$federation = FALSE;

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
        // is the user admin of this IdP?
        $is_owner = FALSE;
        $owners = $idp->owner();
        foreach ($owners as $oneowner) {
            if ($oneowner['ID'] == $_SESSION['user'] && $oneowner['LEVEL'] == "FED") {
                $is_owner = TRUE;
            }
        }
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
        $introtext = sprintf(_("a %s of the %s %s \"%s\" has invited you to manage the %s together with him."), $uiElements->nomenclature_fed, CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_inst, $prettyprintname, $uiElements->nomenclature_inst) . " " . sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X", time() + 86400));
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
        $introtext = sprintf(_("a %s %s has invited you to manage the future %s  \"%s\" (%s)."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_fed, $uiElements->nomenclature_inst, $prettyprintname, $newcountry) . " " . sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X", time() + 86400));
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
        $introtext = sprintf(_("a %s %s has invited you to manage the %s  \"%s\"."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $uiElements->nomenclature_fed, $uiElements->nomenclature_inst, $prettyprintname) . " " . sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X", time() + 86400));
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
// are we on https?
$proto = "http://";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
    $proto = "https://";
}

// then, send out the mail
$message = _("Hello,") . "
    
" . wordwrap($introtext, 72) . "
    
";

if ($new_idp_authorized_fedadmin) { // see if we are supposed to add a custom message
    $customtext = $federation->getAttributes('fed:custominvite');
    if (count($customtext) > 0) {
        $message .= wordwrap(sprintf(_("Additional message from your %s administrator:"), $uiElements->nomenclature_fed), 72) . "
---------------------------------
"
                . wordwrap($customtext[0]['value'], 72) . "
---------------------------------

    ";
    }
}

$message .= wordwrap(sprintf(_("To enlist as an administrator for that %s, please click on the following link:"), $uiElements->nomenclature_inst), 72) . "
    
$proto" . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/action_enrollment.php?token=$newtoken
    
" . wordwrap(sprintf(_("If clicking the link doesn't work, you can also go to the %s Administrator Interface at"), CONFIG['APPEARANCE']['productname']), 72) . "
    
$proto" . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/ 
    
" .
        _("and enter the invitation token") . "
    $newtoken
" . ( /* $new_idp_authorized_fedadmin */ FALSE ?
        wordwrap(sprintf(_("manually. If you reply to this mail, you will reach your %s administrators."), $uiElements->nomenclature_fed), 72) :
        wordwrap(_("manually. Please do not reply to this mail; this is a send-only address.")) ) . "

" . wordwrap(_("Do NOT forward the mail before the token has expired - or the recipients may be able to consume the token on your behalf!"), 72) . "

" . wordwrap(sprintf(_("We wish you a lot of fun with the %s."), CONFIG['APPEARANCE']['productname']), 72) . "
        
" . sprintf(_("Sincerely,

Your friendly folks from %s Operations"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);

$mail = \core\common\OutsideComm::mailHandle();
// who to whom?
$mail->FromName = CONFIG['APPEARANCE']['productname'] . " Invitation System";
if ($new_idp_authorized_fedadmin) {
    foreach ($federation->listFederationAdmins() as $fedadmin_id) {
        $fedadmin = new \core\User($fedadmin_id);
        $mailaddrAttrib = $fedadmin->getAttributes("user:email");
        $nameAttrib = $fedadmin->getAttributes("user:realname");
        $name = $nameAttrib[0]['value'] ?? sprintf(_("%s administrator"), $uiElements->nomenclature_fed);
        if (count($mailaddrAttrib) > 0) {
            $mail->addReplyTo($mailaddrAttrib[0]['value'], $name);
        }
    }
}
if (isset(CONFIG['APPEARANCE']['invitation-bcc-mail']) && CONFIG['APPEARANCE']['invitation-bcc-mail'] !== NULL) {
    $mail->addBCC(CONFIG['APPEARANCE']['invitation-bcc-mail']);
}

// all addresses are wrapped in a string, but PHPMailer needs a structured list of addressees
// sigh... so convert as needed
// first split multiple into one if needed
$recipients = explode(", ", $newmailaddress);

$secStatus = TRUE;
$domainStatus = TRUE;

// fill the destinations in PHPMailer API
foreach ($recipients as $recipient) {
    $mail->addAddress($recipient);
    $status = \core\common\OutsideComm::mailAddressValidSecure($recipient);
    if ($status < \core\common\OutsideComm::MAILDOMAIN_STARTTLS) {
        $secStatus = FALSE;
    }
    if ($status < 0) {
        $domainStatus = FALSE;
    }
}

if (!$domainStatus) {
    $mgmt->invalidateToken($newtoken);
    header("Location: $redirect_destination" . "invitation=FAILURE");
    exit;
}

// what do we want to say?
$mail->Subject = sprintf(_("%s: you have been invited to manage an %s"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclature_inst);
$mail->Body = $message;

$sent = $mail->send();

// invalidate the token immediately if the mail could not be sent!
if (!$sent) {
    $mgmt->invalidateToken($newtoken);
    header("Location: $redirect_destination" . "invitation=FAILURE");
    exit;
}

header("Location: $redirect_destination" . "invitation=SUCCESS&transportsecurity=" . ($secStatus ? "ENCRYPTED" : "CLEAR"));
