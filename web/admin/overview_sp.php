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

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Dashboard"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureHotspot));
require_once "inc/click_button_js.php";

// RADIUS status icons
$radiusMessages = [
    \core\AbstractDeployment::RADIUS_OK => ['icon' => '../resources/images/icons/Quetto/check-icon.png', 'text' => _("Successfully set profile")],
    \core\AbstractDeployment::RADIUS_FAILURE => ['icon' => '../resources/images/icons/Quetto/no-icon.png', 'text' => _("Some problem occured during profile update")],
    ];
// let's check if the inst handle actually exists in the DB
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);

// delete stored realm

if (isset($_SESSION['check_realm'])) {
    unset($_SESSION['check_realm']);
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, TRUE);
echo $mapCode->htmlHeadCode();
?>
<script>
$(document).on('click', '#realmcheck' , function() {
   event.preventDefault();
   location.href = '../diag/diag.php?admin=1&sp=1&realm=';
});
</script>
</head>
<body 
    <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    echo $deco->productheader("ADMIN-SP");
    // Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureHotspot); ?></h1>
    <div>
        <h2><?php echo sprintf(_("%s general settings"), $uiElements->nomenclatureHotspot); ?></h2>
         <form action='edit_participant.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
            <button type='submit' name='submitbutton' value='<?php echo \web\lib\common\FormElements::BUTTON_EDIT; ?>'><?php echo sprintf(_("Edit general %s details"), $uiElements->nomenclatureParticipant); ?></button>
        </form>
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
    $readonly = \config\Master::DB['INST']['readonly'];
    ?>
    <hr><h2><?php echo _("Available Support actions"); ?></h2>
    <table>
        <?php
        if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] !== NULL) {
            echo "<tr>
                        <td>" . sprintf(_("Diagnose reachability and connection parameters of %ss"),$uiElements->nomenclatureInst) . "</td>
                        <td><form method='post' action='../diag/action_realmcheck.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'>
                              <!--<input type='text' name='realm' id='realm'>-->
                              <input type='hidden' name='comefrom' id='comefrom' value='$link'/>
                              <button id='realmcheck' style='cursor:pointer;' type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>";
        }
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
            echo "<tr>
                        <td>" . sprintf(_("Check %s server status"), $uiElements->nomenclatureFed) . "</td>
                        <td>
                           <form action='https://monitor.eduroam.org/mon_direct.php' accept-charset='UTF-8'>
                              <button style='cursor:pointer;' type='submit'>" . _("Go!") . "</button>
                           </form>
                        </td>
                    </tr>";
        }
        ?>
    </table>
    <hr/>
    <?php
    $hotspotProfiles = $my_inst->listDeployments();
    if (count($hotspotProfiles) == 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("There are not yet any known deployments for your %s."), $uiElements->nomenclatureHotspot) . "</h2>";
    }
    if (count($hotspotProfiles) > 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("Deployments for this %s"), $uiElements->nomenclatureHotspot) . "</h2>";
        // display an info box with the connection data
    }
   
    foreach ($hotspotProfiles as $counter => $deploymentObject) {
        $radius_status = array();
        $radius_status[0] = $deploymentObject->radius_status_1;
        $radius_status[1] = $deploymentObject->radius_status_2;
        $retry = $deploymentObject->checkRADIUSHostandConfigDaemon();
        if (is_array($retry)) {
            foreach ($retry as $id => $stat) { 
                if ($stat) {
                    $response = $deploymentObject->setRADIUSconfig($id, 1);
                }
            }
        } 
        ?>
        <div style='display: table-row; margin-bottom: 20px;'>
            <div class='profilebox' style='display: table-cell;'>
                <h2><?php echo core\DeploymentManaged::PRODUCTNAME . " (<span style='color:" . ( $deploymentObject->status == \core\AbstractDeployment::INACTIVE ? "red;'>" . _("inactive") : "green;'>" . _("active") ) . "</span>)"; ?></h2>
                <table>
                    <tr>
                        <td><strong><?php echo _("Your primary RADIUS server") ?></strong><br/>
                        <?php
                            if ($deploymentObject->host1_v4 !== NULL) {
                                echo _("IPv4") . ": " . $deploymentObject->host1_v4;
                            }
                            if ($deploymentObject->host1_v4 !== NULL && $deploymentObject->host1_v6 !== NULL) {
                                echo "<br/>";
                            }
                            if ($deploymentObject->host1_v6 !== NULL) {
                                echo _("IPv6") . ": " . $deploymentObject->host1_v6;
                            }
                            ?>
                        </td>
                        <td><?php echo _("RADIUS port number: ") ?></td>
                        <td><?php echo $deploymentObject->port1; ?></td>
                        <td>
                            <?php
                                echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_1]['icon'] . 
                                     "' alt='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] . 
                                     "' title='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] . "'>";
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php echo _("Your backup RADIUS server") ?><br/></strong>
                            <?php
                            if ($deploymentObject->host2_v4 !== NULL) {
                                echo _("IPv4") . ": " . $deploymentObject->host2_v4;
                            }
                            if ($deploymentObject->host2_v4 !== NULL && $deploymentObject->host2_v6 !== NULL) {
                                echo "<br/>";
                            }
                            if ($deploymentObject->host2_v6 !== NULL) {
                                echo _("IPv6") . ": " . $deploymentObject->host2_v6;
                            }
                            ?></td>
                        <td><?php echo _("RADIUS port number: ") ?></td>
                        <td><?php echo $deploymentObject->port2; ?></td>
                        <td>
                            <?php
                                echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_2]['icon'] .
                                     "' alt='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] . 
                                     "' title='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] . "'>";
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php echo _("RADIUS shared secret"); ?></strong></td>
                        <td><?php echo $deploymentObject->secret; ?></td>
                    </tr>
                    <tr><td colspan="4"><hr></td></tr>
                    <?php if ($opname = $deploymentObject->getAttributes("managedsp:operatorname")[0]['value'] ?? NULL) { ?>
                    <tr>
                        <td><strong><?php echo _("Custom Operator-Name"); ?></strong></td>
                        <td><?php echo $opname; ?></td>
                    </tr>
                    <?php
                    }
                        if ($vlan = $deploymentObject->getAttributes("managedsp:vlan")[0]['value'] ?? NULL) { ?>
                    <tr>
                        <td><strong><?php echo _("VLAN tag for own users"); ?></strong></td>
                        <td><?php echo $vlan; ?></td>
                    </tr>
                        <?php } ?>
                    <?php
                    $allRealms = array_values(array_unique(array_column($deploymentObject->getAttributes("managedsp:realmforvlan"), "value")));
                    if (!empty($allRealms)) {
                    ?>
                    <tr>
                        <td><strong><?php echo _("Realm to be considered own users"); ?></strong></td>
                        <td><?php echo implode(', ', $allRealms); ?></td>
                    </tr>
                    <?php
                    }
                    ?>
                </table>
                <div class='buttongroupprofilebox' style='clear:both;'>
                    <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <br/>
                        <button type='submit' name='profile_action' style='cursor:pointer;' value='edit'><?php echo _("Advanced Configuration"); ?></button>
                    </form>
                    <?php if ($deploymentObject->status == \core\AbstractDeployment::ACTIVE) { ?>
                        <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' type='submit' style='cursor:pointer;' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php printf(_("Do you really want to deactivate the %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Deactivate"); ?>
                            </button>
                            <?php 
                                if (isset($_GET['res']) && is_array($_GET['res'])) {
                                    $res = array_count_values($_GET['res']);
                                    if (array_key_exists('FAILURE', $res) && $res['FAILURE'] > 0) {
                                        echo '<br>';
                                        if ($res['FAILURE'] == 2) {
                                            echo ' <span style="color: red;">' . _("Activation failure.") . '</span>';
                                        } else {
                                            if (isset($_GET['res'][1]) && $_GET['res']['1'] == 'FAILURE') {
                                                echo ' <span style="color: red;">' . _("Activation failure for your primary RADIUS server.") . '</span>';
                                            } else {
                                                echo ' <span style="color: red;">' . _("Activation failure for your backup RADIUS server.") . '</span>';
                                            }
                                        }
                                    }
                                }
                            ?>
                        </form>
                        <?php
                        } else {
                        ?>
                        <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' style='background-color: green;' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_ACTIVATE; ?>'>
                                <?php echo _("Activate"); ?>
                            </button>
                            <?php 
                                if (isset($_GET['res']) && is_array($_GET['res'])) {
                                    $res = array_count_values($_GET['res']);
                                    if ($res['FAILURE'] > 0) {
                                        echo '<br>';
                                        if ($res['FAILURE'] == 2) {
                                            echo ' <span style="color: red;">' . _("Failure during deactivation, your request is queued for handling") . '</span>';
                                        } else {
                                            if (isset($_GET['res'][1]) && $_GET['res']['1'] == 'FAILURE') {
                                                echo ' <span style="color: red;">' . _("Deactivation failure for your primary RADIUS server, your request is queued.") . '</span>';
                                            } else {
                                                echo ' <span style="color: red;">' . _("Deactivation failure for your backup RADIUS server, your request is queued.") . '</span>';
                                            }
                                        }
                                    }
                                }
                            ?>
                        </form>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <div style='width:20px;'></div> <!-- QR code space, reserved -->
            <div style='display: table-cell; min-width:200px;'></div> <!-- statistics space, reserved -->
        </div>

        <?php
    }
    if ($readonly === FALSE) {
        // the opportunity to add a new silverbullet profile is only shown if
        // a) there is no SB profile yet
        // b) federation wants this to happen

        $myfed = new \core\Federation($my_inst->federation);
        if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0 && $my_inst->deploymentCount() == 0) {
            // the button is grayed out if there's no support email address configured...
            $hasMail = count($my_inst->getAttributes("support:email"));
            ?>
            <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <div>
                    <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                        <?php echo sprintf(_("Add %s deployment ..."), \core\DeploymentManaged::PRODUCTNAME); ?>
                    </button>
                </div>
            </form>
            <?php
        }

        // adding a normal profile is always possible if we're configured for it
    }
    echo $deco->footer();
    
