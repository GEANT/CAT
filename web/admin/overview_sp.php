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

$error_message = [
    'WRONGCSR' => _('The uploaded file does not contain a valid CSR!'),
    'NOCSR' => _('The provided file can not be uploaded.'),
];

$langInstance = new \core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";
$errormsg = [];
if (isset($_GET['errormsg'])) {
    $msg = explode('_', trim($_GET['errormsg']));
    if (count($msg) == 2) {
        $errormsg[$msg[1]] = $error_message[$msg[0]];
    }
}
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
<script>
function getFile() {
    if (confirm('<?php printf(_("Do you really want to replace TLS credentials for this deployment? The current TLS credentials will be revoked in 4 hours.")); ?>')) {
    document.getElementById("upfile").click();   
    }
    event.preventDefault(); 
}

function sendcsr(obj) {
    //alert(obj.value);
    var file = obj.value;
    var fileName = file.split("\\");
    //alert(fileName[fileName.length - 1]);
    document.getElementById("submitbuttoncsr").value = '<?php echo web\lib\common\FormElements::BUTTON_USECSR; ?>';
    document.csrupload.submit();
    event.preventDefault();
}

$(function() {
    $(".copy_link").tooltip();
    
    $(".copy_link").on("click", function() {
        $(".copy_link").tooltip({
            content: "<?php echo _("Copy to clipboard") ?>"
        });
        var field = $(this).attr("id").replace('_icon', '_data');
        var toCopy = $("#"+field).html();        
        navigator.clipboard.writeText(toCopy);
        $(this).tooltip({
            content: "<strong><?php echo _("Copied!") ?></strong>"
        });
        $(this).fadeOut(150).fadeIn(150);
    });
});


</script>

<?php
function copyIcon($target) {
    return '<img class="copy_link" id="'.$target.'" src="../resources/images/icons/Tabler/copy.svg" title="'. _("Copy to clipboard").'" >';
}

/**
 * displays an infocard about a Managed SP deployment
 * 
 * @param \core\DeploymentManaged $deploymentObject the deployment to work with
 * @throws Exception
 */
function displayDeploymentPropertyWidget(&$deploymentObject, $errormsg=[]) {
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
                <form action="?inst_id=<?php echo $deploymentObject->institution; ?>" method="post">
                <tr>
                    <th colspan="2"><?php echo("RADIUS over UDP"); ?></th>
                </tr>
                <tr style="vertical-align:top">
                    <td>
                        <?php echo _("Your primary RADIUS server") ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->host1_v4 !== NULL) {
                            printf(_("IPv4: %s"), "<span id='host1_v4_data_$depId'>".$deploymentObject->host1_v4."</span>");
                            echo copyIcon("host1_v4_icon_$depId");
                        }
                        if ($deploymentObject->host1_v4 !== NULL && $deploymentObject->host1_v6 !== NULL) {
                            echo "<br/>";
                        }
                        if ($deploymentObject->host1_v6 !== NULL) {
                            printf(_("IPv6: %s"), "<span id='host1_v6_data_$depId'>".$deploymentObject->host1_v6."</span>");
                            echo copyIcon("host1_v6_icon_$depId");                            
                        }
                        echo "<br/>";
                        printf(_("port: %s"), "<span id='port1_data_$depId'>".$deploymentObject->port1."</span>");
                        echo copyIcon("port1_icon_$depId");                            
                        ?>                        
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->status) {
                            echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_1]['icon'] .
                                "' alt='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] .
                                "' title='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] . "' class='cat-icon'>";
                        }
                        ?>
                    </td>
                </tr>
                <tr style="vertical-align:top">
                    <td>
                        <?php echo _("Your secondary RADIUS server") ?>
                    </td>
                    <td>            
                        <?php
                        if ($deploymentObject->host2_v4 !== NULL) {
                            printf(_("IPv4: %s"), "<span id='host2_v4_data_$depId'>".$deploymentObject->host2_v4."</span>");
                            echo copyIcon("host2_v4_icon_$depId");
                        }
                        if ($deploymentObject->host2_v4 !== NULL && $deploymentObject->host2_v6 !== NULL) {
                            echo "<br/>";
                        }
                        if ($deploymentObject->host2_v6 !== NULL) {
                            printf(_("IPv6: %s"), "<span id='host2_v6_data_$depId'>".$deploymentObject->host2_v6."</span>");
                            echo copyIcon("host2_v6_icon_$depId");                            
                        }
                        echo "<br/>";
                        printf(_("port: %s"), "<span id='port2_data_$depId'>".$deploymentObject->port2."</span>");
                        echo copyIcon("port2_icon_$depId");                            
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->status && $deploymentObject->radius_status_2) {
                            echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_2]['icon'] .
                                "' alt='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] .
                            "' title='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] . "' class='cat-icon'>";
                        }
                        ?>
                    </td>
                </tr>
                <tr style="vertical-align:bottom">
                    <td><?php echo _("RADIUS shared secret for both servers"); ?></td>
                    <td>
                        <span id="shared_data_<?php echo $deploymentObject->identifier;?>"><?php echo $deploymentObject->secret;?></span>
                        <?php echo copyIcon("shared_icon_".$deploymentObject->identifier) ?>
                    </td>
                    <td></td>
                </tr>
                <tr></tr>
                <tr><td colspan="3" style="background-color: #1d4a74; height: 1px"></tr>

                <tr>
                    <th colspan="2"><?php echo("RADIUS over TLS or TLS-PSK"); ?></th>
                </tr>
                <tr style="vertical-align:top">
                    <td>
                        <?php echo _("Your primary RADIUS server") ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->host1_v4 !== NULL) {
                            printf(_("IPv4: %s"), "<span id='host1_v4_t_data_$depId'>".$deploymentObject->host1_v4."</span>");
                            echo copyIcon("host1_v4_t_icon_$depId");                             
                        }
                        if ($deploymentObject->host1_v4 !== NULL && $deploymentObject->host1_v6 !== NULL) {
                            echo "<br/>";
                        }
                        if ($deploymentObject->host1_v6 !== NULL) {
                            printf(_("IPv6: %s"), "<span id='host1_v6_t_data_$depId'>".$deploymentObject->host1_v6."</span>");
                            echo copyIcon("host1_v6_t_icon_$depId");  
                        }
                        echo "<br/>";
                        printf(_("port: %s"), "<span id='port1_t_data_$depId'>2083</span>");
                        echo copyIcon("port1_t_icon_$depId");                          
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->status) {
                            echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_1]['icon'] .
                                "' alt='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] .
                                "' title='" . $radiusMessages[$deploymentObject->radius_status_1]['text'] . "' class='cat-icon'>";
                        }
                        ?>
                    </td>
                </tr>
                <tr style="vertical-align:top">
                    <td>
                        <?php echo _("Your secondary RADIUS server") ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->host2_v4 !== NULL) {
                            printf(_("IPv4: %s"), "<span id='host2_v4_t_data_$depId'>".$deploymentObject->host2_v4."</span>");
                            echo copyIcon("host2_v4_t_icon_$depId");                            
                        }
                        if ($deploymentObject->host2_v4 !== NULL && $deploymentObject->host2_v6 !== NULL) {
                            echo "<br/>";
                        }
                        if ($deploymentObject->host2_v6 !== NULL) {
                            printf(_("IPv6: %s"), "<span id='host2_v6_t_data_$depId'>".$deploymentObject->host2_v6."</span>");
                            echo copyIcon("host2_v6_t_icon_$depId");                            
                        }
                        echo "<br/>";
                        printf(_("port: %s"), "<span id='port2_t_data_$depId'>2083</span>");
                        echo copyIcon("port2_t_icon_$depId");                          
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($deploymentObject->status && $deploymentObject->radius_status_2) {
                            echo "<img src='" . $radiusMessages[$deploymentObject->radius_status_2]['icon'] .
                                "' alt='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] .
                            "' title='" . $radiusMessages[$deploymentObject->radius_status_2]['text'] . "' class='cat-icon'>";
                        }
                        ?>
                    </td>
                </tr>
                

                
                <?php if ($deploymentObject->radsec_cert != '') { 
                    $data = openssl_x509_parse($deploymentObject->radsec_cert);
                    ?>
                <tr style="vertical-align:top">
                    <td><?php echo _("RADIUS over TLS credentials"); ?></td>
                    <td>
                    <?php
                    if ($deploymentObject->radsec_priv == '') {
                        echo _('The client certificate was created using an uploaded CSR, the private key is not available') . '<br><br>';
                    }
                    echo _('Subject:') . ' ' . $data['name'] . '<br>';
                    echo _('Serial number:') . ' ' . $data['serialNumberHex'] . '<br>';
                    $dleft = floor(($data['validTo_time_t']-time())/(24*60*60));
                    if ($dleft < 30) {
                        echo '<font color="red">';
                    }
                    echo _('Not valid after:') . ' '. date_create_from_format('ymdGis', substr($data['validTo'], 0, -1))->format('Y-m-d H:i:s') . ' UTC';
                    if ($dleft > 2) {
                        echo '<br>' . _('Number of days to expiry:') . ' ' . $dleft;
                    } else {
                        echo '<br>' . _('If you are using RADIUS over TLS you should urgently renew your credentials') . '!';
                    }
                    if ($dleft < 30) { echo '</font>'; }
                    ?></td>
                </tr><tr><td></td>

                    <td>
                        <span style="display: none;" id="cert_data_<?php echo $depId;?>"><?php echo $deploymentObject->radsec_cert;?></span>
                        <span style="display: none;" id="ca_cert_data_<?php echo $depId;?>"><?php echo $cacert;?></span>
                        <?php if ($deploymentObject->radsec_priv != '') {
                            echo _("private key").copyIcon("priv_key_icon_$depId")."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                            echo '<span style="display: none;" id="priv_key_data_'.$depId.'">'.$deploymentObject->radsec_priv.'</span>';
                            echo '&nbsp;&nbsp;';
                        }
                            echo _("certificate").copyIcon("cert_icon_$depId")."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                            echo '&nbsp;&nbsp;';
                            echo _("CA certificate").copyIcon("ca_cert_icon_$depId");
                        ?>
                        <br/>
                        <button name="sendzip" onclick="location.href='inc/sendZip.inc.php?inst_id=<?php echo $deploymentObject->institution;?>&dep_id=<?php echo $depId?>'" type="button"><?php echo _('download ZIP-file with full data');?></button>

                    </td>
                    <td></td>
                </tr>
                <tr> 
                <td></td>
                <td>
                    <?php
                        if ($deploymentObject->radsec_cert != NULL) {
                            echo "<i>";
                            echo _('If your certificate is close to expiry or you need to create new RADIUS over TLS credentials') . '<br>' .
                                 _('click on "Renew RADIUS over TLS credentials" button') . '<br>';
                        
                            echo '<br/>' . _('You can upload your own CSR to replace default TLS credentials.') . '<br>' . 
                                _('Click on "Upload CSR to sign my own TLS credentials"');
                            echo "</i>";
                    }
                    ?>    
                </td>
                <td></td>
                </tr>
                <?php         
                  
                }
                if ($deploymentObject->pskkey != '') {?>
                <tr style="vertical-align:top">
                        <td><?php echo _("RADIUS over TLS-PSK credentials"); ?></td>
                        <td>
                            <?php printf(_("PSK Identity: %s"), "<span id='pskid_data_$depId'>SP".$depId.'-'.$deploymentObject->institution.'</span>');
                            echo copyIcon("pskid_icon_$depId");
                           ?>
                            <br>
                            <?php printf(_("PSK hexphrase: %s"), "<span id='pskkey_data_$depId'>".$deploymentObject->pskkey."</span>");
                            echo copyIcon("pskkey_icon_$depId");
                            ?>
                        </td>
                        <td></td>
                </tr>
                
                
                <?php } 
                $allRealms = array_values(array_unique(array_column($deploymentObject->getAttributes("managedsp:realmforvlan"), "value")));
                $opname = $deploymentObject->getAttributes("managedsp:operatorname")[0]['value'] ?? NULL;
                $vlan = $deploymentObject->getAttributes("managedsp:vlan")[0]['value'] ?? NULL;
                $guest_vlan = $deploymentObject->getAttributes("managedsp:guest_vlan")[0]['value'] ?? NULL;
                
                ?>
                <tr></tr>
                <tr><th colspan="2"><?php echo _('Additional deployment settings');?></th></tr>
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
                <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <br/>
                    <button type='submit' name='profile_action' value='edit'><?php echo _("Advanced Configuration"); ?></button>
                </form>
                <?php if ($isradiusready && $deploymentObject->status == \core\AbstractDeployment::ACTIVE) { ?>
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
                    ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_TERMSOFUSE_NEEDACCEPTANCE; ?>'>
                            <?php echo _("Accept Terms of Use"); ?>
                        </button>
                    </form>
                <?php }
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
                    if ($deploymentObject->status == \core\AbstractDeployment::INACTIVE) { ?>
                        <div align="right">
                        <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_REMOVESP; ?>' onclick="return confirm('<?php printf(_("Do you really want to remove this %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Remove deployment"); ?>
                            </button>
                        </form>
                        </div>
                <?php } ?>
                    <div align="right">
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='renewtls' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_RENEWTLS; ?>' onclick="return confirm('<?php printf(_("Do you really want to replace TLS credentials for this %s deployment? The current TLS credentials will be revoked in 4 hours."), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Renew RADIUS over TLS credentials"); ?>
                            </button>
                    </form>           
                    <form name="csrupload" enctype="multipart/form-data" action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                           <button class='usecsr' type='submit' onclick="getFile();"); ?>                
                                <?php echo _("Upload CSR to sign my own TLS credentials"); ?>
                            </button>
                    <div style='height: 0px;width: 0px; overflow:hidden;'>
                        <input name='submitbutton' id='submitbuttoncsr' value=''>
                        <input id="upfile" name="upload" type="file" value="upload" onchange="sendcsr(this);" />
                    </div>
                    </form>
                        <!--
                    <label for="csr"><?php echo _("Upload CSR to sign my own TLS credentials"); ?></label>
                    <div align="right">
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <!--<input type="file" id="csr" class='usecsr' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_USECSR; ?>' onclick="return confirm('<?php printf(_("Do you really want to replace TLS credentials for this %s deployment? The current TLS credentials will be revoked in 4 hours."), core\DeploymentManaged::PRODUCTNAME); ?>')">-->
                    
  <!-- this is your file input tag, so i hide it!-->
  <!-- i used the onchange event to fire the form submission-->
  
  <!-- here you can have file submit button or you can write a simple script to upload the file automatically-->
  <!-- <input type="submit" value='submit' > -->
                    
                    </div>
            </div>
            <?php 
            if (!$isradiusready) { 
                echo '<p>'. _("We are not able to handle a new configuration request requiring contact with RADIUS servers now.") . '<br>' . _("Check later.");
                
            } 
            if (count($errormsg) > 0 && array_key_exists($deploymentObject->identifier, $errormsg)) {
            ?>
            <div style='color: red'>
                <b>
                    <?php echo $errormsg[$deploymentObject->identifier]; ?>
                </b>
            </div>
            <?php
            }
            ?>
        </div>
        <div style='width:20px;'></div> <!-- QR code space, reserved -->
        <div style='display: table-cell; min-width:200px;'>
            <?php $tablecaption = _("Hotspot Usage Statistics");?>
            <h1><?php echo $tablecaption; ?></h1>
            <h2><?php echo _("5 most recent authentications");?></h2>
            <p><?php echo _("<b>AP Identifier</b> is a /-separated tuple of NAS-Identifier/NAS-IP-Address/NAS-IPv6-Address/Called-Station-Id") .'<br>';
                     echo _("<b>Protocol</b> is a protocol used between a client and RADIUS server, for TLS it is a /-separated tuple TLS/<i>TLS-Client-Cert-Serial</i>"); ?></p>
            <table class='authrecord'>
    <caption><?php echo $tablecaption;?></caption>
    <tr style='text-align: left;'>
        <th scope="col"><strong><?php echo _("Timestamp (UTC)");?></strong></th>
        <th scope="col"><strong><?php echo _("Outer-Identity");?></strong></th>
        <th scope="col"><strong><?php echo _("Result");?></strong></th>
        <th scope="col"><strong><?php echo _("MAC Address");?></strong></th>
        <th scope="col"><strong><?php echo _("Chargeable-User-Identity");?></strong></th> 
        <th scope="col"><strong><?php echo _("AP Identifier");?></strong></th>
        <th scope="col"><strong><?php echo _("Protocol");?></strong></th>
    </tr>
    <?php
    $userAuthData = $deploymentObject->retrieveStatistics(0,5);
    $i = 0;	   
    foreach ($userAuthData as $oneRecord) {
        echo "<tr class='".($oneRecord['result'] == "OK" ? "auth-success" : "auth-fail" )."'>"
                . "<td>".$oneRecord['activity_time']."</td>"
                . "<td>".$oneRecord['outer_user']."</td>"
                . "<td>".($oneRecord['result'] == "OK" ? _("Success") : _("Failure"))."</td>"
                . "<td>".$oneRecord['mac']."</td>"
		. "<td>".substr($oneRecord['cui'], 0, 18)
		. ($oneRecord['cui']=='' ? "" : "... " . copyIcon("cui_icon_".$deploymentObject->identifier."_$i") 
	        . "<span style='display: none;' id='cui_data_".$deploymentObject->identifier."_$i'>".$oneRecord['cui'].'</span>')."</td>"
                . "<td>".$oneRecord['ap_id']."</td>"
                . "<td>".$oneRecord['prot']."</td>"
                . "</tr>";
        if ($oneRecord['cui']!='') {
            $i++;
        }
    }
    ?>
</table>
            <div style='display: ruby;'>
            <form style="display: inline;" action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-hour' name='stats' value='HOUR'><?php echo _("Last hour"); ?></button>
            </form>
            <form style="display: inline;" action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-month' name='stats' value='MONTH'><?php echo _("Last 30 days"); ?></button>
            </form>
            <form style="display: inline;" action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-full' name='stats' value='FULL'><?php echo _("Last 6 months"); ?></button>
            </form>
            </div>
            </br>
            <?php 
            echo _('Get statistics as CSV file:').' '; 
            $query = 'inc/deploymentStats.inc.php?inst_id='.$deploymentObject->institution."&deployment_id=$depId&as=csv&backlog=";
            ?>
	    <button name="sendcsv" type="button" onclick="location.href='<?php echo $query;?>WEEK';"><?php echo _('Last week');?>
            </button>
	    <button name="sendcsv" type="button" onclick="location.href='<?php echo $query;?>MONTH';"><?php echo _('Last 30 days');?>
            </button>
	    <button name="sendcsv" type="button" onclick="location.href='<?php echo $query;?>FULL';"><?php echo _('Last 6 months');?>
            </button>
        </div><!-- statistics space -->
        <div style='height:5px'></div>
        <div style='display: table-cell; min-width:200px;'>
            <h1><?php echo _("Hotspot Debug Logs"); ?></h1>
            <h2><?php echo _('To get detailed logs from RADIUS sites click a button below.'); 
            $query = 'inc/deploymentLogs.inc.php?inst_id='.$deploymentObject->institution."&deployment_id=$depId&backlog=";
            ?></h2>
            <?php echo _('You will receive zip file with logs from both RADIUS servers: primary (folder named radius-1) and secondary (folder named radius-2).') . '<br>' .
                  _('If no logs are available an empty zip file is provided.');?>
            <div style='height:3px'></div>
            <button name="logs" type="button" onclick="location.href='<?php echo $query;?>1';"><?php echo _('Today');?>
            </button>
            <button name="logs" type="button" onclick="location.href='<?php echo $query;?>2';"><?php echo _('Last 2 days');?>
            </button>
            <button name="logs" type="button" onclick="location.href='<?php echo $query;?>7';"><?php echo _('Last 7 days');?>
            </button>
        </div>
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
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0) {
                    // the button is greyed out if there's no support email address configured...
                    $hasMail = count($my_inst->getAttributes("support:email"));
                    ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <div>
                            <input type="hidden" name="consortium" value="eduroam"/>
                            <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                                <?php echo sprintf(_("Add %s deployment ..."), \config\ConfAssistant::CONSORTIUM['name'] . " " . \core\DeploymentManaged::PRODUCTNAME); ?>
                            </button>
                            <span style='color: red;'>
                            <?php if ($hasMail == 0) { 
                              echo _("Helpdesk mail address is required but missing!");  
                            }
                            ?>
                            </span>
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
                    displayDeploymentPropertyWidget($deploymentObject, $errormsg);
                    break;
                case "core\DeploymentClassic":
                    displayClassicHotspotPropertyWidget($deploymentObject);
                    break;
                default:
                    throw new Exception("We were asked to operate on something that is neither a classic nor a Managed hotspot deployment!");
            }
        }
?>
