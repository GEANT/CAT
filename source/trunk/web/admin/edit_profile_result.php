<?php
/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("CAT.php");
require_once("IdP.php");
require_once("Profile.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_parse.inc.php");

require_once('inc/auth.inc.php');

// deletion sets its own header-location  - treat with priority before calling default auth

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_DELETE && isset($_GET['inst_id']) && isset($_GET['profile_id'])) {
    authenticate();
    $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
    $my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);
    $profile_id = $my_profile->identifier;
    $my_profile->destroy();
    CAT::writeAudit($_SESSION['user'], "DEL", "Profile $profile_id");
    header("Location: overview_idp.php?inst_id=$my_inst->identifier");
}

pageheader(sprintf(_("%s: Profile wizard (step 3 completed)"), Config::$APPEARANCE['productname']), "ADMIN-IDP");

// check if profile exists and belongs to IdP

$my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);

$edit_mode = FALSE;
$my_profile = FALSE;

if (isset($_GET['profile_id'])) {
    $my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);
    $edit_mode = TRUE;
}

// extended input checks

$realm = FALSE;
if (isset($_POST['realm']) && $_POST['realm'] != "")
    $realm = valid_Realm($_POST['realm']);

$anon = FALSE;
if (isset($_POST['anon_support']))
    $anon = valid_boolean($_POST['anon_support']);

$anon_local = "anonymous";
if (isset($_POST['anon_local'])) {
    $anon_local = valid_string_db($_POST['anon_local']);
} else if ($my_profile !== FALSE) { // get the old anon outer id from DB. People don't appreciate "forgetting" it when unchecking anon id
    $local = $my_profile->getAttributes("internal:anon_local_value");
    if (isset($local[0]))
        $anon_local = $local[0]['value'];
}

$redirect = FALSE;
if (isset($_POST['redirect']))
    $redirect = valid_boolean($_POST['redirect']);

// did the user submit info? If so, submit to DB and go on to the 'dashboard' or 'next profile' page.
// if not, what is he doing on this page anyway!

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_SAVE) {
    $idpoptions = $my_inst->getAttributes();
    // maybe we were asked to edit an existing profile? check for that...
    if ($edit_mode) {
        $profile = $my_profile;
    } else {
        $profile = $my_inst->newProfile();
        CAT::writeAudit($_SESSION['user'], "NEW", "IdP " . $my_inst->identifier . " - Profile created");
    }
}
if (!$profile instanceof Profile) {
    echo _("Darn! Could not get a proper profile handle!");
    exit(1);
};
$profile->flushSupportedEapMethods();
?>
<h1><?php _("Submitted attributes for this profile"); ?></h1>
<table>
    <?php
    // set realm info, if submitted
    if ($realm != FALSE) {
        $profile->setRealm($anon_local . "@" . $realm);
        echo UI_okay(sprintf(_("Realm: <strong>%s</strong>"), $realm));
    }
    else
        $profile->setRealm("");
    // set anon ID, if submitted
    if ($anon != FALSE) {
        if ($realm == FALSE) {
            echo UI_error(_("Anonymous Outer Identities cannot be turned on: realm is missing!"));
        } else {
            $profile->setAnonymousIDSupport(true);
            echo UI_okay(sprintf(_("Anonymous Identity support is <strong>%s</strong>, the anonymous outer identity is <strong>%s</strong>"), _("ON"), $profile->realm));
        }
    } else {
        $profile->setAnonymousIDSupport(false);
        echo UI_okay(sprintf(_("Anonymous Identity support is <strong>%s</strong>"), _("OFF")));
    };

    $remaining_attribs = $profile->beginflushAttributes(0);
    $killlist = processSubmittedFields($profile, $remaining_attribs);
    $profile->commitFlushAttributes($killlist);

    if ($redirect != FALSE) {
        if (!isset($_POST['redirect_target']) || $_POST['redirect_target'] == "") {
            echo UI_error(_("Redirection can't be activated - you did not specify a target location!"));
        } else {
            $profile->addAttribute("device-specific:redirect", serialize(Array('lang' => 'C', 'content' => $_POST['redirect_target'])), 0, 0);
            echo UI_okay(sprintf("Redirection set to <strong>%s</strong>", $_POST['redirect_target']));
        }
    } else {
        echo UI_okay(_("Redirection is <strong>OFF</strong>"));
    }

    CAT::writeAudit($_SESSION['user'], "MOD", "Profile " . $profile->identifier . " - attributes changed");

    // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()

    $profile = new Profile($profile->identifier);

    foreach (EAP::listKnownEAPTypes() as $a) {
        if (isset($_POST[display_name($a)]) && isset($_POST[display_name($a) . "-priority"]) && $_POST[display_name($a) . "-priority"] != "") {
            // add EAP type to profile as requested, but ...
            $profile->addSupportedEapMethod($a, $_POST[display_name($a) . "-priority"]);
            CAT::writeAudit($_SESSION['user'], "MOD", "Profile " . $profile->identifier . " - supported EAP types changed");
            // see if we can enable the EAP type, or if info is missing
            $eapcompleteness = $profile->isEapTypeDefinitionComplete($a);
            if ($eapcompleteness === true) {
                echo UI_okay(_("Supported EAP Type: ") . "<strong>" . display_name($a) . "</strong>");
            } else {
                $warntext = "";
                if (is_array($eapcompleteness))
                    foreach ($eapcompleteness as $item)
                        $warntext .= "<strong>" . display_name($item) . "</strong> ";
                echo UI_warning(sprintf(_("Supported EAP Type: <strong>%s</strong> is missing required information %s !"), display_name($a), $warntext) . "<br/>" . _("The EAP type was added to the profile, but you need to complete the missing information before we can produce installers for you."));
            }
        }
    }
    // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()
    $profile = new Profile($profile->identifier);
    $profile->prepShowtime();
    ?>
</table>
<br/>
<form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
    <button type='submit'><?php echo _("Continue to dashboard"); ?></button>
</form>
<?php
if (count($profile->getEapMethodsinOrderOfPreference(1)) > 0)
    echo "<form method='post' action='overview_installers.php?inst_id=$my_inst->identifier&profile_id=$profile->identifier' accept-charset='UTF-8'>
        <button type='submit'>" . _("Continue to Installer Fine-Tuning and Download") . "</button>
    </form>";
footer();
?>