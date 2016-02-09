<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("IdP.php");
require_once("Profile.php");
require_once("RADIUSTests.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");

function profilechecks(IdP $idpinfo,Profile $profile) {

    $tabletext = "<tr><td>" . $idpinfo->name . "</td><td>" . $profile->name . "</td>";
    
    $cr = $profile->getAttributes("internal:realm");
    $realm = $cr[0]['value'];
    if (empty($realm)) {
        $tabletext .= "<td>N/A: Realm not known</td><td></td><td></td><td></td>";
        
        // update database with the findings
        
        DBConnection::exec("INST", "UPDATE profile SET "
                . "status_dns = ".RETVAL_SKIPPED.", "
                . "status_cert = ".RETVAL_SKIPPED.", "
                . "status_reachability = ". RETVAL_SKIPPED.", "
                . "status_TLS = ".RETVAL_SKIPPED.", "
                . "last_status_check = NOW() "
                . "WHERE profile_id = ".$profile->identifier);
        
        return $tabletext;
    }
    $testsuite = new RADIUSTests($realm, $profile->identifier);

    // NAPTR existence check
    $tabletext .= "<td>";
    $naptr = $testsuite->NAPTR();
    if ($naptr != RETVAL_NOTCONFIGURED)
        switch ($naptr) {
            case RETVAL_NONAPTR:
                $tabletext .= _("No NAPTR records");
                break;
            case RETVAL_ONLYUNRELATEDNAPTR:
                $tabletext .= sprintf(_("No associated NAPTR records"));
                break;
            default: // if none of the possible negative retvals, then we have matching NAPTRs
                $tabletext .= sprintf(_("%d %s NAPTR records"), $naptr, Config::$CONSORTIUM['name']);
        }

    // compliance checks for NAPTRs

    $NAPTR_issues = false;

    if ($naptr > 0) {
        $naptr_valid = $testsuite->NAPTR_compliance();
        if ($naptr_valid == RETVAL_INVALID)
            $NAPTR_issues = true;
    }

    // SRV resolution

    if ($naptr > 0 && $naptr_valid == RETVAL_OK) {
        $srv = $testsuite->NAPTR_SRV();
        if ($srv == RETVAL_INVALID)
            $NAPTR_issues = true;
    }

    // IP addresses for the hosts
    if ($naptr > 0 && $naptr_valid == RETVAL_OK && $srv > 0) {
        $hosts = $testsuite->NAPTR_hostnames();
        if ($hosts == RETVAL_INVALID)
            $NAPTR_issues = true;
    }

    if ($NAPTR_issues) {
        $tabletext .= UI_error(0,0,true);
    } else {
        $tabletext .= UI_okay(0,0,true);
    }
    
    $UDP_errors = false;
    $cert_biggest_oddity = L_OK;
    
    foreach (Config::$RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
        $testsuite->UDP_reachability($hostindex, true, true);
        $results = $testsuite->UDP_reachability_result[$hostindex];
        //echo "<pre>".print_r($results,true)."</pre>";
        if ($results['packetflow_sane'] != TRUE)
            $UDP_errors = true;
        if (empty($results['packetflow'][11]))
            $UDP_errors = true;
        if (count($results['cert_oddities']) > 0) {
            foreach ($results['cert_oddities'] as $oddity)
                if ($oddity['level'] > $cert_biggest_oddity)
                    $cert_biggest_oddity = $oddity['level'];
        }
    }
    
    $tabletext .= "</td><td>";
    $tabletext .= UI_message($cert_biggest_oddity,0,0,true);
        
    $tabletext .= "</td><td>";
    if (!$UDP_errors) {
        $tabletext .= UI_okay(0,0,true);
    } else {
        $tabletext .= UI_error(0,0,true);
    }
    
    $tabletext .= "</td><td>";

    $dynamic_errors = false;

    if ($naptr > 0 && count($testsuite->NAPTR_hostname_records) > 0) {
        foreach ($testsuite->NAPTR_hostname_records as $hostindex => $addr) {
            $retval = $testsuite->TLS_clients_side_check($addr);
            if ($retval != RETVAL_OK && $retval != RETVAL_SKIPPED)
                $dynamic_errors = true;
        }
    }
    if (!$dynamic_errors) {
        $tabletext .= UI_okay(0,0,true);
    } else {
        $tabletext .= UI_error(0,0,true);
    }
    $tabletext .= "</td></tr>";

    DBConnection::exec("INST", "UPDATE profile SET "
                . "status_dns = ".($NAPTR_issues ? RETVAL_INVALID : RETVAL_OK) . ", "
                . "status_cert = ".($cert_biggest_oddity) . ", "
                . "status_reachability = ".($UDP_errors ? RETVAL_INVALID : RETVAL_OK) . ", "
                . "status_TLS = ".($dynamic_errors ? RETVAL_INVALID : RETVAL_OK) . ", "
                . "last_status_check = NOW() "
                . "WHERE profile_id = ".$profile->identifier);    
        
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

$cat = defaultPagePrelude(_("Authentication Server Status for all known federation members"));

// check authorisation of user; this check immediately dies if not authorised

$fed = valid_Fed($_GET['fed'], $_SESSION['user']);
$allIDPs = $fed->listIdentityProviders();

$profiles_showtime = [];
$profiles_readyconf = [];

foreach ($allIDPs as $index => $oneidp)
    foreach ($oneidp['instance']->listProfiles() as $profile)
        if ($profile->getShowtime()) {
            $profiles_showtime[] = ['idp' => $oneidp['instance'], 'profile' => $profile];
        } else if ($profile->readyForShowtime()) {
            $profiles_confready[] = ['idp' => $oneidp['instance'], 'profile' => $profile];
        }

if (count($profiles_showtime) > 0) {
    echo "<h2>" . _("Profiles marked as visible (V)") . "</h2>" . "<table>";
    echo rowdescription();
    foreach ($profiles_showtime as $oneprofile)
        echo profilechecks($oneprofile['idp'],$oneprofile['profile']);
    echo "</table>";
}

if (count($profiles_confready) > 0) {
    echo "<h2>" . _("Profiles with sufficient configuration, not marked as visible (C)") . "</h2>" . "<table>";
    echo rowdescription();
    foreach ($profiles_confready as $oneprofile)
        echo profilechecks($oneprofile['idp'],$oneprofile['profile']);
    echo "</table>";
}
?>
<form method='post' action='overview_federation.php' accept-charset='UTF-8'>
    <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE; ?>'><?php echo _("Return to federation overview"); ?></button>
</form>

<?php footer() ?>

</body>

