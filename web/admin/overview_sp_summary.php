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
 * */
 
 /**
 * This file is used to display a deployment.
 * 
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 */
?>
<?php


$langInstance = new \core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";

?>
<style>
    .deployments td:first-child {
        padding-right: 15px;
    }
    img.copy_link {
        position: relative;
        top: 3px;
        <?php echo $start ?>: 3px;
        height: 16px;
    }
</style>


<?php

/**
 * displays an infocard about a Managed SP deployment
 * 
 * @param \core\DeploymentManaged $deploymentObject the deployment to work with
 * @throws Exception
 */
function displayDeploymentPropertyWidget(&$deploymentObject, $errormsg=[], $editMode) {
    // RADIUS status icons
    $depId = $deploymentObject->identifier;
    $radiusMessages = [
        \core\AbstractDeployment::RADIUS_OK => ['icon' => '../resources/images/icons/Tabler/square-rounded-check-filled-green.svg', 'text' => _("Successfully set profile")],
        \core\AbstractDeployment::RADIUS_FAILURE => ['icon' => '../resources/images/icons/Tabler/square-rounded-x-filled-red.svg', 'text' => _("Some problem occurred during profile update")],
    ];
    $radius_status = array();
    $radius_status[0] = $deploymentObject->radius_status_1;
    $radius_status[1] = $deploymentObject->radius_status_2;
    $cacert = file_get_contents(ROOT .  "/config/ManagedSPCerts/eduroamSP-CA.pem");
    $retry = $deploymentObject->checkRADIUSHostandConfigDaemon();
    $isradiusready = radius_ready($deploymentObject);
    if (is_array($retry)) {
        foreach ($retry as $id => $stat) {
            if ($stat) {
                $response = $deploymentObject->setRADIUSconfig($id, 1);
            }
        }
    }
    ?>
    <div style='display: table-row_id;'>
        <div class='profilebox' id="profilebox_<?php echo $depId;?>" style='display: table-cell;'>
            <h2><?php
                switch ($deploymentObject->consortium) {
                    case "eduroam":
                        $displayname = config\ConfAssistant::CONSORTIUM['name'] . " " . core\DeploymentManaged::PRODUCTNAME. ": SP$depId-".$deploymentObject->institution;
                        break;
                    case "OpenRoaming":
                        $displayname = "OpenRoaming ANP";
                        break;
                    default:
                        throw new Exception("We are supposed to operate on a roaming consortium we don't know.");
                }
                echo $displayname . " (<span style='color:" . ( $deploymentObject->status == \core\AbstractDeployment::INACTIVE ? "red;'>" . _("inactive") : "green;'>" . _("active") ) . "</span>)";
                ?></h2>
            <table class="deployments">
                <caption><?php echo _("Deployment Details"); ?></caption>
                <tr><td>
                <?php 
                echo _("Last seen:").' </td><td>';
                $when = $deploymentObject->getLastActivity();
                if (! $when ) {
                    echo _("never");
                } else {
                    echo $when; 
                }
                ?>     
                </td></tr>
                <tr> 
                <?php         
                $allRealms = array_values(array_unique(array_column($deploymentObject->getAttributes("managedsp:realmforvlan"), "value")));
                $opname = $deploymentObject->getAttributes("managedsp:operatorname")[0]['value'] ?? NULL;
                $vlan = $deploymentObject->getAttributes("managedsp:vlan")[0]['value'] ?? NULL;
                $guest_vlan = $deploymentObject->getAttributes("managedsp:guest_vlan")[0]['value'] ?? NULL;
                
                ?>
                <tr></tr>
                <tr><th colspan="2"><?php echo _('Settings');?></th></tr>
                    <tr>
                        <td>
                            <?php
                                if ($opname) {
                                    echo _("Custom Operator-Name");
                                } else {
                                    echo _("Default Operator-Name");
                                }
                            ?>
                        </td>
                        <td>
                        <?php
                                if ($opname) { 
                                    echo $opname; 
                                } else {
                                    echo '1sp.'.$depId.'-'.$deploymentObject->institution.'.hosted.eduroam.org';
                                }
                        ?>
                        </td>
                    </tr>
                <?php
                if ($guest_vlan) {
                    ?>
                    <tr>
                        <td><?php echo _("VLAN tag for guests"); ?></td>
                        <td>
                            <?php 
                                if ($guest_vlan) {
                                    echo $guest_vlan;
                                }
                            ?>
                        </td>
                    </tr>
                <?php
                }
                if (!empty($allRealms) || $vlan) {
                    ?>
                    <tr>
                        <td><?php echo _("VLAN tag for own users"); ?></td>
                        <td>
                            <?php 
                                if ($vlan) {
                                    echo $vlan;
                                } else {
                                    echo _('not set, be aware that realm setting is not used until a VLAN tag is added');
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php echo _("Realm to be considered own users"); ?></td>
                        <td>
                            <?php
                                if (!empty($allRealms)) {
                                    echo implode(', ', $allRealms);
                                } else {
                                    echo _('not set, be aware that VLAN setting is not used until a realm is added');
                                }
                } 
                ?>
                </td></tr>
                </form>
            </table>
            <div class='buttongroupprofilebox' style='clear:both;'>
                <form action='overview_sp_wrapper.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <br/>
                    <button type='submit' name='profile_action' value='edit'><?php echo _("Details"); ?></button>
                </form>
                <?php if ($isradiusready && $deploymentObject->status == \core\AbstractDeployment::ACTIVE && $editMode === 'fullaccess') { ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php printf(_("Do you really want to deactivate the %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
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
                } elseif (count($deploymentObject->getAttributes("hiddenmanagedsp:tou_accepted")) == 0) {
                    if ($editMode === 'fullaccess') {
                    ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_TERMSOFUSE_NEEDACCEPTANCE; ?>'>
                            <?php echo _("Accept Terms of Use"); ?>
                        </button>
                    </form>
                <?php 
                    } else {
                        echo "<strong>"._("Terms of Use not accepted.")."</strong>";
                    }
                    }
                    if ($isradiusready && $deploymentObject->status == \core\AbstractDeployment::INACTIVE && count($deploymentObject->getAttributes("hiddenmanagedsp:tou_accepted"))) { ?>
                        <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_ACTIVATE; ?>'>
                                <?php echo _("Activate"); ?>
                            </button>
                            <?php
                            if (isset($_GET['res']) && is_array($_GET['res'])) {
                                $res = array_count_values($_GET['res']);
                                if (array_key_exists('FAILURE', $res) && $res['FAILURE'] > 0) {
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
                <?php } 
                    if ($deploymentObject->status == \core\AbstractDeployment::INACTIVE && $editMode === 'fullaccess') { ?>
                        <div align="right">
                        <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_REMOVESP; ?>' onclick="return confirm('<?php printf(_("Do you really want to remove this %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Remove deployment"); ?>
                            </button>
                        </form>
                        </div>
                <?php } ?>
                    
            </div>
            <?php 
            if (!$isradiusready) { 
                echo '<p>'. _("We are not able to handle a new configuration request requiring contact with RADIUS servers now.") . '<br>' . _("Check later.");
                
            } 
            
            ?>
            
            
        </div>
</table>
</div>    
    <!-- dummy div to keep a little distance-->
    <div style='height:20px'></div>
    
    <?php
}

/**
 * displays a eduroam DB entry for SPs. Not implemented yet.
 * 
 * @param \core\DeploymentClassic $deploymentObject the deployment to work with
 */
function displayClassicHotspotPropertyWidget($deploymentObject) {
    
}

/**
 * checks if both RADIUS servers are ready to accept reconfiguration requests
 * 
 * 
 */
function radius_ready($dsp) {
    foreach (array($dsp->host1_v4, $dsp->host2_v4) as $host) {
        $connection = @fsockopen($host, \config\Master::MANAGEDSP['radiusconfigport']);
        if (is_resource($connection)) {
           fclose($connection);
        } else {
           return false;
        }
    }
    return true;
}

?>
<h2 style='display: flex;'><?php printf(_("%s: %s Deployment Details"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureHotspot); ?>&nbsp;
            <?php
            if ($readonly === FALSE && $editMode === 'fullaccess') {
                if (\core\CAT::hostedSPEnabled() && count($myfed->getAttributes("fed:silverbullet")) > 0) {
                    // the button is greyed out if there's no support email address configured...
                    ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <div>
                            <input type="hidden" name="consortium" value="eduroam"/>
                            <button type='submit' name='profile_action' value='new'>
                                <?php echo sprintf(_("Add %s deployment ..."), \config\ConfAssistant::CONSORTIUM['name'] . " " . \core\DeploymentManaged::PRODUCTNAME); ?>
                            </button>
                        </div>
                    </form>
                    
                    <?php 
                    /*
                    if (count($myfed->getAttributes("fed:openroaming")) > 0) {
                        ?>
                        &nbsp;
                        <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <div>
                                <input type="hidden" name="consortium" value="OpenRoaming"/>
                                <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                                    <?php echo sprintf(_("Add %s deployment ..."), "OpenRoaming ANP"); ?>
                                </button>

                            </div>
                        </form>
                        <?php
                    }
                    */
                }
            }
            ?>
        </h2>
<?php
        $hotspotProfiles = $my_inst->listDeployments();
        if (count($hotspotProfiles) == 0) { // no profiles yet.
            echo sprintf(_("There are not yet any known deployments for your %s."), $uiElements->nomenclatureHotspot);
        }

        foreach ($hotspotProfiles as $counter => $deploymentObject) {
            switch (get_class($deploymentObject)) {
                case "core\DeploymentManaged":
                    displayDeploymentPropertyWidget($deploymentObject, '', $editMode);
                    break;
                case "core\DeploymentClassic":
                    displayClassicHotspotPropertyWidget($deploymentObject);
                    break;
                default:
                    throw new Exception("We were asked to operate on something that is neither a classic nor a Managed hotspot deployment!");
            }
        }
?>
