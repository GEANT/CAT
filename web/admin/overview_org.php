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

function displaySilverbulletPropertyWidget(&$theProfile, $readonly, &$uiElements) {
    ?>
    <div style='padding-bottom:20px;'>
        <h2><?php echo $theProfile->name; ?></h2>
        <?php
        $maxusers = $theProfile->getAttributes("internal:silverbullet_maxusers");
        $completeness = $theProfile->isEapTypeDefinitionComplete(new core\common\EAP(core\common\EAP::INTEGER_SILVERBULLET));
        // do we have all info needed for showtime? particularly: support email
        if (is_array($completeness)) {
            ?>
            <div class='notacceptable'>
                <?php echo _("Information needed!"); ?>
                <ul style='margin:1px'>
                    <?php
                    foreach ($completeness as $missing_attrib) {
                        echo "<li>" . $uiElements->displayName($missing_attrib) . "</li>";
                    }
                    ?>
                </ul>
            </div>
            <?php
        } else {
            echo sprintf(_("You can create up to %d users."), $maxusers[0]['value']) . "<br/>" . sprintf(_("Their credentials will carry the name <strong>%s</strong>."), $theProfile->realm);
        }
        ?>
        <br/>
        <br/>
        <?php
        if ($readonly === FALSE) {
            ?>
            <form action='edit_silverbullet.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='POST'>
                <button <?php echo ( is_array($completeness) ? "disabled" : "" ); ?> type='submit' name='sb_action' value='sb_edit'><?php echo _("Manage User Base"); ?></button>
            </form>
            <?php
        }
        ?>
    </div>
    <?php
}

/**
 * display an infocard with overview data of a RADIUS profile
 * 
 * @param \core\Profile             $theProfile the profile we display
 * @param boolean                   $readonly     are we in readonly mode? No edit buttons then...
 * @param \web\lib\admin\UIElements $uiElements   some UI elements
 * @throws Exception
 */
function displayRadiusPropertyWidget(&$theProfile, $readonly, &$uiElements) {
    ?>
    <div style='padding-bottom:20px;'>
        <?php $profile_name = $theProfile->name; ?>
        <div style='margin-bottom:10px; display:block;'>
            <h2 style='overflow:auto; display:inline; padding-bottom: 10px;'><?php printf(_("Profile: %s"), $profile_name); ?></h2>
        </div>
        <?php
        // see if there are any profile-level overrides
        $attribs = $theProfile->getAttributes();
        // write things into a buffer; we need some function calls to determine
        // readiness - but want to display it before!
        $has_overrides = FALSE;
        foreach ($attribs as $attrib) {
            if ($attrib['level'] == \core\Options::LEVEL_PROFILE && !preg_match("/^(internal:|profile:name|profile:description|eap:)/", $attrib['name'])) {
                $has_overrides = TRUE;
            }
        }
        $buffer_eaptypediv = "<div style='margin-bottom:40px; float:left;'>" . _("<strong>EAP Types</strong> (in order of preference):") . "<br/>";
        $typelist = $theProfile->getEapMethodsinOrderOfPreference();
        $allcomplete = TRUE;
        foreach ($typelist as $eaptype) {
            $buffer_eaptypediv .= $eaptype->getPrintableRep();
            $completeness = $theProfile->isEapTypeDefinitionComplete($eaptype);
            if ($completeness === true) {
                $buffer_eaptypediv .= " <div class='acceptable'>" . _("OK") . "</div>";
            } else {
                $buffer_eaptypediv .= " <div class='notacceptable'>";
                $buffer_eaptypediv .= _("Information needed!");
                if (is_array($completeness)) {
                    $buffer_eaptypediv .= "<ul style='margin:1px'>";
                    foreach ($completeness as $missing_attrib) {
                        $buffer_eaptypediv .= "<li>" . $uiElements->displayName($missing_attrib) . "</li>";
                    }
                    $buffer_eaptypediv .= "</ul>";
                }
                $buffer_eaptypediv .= "</div>";
                $allcomplete = FALSE;
            }
            $attribs = $theProfile->getAttributes();
            $justOnce = FALSE;
            foreach ($attribs as $attrib) {
                if ($attrib['level'] == \core\Options::LEVEL_METHOD && !preg_match("/^internal:/", $attrib['name']) && !$justOnce) {
                    $justOnce = TRUE;
                    $buffer_eaptypediv .= "<img src='../resources/images/icons/Letter-E-blue-icon.png' alt='" . _("Options on EAP Method/Device level are in effect.") . "'>";
                }
            }
            $buffer_eaptypediv .= "<br/>";
        }
        $buffer_eaptypediv .= "</div>";

        $buffer_headline = "<span style='float:right;'>";
        $readiness = $theProfile->readinessLevel();
        if ($has_overrides) {
            $buffer_headline .= $uiElements->boxRemark("", _("Option override on profile level is in effect."), TRUE);
        }
        if (!$allcomplete) {
            $buffer_headline .= $uiElements->boxError("", _("The information in this profile is incomplete."), TRUE);
        }
        switch ($readiness) {
            case core\AbstractProfile::READINESS_LEVEL_SHOWTIME:
                $buffer_headline .= $uiElements->boxOkay("", _("This profile is shown on the user download interface."), TRUE);
                break;
            case core\AbstractProfile::READINESS_LEVEL_SUFFICIENTCONFIG:
                $buffer_headline .= $uiElements->boxWarning("", sprintf(_("This profile is NOT shown on the user download interface, even though we have enough information to show. To enable the profile, add the attribute \"%s\" and tick the corresponding box."), $uiElements->displayName("profile:production")), TRUE);
        }

        $buffer_headline .= "</span>";

        echo $buffer_headline;
        echo $buffer_eaptypediv;

        $has_eaptypes = count($theProfile->getEapMethodsInOrderOfPreference(1));
        $hasRealmArray = $theProfile->getAttributes("internal:realm");
        $has_realm = $hasRealmArray[0]['value'];

        // our own base location, to give to diag URLs
        if (isset($_SERVER['HTTPS'])) {
            $link = 'https://';
        } else {
            $link = 'http://';
        }
        $link .= $_SERVER['SERVER_NAME'];
        ?>
        <div class='profilemodulebuttons' style='float:right;'>
            <?php
            if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] !== NULL) {
                if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] == "LOCAL") {
                    $diagUrl = "../diag/";
                } else {
                    $diagUrl = \config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] . "/diag/";
                }
                ?>
                <form action='<?php echo $diagUrl . "action_realmcheck.php?inst_id=" . $theProfile->institution . "&profile_id=" . $theProfile->identifier ?>' method='post' accept-charset='UTF-8'>
                    <input type='hidden' name='comefrom' value='<?php echo htmlspecialchars($link . $_SERVER['SCRIPT_NAME']); ?>'/>
                    <button type='submit' name='profile_action' value='check' <?php echo ($has_realm ? "" : "disabled='disabled'"); ?> title='<?php echo _("The realm can only be checked if you configure the realm!"); ?>'>
                        <?php echo _("Check realm reachability"); ?>
                    </button>
                </form>
                <?php
            }
            ?>
            <form action='overview_installers.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                <button type='submit' name='profile_action' value='check' <?php echo ($has_eaptypes ? "" : "disabled='disabled'"); ?> title='<?php echo _("You have not fully configured any supported EAP types!"); ?>'>
                    <?php echo _("Installer Fine-Tuning and Download"); ?>
                </button>
            </form>
        </div>
        <div class='buttongroupprofilebox' style='clear:both; display: flex;'>
            <?php if ($readonly === FALSE) { ?>
                <div style='margin-right: 200px; display: ruby'>
                    <form action='edit_profile.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <hr/>
                        <button type='submit' name='profile_action' value='edit'><?php echo _("Edit"); ?></button>
                    </form>
                    <form action='edit_profile_result.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo sprintf(_("Do you really want to delete the profile %s?"), $profile_name); ?>')">
                            <?php echo _("Delete") ?>
                        </button>
                    </form>
                </div>
                <?php
            }
            if ($readiness == core\AbstractProfile::READINESS_LEVEL_SHOWTIME) {
                ?>
                <div style='display: flex;'>
                    <?php
                    $idpLevelUrl = $link . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $theProfile->institution;
                    $displayurl = $idpLevelUrl . "&amp;profile=" . $theProfile->identifier;
                    $QRurl = $idpLevelUrl . "&profile=" . $theProfile->identifier;
                    $qrCode = new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
                                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_H,
                                'scale' => web\lib\admin\UIElements::QRCODE_PIXELS_PER_SYMBOL,
                                'imageBase64' => false,
                    ]));
                    echo "<a href='$displayurl' style='white-space: nowrap; text-align: center;'>";
                    $rawQr = $qrCode->render($QRurl);
                    if (empty($rawQr)) {
                        throw new Exception("Something went seriously wrong during QR code generation!");
                    }
                    $uri = "data:image/png;base64," . base64_encode($uiElements->pngInjectConsortiumLogo($rawQr, web\lib\admin\UIElements::QRCODE_PIXELS_PER_SYMBOL));
                    $size = getimagesize($uri);
                    echo "<img width='" . ($size[0] / 4) . "' height='" . ($size[1] / 4) . "' src='$uri' alt='QR-code'/>";

                    //echo "<nobr>$displayurl</nobr></a>";
                    echo "<p>$displayurl</p></a>";
                    ?>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * displays an infocard about a Managed SP deployment
 * 
 * @param \core\DeploymentManaged $deploymentObject the deployment to work with
 * @throws Exception
 */
function displayDeploymentPropertyWidget(&$deploymentObject) {
    // RADIUS status icons
    $radiusMessages = [
        \core\AbstractDeployment::RADIUS_OK => ['icon' => '../resources/images/icons/Quetto/check-icon.png', 'text' => _("Successfully set profile")],
        \core\AbstractDeployment::RADIUS_FAILURE => ['icon' => '../resources/images/icons/Quetto/no-icon.png', 'text' => _("Some problem occured during profile update")],
    ];

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
    <div style='display: table-row;'>
        <div class='profilebox' style='display: table-cell;'>
            <h2><?php
                switch ($deploymentObject->consortium) {
                    case "eduroam":
                        $displayname = config\ConfAssistant::CONSORTIUM['name'] . " " . core\DeploymentManaged::PRODUCTNAME;
                        break;
                    case "OpenRoaming":
                        $displayname = "OpenRoaming ANP";
                        break;
                    default:
                        throw new Exception("We are supposed to operate on a roaming consortium we don't know.");
                }
                echo $displayname . " (<span style='color:" . ( $deploymentObject->status == \core\AbstractDeployment::INACTIVE ? "red;'>" . _("inactive") : "green;'>" . _("active") ) . "</span>)";
                ?></h2>
            <table>
                <caption><?php echo _("Deployment Details"); ?></caption>
                <tr>
                    <th class='wai-invisible' scope='col'><?php echo("Server IP addresses"); ?></th>
                    <th class='wai-invisible' scope='col'><?php echo("Server Port label"); ?></th>
                    <th class='wai-invisible' scope='col'><?php echo("Server Port value"); ?></th>
                    <th class='wai-invisible' scope='col'><?php echo("Deployment Status"); ?></th>
                </tr>
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
                if ($vlan = $deploymentObject->getAttributes("managedsp:vlan")[0]['value'] ?? NULL) {
                    ?>
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
                <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <br/>
                    <button type='submit' name='profile_action' style='cursor:pointer;' value='edit'><?php echo _("Advanced Configuration"); ?></button>
                </form>
                <?php if ($deploymentObject->status == \core\AbstractDeployment::ACTIVE) { ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
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
                } elseif (count($deploymentObject->getAttributes("hiddenmanagedsp:tou_accepted")) == 0) {
                    ?>
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' style='background-color: yellow; color: black;' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_TERMSOFUSE_NEEDACCEPTANCE; ?>'>
                            <?php echo _("Accept Terms of Use"); ?>
                        </button>
                    </form>
                    <div align="right">
                    <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' style='background-color: yellow; color: black' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_REMOVESP; ?>' onclick="return confirm('<?php printf(_("Do you really want to remove this %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Remove deployment"); ?>
                            </button>
                    </form>
                    </div>
                    <?php
                    } else {
                        ?>
                        <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
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
                        <div align="right">
                        <form action='edit_hotspot.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>' method='post' accept-charset='UTF-8'>
                            <button class='delete' style='background-color: yellow; color: black' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_REMOVESP; ?>' onclick="return confirm('<?php printf(_("Do you really want to remove this %s deployment?"), core\DeploymentManaged::PRODUCTNAME); ?>')">
                                <?php echo _("Remove deployment"); ?>
                            </button>
                        </form>
                        </div>
                        <?php
                    }
                    ?>
            </div>
        </div>
        <div style='width:20px;'></div> <!-- QR code space, reserved -->
        <div style='display: table-cell; min-width:200px;'>
            <?php $tablecaption = _("Hotspot Usage Statistics");?>
            <h1><?php echo $tablecaption; ?></h1>
            <h2><?php echo _("5 most recent authentications");?></h2>
            <table class='authrecord'>
    <caption><?php echo $tablecaption;?></caption>
    <tr style='text-align: left;'>
        <th scope="col"><strong><?php echo _("Timestamp (UTC)");?></strong></th>
        <th scope="col"><strong><?php echo _("Realm");?></strong></th>
        <th scope="col"><strong><?php echo _("MAC Address");?></strong></th>
        <th scope="col"><strong><?php echo _("Chargeable-User-Identity");?></strong></th>
        <th scope="col"><strong><?php echo _("Result");?></strong></th>
    </tr>
    <?php
    $userAuthData = $deploymentObject->retrieveStatistics(0,5);
    foreach ($userAuthData as $oneRecord) {
        echo "<tr class='".($oneRecord['result'] == "OK" ? "auth-success" : "auth-fail" )."'>"
                . "<td>".$oneRecord['activity_time']."</td>"
                . "<td>".$oneRecord['realm']."</td>"
                . "<td>".$oneRecord['mac']."</td>"
                . "<td>".$oneRecord['cui']."</td>"
                . "<td>".($oneRecord['result'] == "OK" ? _("Success") : _("Failure"))."</td>"
                . "</tr>";
    }
    ?>
</table>
            <div style='display: ruby;'><form action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-hour' name='stats' value='HOUR'><?php echo _("Last hour"); ?></button>
            </form>
            <form action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-month' name='stats' value='MONTH'><?php echo _("Last 30 days"); ?></button>
            </form>
            <form action="inc/deploymentStats.inc.php?inst_id=<?php echo $deploymentObject->institution; ?>&amp;deployment_id=<?php echo $deploymentObject->identifier; ?>" onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8' method='post'>
                <button type='submit' id='stats-full' name='stats' value='FULL'><?php echo _("Last 6 months"); ?></button>
            </form>
            </div>
        </div><!-- statistics space -->
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

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();

echo $deco->defaultPagePrelude(sprintf(_("%s: %s Dashboard"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant));
require_once "inc/click_button_js.php";

// let's check if the inst handle actually exists in the DB
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
$myfed = new \core\Federation($my_inst->federation);

// delete stored realm

if (isset($_SESSION['check_realm'])) {
    unset($_SESSION['check_realm']);
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, TRUE);
echo $mapCode->htmlHeadCode();
?>
<script src="js/XHR.js" type="text/javascript"></script>    
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    echo $deco->productheader("ADMIN-PARTICIPANT");

// Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureParticipant); ?></h1>
    <hr/>
    <div>
        <h2 style='display: flex;'><?php echo sprintf(_("%s general settings"), $uiElements->nomenclatureParticipant); ?>&nbsp;
            <form action='edit_participant.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <button type='submit' name='submitbutton' value='<?php echo \web\lib\common\FormElements::BUTTON_EDIT; ?>'><?php echo _("Edit ..."); ?></button>
            </form>
        </h2>
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
    <hr/>
    <?php
    $readonly = \config\Master::DB['INST']['readonly'];
    if (preg_match("/IdP/", $my_inst->type)) {
        ?>
        <h2 style='display: flex;'><?php printf(_("%s: %s Deployment Details"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureIdP); ?>&nbsp;
            <?php
            $profiles_for_this_idp = $my_inst->listProfiles();
            if ($readonly === FALSE) {

                // the opportunity to add a new silverbullet profile is only shown if
                // a) there is no SB profile yet
                // b) federation wants this to happen
                // first find out if we already have SB profiles
                $sbProfileExists = FALSE;
                foreach ($profiles_for_this_idp as $profilecount => $profile_list) {
                    switch (get_class($profile_list)) {
                        case "core\ProfileSilverbullet":
                            $sbProfileExists = TRUE;
                            break;
                        default:
                    }
                }

                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0 && $sbProfileExists === FALSE) {
                    // the button is grayed out if there's no support email address configured...
                    $hasMail = count($my_inst->getAttributes("support:email"));
                    ?>
                    <form action='edit_silverbullet.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <div>
                            <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                                <?php echo sprintf(_("Add %s profile ..."), \core\ProfileSilverbullet::PRODUCTNAME); ?>
                            </button>
                        </div>
                    </form>&nbsp;
                    <?php
                }
                ?>

                <?php
                // adding a normal profile is always possible if we're configured for it
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == "LOCAL") {
                    ?>
                    <form action='edit_profile.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <div>
                            <button type='submit' name='profile_action' value='new'>
                                <?php echo _("New RADIUS/EAP profile (manual setup) ..."); ?>
                            </button>
                        </div>
                    </form>&nbsp;
                    <form method='post' action='inc/profileAutodetectCA.inc.php?inst_id=<?php echo $my_inst->identifier; ?>' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                        <div>
                            <button type='submit' name='profile_action' value='new'>
                                <?php echo _("New RADIUS/EAP profile (autodetect server details) ..."); ?>
                            </button>
                        </div>
                    </form>
                    <?php
                }
            }
            ?>
        </h2>
        <?php
        if (count($profiles_for_this_idp) == 0) { // no profiles yet.
            printf(_("There are not yet any profiles for your %s."), $uiElements->nomenclatureIdP);
        }
// if there is one profile and it is of type Silver Bullet, display a very
// simple widget with just a "Manage" button
        foreach ($profiles_for_this_idp as $profilecount => $profile_list) {
            ?>
            <div style='display: table-row; margin-bottom: 20px;'>
                <div class='profilebox' style='display: table-cell; min-width: 650px;'>
                    <?php
                    switch (get_class($profile_list)) {
                        case "core\ProfileSilverbullet":
                            displaySilverbulletPropertyWidget($profile_list, $readonly, $uiElements);
                            break;
                        case "core\ProfileRADIUS":
                            displayRadiusPropertyWidget($profile_list, $readonly, $uiElements);
                            break;
                        default:
                            throw new Exception("We were asked to operate on something that is neither a RADIUS nor Silverbullet profile!");
                    }
                    ?>
                </div>
                <!-- dummy width to keep a little distance -->
                <div style='width:20px;'></div>
                <div style='display: table-cell; min-width:200px;'>
                    <p>
                        <strong><?php echo _("User Downloads"); ?></strong>
                    </p>
                    <table>
                        <?php
                        $stats = $profile_list->getUserDownloadStats();
                        foreach ($stats as $dev => $count) {
                            echo "<tr><td><strong>$dev</strong></td><td>$count</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
            <!-- dummy div to keep a little distance-->
            <div style='height:20px'></div>
            <?php
        }
        ?>
        <hr/>
        <?php
    }
    if (preg_match("/SP/", $my_inst->type)) {
        ?>
        <h2 style='display: flex;'><?php printf(_("%s: %s Deployment Details"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureHotspot); ?>&nbsp;
            <?php
            if ($readonly === FALSE) {
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0) {
                    // the button is grayed out if there's no support email address configured...
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
                    <?php if (count($myfed->getAttributes("fed:openroaming")) > 0) {
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
                    displayDeploymentPropertyWidget($deploymentObject);
                    break;
                case "core\DeploymentClassic":
                    displayClassicHotspotPropertyWidget($deploymentObject);
                    break;
                default:
                    throw new Exception("We were asked to operate on something that is neither a classic nor a Managed hotspot deployment!");
            }
        }
    }
    echo $deco->footer();
    