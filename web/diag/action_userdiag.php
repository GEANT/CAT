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

// no authentication - this is for John Doe

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();

echo $deco->defaultPagePrelude(_("eduroam authentication diagnostics"), FALSE);
echo $deco->productheader("USER");
?>
<h1><?php _("Authentication Diagnostics"); ?></h1>
<p><?php _("We are sorry to hear that you have problems connecting to the network. The series of diagnostic tests on this page will help us narrow down the problem and suggest a possible solution to your problem."); ?></p>
<p><?php
echo _("Please follow the instructions below.");
$global = new \core\Federation();
?></p><hr/>

<?php if (empty($_POST['realm']) && empty($_POST['norealm']) && empty($_POST['problemscope'])) { ?> <!-- COND-BLOCK-1 -->
    <form action="action_userdiag.php" method="POST">
        <h2><?php echo _("Q1: For our tests, we need the part of your username behind the '@' character (e.g. john.doe@<strong>example.org</strong>). Please provide this so-called 'realm' in the following field:"); ?></h2>
        <table>
            <tr>
                <td><?php echo _("My realm is: "); ?></td>
                <td><input type='text'     style='display:block' name='realm'   id='realm'/></td>
            </tr>
            <tr>
                <td><input type='checkbox' style='display:block' name='norealm' id='norealm'/>
                <td><?php echo _("My username does not contain the '@' character"); ?></td>
            </tr>
            <tr>
                <td><input type='checkbox' style='display:block' name='norealm' id='noclue'/>
                <td><?php echo _("I don't know what to answer here"); ?></td>
            </tr>
        </table>


        <h2><?php echo _("Q2: When do you have problems using eduroam?"); ?></h2>
        <select style='display:block' name='problemscope' id='problemscope'>
            <option value='always' name='always'><?php echo _("Always: I can never connect at all"); ?></option>
            <option value='always-roaming' name='always-roaming' selected><?php echo _("Always when roaming: eduroam always works at my home hotspot, but never when going to another hotspot"); ?></option>
            <option value='sometimes-roaming' name='sometimes-roaming'><?php echo _("Sometimes when roaming: eduroam always works at my home hotspot, but only sometimes when going to another hotspot"); ?></option>
            <option value='sometimes' name='sometimes'><?php echo _("Sometimes: eduroam works, but only sometimes (failures both at my home hotspot and when going to another hotspot"); ?></option>
            <option value='deviceprob' name='deviceprob'><?php echo _("Device-dependent: eduroam works with one of my devices, but not with another. I use the same login data on the devices."); ?></option>
        </select>
        <button type='submit' class='submit'><?php echo _("Submit Information"); ?></button>
    </form>
    <?php
} //COND-BLOCK-1
// if user claims norealm, and has deterministic problems:
// his username is incorrect, he needs a realm. educate and finish.
// otherwise, maybe an IdP problem. We still need his realm to investigate, but
// need to ask more subtle

function username_format_lecture() {
    $skinjob = new \web\lib\user\Skinjob();
    $basepath = $skinjob->findResourceUrl("BASE", "/index.php");
    $retval = "<div class='problemdescription'><p>" . _("Roaming with eduroam requires a username in the format 'localname@realm'. Many Identity Providers also require that same format also when using the network locally.") . "</p>";
    $retval .= "<p>" . _("Exceptions to that format requirement apply only when an Idenity Provider forces the use of an anonymous outer identity using specially prepared configuration profiles or extensive manual instructions.") . "</p>";
    $retval .= "<p>" . _("Since you do not know the realm that is used by your Identity Provider, the first step is to double-check the correct username format.") . "</p></div>";
    $retval .= "<div class='problemsolution'><p>" . sprintf(_("If your identity provider is listed in the %s <a href='%s'>download page</a>, please use the correct installer for your Identity Provider - it will, among others, set up the correct username format. If you do not find your Identity Provider there, please contact the organisation's helpdesk directly."), CONFIG['APPEARANCE']['productname'], $basepath) . "</p></div>";
    return $retval;
}

if ((empty($_POST['norealm']) && empty($_POST['realm'])) XOR empty($_POST['problemscope'])) {
    echo _("We are sorry, but we can only help you if you answer all the questions. Please <a href='.'>start again</a>.");
}

if (!empty($_POST['norealm']) && !empty($_POST['realm'])) {
    echo _("You have indicated a realm in your username AND that your username does not contain the '@' character. This is contradictory. Please <a href='.'>start again</a>.");
}
const KNOWN_SCOPES = ["always" => "always", "always-roaming" => "always-roaming", "deviceprob" => "deviceprob", "sometimes-roaming" => "sometimes-roaming", "sometimes" => "sometimes"];

if (!empty($_POST['norealm']) && !empty($_POST['problemscope']) && empty($_POST['completion'])) {
// wash clean the input
    $scope = array_key(KNOWN_SCOPES, $_POST['problemscope']);
    if (count($scope) == 0) {
        throw new Exception("Unknown problem scope");
    }

    switch ($scope) {
        case "always":
            // username and/or password incorrect. Point to installer and helpdesk. Finish.
            echo "<h2>" . _("It is very likely that you have a problem with your username or your password.") . "</h2>";
            echo username_format_lecture();
            echo "<div class='problemsolution'><p>" . _("Once the username has been ruled out as being the problem, the second step is to verify that your password is correct. You need to contact your Identity Provider's helpdesk directly for that, as eduroam does not centrally store any credential information.") . "</p></div>";
            break;

        case "always-roaming":
            // username is incorrect, he needs a realm. Educate. Finish.
            echo "<h2>" . _("It is very likely that your username is incorrect and prevents roaming from working.") . "</h2>";
            echo username_format_lecture();
            break;
        case "deviceprob":
            $skinjob = new \web\lib\user\Skinjob();
            $basepath = $skinjob->findResourceUrl("BASE", "/index.php");
            echo "<h2>" . _("It is very likely that the configuration of the non-working device is incorrect.") . "</h2>";
            echo "<div class='problemdescription'><p>" . _("A proper configuration for eduroam requires more than a username and password. Some settings such as a required 'anonymous outer identity' can prevent the device from working.") . "</p></div>";
            echo "<div class='problemsolution'><p>" . sprintf(_("If your identity provider is listed in the %s <a href='%s'>download page</a>, please use the correct installer for your device and Identity Provider. If you do not find your Identity Provider or device there, please contact the organisation's helpdesk directly."), CONFIG['APPEARANCE']['productname'], $basepath) . "</p></div>";
        case "sometimes-roaming":
        case "sometimes":
            // a real problem maybe. But the user is clueless about his realm.
            // find out where he has problems, and try to dig out realm info.
            ?> <!-- COND-BLOCK-2 -->
            <p><?php echo _("It is good to hear that eduroam works at least sometimes. This probably means that your device configuration is correct. Please do not change it!"); ?></p>
            <p><?php echo _("The following questions help us narrow down the problem"); ?></p>
            <form action = 'action_userdiag.php' method='POST'>
                <input type='hidden' name='completion' id='completion' value='NOREALM-1'/>
                <input type='hidden' name='problemscope' id='problemscope' value='<?php echo $scope; ?>'/>
                <h2><?php echo _("Q3: We need to find out which organisation has issued your eduroam login. It can usually be identified by its realm, but since you do not have this information, please select country and institution name from the lists below:"); ?></h2>
                <select style='display:block' name='realm' id='realm'>
                    <option id='NONE' value='dropdown-unknown-selected'>My institution is not in this list!</option>
                    <?php
                    $instlist = $global->listExternalEntities(FALSE);
                    // reformat for extra niceness
                    foreach ($instlist as $key => $oneinst) {
                        $displaylist[$oneinst['ID']] = ["realmlist" => $oneinst['realmlist'], "name" => strtoupper($oneinst['country']) . ": " . $oneinst['name']];
                        $name[$oneinst['ID']] = $displaylist[$oneinst['ID']]["name"];
                    }

                    $current_locale = setlocale(LC_ALL, 0);
                    $langObject = new \core\common\Language();
                    setlocale(LC_ALL, CONFIG['LANGUAGES'][$langObject->locale]);
                    array_multisort($name, SORT_ASC, SORT_LOCALE_STRING, $displaylist);
                    setlocale(LC_ALL, $current_locale);

                    foreach ($displaylist as $id => $oneinst)
                        echo "<option id='" . $id . "' value='" . $oneinst['realmlist'] . "'>" . $oneinst['name'] . "</option>";
                    ?>
                </select>
                <button type='submit' class='submit'><?php echo _("Submit Information"); ?></button>

            </form>
            <?php
            // COND-BLOCK-2
            break;
        default:
            echo _("The value for problem scope is not recognised. Please <a href='.'>start again</a>.");
    }
}

// after answering Q3, there is ALWAYS a realm known, and we know problemscope
// since we know the realm, we can now run a realm check
// first see if we have a CAT profile for this realm, to learn anon outer if applicable
// if coming from drop-down, there could be more than one realm. Look them all up

if (!empty($_POST['realm']) && !empty($_POST['problemscope'])) {

    $listofrealms = explode(',', $_POST['realm']);
    $checks = [];
    foreach ($listofrealms as $realm) {
        $sanitised_realm = trim($validator->string($realm));
        $cat = new \core\CAT();
        if (AbstractProfile::profileFromRealm($sanitised_realm)) { // a CAT participant
            $profile_id = AbstractProfile::profileFromRealm($sanitised_realm);
            $profile = \core\ProfileFactory::instantiate($profile_id);
            $checks[] = ["realm" => $sanitised_realm, "instance" => new \core\diag\RADIUSTests($sanitised_realm, $profile->getRealmCheckOuterUsername(), $profile->getEapMethodsinOrderOfPreference(1), $profile->getCollapsedAttributes()['eap:server_name'], $profile->getCollapsedAttributes()['eap:ca_file']), "class" => "CAT", "profile" => $profile];
            echo "Debugging CAT Profile $profile_id for $sanitised_realm<br/>";
        } else if (!empty($cat->getExternalDBEntityDetails(0, $realm))) {
            $checks[] = ["realm" => $sanitised_realm, "instance" => new \core\diag\RADIUSTests($sanitised_realm, "@".$sanitised_realm), "class" => "EXT_DB"];
            echo "Debugging non-CAT but existing realm $sanitised_realm<br/>";
        } else {
            $checks[] = ["realm" => $sanitised_realm, "instance" => new \core\diag\RADIUSTests($sanitised_realm, "@".$sanitised_realm), "class" => "ALIEN"];
            echo "Debugging non-existing realm $sanitised_realm<br/>";
        }
    }

// So, does the realm actually work? Make an as thorough check as possible
// collect problems in $realm_problems

    $realmproblems = [];

    foreach ($checks as $check) {
        foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'] as $number => $probe) {
            $checkresult[$number] = $check['instance']->UDP_reachability($number, TRUE, TRUE);
            if ($checkresult[$number] == \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT) { // great
                // only emit a warning in case of ALIEN - NRO did not populate DB!
                if ($check['class'] == "ALIEN") {
                    $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REACHABLE", "FROM" => $probe['display_name'], "DETAIL" => "REALM_NOT_IN_DB"];
                } else {
                    $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REACHABLE", "FROM" => $probe['display_name'], "DETAIL" => ""];
                }
                continue;
            } else if ($checkresult[$number] == \core\diag\RADIUSTests::RETVAL_NO_RESPONSE || $checkresult[$number] == \core\diag\RADIUSTests::RETVAL_IMMEDIATE_REJECT) {
                // this could be harmless/undetectable if it's an NPS that won't talk to us
                // but if the get results with smaller packets and/or Operator-Name omitted
                // then there is a smoking gun!
                $checkresult[$number] = $check['instance']->UDP_reachability($number, FALSE, FALSE);
                if ($checkresult[$number] == \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT) { // so now things work?!
                    // either a packet size or Operator-Name problem!
                    if ($check['instance']->UDP_reachability($number, TRUE, FALSE) != \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT)
                        $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "OPERATOR-NAME", "FROM" => $probe['display_name'], "DETAIL" => ""];
                    if ($check['instance']->UDP_reachability($number, FALSE, TRUE) != \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT)
                        $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "PACKETSIZE", "FROM" => $probe['display_name'], "DETAIL" => ""];
                } else { // still no response or immediate reject
                    // if this is a CAT realm with anon ID set, we can't be seeing an NPS ignorance problem
                    // and consequently, the realm has actual issues
                    switch ($check['class']) {
                        case "CAT":
                            $anon_configured = $check['profile']->getAttributes("internal:use_anon_outer");

                            if ($anon_configured[0]['value'] == 1) { // definitely a problem
                                $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REALM_DOWN", "FROM" => $probe['display_name'], "DETAIL" => ""];
                            } else {
                                $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REALM_POSSIBLY_NPS", "FROM" => $probe['display_name'], "DETAIL" => ""];
                            }
                            break;
                        // for DB only, or non-existent realms, we don't know if an NPS 
                        // is causing the blindfoldedness
                        // but for NX we can at least warn that we don't think this is a valid realm at all
                        case "ALIEN":
                            $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REALM_INVALID", "FROM" => $probe['display_name'], "DETAIL" => ""];
                            break;
                        case "EXT_DB":
                            $realmproblems[] = ["REALM" => $check['realm'], "STATUS" => "REALM_POSSIBLY_NPS", "FROM" => $probe['display_name'], "DETAIL" => ""];
                    }
                }
            }
        }
    }

// we can do better than that... some problems in isolation can lead to "POSSIBLY_NPS"
// but if there is a successful run on another probe for the same realm, then
// it can't be that (you either have an NPS or you don't)
// re-write check history with that extra knowledge
    $copycat = $realmproblems;

    foreach ($realmproblems as &$problem)
        if ($problem['STATUS'] == "REALM_POSSIBLY_NPS")
            foreach ($copycat as $otherproblem)
                if ($problem['REALM'] == $otherproblem['REALM'] && $problem['FROM'] != $otherproblem['FROM'] && $otherproblem['STATUS'] != "REALM_POSSIBLY_NPS")
                    if ($otherproblem['STATUS'] == "REACHABLE") { // worked elsewhere, but not on this probe:
                        $problem['STATUS'] = "REALM_DOWN";
                    } else { // inherit other problem; in any case not an NPS problem
                        $problem['STATUS'] = $otherproblem['STATUS'];
                    }

// second post-processing: if things work from one probe, but not the other,
// then it's not the realm's fault: we have an infrastructure proxy problem!

    $copycat = $realmproblems;

    foreach ($realmproblems as &$problem)
        if ($problem['STATUS'] == "REALM_DOWN")
            foreach ($copycat as $otherproblem)
                if ($problem['REALM'] == $otherproblem['REALM'] && $problem['FROM'] != $otherproblem['FROM'] && $otherproblem['STATUS'] == "REACHABLE")
                    $problem['STATUS'] = "INFRASTRUCTURE";
    unset($problem);

// finally, extract all certprobs we got from the reachability checks; merge from all
// probes into one result (we don't care which prob came from where here)

    $all_certprobs = [];

    foreach ($checks as $check) {
        $instance = $check['instance'];
        $resultset = $instance->UDP_reachability_result;
        foreach ($resultset as $result)
            $all_certprobs = array_merge($all_certprobs, $result['cert_oddities']);
    }

// now we have something to say...
// infrastructure problems are always a problem, regardless how often they affect
// the user. But warning only once is enough.
    $infrastructure_warned = FALSE;
    $certprobs_warned = [];
    $warning_html = "";
    switch ($_POST['problemscope']) {
        case "always":
        case "deviceprob":
            // nothing ever works? See if we were able to reach IdP ourselves.
            // If yes, it's a user/device problem; if not, it's an IdP or proxy infra problem
            foreach ($realmproblems as $problem) {

                switch ($problem['STATUS']) {
                    case "INFRASTRUCTURE":
                        if ($infrastructure_warned === FALSE) {
                            $warning_html = "<div class='problemsolution'>" . _("There is nothing you can do about this problem. Please do not change your device configuration. Please wait until the problem is resolved.") . "</div>" . $warning_html;
                            $warning_html = "<div class='problemdescription'>" . _("There is currently a problem with the roaming infrastructure. This may lead to intermittent or permanent failures. We will send an email to notify the operators about this problem.") . "</div>" . $warning_html;
                        }
                        $infrastructure_warned = TRUE;
                    case "REACHABLE": // only complain if ALIEN
                        if ($problem['DETAIL'] == "REALM_NOT_IN_DB") {
                            $warning_html .= "<div class='problemdescription'>" . _("This realm is not a known participating institution. However, our tests indicate that it is actually functioning normally. This is a non-fatal error (the Identity Provider did not supply the required information into the operator database). We should probably not tell you this anyway, but send an immediate email to the NRO instead.") . "</div>";
                            $warning_html .= "<div class='problemsolution'>" . _("You do not need to take action. In particular, please do not change your device configuration.") . "</div>";
                        }
                        // drill down further: are there any certprobs of critical
                        // level? They would be the likely explanation
                        $oneRADIUSTest = $checks[0]['instance'];
                        foreach ($all_certprobs as $certprob) {
                            if (!in_array($certprob, $certprobs_warned) && $oneRADIUSTest->returnCodes[$certprob]['severity'] == \core\common\Entity::L_ERROR) {
                                $warning_html .= "<div class='problemdescription'>" . _("We found a problem with your Identity Provider. This may be the cause of your problems. The exact error is: ") . $oneRADIUSTest->returnCodes[$certprob]['message'] . "</div>";
                                $warning_html .= "<div class='problemsolution'>" . _("You do not need to take action. In particular, please do not change your device configuration. We will notify the Identity Provider about the problem. Please wait until the problem is resolved. ") . $oneRADIUSTest->returnCodes[$certprob]['message'] . "</div>";
                                $certprobs_warned[] = $certprob;
                            }
                            if (!in_array($certprob, $certprobs_warned) && $oneRADIUSTest->returnCodes[$certprob]['severity'] == \core\common\Entity::L_WARN) {
                                $warning_html .= "<div class='problemdescription'>" . _("We found a minor misconfiguration of your Identity Provider. Certain devices may not work because of this. The exact warning is: ") . $oneRADIUSTest->returnCodes[$certprob]['message'] .
                                        "<br/>It is not necessarily the case that these warnings are the source of your problem; e.g. simple errors in username or password can not be ruled out.</div>";
                                $warning_html .= "<p>Please answer some supplementary questions on the next page so that we can send a detailed problem report to your identity provider. (next page does not exist yet!)</p>";
                                $certprobs_warned[] = $certprob;
                            }
                        }
                }
            }
            break;

        // Username and password. Be extra helpful by looking
        // up if the realm matches a CAT IdP, and send user directly there!
        case "always-roaming":
            break;
        case "sometimes-roaming":
            // suspicion: specific hotspots problematic. check IdP realm for MTU problems and attribute resilience
            // TBD right here.
            // if problems found, send notice to IdP.
            // if none, ask user about his hotspot and send mail to SP, cc IdP


            /* switch ($_POST['deterministic-hotspot']) {
              case "deterministic":
              echo "XXX";
              break;
              case "indeterministic":
              echo "YYY";
              break;
              default:
              echo _("The value for determinism is not recognised. Please <a href='.'>start again</a>.");
              } */
            break;
        case "sometimes":
            // suspicion: IdP has problems. Normal realmcheck, send note to IdP
            echo "<p>" . _("Since you indicate that") . "</p>";

        // Then, find out why it only happens when roaming. In-depth analysis required.
        // So, start with asking 
        // Q3 (deterministic at specific hotspots?) and 
        // Q4 (where are you from)
    }
    echo $warning_html;
}
echo $deco->footer();
