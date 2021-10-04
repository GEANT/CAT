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
// okay... we are indeed entitled to "do stuff"
    $feds = $user->getAttributes("user:fedadmin");
    // got a SAVE button? Mangle CSR, request certificate at CA and store info in DB
    // also send user back to the overview page
    if (isset($_POST['requestcert']) && $_POST['requestcert'] == \web\lib\common\FormElements::BUTTON_SAVE) {
        // basic sanity checks before we hand this over to openssl
        $sanitisedCsr = $validator->string($_POST['CSR'] ?? "" , TRUE);
        if (openssl_csr_get_public_key($sanitisedCsr) === FALSE) {
            throw new Exception("Sorry: Unable to parse the submitted public key - no public key inside?");
        }
        $DN = ["DC=eduroam", "DC=test", "DC=test"];
        $policies = [];
        switch ($_POST['LEVEL'] ?? "") {
            case "NRO":
                if ($user->isFederationAdmin($_POST['NRO-list']) === FALSE) {
                    throw new Exception(sprintf("Sorry: you are not %s admin for the %s requested in the form.", $uiElements->nomenclatureFed, $uiElements->nomenclatureFed));
                }
                $fed = $validator->existingFederation($_POST['NRO-list']);
                $country = strtoupper($fed->tld);
                $DN[] = "C=$country";
                $DN[] = "O=NRO of " . $cat->knownFederations[strtoupper($fed->tld)];
                $DN[] = "CN=comes.from.eduroam.db";
                $policies[] = "eduroam IdP";
                $policies[] = "eduroam SP";
                break;
            case "INST":
                $desiredInst = $validator->existingIdP($_POST['INST-list']);
                $fed = $validator->existingFederation($desiredInst->federation, $_SESSION['user']);                
                $country = strtoupper($fed->tld);
                $DN[] = "C=$country";
                $DN[] = "O=".$desiredInst->name;
                $DN[] = "CN=comes.from.eduroam.db";
                // TODO: with the info available in CAT 2.1, can also issue SP
                $policies[] = "eduroam IdP";
                break;
            default:
                throw new Exception("Sorry: Unknown level of issuance requested.");
        }
        echo "<p>" . _("Requesting a certificate with the following properties");
        echo "<ul>";
        echo "<li>" . _("Policy OIDs: ") . implode(", ", $policies) . "</li>";
        echo "<li>" . _("Distinguished Name: ") . implode(", ", $DN) . "</li>";
        echo "<li>" . _("Requester Contact Details: will come from eduroam DB (using stub 'Someone, &lt;someone@somewhere.xy&gt;').") . "</li>";
        echo "</ul></p>";
        /* $ossl = proc_open("openssl req -subj '/".implode("/", $DN)."'", [ 0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => [ "file", "/tmp/voodoo-error", "a"] ], $pipes);
        if (is_resource($ossl)) {
            fwrite($pipes[0], $_POST['CSR']);
            fclose($pipes[0]);
            $newCsr = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $retval = proc_close($ossl);
        } else {
            throw new Exception("Calling openssl in a fancy way did not work.");
        }
        echo "<p>"._("This is the new CSR (return code was $retval)")."<pre>$newCsr</pre></p>"; */
        $newCsrWithMeta = ["CSR" => /* $newCsr */ $_POST['CSR'], "USERNAME" => "Someone", "USERMAIL" => "someone@somewhere.xy", "SUBJECT" => implode(",", $DN) ,"FED" => $country];
        // our certs can be good for max 5 years
        $fed->requestCertificate($newCsrWithMeta, 1825);
        echo "<p>"._("The certificate was requested.")."</p>";
        ?>
        <form action="overview_certificates.php" method="GET">
            <button type="submit"><?php echo _("Back to Certificate Overview");?></button>
        </form>
    <?php
    echo $deco->footer();
    exit(0);
    }

    // if we did not get a SAVE button, display UI for a fresh request instead
    ?>
    <h2><?php echo _("1. Certificate Holder Details");?></h2>
    <form action="action_req_certificate.php" method="POST">
        <input type="radio" name="LEVEL" id="NRO" value="NRO" checked><?php printf(_("Certificate for %s role"), $uiElements->nomenclatureFed); ?></input>
        <?php
        if (count($feds) == 1) {
            $fedObject = new \core\Federation($feds[0]['value']);
            echo " <strong>" . $cat->knownFederations[$fedObject->tld] . "</strong>";
            echo '<input type="hidden" name="NRO-list" id="NRO-list" value="' . $fedObject->tld . '"/>';
        } else {
            ?>
            <select name="NRO-list" id="NRO-list">
                <option value="notset"><?php echo _("---PLEASE CHOOSE---"); ?></option>
                <?php
                foreach ($feds as $oneFed) {
                    $fedObject = new \core\Federation($oneFed['value']);
                    echo '<option value="' . strtoupper($fedObject->tld) . '">' . $cat->knownFederations[$fedObject->tld] . "</option>";
                }
                ?>
            </select>
            <?php
        }
        ?>
        <br/>
        <input type="radio" name="LEVEL" id="INST" value="INST"><?php printf(_("Certificate for %s role"), $uiElements->nomenclatureIdP); ?></input>
        <select name="INST-list" id="INST-list">
            <option value="notset"><?php echo _("---PLEASE CHOOSE---"); ?></option>
            <?php
            $allIdPs = [];
            foreach ($feds as $oneFed) {
                $fedObject = new \core\Federation($oneFed['value']);
                foreach ($fedObject->listIdentityProviders(0) as $oneIdP) {
                    $allIdPs[$oneIdP['entityID']] = $oneIdP["title"];
                }
            }
            foreach ($allIdPs as $id => $name) {
                echo '<option value="' . $id . '">' . $name . "</option>";
            }
            ?>
        </select>
        <br/>
        <h2><?php echo _("2. CSR generation");?></h2>
        <p><?php echo _("One way to generate an acceptable certificate request is via this openssl one-liner:");?></p>
        <p>openssl req -new -newkey rsa:4096 -out test.csr -keyout test.key -subj /DC=test/DC=test/DC=eduroam/C=XY/O=WillBeReplaced/CN=will.be.replaced</p>
        <h2><?php echo _("3. Submission");?></h2>
        <?php echo _("Please paste your CSR here:"); ?><br/><textarea name="CSR" id="CSR" rows="20" cols="85"/></textarea><br/>
    <button type="submit" name="requestcert" id="requestcert" value="<?php echo \web\lib\common\FormElements::BUTTON_SAVE ?>"><?php echo _("Send request"); ?></button>
</form>
    <form action="overview_certificates.php" method="POST">
        <button type="submit" name="abort" id="abort" value="<?php echo \web\lib\common\FormElements::BUTTON_CLOSE ?>"><?php echo _("Back to Overview Page"); ?></button>
    </form>
<?php
echo $deco->footer();
