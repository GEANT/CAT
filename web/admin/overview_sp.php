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
 * This page displays the dashboard overview of an entire IdP.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
require_once dirname(dirname(dirname(__FILE__))) . "/core/phpqrcode.php";


$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();

// our own location, to give to diag URLs
if (isset($_SERVER['HTTPS'])) {
    $link = 'https://';
} else {
    $link = 'http://';
}
$link .= $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
$link = htmlspecialchars($link);

const QRCODE_PIXELS_PER_SYMBOL = 12;

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Dashboard"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclatureHotspot));
require_once "inc/click_button_js.php";

// let's check if the inst handle actually exists in the DB
$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);

// delete stored realm

if (isset($_SESSION['check_realm'])) {
    unset($_SESSION['check_realm']);
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, TRUE);
echo $mapCode->htmlHeadCode();
?>
</head>
<body <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    echo $deco->productheader("ADMIN-SP");

    // Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureHotspot); ?></h1>
    <div>
        <h2><?php echo sprintf(_("%s general settings"), $uiElements->nomenclatureHotspot); ?></h2>
        <?php
        echo $uiElements->instLevelInfoBoxes($my_inst);
        ?>
        <?php
        foreach ($idpoptions as $optionname => $optionvalue) {
            if ($optionvalue['name'] == "general:geo_coordinates") {
                echo '<div class="infobox">';
                echo $mapCode->htmlShowtime();
                echo '</div>';
                break;
            }
        }
        ?>
    </div>
    <?php
    $readonly = CONFIG['DB']['INST']['readonly'];
    ?>
    <hr><h2><?php echo _("Available Support actions"); ?></h2>
    <table>
        <?php
        if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] !== NULL) {
            echo "<tr>
                        <td>" . _("Check another realm's reachability") . "</td>
                        <td><form method='post' action='../diag/action_realmcheck.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'>
                              <input type='text' name='realm' id='realm'>
                              <input type='hidden' name='comefrom' id='comefrom' value='$link'/>
                              <button type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>";
        }
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam") { // SW: APPROVED
            echo "<tr>
                        <td>" . sprintf(_("Check %s server status"), $uiElements->nomenclatureFed) . "</td>
                        <td>
                           <form action='https://monitor.eduroam.org/mon_direct.php' accept-charset='UTF-8'>
                              <button type='submit'>" . _("Go!") . "</button>
                           </form>
                        </td>
                    </tr>";
        }
        ?>
    </table>
    <hr/>
    <?php
    $hotspotProfiles = []; // $my_inst->listHotspots();
    if (count($hotspotProfiles) == 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("There are not yet any known deployments for your %s."), $uiElements->nomenclatureHotspot) . "</h2>";
    }
    if (count($hotspotProfiles) > 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("Deployments for this %s"), $uiElements->nomenclatureHotspot) . "</h2>";
    }

    if ($readonly === FALSE) {
        // the opportunity to add a new silverbullet profile is only shown if
        // a) there is no SB profile yet
        // b) federation wants this to happen

        $myfed = new \core\Federation($my_inst->federation);
        if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0 && $sbProfileExists === FALSE) {
            // the button is grayed out if there's no support email address configured...
            $hasMail = count($my_inst->getAttributes("support:email"));
            ?>
            <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <div>
                    <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                        <?php echo sprintf(_("Add %s deployment ..."), "Managed SP" );// \core\ProfileSilverbullet::PRODUCTNAME); ?>
                    </button>
                </div>
            </form>
            <?php
        }

        // adding a normal profile is always possible if we're configured for it
    }
    echo $deco->footer();
    