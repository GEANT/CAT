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
echo $deco->defaultPagePrelude(sprintf(_("%s: RADIUS/TLS superglue zone management"), \config\Master::APPEARANCE['productname']));
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
        echo _("DNS NAPTR certificate management");
        ?>
    </h1>
    <?php
    $user = new \core\User($_SESSION['user']);
    $mgmt = new \core\UserManagement();
    $isFedAdmin = $user->isFederationAdmin();

// if not, send the user away
    if (!$isFedAdmin) {
        echo _("You do not have the necessary privileges to request NAPTR superglue zones.");
        exit(1);
    }
    ?>
    <pre>
<?php
// this manual list of RADIUS/TLS endpoints will go away for the eduroam DB 2.0.1 data
$NROs = [
    "lu" => "_radsec._tcp.eduroam.lu.",
    "nl" => "_radsec._tcp.eduroam.nl.",
    ];
    foreach ($cat->getSuperglueZone() as $oneEntry) {
        foreach (explode(',',$oneEntry['inst_realm']) as $oneRealm) {
            $target = "_radsec._somewhere.eduroam.org";
            foreach ($NROs as $tld => $nroTarget) {
                if (preg_match("/$tld$/", $oneRealm)) {
                    $target = $NROs["$tld"];
                }
            }
            echo "$oneRealm IN NAPTR 100 10 \"s\" \"x-eduroam:radius.tls\" \"\" $target\n";
        }
    }
    ?>
    </pre>
<?php
echo $deco->footer();
