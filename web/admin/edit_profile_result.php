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

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

// deletion sets its own header-location  - treat with priority before calling default auth

$loggerInstance = new \core\common\Logging();
if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\admin\FormElements::BUTTON_DELETE && isset($_GET['inst_id']) && isset($_GET['profile_id'])) {
    $auth->authenticate();
    $my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
    $my_profile = $validator->Profile($_GET['profile_id'], $my_inst->identifier);
    $profile_id = $my_profile->identifier;
    $my_profile->destroy();
    $loggerInstance->writeAudit($_SESSION['user'], "DEL", "Profile $profile_id");
    header("Location: overview_idp.php?inst_id=$my_inst->identifier");
    exit;
}

echo $deco->pageheader(sprintf(_("%s: Profile wizard (step 3 completed)"), CONFIG['APPEARANCE']['productname']), "ADMIN-IDP");

// check if profile exists and belongs to IdP

$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);

$my_profile = NULL;

if (isset($_GET['profile_id'])) {
    $my_profile = $validator->Profile($_GET['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page should only be called to submit RADIUS Profile information!");
    }
}


// extended input checks

$realm = FALSE;
if (isset($_POST['realm']) && $_POST['realm'] != "") {
    $realm = $validator->realm(filter_input(INPUT_POST, 'realm', FILTER_SANITIZE_STRING));
}

$anon = FALSE;
if (isset($_POST['anon_support'])) {
    $anon = $validator->boolean($_POST['anon_support']);
}

$anonLocal = "anonymous";
if (isset($_POST['anon_local'])) {
    $anonLocal = $validator->string(filter_input(INPUT_POST, 'anon_local', FILTER_SANITIZE_STRING));
} elseif ($my_profile !== NULL) { // get the old anon outer id from DB. People don't appreciate "forgetting" it when unchecking anon id
    $local = $my_profile->getAttributes("internal:anon_local_value");
    if (isset($local[0])) {
        $anonLocal = $local[0]['value'];
    }
}

$checkuser = FALSE;
if (isset($_POST['checkuser_support'])) {
    $checkuser = $validator->boolean($_POST['checkuser_support']);
}

$checkuser_name1 = "anonymous";
if (isset($_POST['checkuser_local'])) {
    $checkuser_name1 = $validator->string($_POST['checkuser_local']);
} elseif ($my_profile !== NULL) { // get the old value from profile settings. People don't appreciate "forgetting" it when unchecking
    $checkuser_name1 = $my_profile->getAttributes("internal:checkuser_value")[0]['value'];
}
// it's a RADIUS username; and it's displayed later on. Be sure it contains no
// "interesting" HTML characters before further processing
$checkuser_name = htmlentities($checkuser_name1);

$verify = FALSE;
$hint = FALSE;
$redirect = FALSE;
if (isset($_POST['verify_support'])) {
    $verify = $validator->boolean($_POST['verify_support']);
}
if (isset($_POST['hint_support'])) {
    $hint = $validator->boolean($_POST['hint_support']);
}
if (isset($_POST['redirect'])) {
    $redirect = $validator->boolean($_POST['redirect']);
}

// did the user submit info? If so, submit to DB and go on to the 'dashboard' or 'next profile' page.
// if not, what is he doing on this page anyway!

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == web\lib\admin\FormElements::BUTTON_SAVE) {
    // maybe we were asked to edit an existing profile? check for that...
    if ($my_profile instanceof \core\AbstractProfile) {
        $profile = $my_profile;
    } else {
        $profile = $my_inst->newProfile("RADIUS");
        $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP " . $my_inst->identifier . " - Profile created");
    }
}

if (!$profile instanceof \core\ProfileRADIUS) {
    echo _("Darn! Could not get a proper profile handle!");
    exit(1);
}
?>
<h1><?php echo _("Submitted attributes for this profile"); ?></h1>
<table>
    <?php
    $uiElements = new web\lib\admin\UIElements();
    // set realm info, if submitted
    if ($realm !== FALSE) {
        $profile->setRealm($anonLocal . "@" . $realm);
        echo $uiElements->boxOkay(sprintf(_("Realm: <strong>%s</strong>"), $realm));
    } else {
        $profile->setRealm("");
    }
    // set anon ID, if submitted
    if ($anon !== FALSE) {
        if ($realm === FALSE) {
            echo $uiElements->boxError(_("Anonymous Outer Identities cannot be turned on: realm is missing!"));
        } else {
            $profile->setAnonymousIDSupport(true);
            echo $uiElements->boxOkay(sprintf(_("Anonymous Identity support is <strong>%s</strong>, the anonymous outer identity is <strong>%s</strong>"), _("ON"), $profile->realm));
        }
    } else {
        $profile->setAnonymousIDSupport(false);
        echo $uiElements->boxOkay(sprintf(_("Anonymous Identity support is <strong>%s</strong>"), _("OFF")));
    }

    if ($checkuser !== FALSE) {
        if ($realm === FALSE) {
            echo $uiElements->boxError(_("Realm check username cannot be configured: realm is missing!"));
        } else {
            $profile->setRealmcheckUser(true, $checkuser_name);
            echo $uiElements->boxOkay(sprintf(_("Special username for realm check is <strong>%s</strong>, the value is <strong>%s</strong>"), _("ON"), $checkuser_name . "@" . $realm));
        }
    } else {
        $profile->setRealmCheckUser(false);
        echo $uiElements->boxOkay(_("No special username for realm checks is configured."));
    }

    if ($verify !== FALSE) {
        if ($realm === FALSE) {
            echo $uiElements->boxError(_("Realm check username cannot be configured: realm is missing!"));
        } else {
            $profile->setInputVerificationPreference($verify, $hint);
            if ($hint !== FALSE) {
                $extratext = " " . sprintf(_("and the input field will be prefilled with '<strong>@%s</strong>'."), $realm);
            } else {
                $extratext = ".";
            }
            echo $uiElements->boxOkay(sprintf(_("Where possible, username inputs will be <strong>verified to contain an @ and end with %s</strong>%s"), $realm, $extratext));
        }
    } else {
        $profile->setInputVerificationPreference(false, false);
    }

    echo $optionParser->processSubmittedFields($profile, $_POST, $_FILES);

    if ($redirect !== FALSE) {
        if (!isset($_POST['redirect_target']) || $_POST['redirect_target'] == "") {
            echo $uiElements->boxError(_("Redirection can't be activated - you did not specify a target location!"));
        } elseif (!preg_match("/^(http|https):\/\//", $_POST['redirect_target'])) {
            echo $uiElements->boxError(_("Redirection can't be activated - the target needs to be a complete URL staring with http:// or https:// !"));
        } else {
            $profile->addAttribute("device-specific:redirect", 'C', $_POST['redirect_target']);
            echo $uiElements->boxOkay(sprintf("Redirection set to <strong>%s</strong>", htmlspecialchars($_POST['redirect_target'])));
        }
    } else {
        echo $uiElements->boxOkay(_("Redirection is <strong>OFF</strong>"));
    }

    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $profile->identifier . " - attributes changed");

    // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()

    $profile = \core\ProfileFactory::instantiate($profile->identifier);
    if (!$profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page handles RADIUS Profiles only. For some reason, a different type of Profile was requested.");
    }

    foreach (\core\common\EAP::listKnownEAPTypes() as $a) {
        if ($a->getIntegerRep() == \core\common\EAP::INTEGER_SILVERBULLET) { // do not allow adding silverbullet via the backdoor
            continue;
        }
        if (isset($_POST[$a->getPrintableRep()]) && isset($_POST[$a->getPrintableRep() . "-priority"]) && is_numeric($_POST[$a->getPrintableRep() . "-priority"])) {
            $priority = (int) $_POST[$a->getPrintableRep() . "-priority"];
            // add EAP type to profile as requested, but ...
            $profile->addSupportedEapMethod($a, $priority);
            $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $profile->identifier . " - supported EAP types changed");
            // see if we can enable the EAP type, or if info is missing
            $eapcompleteness = $profile->isEapTypeDefinitionComplete($a);
            if ($eapcompleteness === true) {
                echo $uiElements->boxOkay(_("Supported EAP Type: ") . "<strong>" . $a->getPrintableRep() . "</strong>");
            } else {
                $warntext = "";
                if (is_array($eapcompleteness)) {
                    foreach ($eapcompleteness as $item) {
                        $warntext .= "<strong>" . $item->getPrintableRep() . "</strong> ";
                    }
                }
                echo $uiElements->boxWarning(sprintf(_("Supported EAP Type: <strong>%s</strong> is missing required information %s !"), $a->getPrintableRep(), $warntext) . "<br/>" . _("The EAP type was added to the profile, but you need to complete the missing information before we can produce installers for you."));
            }
        }
    }
    // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()
    $reloadedProfile = \core\ProfileFactory::instantiate($profile->identifier);
    // this can't possibly be another type of Profile, but to make code analysers happy:
    if (!$profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page handles RADIUS Profiles only. For some reason, a different type of Profile was requested.");
    }
    $reloadedProfile->prepShowtime();
    ?>
</table>
<br/>
<form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
    <button type='submit'><?php echo _("Continue to dashboard"); ?></button>
</form>
<?php
if (count($reloadedProfile->getEapMethodsinOrderOfPreference(1)) > 0) {
    echo "<form method='post' action='overview_installers.php?inst_id=$my_inst->identifier&profile_id=$reloadedProfile->identifier' accept-charset='UTF-8'>
        <button type='submit'>" . _("Continue to Installer Fine-Tuning and Download") . "</button>
    </form>";
}
echo $deco->footer();
