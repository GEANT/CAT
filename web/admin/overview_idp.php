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

echo $deco->defaultPagePrelude(sprintf(_("%s: IdP Dashboard"), CONFIG['APPEARANCE']['productname']));
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
    echo $deco->productheader("ADMIN-IDP");

    // Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureInst); ?></h1>
    <div>
        <h2><?php echo sprintf(_("%s general settings"), $uiElements->nomenclatureInst); ?></h2>
        <?php
        echo $uiElements->instLevelInfoBoxes($my_inst);
        ?>
        <div class='infobox' style='text-align:center;'>
            <h2><?php echo sprintf(_("%s download area QR code"), $uiElements->nomenclatureInst); ?></h2>
            <?php
            $idpLevelUrl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $my_inst->identifier;
            // never NULL, because $filename is FALSE; but make Scrutinizer happy
            $rawQr = QRcode::png($idpLevelUrl, FALSE, QR_ECLEVEL_Q, QRCODE_PIXELS_PER_SYMBOL);
            if ($rawQr === NULL) {
                throw new Exception("Something went seriously wrong during QR code generation!");
            }
            $uri = "data:image/png;base64," . base64_encode($uiElements->pngInjectConsortiumLogo($rawQr, QRCODE_PIXELS_PER_SYMBOL));
            $size = getimagesize($uri);
            echo "<img width='" . ($size[0] / 4) . "' height='" . ($size[1] / 4) . "' src='$uri' alt='QR-code'/>";
            ?>
            <br>
            <?php echo "<a href='$idpLevelUrl'>$idpLevelUrl</a>"; ?>
        </div>
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
    if ($readonly === FALSE) {
        ?>
        <table>
            <tr>
                <td>
                    <form action='edit_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_EDIT; ?>'><?php echo sprintf(_("Edit general %s details"), $uiElements->nomenclatureInst); ?></button>
                    </form>
                </td>
                <td>
                    <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo ( CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'] === NULL ? sprintf(_("After deleting the %s, you can not recreate it yourself - you need a new invitation token from the %s administrator!"), $uiElements->nomenclatureInst, $uiElements->nomenclatureFed) . " " : "" ) . sprintf(_("Do you really want to delete your %s %s?"), $uiElements->nomenclatureInst, $my_inst->name); ?>')"><?php echo sprintf(_("Delete %s"), $uiElements->nomenclatureInst); ?></button>
                    </form>

                </td>
                <td>
                    <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_FLUSH_AND_RESTART; ?>' onclick="return confirm('<?php echo sprintf(_("This action will delete all properties of the %s and start over the configuration from scratch. Do you really want to reset all settings of the %s %s?"), $uiElements->nomenclatureInst, $uiElements->nomenclatureInst, $my_inst->name); ?>')"><?php echo sprintf(_("Reset all %s settings"), $uiElements->nomenclatureInst); ?></button>
                    </form>

                </td>
            </tr>
        </table>
        <hr/>
        <?php
    }
    ?>
    <h2><?php echo _("Available Support actions"); ?></h2>
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
    $profiles_for_this_idp = $my_inst->listProfiles();
    if (count($profiles_for_this_idp) == 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("There are not yet any profiles for your %s."), $uiElements->nomenclatureInst) . "</h2>";
    }
    if (count($profiles_for_this_idp) > 0) { // no profiles yet.
        echo "<h2>" . sprintf(_("Profiles for this %s"), $uiElements->nomenclatureInst) . "</h2>";
    }
// if there is one profile and it is of type Silver Bullet, display a very
// simple widget with just a "Manage" button
    $sbProfileExists = FALSE;

    foreach ($profiles_for_this_idp as $profilecount => $profile_list) {
        ?>
        <div style='display: table-row; margin-bottom: 20px;'>
            <div class='profilebox' style='display: table-cell;'>
                <?php
                switch (get_class($profile_list)) {
                    case "core\ProfileSilverbullet":
                        $sbProfileExists = TRUE;
                        ?>

                        <h2><?php echo $profile_list->name; ?></h2>
                        <?php
                        $maxusers = $profile_list->getAttributes("internal:silverbullet_maxusers");
                        $completeness = $profile_list->isEapTypeDefinitionComplete(new core\common\EAP(core\common\EAP::INTEGER_SILVERBULLET));
                        // do we have all info needed for showtime? particularly: support email
                        if (is_array($completeness)) {
                            echo "<div class='notacceptable'>";
                            echo _("Information needed!");
                            echo "<ul style='margin:1px'>";
                            foreach ($completeness as $missing_attrib) {
                                echo "<li>" . $uiElements->displayName($missing_attrib) . "</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                        } else {
                            printf(_("You can create up to %d users. Their credentials will carry the name <strong>%s</strong>."), $maxusers[0]['value'], $profile_list->realm);
                        }
                        ?>
                        <br/>
                        <br/>
                        <?php
                        if ($readonly === FALSE) {
                            ?>
                            <form action='edit_silverbullet.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;profile_id=<?php echo $profile_list->identifier; ?>' method='POST'>
                                <button <?php echo ( is_array($completeness) ? "disabled" : "" ); ?> type='submit' name='sb_action' value='sb_edit'><?php echo _("Manage User Base"); ?></button>
                            </form>
                            <?php
                        }
                        ?>
                    </div>

                    <div style='width:20px;'></div>
                    <div style='display: table-cell; min-width:200px;'><p><strong><?php echo _("User Downloads"); ?></strong></p><table>
                                <?php
                                $stats = $profile_list->getUserDownloadStats();
                                foreach ($stats as $dev => $count) {
                                    echo "<tr><td><strong>$dev</strong></td><td>$count</td></tr>";
                                }
                                ?>
                        </table>
                    </div>
                </div>
                <?php
                break;
            case "core\ProfileRADIUS":
                $profile_name = $profile_list->name;
                // see if there are any profile-level overrides
                $attribs = $profile_list->getAttributes();
                // write things into a buffer; we need some function calls to determine
                // readiness - but want to display it before!
                $has_overrides = FALSE;
                foreach ($attribs as $attrib) {
                    if ($attrib['level'] == "Profile" && !preg_match("/^(internal:|profile:name|profile:description|eap:)/", $attrib['name'])) {
                        $has_overrides = TRUE;
                    }
                }
                $buffer_eaptypediv = "<div style='margin-bottom:40px; float:left;'>" . _("<strong>EAP Types</strong> (in order of preference):") . "<br/>";
                $typelist = $profile_list->getEapMethodsinOrderOfPreference();
                $allcomplete = TRUE;
                foreach ($typelist as $eaptype) {
                    $buffer_eaptypediv .= $eaptype->getPrintableRep();
                    $completeness = $profile_list->isEapTypeDefinitionComplete($eaptype);
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
                    $attribs = $profile_list->getAttributes();
                    $justOnce = FALSE;
                    foreach ($attribs as $attrib) {
                        if ($attrib['level'] == "Method" && !preg_match("/^internal:/", $attrib['name']) && !$justOnce) {
                            $justOnce = TRUE;
                            $buffer_eaptypediv .= "<img src='../resources/images/icons/Letter-E-blue-icon.png' alt='" . _("Options on EAP Method/Device level are in effect.") . "'>";
                        }
                    }
                    $buffer_eaptypediv .= "<br/>";
                }
                $buffer_headline = "<div style='padding-bottom:20px;'><h2 style='overflow:auto; display:inline; padding-bottom: 10px;'>" . sprintf(_("Profile: %s"), $profile_name) . "</h2>";

                $buffer_headline .= "<span style='float:right;'>";
                $readiness = $profile_list->readinessLevel();
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

                $buffer_headline .= "</span></div>";

                echo $buffer_headline;

                $buffer_eaptypediv .= "</div>";
                echo $buffer_eaptypediv;
                $has_eaptypes = count($profile_list->getEapMethodsInOrderOfPreference(1));
                $hasRealmArray = $profile_list->getAttributes("internal:realm");
                $has_realm = $hasRealmArray[0]['value'];
                echo "<div class='profilemodulebuttons' style='float:right;'>";
                if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] !== NULL) {
                    if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] == "LOCAL") {
                        $diagUrl = "../diag/";
                    } else {
                        $diagUrl = CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] . "/diag/";
                    }
                    echo "<form action='" . $diagUrl . "action_realmcheck.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                              <input type='hidden' name='comefrom' id='comefrom-$profilecount' value='$link'/>
                              <button type='submit' name='profile_action' value='check' " . ($has_realm ? "" : "disabled='disabled' title='" . _("The realm can only be checked if you configure the realm!") . "'") . ">
                                  " . _("Check realm reachability") . "
                              </button>
                          </form>";
                }
                echo "<form action='overview_installers.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                              <button type='submit' name='profile_action' value='check' " . ($has_eaptypes ? "" : "disabled='disabled'  title='" . _("You have not fully configured any supported EAP types!") . "'") . ">
                                  " . _("Installer Fine-Tuning and Download") . "
                              </button>
                 </form>
                   </div>";

                if ($readonly === FALSE) {
                    echo "        <div class='buttongroupprofilebox' style='clear:both;'>
                          <form action='edit_profile.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                               <hr/>
                               <button type='submit' name='profile_action' value='edit'>" . _("Edit") . "</button>
                          </form>
                          <form action='edit_profile_result.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                               <button class='delete' type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_DELETE . "' onclick=\"return confirm('" . sprintf(_("Do you really want to delete the profile %s?"), $profile_name) . "')\">
                                   " . _("Delete") . "
                               </button>
                           </form>
                      </div>";
                }
                echo "</div>";
// dummy width to keep a little distance
                echo "<div style='width:20px;'></div>";
                if ($readiness == core\AbstractProfile::READINESS_LEVEL_SHOWTIME) {
                    echo "<div style='display: table-cell; text-align:center;'><p><strong>" . _("User Download Link") . "</strong></p>";
                    $displayurl = $idpLevelUrl . "&amp;profile=" . $profile_list->identifier;
                    $QRurl = $idpLevelUrl . "&profile=" . $profile_list->identifier;
                    echo "<a href='$displayurl' style='white-space: nowrap; text-align: center;'>";
                    $rawQr = QRcode::png($QRurl, FALSE, QR_ECLEVEL_Q, QRCODE_PIXELS_PER_SYMBOL);
                    if ($rawQr === NULL) {
                        throw new Exception("Something went seriously wrong during QR code generation!");
                    }
                    $uri = "data:image/png;base64," . base64_encode($uiElements->pngInjectConsortiumLogo($rawQr, QRCODE_PIXELS_PER_SYMBOL));
                    $size = getimagesize($uri);
                    echo "<img width='" . ($size[0] / 4) . "' height='" . ($size[1] / 4) . "' src='$uri' alt='QR-code'/>";

                    //echo "<nobr>$displayurl</nobr></a>";
                    echo "<p>$displayurl</p></a>";
                    echo "</div>";
                    // dummy width to keep a little distance
                    echo "<div style='width:20px;'></div>";
                    echo "<div style='display: table-cell; min-width:200px;'><p><strong>" . _("User Downloads") . "</strong></p><table>";
                    $stats = $profile_list->getUserDownloadStats();
                    foreach ($stats as $dev => $count) {
                        echo "<tr><td><strong>$dev</strong></td><td>$count</td></tr>";
                    }
                    echo "</table></div>";
                }
                echo "</div>";
                break;
            default:
                throw new Exception("We were asked to operate on something that is neither a RADIUS nor Silverbullet profile!");
        }
        // dummy div to keep a little distance
        echo "<div style='height:20px'></div>";
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
            <form action='edit_silverbullet.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <div>
                    <button type='submit' <?php echo ($hasMail > 0 ? "" : "disabled"); ?> name='profile_action' value='new'>
                        <?php echo sprintf(_("Add %s profile ..."), \core\ProfileSilverbullet::PRODUCTNAME); ?>
                    </button>
                </div>
            </form>
            <?php
        }

        // adding a normal profile is always possible if we're configured for it
        if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT_RADIUS'] == "LOCAL") {
            ?>
            <form action='edit_profile.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <div>
                    <button type='submit' name='profile_action' value='new'>
                        <?php echo _("Add new RADIUS/EAP profile ..."); ?>
                    </button>
                </div>
            </form>
            <?php
        }
    }
    echo $deco->footer();
    