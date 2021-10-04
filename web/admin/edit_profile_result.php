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

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();
$ui = new \web\lib\admin\UIElements();

// deletion sets its own header-location  - treat with priority before calling default auth

$loggerInstance = new \core\common\Logging();

// check if profile exists and belongs to IdP

$auth->authenticate();
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);

switch ($_POST['submitbutton']) {
    case web\lib\common\FormElements::BUTTON_DELETE:
        if (!isset($_GET['profile_id'])) {
            throw new Exception("Can only delete a profile that exists and is named!");
        }
        $profileToBeDel = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
        $profileToBeDel->destroy();
        $loggerInstance->writeAudit($_SESSION['user'], "DEL", "Profile " . $profileToBeDel->identifier);
        header("Location: overview_org.php?inst_id=$my_inst->identifier");
        exit;
    case web\lib\common\FormElements::BUTTON_SAVE:
        if (isset($_GET['profile_id'])) {
            $profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
            echo $deco->pageheader(sprintf(_("%s: Edit Profile - Result"), \config\Master::APPEARANCE['productname']), "ADMIN-IDP");
        } else {
            $profile = $my_inst->newProfile(core\AbstractProfile::PROFILETYPE_RADIUS);
            $loggerInstance->writeAudit($_SESSION['user'], "NEW", "IdP " . $my_inst->identifier . " - Profile created");
            echo $deco->pageheader(sprintf(_("%s: Profile wizard (step 3 completed)"), \config\Master::APPEARANCE['productname']), "ADMIN-IDP");
        }
        if (!$profile instanceof \core\ProfileRADIUS) {
            throw new Exception("This page should only be called to submit RADIUS Profile information!");
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
        } else { // get the old anon outer id from DB. People don't appreciate "forgetting" it when unchecking anon id
            $local = $profile->getAttributes("internal:anon_local_value");
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
        } else { // get the old value from profile settings. People don't appreciate "forgetting" it when unchecking
            $checkuser_name1 = $profile->getAttributes("internal:checkuser_value")[0]['value'];
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
        ?>
        <h1><?php
            $tablecaption = _("Submitted attributes for this profile");
            echo $tablecaption;
            ?></h1>
        <table>
            <caption><?php echo $tablecaption; ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Overall Result"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Details"); ?></th>
            </tr>
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
                if ($verify === FALSE) { // no anon outer ID, and no realm suffix verification? Bad idea!
                    echo $uiElements->boxWarning(_("Without Anoymous Identity, the actual username will be used as outer identity and be the basis for request routing. For that to work, the username must have a correct realm suffix. Yet, realm suffix verification has been turned OFF. Supplicants will not verify that usernames contain a realm, and errors such as username 'johndoe' which will not work in roaming scenarios will not be prohibited. Consider checking the box 'Enforce realm suffix in username'!"));
                }
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
                $profile->setInputVerificationPreference($verify, $hint);
                $extratext = "";
                if (!empty($realm)) {
                    if ($hint !== FALSE) {
                        $extratext = " " . sprintf(_("The realm portion MUST be exactly '...@%s'."), $realm);
                    } else {
                        $extratext = " " . sprintf(_("The realm portion MUST end with '%s' but sub-realms of it are allowed (i.e. 'user@%s' and 'user@<...>.%s' are both acceptable)."), $realm, $realm, $realm);
                    }
                }
                echo $uiElements->boxOkay(_("Where possible, supplicants will verify that username inputs contain a syntactically correct realm.") . $extratext);
            } else {
                $profile->setInputVerificationPreference(false, false);
            }

            echo $optionParser->processSubmittedFields($profile, $_POST, $_FILES);

            if ($redirect !== FALSE) {
                if (!isset($_POST['redirect_target']) || $_POST['redirect_target'] == "") {
                    echo $uiElements->boxError(_("Redirection can't be activated - you did not specify a target location!"));
                } elseif (!preg_match("/^(http|https):\/\//", $_POST['redirect_target'])) {
                    echo $uiElements->boxError(_("Redirection can't be activated - the target needs to be a complete URL starting with http:// or https:// !"));
                } else {
                    $profile->addAttribute("device-specific:redirect", 'C', $_POST['redirect_target']);
                    // check if there is a device-level redirect which effectively disables profile-level redirect, and warn if so
                    $redirects = $profile->getAttributes("device-specific:redirect");
                    $deviceSpecificFound = FALSE;
                    foreach ($redirects as $oneRedirect) {
                        if ($oneRedirect["level"] == \core\Options::LEVEL_METHOD) {
                            $deviceSpecificFound = TRUE;
                        }
                    }
                    if ($deviceSpecificFound) {
                        echo $uiElements->boxWarning(sprintf(_("Redirection set to <strong>%s</strong>, but will be ignored due to existing device-level redirect."), htmlspecialchars($_POST['redirect_target'])));
                    } else {
                        echo $uiElements->boxOkay(sprintf(_("Redirection set to <strong>%s</strong>"), htmlspecialchars($_POST['redirect_target'])));
                    }
                }
            } else {
                echo $uiElements->boxOkay(_("Redirection is <strong>OFF</strong>"));
            }

            $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $profile->identifier . " - attributes changed");
            // reload the profile to ingest new CA and server names if any; before checking EAP completeness
            $reloadedProfileNr1 = \core\ProfileFactory::instantiate($profile->identifier);
            foreach (\core\common\EAP::listKnownEAPTypes() as $a) {
                if ($a->getIntegerRep() == \core\common\EAP::INTEGER_SILVERBULLET) { // do not allow adding silverbullet via the backdoor
                    continue;
                }
                if (isset($_POST[$a->getPrintableRep()]) && isset($_POST[$a->getPrintableRep() . "-priority"]) && is_numeric($_POST[$a->getPrintableRep() . "-priority"])) {
                    $priority = (int) $_POST[$a->getPrintableRep() . "-priority"];
                    // add EAP type to profile as requested, but ...
                    $reloadedProfileNr1->addSupportedEapMethod($a, $priority);
                    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "Profile " . $reloadedProfileNr1->identifier . " - supported EAP types changed");
                    // see if we can enable the EAP type, or if info is missing
                    $eapcompleteness = $reloadedProfileNr1->isEapTypeDefinitionComplete($a);
                    if ($eapcompleteness === true) {
                        echo $uiElements->boxOkay(_("Supported EAP Type: ") . "<strong>" . $a->getPrintableRep() . "</strong>");
                    } else {
                        $warntext = "";
                        if (is_array($eapcompleteness)) {
                            foreach ($eapcompleteness as $item) {
                                $warntext .= "<strong>" . $uiElements->displayName($item) . "</strong> ";
                            }
                        }
                        echo $uiElements->boxWarning(sprintf(_("Supported EAP Type: <strong>%s</strong> is missing required information %s !"), $a->getPrintableRep(), $warntext) . "<br/>" . _("The EAP type was added to the profile, but you need to complete the missing information before we can produce installers for you."));
                    }
                }
            }
            // re-instantiate $profile again, we need to do final checks on the
            // full set of new information
            $reloadedProfileNr2 = \core\ProfileFactory::instantiate($profile->identifier);
            $significantChanges = \core\AbstractProfile::significantChanges($profile, $reloadedProfileNr2);
            if (count($significantChanges) > 0) {
                $myInstOriginal = new \core\IdP($profile->institution);
                // send a notification/alert mail to someone we know is in charge
                $text = _("To whom it may concern,") . "\n\n";
                /// were made to the *Identity Provider* *LU* / integer number of IdP / (previously known as) Name
                $text .= sprintf(_("significant changes were made to a RADIUS deployment profile of the %s %s / %s / '%s'."), $ui->nomenclatureIdP, strtoupper($myInstOriginal->federation), $myInstOriginal->identifier, $myInstOriginal->name) . "\n\n";
                if (isset($significantChanges[\core\AbstractProfile::CA_CLASH_ADDED])) {
                    $text .= _("WARNING! A new trusted root CA was added, and it has the exact same name as a previously existing root CA. This may (but does not necessarily) mean that this is an attempt to insert an unauthorised trust root by disguising as the genuine one. The details are below:") . "\n\n";
                    $text .= $significantChanges[\core\AbstractProfile::CA_CLASH_ADDED] . "\n\n";
                }
                if (isset($significantChanges[\core\AbstractProfile::CA_ADDED])) {
                    $text .= _("A new trusted root CA was added. The details are below:") . "\n\n";
                    $text .= $significantChanges[\core\AbstractProfile::CA_ADDED] . "\n\n";
                }
                if (isset($significantChanges[\core\AbstractProfile::SERVERNAME_ADDED])) {
                    $text .= _("A new acceptable server name for the authentication server was added. The details are below:") . "\n\n";
                    $text .= $significantChanges[\core\AbstractProfile::SERVERNAME_ADDED] . "\n\n";
                }
                $text .= _("This mail is merely a cross-check because these changes can be security-relevant. If the change was expected, you do not need to take any action.") . "\n\n";
                $text .= _("Greetings, ") . "\n\n" . \config\Master::APPEARANCE['productname_long'];
                // (currently, send hard-wired to NRO - future: for linked insts, check eduroam DBv2 and send to registered admins directly)
                $fed = new core\Federation($myInstOriginal->federation);
                foreach ($fed->listFederationAdmins() as $id) {
                    $user = new core\User($id);
                    $user->sendMailToUser(sprintf(_("%s: Significant Changes made to %s"), \config\Master::APPEARANCE['productname'], $ui->nomenclatureIdP), $text);
                }
            }
            $reloadedProfileNr2->prepShowtime();

            // do OpenRoaming initial diagnostic checks
            // numbers correspond to RFC7585Tests::OVERALL_LEVEL
            $resultLevel = \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NO;
            if (sizeof($reloadedProfileNr2->getAttributes("media:openroaming")) > 0) {
                $resultLevel = \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_GOOD; // assume all is well, degrade if we have concrete findings to suggest otherwise
                $tag = "aaa+auth:radius.tls.tcp";
                // do we know the realm at all? Notice if not.
                if (!isset($reloadedProfileNr2->getAttributes("internal:realm")[0]['value'])) {
                    echo $uiElements->boxRemark(_("The profile information does not include the realm, so no DNS checks for OpenRoaming can be executed."));
                    $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NOTE]);
                    
                } else {
                    $dnsChecks = new \core\diag\RFC7585Tests($reloadedProfileNr2->getAttributes("internal:realm")[0]['value'], $tag);
                    $relevantNaptrRecords = $dnsChecks->relevantNAPTR();
                    if ($relevantNaptrRecords <= 0) {
                        echo $uiElements->boxError(_("There is no relevant DNS NAPTR record ($tag) for this realm. OpenRoaming will not work."));
                        $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR]);
                    } else {
                        $recordCompliance = $dnsChecks->relevantNAPTRcompliance();
                        if ($recordCompliance != core\diag\AbstractTest::RETVAL_OK) {
                            echo $uiElements->boxWarning(_("The DNS NAPTR record ($tag) for this realm is not syntax conform. OpenRoaming will likely not work."));
                            $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_WARN]);
                        }
                        $fed = new \core\Federation($my_inst->federation);
                        // check if target is the expected one, if set by NRO
                        $hasCustomTarget = $fed->getAttributes("fed:openroaming_customtarget");
                        if (sizeof($hasCustomTarget) > 0) {
                            foreach ($dnsChecks->NAPTR_records as $orpointer) {
                                if ($orpointer["replacement"] != $hasCustomTarget[0]['value']) {
                                    echo $uiElements->boxRemark(_("The SRV target of an OpenRoaming NAPTR record is unexpected."));
                                    $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_NOTE]);
                                }
                            }
                        }
                        $srvResolution = $dnsChecks->relevantNAPTRsrvResolution();
                        $hostnameResolution = $dnsChecks->relevantNAPTRhostnameResolution();

                        if ($srvResolution <= 0) {
                            echo $uiElements->boxError(_("The DNS SRV target for NAPTR $tag does not resolve. OpenRoaming will not work."));
                            $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR]);
                        } elseif ($hostnameResolution <= 0) {
                            echo $uiElements->boxError(_("The DNS hostnames in the SRV records do not resolve to actual host IPs. OpenRoaming will not work."));
                            $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR]);
                        }
                        // connect to all IPs we found and see if they are really an OpenRoaming server
                        $allHostsOkay = TRUE;
                        $oneHostOkay = FALSE;
                        $testCandidates = [];
                        foreach ($dnsChecks->NAPTR_hostname_records as $oneServer) {
                            $testCandidates[$oneServer['hostname']][] = ($oneServer['family'] == "IPv4" ? $oneServer['IP'] : "[" . $oneServer['IP'] . "]") . ":" . $oneServer['port'];
                        }
                        foreach ($testCandidates as $oneHost => $listOfIPs) {
                            $connectionTests = new core\diag\RFC6614Tests(array_values($listOfIPs), $oneHost, "openroaming");
                            // for now (no OpenRoaming client certs available) only run server-side tests
                            foreach ($listOfIPs as $oneIP) {
                                $connectionResult = $connectionTests->cApathCheck($oneIP);
                                if ($connectionResult != core\diag\AbstractTest::RETVAL_OK || ( isset($connectionTests->TLS_CA_checks_result['cert_oddity']) && count($connectionTests->TLS_CA_checks_result['cert_oddity']) > 0)) {
                                    $allHostsOkay = FALSE;
                                } else {
                                    $oneHostOkay = TRUE;
                                }
                            }
                        }
                        if (!$allHostsOkay) {
                            if (!$oneHostOkay) {
                                echo $uiElements->boxError(_("When connecting to the discovered OpenRoaming endpoints, they all had errors. OpenRoaming will likely not work."));
                                $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_ERROR]);
                            } else {
                                echo $uiElements->boxWarning(_("When connecting to the discovered OpenRoaming endpoints, only a subset of endpoints had no errors."));
                                $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_WARN]);
                            }
                        }
                    }
                }

                if (!$dnsChecks->allResponsesSecure) {
                    echo $uiElements->boxWarning(_("At least one DNS response was NOT secured using DNSSEC. OpenRoaming ANPs may refuse to connect to the endpoint."));
                    $resultLevel = min([$resultLevel, \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_WARN]);
                }
                if ($resultLevel == \core\AbstractProfile::OVERALL_OPENROAMING_LEVEL_GOOD) {
                    echo $uiElements->boxOkay(_("Initial diagnostics regarding the DNS part of OpenRoaming (including DNSSEC) were successful."));
                }                
            }
            $reloadedProfileNr2->setOpenRoamingReadinessInfo($resultLevel);
            ?>
        </table>
        <br/>
        <form method='post' action='overview_org.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
            <button type='submit'><?php echo _("Continue to dashboard"); ?></button>
        </form>
        <?php
        if (count($reloadedProfileNr2->getEapMethodsinOrderOfPreference(1)) > 0) {
            echo "<form method='post' action='overview_installers.php?inst_id=$my_inst->identifier&profile_id=$reloadedProfileNr2->identifier' accept-charset='UTF-8'>
        <button type='submit'>" . _("Continue to Installer Fine-Tuning and Download") . "</button>
    </form>";
        }
        echo $deco->footer();
        break;
    default:
        throw new Exception("Unknown submit value received.");
}
