<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Federation.php");
require_once("IdP.php");
require_once("CAT.php");
require_once("UserManagement.php");

require_once("auth.inc.php");
require_once("common.inc.php");
require_once("input_validation.inc.php");
require_once("core/PHPMailer/PHPMailerAutoload.php");

authenticate();

$cat = new CAT();
$cat->set_locale("web_admin");

$mgmt = new UserManagement;

// check if the user is authenticated, and we have a valid mail address
if (!isset($_SESSION['user']) || !isset($_POST['mailaddr']))
    exit(1);

$newmailaddress = valid_string_db($_POST['mailaddr']);

// fed admin stuff
// we are either inviting to co-manage an existing inst ...

$user_object = new User($_SESSION['user']);
$fed_privs = $user_object->getAttributes("user:fedadmin");

if (isset($_GET['inst_id'])) {
    $idp = valid_IdP($_GET['inst_id']);
    // is the user admin of this IdP?
    $is_owner = FALSE;
    $owners = $idp->owner();
    foreach ($owners as $oneowner) {
        if ($oneowner['ID'] == $_SESSION['user'] && $oneowner['LEVEL'] == "FED")
            $is_owner = TRUE;
    }
    // check if he is (also) federation admin for the federation this IdP is in. His invitations have more blessing then.
    $fedadmin = $user_object->isFederationAdmin($idp->federation);
    // check if he is either one, if not, complain
    if (!$is_owner && !$fedadmin) {
        echo "<p>" . _("Something's wrong... you are a federation admin, but not for the federation the requested institution belongs to!") . "</p>";
        exit(1);
    }

    $prettyprintname = getLocalisedValue($idp->getAttributes('general:instname', 0, 0), CAT::$lang_index);
    $newtoken = $mgmt->createToken($fedadmin, $newmailaddress, $idp);
    CAT::writeAudit($_SESSION['user'], "NEW", "IdP " . $idp->identifier . " - Token created for " . $newmailaddress);
    $introtext = sprintf(_("an administrator of the %s Identity Provider \"%s\" has invited you to manage the IdP together with him."), Config::$CONSORTIUM['name'], $prettyprintname);
    // editing IdPs is done from within the popup. Send the user back to the popup, append the result of the operation later
    $redirect_destination = "manageAdmins.inc.php?inst_id=" . $_GET['inst_id'] . "&";
} // or invite to manage a new inst, only for fedAdmins
else if (isset($_POST['creation'])) {
    if ($_POST['creation'] == "new" && isset($_POST['name']) && isset($_POST['country'])) {
        // run an input check and conversion of the raw inputs... just in case
        $newinstname = valid_string_db($_POST['name']);
        $newcountry = valid_string_db($_POST['country']);
        // a new IdP was requested and all the required parameters are there
        $is_authorized = FALSE;
        foreach ($fed_privs as $onefed) {
            if ($onefed['value'] == $newcountry)
                $is_authorized = TRUE;
        }
        if (!$is_authorized) {
            echo "<p>" . _("Something's wrong... you want to create a new institution, but are not a federation admin for the federation it should be in!") . "</p>";
            exit(1);
        }
        $prettyprintname = $newinstname;
        $introtext = sprintf(_("a %s operator has invited you to manage the future IdP  \"%s\" (%s)."), Config::$CONSORTIUM['name'], $prettyprintname, $newcountry);
        // send the user back to his federation overview page, append the result of the operation later
        $redirect_destination = "../overview_federation.php?";
        // do the token creation magic
        $newtoken = $mgmt->createToken("FED", $newmailaddress, $newinstname, 0, $newcountry);
        CAT::writeAudit($_SESSION['user'], "NEW", "IdP FUTURE  - Token created for " . $newmailaddress);
    } else if ($_POST['creation'] == "existing" && isset($_POST['externals']) && $_POST['externals'] != "FREETEXT") {
        // a real external DB entry was submitted and all the required parameters are there
        $newexternalid = valid_string_db($_POST['externals']);
        $extinfo = Federation::getExternalDBEntityDetails($newexternalid);
        // see if the inst name is defined in the currently set language; if not, pick its English name; if N/A, pick the last in the list
        $ourlang = CAT::$lang_index;
        $prettyprintname = "";
        foreach ($extinfo['names'] as $lang => $name)
            if ($lang == $ourlang)
                $prettyprintname = $name;
        if ($prettyprintname == "" && isset($extinfo['names']['en']))
            $prettyprintname = $extinfo['names']['en'];
        if ($prettyprintname == "")
            foreach ($extinfo['names'] as $name)
                $prettyprintname = $name;
        // fill the rest of the text
        $introtext = sprintf(_("a %s operator has invited you to manage the IdP  \"%s\"."), Config::$CONSORTIUM['name'], $prettyprintname) . " " . sprintf(_("This invitation is valid for 24 hours from now, i.e. until %s."), strftime("%x %X", time() + 86400));
        $redirect_destination = "../overview_federation.php?";
        // do the token creation magic
        // TODO finish
        $newtoken = $mgmt->createToken("FED", $newmailaddress, $prettyprintname, $newexternalid);
        CAT::writeAudit($_SESSION['user'], "NEW", "IdP FUTURE  - Token created for " . $newmailaddress);
    }
} else {
    $wrongcontent = print_r($_POST, TRUE);
    echo "<pre>Wrong parameters in POST:
".htmlspecialchars($wrongcontent)."
</pre>";
    exit(1);
}
// are we on https?
$proto = "http://";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
    $proto = "https://";

// then, send out the mail
$message = _("Hello,") . "
    
" . wordwrap($introtext . " " . _("To enlist as an administrator for that IdP, please click on the following link:"), 72) . "
    
$proto" . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/action_enrollment.php?token=$newtoken
    
" . wordwrap(sprintf(_("If clicking the link doesn't work, you can also go to the %s Administrator Interface at"), Config::$APPEARANCE['productname']), 72) . "
    
$proto" . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/ 
    
" .
        _("and enter the invitation token") . "
    $newtoken
" . wordwrap(_("manually. Please do not reply to this email, it is a send-only address."), 72) . "
        
" . wordwrap(sprintf(_("We wish you a lot of fun with the %s."), Config::$APPEARANCE['productname'])) . "
        
" . sprintf(_("Sincerely,

Your friendly folks from %s Operations"), Config::$CONSORTIUM['name']);

// use PHPMailer to send the mail
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Port = 587;
$mail->SMTPAuth = true;
$mail->SMTPSecure = 'tls';
$mail->Host = Config::$MAILSETTINGS['host'];
$mail->Username = Config::$MAILSETTINGS['user'];
$mail->Password = Config::$MAILSETTINGS['pass'];
// formatting nitty-gritty
$mail->WordWrap = 72;
$mail->isHTML(FALSE);
$mail->CharSet = 'UTF-8';
// who to whom?
$mail->From = Config::$APPEARANCE['from-mail'];
$mail->FromName = Config::$APPEARANCE['productname'] . " Invitation System";
$mail->addReplyTo(Config::$APPEARANCE['admin-mail'], Config::$APPEARANCE['productname'] . " " . _("Feedback"));

if (isset(Config::$APPEARANCE['invitation-bcc-mail']) && Config::$APPEARANCE['invitation-bcc-mail'] !== NULL)
    $mail->addBCC(Config::$APPEARANCE['invitation-bcc-mail']);

// all addresses are wrapped in a string, but PHPMailer needs a structured list of addressees
// sigh... so convert as needed
// first split multiple into one if needed
$recipients = explode(", ", $newmailaddress);

// fill the destinations in PHPMailer API
foreach ($recipients as $recipient)
    $mail->addAddress($recipient);

// what do we want to say?
$mail->Subject = sprintf(_("%s: you have been invited to manage an IdP"), Config::$APPEARANCE['productname']);
$mail->Body = $message;

if (isset(Config::$CONSORTIUM['certfilename'], Config::$CONSORTIUM['keyfilename'], Config::$CONSORTIUM['keypass']))
    $mail->sign(Config::$CONSORTIUM['certfilename'], Config::$CONSORTIUM['keyfilename'], Config::$CONSORTIUM['keypass']);

$sent = $mail->send();

// invalidate the token immediately if the mail could not be sent!
if (!$sent)
    $mgmt->invalidateToken($newtoken);
$status = ($sent ? "SUCCESS" : "FAILURE");
header("Location: $redirect_destination" . "invitation=$status");
?>
