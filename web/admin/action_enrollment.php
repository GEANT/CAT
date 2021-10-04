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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$elements = new \web\lib\admin\UIElements();
$usermgmt = new \core\UserManagement();

$auth->authenticate();

if (!isset($_GET['token'])) {
    $elements->errorPage(_("Error creating new IdP binding!"),_("This page needs to be called with a valid invitation token!"));
}

if (\config\ConfAssistant::CONSORTIUM['selfservice_registration'] === NULL && $_GET['token'] == "SELF-REGISTER") {
    $elements->errorPage(_("Error creating new IdP binding!"),_("You tried to register in self-service, but this deployment does not allow self-service!"));
}

switch ($_GET['token']) {
    case "SELF-REGISTER":
        $token = "SELF-REGISTER";
        $checkval = \core\UserManagement::TOKENSTATUS_OK_NEW;
        $federation = \config\ConfAssistant::CONSORTIUM['selfservice_registration'];
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
            echo "<p>" . sprintf(_("Sorry... this token has already been used. The %s is already created. If you got the invitation from a mailing list, probably someone else used it before you."), $elements->nomenclatureParticipant) . "</p>";
            break;
        case \core\UserManagement::TOKENSTATUS_FAIL_EXPIRED:
            echo "<p>" . sprintf(_("Sorry... this token has expired. Invitation tokens are valid for 24 hours. The %s administrator can create a new one for you."), $elements->nomenclatureFed) . "</p>";
            break;
        default:
            echo "<p>" . _("Sorry... you have come to the enrollment page without a valid token. Are you a nasty person? If not, you should go to <a href='overview_user.php'>your profile page</a> instead.") . "</p>";
    }
    echo $deco->footer();
    throw new Exception("Terminating because something is wrong with the token we received.");
}

// token is valid. Get meta-info and create inst
$user = $validator->syntaxConformUser($_SESSION['user']);

$loggerInstance = new \core\common\Logging();

switch ($token) {
    case "SELF-REGISTER":
        $fed = new \core\Federation($federation);
        $newidp = new \core\IdP($fed->newIdP(core\IdP::TYPE_IDPSP, $user, "FED", "SELFSERVICE"));
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
    header("Location: edit_participant.php?inst_id=$newidp->identifier&wizard=true");
}
