<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("inc/common.inc.php");

$validator = new \web\lib\common\InputValidation();

function profilechecks(IdP $idpinfo, ProfileRADIUS $profile) {

    $dbHandle = \core\DBConnection::handle("INST");
    $uiElements = new web\lib\admin\UIElements();

    $tabletext = "<tr><td>" . $idpinfo->name . "</td><td>" . $profile->name . "</td>";

    $configuredRealm = $profile->getAttributes("internal:realm");
    $realm = $configuredRealm[0]['value'];
    if (empty($realm)) {
        $tabletext .= "<td>N/A: Realm not known</td><td></td><td></td><td></td>";

        // update database with the findings

        $dbHandle->exec("UPDATE profile SET "
                . "status_dns = " . \core\diag\RADIUSTests::RETVAL_SKIPPED . ", "
                . "status_cert = " . \core\diag\RADIUSTests::RETVAL_SKIPPED . ", "
                . "status_reachability = " . \core\diag\RADIUSTests::RETVAL_SKIPPED . ", "
                . "status_TLS = " . \core\diag\RADIUSTests::RETVAL_SKIPPED . ", "
                . "last_status_check = NOW() "
                . "WHERE profile_id = " . $profile->identifier);

        return $tabletext;
    }
    $testsuite = new \core\diag\RADIUSTests($realm, $profile->identifier);

    // NAPTR existence check
    $tabletext .= "<td>";
    $naptr = $testsuite->NAPTR();
    if ($naptr != \core\diag\RADIUSTests::RETVAL_NOTCONFIGURED)
        switch ($naptr) {
            case \core\diag\RADIUSTests::RETVAL_NONAPTR:
                $tabletext .= _("No NAPTR records");
                break;
            case \core\diag\RADIUSTests::RETVAL_ONLYUNRELATEDNAPTR:
                $tabletext .= sprintf(_("No associated NAPTR records"));
                break;
            default: // if none of the possible negative retvals, then we have matching NAPTRs
                $tabletext .= sprintf(_("%d %s NAPTR records"), $naptr, CONFIG['CONSORTIUM']['name']);
        }

    // compliance checks for NAPTRs

    $NAPTR_issues = false;

    if ($naptr > 0) {
        $naptrValid = $testsuite->NAPTR_compliance();
        switch ($naptrValid) {
            case \core\diag\RADIUSTests::RETVAL_INVALID:
                $NAPTR_issues = true;
                break;
            case \core\diag\RADIUSTests::RETVAL_OK:
                $srv = $testsuite->NAPTR_SRV();
                if ($srv == \core\diag\RADIUSTests::RETVAL_INVALID) {
                    $NAPTR_issues = true;
                }
                if ($srv > 0) {
                    $hosts = $testsuite->NAPTR_hostnames();
                    if ($hosts == \core\diag\RADIUSTests::RETVAL_INVALID)
                        $NAPTR_issues = true;
                }
                break;
        }
    }
    if ($NAPTR_issues) {
        $tabletext .= $uiElements->boxError(0, 0, true);
    } else {
        $tabletext .= $uiElements->boxOkay(0, 0, true);
    }

    $UDPErrors = false;
    $certBiggestOddity = \core\common\Entity::L_OK;

    foreach (CONFIG['RADIUSTESTS']['UDP-hosts'] as $hostindex => $host) {
        $testsuite->UDP_reachability($hostindex, true, true);
        $results = $testsuite->UDP_reachability_result[$hostindex];
        if ($results['packetflow_sane'] != TRUE)
            $UDPErrors = true;
        if (empty($results['packetflow'][11]))
            $UDPErrors = true;
        if (count($results['cert_oddities']) > 0) {
            foreach ($results['cert_oddities'] as $oddity)
                if ($oddity['level'] > $certBiggestOddity)
                    $certBiggestOddity = $oddity['level'];
        }
    }

    $tabletext .= "</td><td>";

    $uiElements = new web\lib\admin\UIElements();
    $tabletext .= $uiElements->boxFlexible($certBiggestOddity, 0, 0, true);

    $tabletext .= "</td><td>";
    if (!$UDPErrors) {
        $tabletext .= $uiElements->boxOkay(0, 0, true);
    } else {
        $tabletext .= $uiElements->boxError(0, 0, true);
    }

    $tabletext .= "</td><td>";

    $dynamicErrors = false;

    if ($naptr > 0 && count($testsuite->NAPTR_hostname_records) > 0) {
        foreach ($testsuite->NAPTR_hostname_records as $hostindex => $addr) {
            $retval = $testsuite->TLS_clients_side_check($addr);
            if ($retval != \core\diag\RADIUSTests::RETVAL_OK && $retval != \core\diag\RADIUSTests::RETVAL_SKIPPED)
                $dynamicErrors = true;
        }
    }
    if (!$dynamicErrors) {
        $tabletext .= $uiElements->boxOkay(0, 0, true);
    } else {
        $tabletext .= $uiElements->boxError(0, 0, true);
    }
    $tabletext .= "</td></tr>";

    $dbHandle->exec("UPDATE profile SET "
            . "status_dns = " . ($NAPTR_issues ? \core\diag\RADIUSTests::RETVAL_INVALID : \core\diag\RADIUSTests::RETVAL_OK) . ", "
            . "status_cert = " . ($certBiggestOddity) . ", "
            . "status_reachability = " . ($UDPErrors ? \core\diag\RADIUSTests::RETVAL_INVALID : \core\diag\RADIUSTests::RETVAL_OK) . ", "
            . "status_TLS = " . ($dynamicErrors ? \core\diag\RADIUSTests::RETVAL_INVALID : \core\diag\RADIUSTests::RETVAL_OK) . ", "
            . "last_status_check = NOW() "
            . "WHERE profile_id = " . $profile->identifier);

    return $tabletext;
}

function rowdescription() {
    return "<tr style='text-align:left'>"
            . "<th>" . _("Inst Name") . "</th>"
            . "<th>" . _("Profile Name") . "</th>"
            . "<th>" . _("DNS Checks") . "</th>"
            . "<th>" . _("Cert Checks") . "</th>"
            . "<th>" . _("Reachability Checks") . "</th>"
            . "<th>" . _("RADIUS/TLS Checks") . "</th>"
            . "</tr>";
}

$deco = new \web\lib\admin\PageDecoration();

echo $deco->defaultPagePrelude(_("Authentication Server Status for all known federation members"));

// check authorisation of user; this check immediately dies if not authorised

$fed = $validator->Federation($_GET['fed'], $_SESSION['user']);
$allIDPs = $fed->listIdentityProviders();

$profiles_showtime = [];
$profiles_readyconf = [];

foreach ($allIDPs as $index => $oneidp)
    foreach ($oneidp['instance']->listProfiles() as $profile)
        if ($profile->isShowtime()) {
            $profiles_showtime[] = ['idp' => $oneidp['instance'], 'profile' => $profile];
        } else if ($profile->readyForShowtime()) {
            $profiles_confready[] = ['idp' => $oneidp['instance'], 'profile' => $profile];
        }

if (count($profiles_showtime) > 0) {
    echo "<h2>" . _("Profiles marked as visible (V)") . "</h2>" . "<table>";
    echo rowdescription();
    foreach ($profiles_showtime as $oneprofile)
        echo profilechecks($oneprofile['idp'], $oneprofile['profile']);
    echo "</table>";
}

if (count($profiles_confready) > 0) {
    echo "<h2>" . _("Profiles with sufficient configuration, not marked as visible (C)") . "</h2>" . "<table>";
    echo rowdescription();
    foreach ($profiles_confready as $oneprofile)
        echo profilechecks($oneprofile['idp'], $oneprofile['profile']);
    echo "</table>";
}
?>
<form method='post' action='overview_federation.php' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_CLOSE; ?>'><?php echo _("Return to federation overview"); ?></button>
</form>

<?php echo $deco->footer() ?>

</body>