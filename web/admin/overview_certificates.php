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
$eduroamDb = new \core\ExternalEduroamDBData();

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
    foreach ($feds as $oneFed) {
        $theFed = new \core\Federation($oneFed['value']);
        printf("<h2>" . _("Certificate Information for %s %s")."</h2>", $uiElements->nomenclatureFed, $theFed->name);
        foreach ($theFed->listTlsCertificates() as $oneCert) {
            if ($oneCert['STATUS'] == "REQUESTED") {
                $theFed->updateCertificateStatus($oneCert['REQSERIAL']);
            }
        }
        echo "<table>";
        echo "<tr><th>"._("Request Serial")."</th><th>"._("Distinguished Name")."</th><th>Status</th><th>"._("Expiry")."</th><th>"._("Download")."</th></tr>";
        foreach ($theFed->listTlsCertificates() as $oneCert) { // fetch list a second time, in case we got a cert
            $status = $oneCert['STATUS'];
            echo "<tr>";
            echo "<td>" . $oneCert['REQSERIAL'] . "</td><td>" . $oneCert['DN'] . "</td><td>" . $status . "</td><td>" . $oneCert['EXPIRY'] . "</td>";
            if ($status == "ISSUED") {
                ?>
            <td>
                <form action='inc/showCert.inc.php' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method="POST">
                    <input type="hidden" name="certdata" value="<?php echo $oneCert['CERT'];?>"/>
                <button type="submit"><?php echo _("Display");?></button>
                </form>
            <td>
                <?php
            }
            echo "</tr>";
        }
        echo "</table>";
        
        if (count($eduroamDb->listExternalTlsServersFederation($theFed->tld)) > 0) {
            ?>
            <form action="action_req_certificate.php" method="POST">
                <button type="submit" name="newreq" id="newreq" value="<?php echo \web\lib\common\FormElements::BUTTON_CONTINUE ?>"><?php echo _("Request new Certificate"); ?></button>
            </form>
            <?php
        } else {
            ?>
            <span style="color: red"><?php echo sprintf(_("You can not request certificates because there is no server information for %s in the eduroam DB."), $theFed->tld); ?></span>
            <?php
        }
    }
    echo $deco->footer();
    