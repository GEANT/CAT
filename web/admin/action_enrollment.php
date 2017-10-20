<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This file executes the enrollment of a new admin to the system.
 * 
 * The administrator authenticates and then presents an invitation token via
 * the $_GET['token'] parameter.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$elements = new \web\lib\admin\UIElements();
$usermgmt = new \core\UserManagement();

$auth->authenticate();

/**
 * Something went wrong. We display the error cause and then throw an Exception.
 * 
 * @param string $uiDisplay error string to display
 * @param \web\lib\admin\PageDecoration $decoObject the instance of PageDecoration, needed for footer display.
 * @throws Exception
 */
function bailout(string $uiDisplay, $decoObject) {
    echo $decoObject->pageheader(_("Error creating new IdP binding!"), "ADMIN-IDP");
    echo "<h1>$uiDisplay</h1>";
    echo $decoObject->footer();
    throw new Exception("action_enrollment: $uiDisplay.");
}

if (!isset($_GET['token'])) {
    bailout(_("This page needs to be called with a valid invitation token!"), $deco);
}

if (CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'] === NULL && $_GET['token'] == "SELF-REGISTER") {
    bailout(_("You tried to register in self-service, but this deployment does not allow self-service!"), $deco);
}

switch ($_GET['token']) {
    case "SELF-REGISTER":
        $token = "SELF-REGISTER";
        $checkval = \core\UserManagement::TOKENSTATUS_OK_NEW;
        $federation = CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'];
        break;
    default:
        $token = $validator->token(filter_input(INPUT_GET,'token',FILTER_SANITIZE_STRING));
        $checkval = $usermgmt->checkTokenValidity($token);
}

if ($checkval < 0) {
    echo $deco->pageheader(_("Error creating new IdP binding!"), "ADMIN-IDP");
    echo "<h1>" . _("Error creating new IdP binding!") . "</h1>";
    switch ($checkval) {
        case \core\UserManagement::TOKENSTATUS_FAIL_ALREADYCONSUMED:
            echo "<p>" . sprintf(_("Sorry... this token has already been used to create an %s. If you got it from a mailing list, probably someone else used it before you."), $elements->nomenclature_inst) . "</p>";
            break;
        case \core\UserManagement::TOKENSTATUS_FAIL_EXPIRED:
            echo "<p>" . sprintf(_("Sorry... this token has expired. Invitation tokens are valid for 24 hours. Please ask your %s administrator for a new one."), $elements->nomenclature_fed) . "</p>";
            break;
        default:
            echo "<p>" . _("Sorry... you have come to the enrollment page without a valid token. Are you a nasty person? If not, you should go to <a href='overview_user.php'>your profile page</a> instead.") . "</p>";
    }
    echo $deco->footer();
    throw new Exception("Terminating because something is wrong with the token we received.");
}

// token is valid. Get meta-info and create inst
// TODO get invitation level and mail, store it as property
$user = $validator->User($_SESSION['user']);

$loggerInstance = new \core\common\Logging();

switch ($token) {
    case "SELF-REGISTER":
        $fed = new \core\Federation($federation);
        $newidp = new \core\IdP($fed->newIdP($user, "FED", "SELFSERVICE"));
        $loggerInstance->writeAudit($user, "MOD", "IdP " . $newidp->identifier . " - selfservice registration");
        break;
    default:
        $newidp = $usermgmt->createIdPFromToken($token, $user);
        $usermgmt->invalidateToken($token);
        $loggerInstance->writeAudit($user, "MOD", "IdP " . $newidp->identifier . " - Token used and invalidated");
        break;
}

if ($checkval == \core\UserManagement::TOKENSTATUS_OK_EXISTING) {
    header("Location: overview_user.php");
} else {
    header("Location: edit_idp.php?inst_id=$newidp->identifier&wizard=true");
}
    