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
 * This page edits a federation.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();
$cat = new core\CAT();

/* Are we operating against the eduPKI Test CA? For the prod CA, set to false */
$is_testing = true;


$auth->authenticate();

/// product name (eduroam CAT), then term used for "federation", then actual name of federation.
echo $deco->defaultPagePrelude(sprintf(_("%s: RADIUS/TLS certificate management for %s"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureFed));
$langObject = new \core\common\Language();
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate.js"></script> 
</head>
<body>

    <?php echo $deco->productheader("FEDERATION"); ?>

    <h1>
        <?php
        /// nomenclature for federation, then actual federation name
        printf(_("RADIUS/TLS certificate management for %s"), $uiElements->nomenclatureFed);
        ?>
    </h1>
    <?php
    $user = new \core\User($_SESSION['user']);
    $mgmt = new \core\UserManagement();
    $isFedAdmin = $user->isFederationAdmin();

// if not, send the user away
    if (!$isFedAdmin) {
        echo _("You do not have the necessary privileges to request server certificates.");
        exit(1);
    }
    $externalDb = new \core\ExternalEduroamDBData();
// okay... we are indeed entitled to "do stuff"
    $feds = [];
    $allAuthorizedFeds = $user->getAttributes("user:fedadmin");
    foreach ($allAuthorizedFeds as $oneFed) {
        $fed = $validator->existingFederation($oneFed["value"]);
        $country = strtoupper($fed->tld);
        $serverInfo = $externalDb->listExternalTlsServersFederation($fed->tld);
        if (count($serverInfo) > 0) {
            $feds[] = $fed;
        }
    }
    if ($is_testing === true) {
        $DN = ["DC=eduroam", "DC=test", "DC=test"];
        $expiryDays = 365;
    } else {
        $DN = ["DC=eduroam", "DC=geant", "DC=net"];
        $expiryDays = 1825;
    }

    // got a SAVE button? Mangle CSR, request certificate at CA and store info in DB
    // also send user back to the overview page
    if (isset($_POST['requestcert']) && $_POST['requestcert'] == \web\lib\common\FormElements::BUTTON_SAVE) {
        // basic sanity checks before we hand this over to openssl
        $sanitisedCsr = $validator->string($_POST['CSR'] ?? "", TRUE);
        if (openssl_csr_get_public_key($sanitisedCsr) === FALSE) {
            throw new Exception("Sorry: Unable to parse the submitted public key - no public key inside?");
        }
        $policies = [];
        switch ($_POST['LEVEL'] ?? "") {
            case "NRO":
                if ($user->isFederationAdmin($_POST['NRO-list']) === FALSE) {
                    throw new Exception(sprintf("Sorry: you are not %s admin for the %s requested in the form.", $uiElements->nomenclatureFed, $uiElements->nomenclatureFed));
                }
                $fed = $validator->existingFederation($_POST['NRO-list']);
                $country = strtoupper($fed->tld);
                $DN[] = "C=$country";
                $DN[] = "O=NRO of " . iconv('UTF-8', 'ASCII//TRANSLIT', $cat->knownFederations[strtoupper($fed->tld)]);
                $serverInfo = $externalDb->listExternalTlsServersFederation($fed->tld);
                $serverList = explode(",", array_key_first($serverInfo));
                $DN[] = "CN=" . $serverList[0];
                $policies[] = "eduroam IdP";
                $policies[] = "eduroam SP";
                $firstName = $serverInfo[array_key_first($serverInfo)][0]["name"];
                $firstMail = $serverInfo[array_key_first($serverInfo)][0]["mail"];

                break;
            case "INST":
                $matches = [];
                preg_match('/^([A-Z][A-Z]).*\-.*/', $_POST['INST-list'], $matches);
                $extInsts = $externalDb->listExternalTlsServersInstitution($matches[1]);
                if ($user->isFederationAdmin($matches[1]) === FALSE) {
                    throw new Exception(sprintf("Sorry: you are not %s admin for the %s requested in the form.", $uiElements->nomenclatureFed, $uiElements->nomenclatureFed));
                }
                $country = strtoupper($matches[1]);
                $DN[] = "C=$country";
                $serverInfo = $extInsts[$_POST['INST-list']];
                if (isset($serverInfo["names"]["en"])) {
                    $ou = $serverInfo["names"]["en"];
                } else {
                    $ou = $serverInfo["names"][$langInstance->getLang()];
                }
                $DN[] = "O=".iconv('UTF-8', 'ASCII//TRANSLIT', $ou);
                $serverList = explode(",", $serverInfo["servers"]);
                $DN[] = "CN=" . $serverList[0];
                switch ($serverInfo["type"]) {
                    case core\IdP::TYPE_IDPSP:
                        $policies[] = "eduroam IdP";
                        $policies[] = "eduroam SP";
                        break;
                    case core\IdP::TYPE_IDP:
                        $policies[] = "eduroam IdP";
                        break;
                    case core\IdP::TYPE_SP:
                        $policies[] = "eduroam SP";
                        break;
                }
                $firstName = $serverInfo["contacts"][0]["name"];
                $firstMail = $serverInfo["contacts"][0]["mail"];

                break;
            default:
                throw new Exception("Sorry: Unknown level of issuance requested.");
        }
        echo "<p style='font-size: large'>" . _("Requesting a certificate with the following properties");
        echo "<ul>";
        echo "<li>" . _("Policy OIDs: ") . implode(", ", $policies) . "</li>";
        echo "<li>" . _("Distinguished Name: ") . implode(", ", $DN) . "</li>";
        echo "<li>" . _("subjectAltName:DNS : ") . implode(", ", $serverList) . "</li>";
        echo "<li>" . _("Requester Contact Details: ") . $firstName . " &lt;" . $firstMail . "&gt;" . "</li>";
        echo "</ul></p>";

        $vettedCsr = $validator->string($_POST['CSR'], true);
        $newCsrWithMeta = [
            "CSR_STRING" => /* $newCsr */ $vettedCsr,
            "USERNAME" => $firstName,
            "USERMAIL" => $firstMail,
            "SUBJECT" => implode(",", $DN),
            "ALTNAMES" => $serverList,
            "POLICIES" => $policies,
            "FED" => $country];
        // our certs can be good for max 5 years
        $fed->requestCertificate($user->identifier, $newCsrWithMeta, $expiryDays);
        echo "<p>" . _("The certificate was requested.") . "</p>";
        ?>
        <form action="overview_certificates.php" method="GET">
            <button type="submit"><?php echo _("Back to Certificate Overview"); ?></button>
        </form>
        <?php
        echo $deco->footer();
        exit(0);
    }

    // if we did not get a SAVE button, display UI for a fresh request instead
    ?>
    <h2><?php echo _("1. Certificate Holder Details"); ?></h2>
    <form action="action_req_certificate.php" onsubmit="check_csr();" method="POST">
        <?php
        switch (count($feds)) {
            case 0:
                echo "<div>";
                echo $uiElements->boxRemark("<strong>" . sprintf(_("None of your %s servers has complete information in the database."),$uiElements->nomenclatureFed)."</strong>" . _("At least the DNS names of TLS servers and a role-based contact mail address are required."));
                echo "</div>";
                break;
            case 1:
                echo '<input type="radio" name="LEVEL" id="NRO" value="NRO" checked>' . sprintf(_("Certificate for %s") ." ", $uiElements->nomenclatureFed) . '</input>';
                echo " <strong>" . $cat->knownFederations[$feds[0]->tld] . "</strong>";
                echo '<input type="hidden" name="NRO-list" id="NRO-list" value="' . $feds[0]->tld . '"/>';
                break;
            default:
                echo '<input type="radio" name="LEVEL" id="NRO" value="NRO" checked>' . sprintf(_("Certificate for %s") ." ", $uiElements->nomenclatureFed) . '</input>';
                ?>
                <select name="NRO-list" id="NRO-list">
                    <option value="notset"><?php echo _("---PPPLEASE CHOOSE---"); ?></option>
                    <?php
                    foreach ($feds as $oneFed) {
                        #echo '<option value="' . strtoupper($oneFed->tld) . '">' . $cat->knownFederations[$oneFed->tld] . "</option>";
                        echo '<option value="AAA' . strtoupper($oneFed->tld) . '">' . $oneIdP["names"][$langObject->getLang()] . "</option>";
                        
                    }
                    ?>
                </select>
            <?php
        }
        $allIdPs = [];
        foreach ($allAuthorizedFeds as $oneFed) {
            foreach ($externalDb->listExternalTlsServersInstitution($oneFed['value']) as $id => $oneIdP) {
                $allIdPs[$id] = '[' . substr($id, 0, 2) . '] ' . $oneIdP["names"][$langObject->getLang()];
            }
        }
        if (count($allIdPs) > 0) {
        ?>
        <br/>
        <input type="radio" name="LEVEL" id="INST" value="INST"><?php printf(_("Certificate for %s "), $uiElements->nomenclatureParticipant); ?></input>
        <select name="INST-list" id="INST-list">
            <option value="notset"><?php echo _("---PLEASE CHOOSE---"); ?></option>
<?php
foreach ($allIdPs as $id => $name) {
    echo '<option value="' . $id . '">' . $name . "</option>";
}
?>
        </select>
        <?php
        } else {
            echo "<div>";
            echo $uiElements->boxRemark(sprintf(_("<strong>No organisation inside your %s has complete information in the database</strong>."." "._("At least the DNS names of TLS servers and a role-based contact mail address are required.")),$uiElements->nomenclatureFed), "No TLS capable org!", true);
            echo "</div>";
        }
        ?>
        <br/>
        <?php
        if (count($feds) > 0 || count($allIdPs) > 0) {?>
        <h2><?php echo _("2. CSR generation"); ?></h2>
        <p><?php echo _("One way to generate an acceptable certificate request is via this openssl one-liner:"); ?></p>
        <?php 
        echo "openssl req -new -newkey rsa:4096 -out test.csr -keyout test.key -subj /". implode('/', array_reverse($DN)) ."/C=XY/O=WillBeReplaced/CN=will.be.replaced";
        ?>
        <h2><?php echo _("3. Submission"); ?></h2>
<?php echo _("Please paste your CSR here:"); ?><br/><textarea name="CSR" id="CSR" rows="20" cols="85"/></textarea><br/>
    <button type="submit" name="requestcert" id="requestcert" value="<?php echo \web\lib\common\FormElements::BUTTON_SAVE ?>"></td><?php echo _("Send request"); ?></button>
    <?php
        }
        ?>
</form>
    <div style="margin-left:50em">
<form action="overview_certificates.php" method="POST">
    <button type="submit" name="abort" id="abort" value="<?php echo \web\lib\common\FormElements::BUTTON_CLOSE ?>"><?php echo _("Back to Overview Page"); ?></button>
</form>
    </div>
<?php
echo $deco->footer();
?>
<script type="text/javascript">
    function check_csr() {
        var ok = true;
        var level = $("input[type='radio'][name='LEVEL']:checked").val();
        if (level == 'INST') { 
          var inst = document.getElementById('INST-list').value;
          if (inst == 'notset') {
              alert('You have to choose organisation!');
              ok = false;
          }
        }
        var csr = document.getElementById('CSR').value;
        if (csr == '') {
            alert('Your CSR is empty!');
            ok = false;
        } else {
            if (!csr.startsWith('-----BEGIN CERTIFICATE REQUEST-----')) {
                alert('The CSR must start with -----BEGIN CERTIFICATE REQUEST-----');
                ok = false;
            } else {
                if (!csr.endsWith('-----END CERTIFICATE REQUEST-----')) {
                    alert('The CSR must end with -----END CERTIFICATE REQUEST-----');
                    ok = false;
                }
            }
        }
        if (!ok) {
            event.preventDefault();
            return false;
        }
        return true;
    }
</script>
