<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/core/phpqrcode.php");
require_once("inc/common.inc.php");

$uiElements = new web\lib\admin\UIElements();

function png_inject_consortium_logo($inputpngstring, $symbolsize = 12, $marginsymbols = 4) {
    $loggerInstance = new \core\Logging();
    $inputgd = imagecreatefromstring($inputpngstring);

    $loggerInstance->debug(4, "Consortium logo is at: " . ROOT . "/web/resources/images/consortium_logo_large.png");
    $logogd = imagecreatefrompng(ROOT . "/web/resources/images/consortium_logo_large.png");

    $sizeinput = [imagesx($inputgd), imagesy($inputgd)];
    $sizelogo = [imagesx($logogd), imagesy($logogd)];
    // Q level QR-codes can sustain 25% "damage"
    // make our logo cover approx 15% of area to be sure; mind that there's a $symbolsize * $marginsymbols pixel white border around each edge
    $totalpixels = ($sizeinput[0] - $symbolsize * $marginsymbols) * ($sizeinput[1] - $symbolsize * $marginsymbols);
    $totallogopixels = ($sizelogo[0]) * ($sizelogo[1]);
    $maxoccupy = $totalpixels * 0.04;
    // find out how much we have to scale down logo to reach 10% QR estate
    $scale = sqrt($maxoccupy / $totallogopixels);
    $loggerInstance->debug(4, "Scaling info: $scale, $maxoccupy, $totallogopixels\n");
    // determine final pixel size - round to multitude of $symbolsize to match exact symbol boundary
    $targetwidth = $symbolsize * round($sizelogo[0] * $scale / $symbolsize);
    $targetheight = $symbolsize * round($sizelogo[1] * $scale / $symbolsize);
    // paint white below the logo, in case it has transparencies (looks bad)
    // have one symbol in each direction extra white space
    $whiteimage = imagecreate($targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
    imagecolorallocate($whiteimage, 255, 255, 255);
    // also make sure the initial placement is a multitude of 12; otherwise "two half" symbols might be affected
    $targetplacementx = $symbolsize * round(($sizeinput[0] / 2 - ($targetwidth - $symbolsize) / 2) / $symbolsize);
    $targetplacementy = $symbolsize * round(($sizeinput[1] / 2 - ($targetheight - $symbolsize) / 2) / $symbolsize);
    imagecopyresized($inputgd, $whiteimage, $targetplacementx - $symbolsize, $targetplacementy - $symbolsize, 0, 0, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
    imagecopyresized($inputgd, $logogd, $targetplacementx, $targetplacementy, 0, 0, $targetwidth, $targetheight, $sizelogo[0], $sizelogo[1]);
    ob_start();
    imagepng($inputgd);
    return ob_get_clean();
}

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();

echo $deco->defaultPagePrelude(sprintf(_("%s: IdP Dashboard"), CONFIG['APPEARANCE']['productname']));

// let's check if the inst handle actually exists in the DB
$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);

// delete stored realm

if (isset($_SESSION['check_realm'])) {
    unset($_SESSION['check_realm']);
}
$widget = new \web\lib\admin\GeoWidget();

echo $widget->insertInHead($my_inst->federation, $my_inst->name);
?>
</head>
<body  onload='load(0)'>
    <?php
    echo $deco->productheader("ADMIN-IDP");

    // Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo _("Identity Provider Overview"); ?></h1>
    <div>
        <h2><?php echo _("IdP-wide settings"); ?></h2>
        <?php
        echo $uiElements->instLevelInfoBoxes($my_inst);
        ?>
        <div class='infobox' style='text-align:center;'>
            <h2><?php echo _("Institution Download Area QR Code"); ?></h2>
            <?php
            $displayurl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $my_inst->identifier;
            $uri = "data:image/png;base64," . base64_encode(png_inject_consortium_logo(QRcode::png($displayurl, FALSE, QR_ECLEVEL_Q, 12)));
            $size = getimagesize($uri);
            echo "<img width='" . ($size[0] / 4) . "' height='" . ($size[1] / 4) . "' src='$uri' alt='QR-code'/>";
            ?>
            <br>
            <?php echo "<a href='$displayurl'>$displayurl</a>"; ?>
        </div>
        <?php
        $loadmap = FALSE;
        foreach ($idpoptions as $optionname => $optionvalue) {
            if ($optionvalue['name'] == "general:geo_coordinates") {
                $loadmap = TRUE;
            }
        }
        if ($loadmap === TRUE) {
            echo '
<div class="infobox"  style="width:270px;">
<div id="map" style="width:100%; height:150px"></div>
</div>
';
        }
        ?>
    </div>
    <table>
        <tr>
            <td>
                <form action='edit_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <button type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_EDIT; ?>'><?php echo _("Edit IdP-wide settings"); ?></button>
                </form>
            </td>
            <td>
                <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo ( CONFIG['CONSORTIUM']['selfservice_registration'] === NULL ? _("After deleting the IdP, you can not recreate it yourself - you need a new invitation token from the federation administrator!") . " " : "" ) . sprintf(_("Do you really want to delete your IdP %s?"), $my_inst->name); ?>')"><?php echo _("Delete IdP"); ?></button>
                </form>

            </td>
            <td>
                <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_FLUSH_AND_RESTART; ?>' onclick="return confirm('<?php echo sprintf(_("This action will delete all properties of your IdP and start over the configuration from scratch. Do you really want to reset all settings of your IdP %s?"), $my_inst->name); ?>')"><?php echo _("Reset all IdP settings"); ?></button>
                </form>

            </td>
        </tr>
    </table>
    <hr/>
    <h2><?php echo _("Available Support actions"); ?></h2>
    <table>
        <?php
        if (count(CONFIG['RADIUSTESTS']['UDP-hosts']) > 0 || CONFIG['RADIUSTESTS']['TLS-discoverytag'] != "") {
            echo "<tr>
                        <td>" . _("Check another realm's reachability") . "</td>
                        <td><form method='post' action='action_realmcheck.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'>
                              <input type='text' name='realm' id='realm'>
                              <button type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>";
        }
        if (CONFIG['CONSORTIUM']['name'] == "eduroam") { // SW: APPROVED
            echo "<tr>
                        <td>" . _("Check server status of European federations") . "</td>
                        <td>
                           <form action='https://monitor.eduroam.org' accept-charset='UTF-8'>
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
        echo "<h2>" . _("There are not yet any profiles for your institution.") . "</h2>";
    }

    // if there is one profile and it is of type Silver Bullet, display a very
    // simple widget with just a "Manage" button

    if (count($profiles_for_this_idp) == 1) {
        $profile = $profiles_for_this_idp[0];
        if ($profile instanceof \core\ProfileSilverbullet) {
            ?>
            <div style='display: table-row; margin-bottom: 20px;'>
                <div class='profilebox' style='display: table-cell;'>
                    <h2><?php echo $profile->name; ?></h2>
                    <?php
                    $maxusers = $profile->getAttributes("internal:silverbullet_maxusers");
                    printf(_("You can create up to %d users. Their credentials will carry the name <strong>%s</strong>."), $maxusers[0]['value'], $profile->realm);
                    ?>
                    <br/>
                    <br/>
                    <form action='edit_silverbullet.php?inst_id=<?php echo $my_inst->identifier; ?>&profile_id=<?php echo $profile->identifier; ?>' method='POST'>
                        <button type='submit' name='sb_action' value='sb_edit'><?php echo _("Manage User Base"); ?></button>
                    </form>
                </div>

                <div style='width:20px;'></div>
                <div style='display: table-cell; min-width:200px;'><p><strong><?php echo _("User Downloads"); ?></strong></p><table>
                            <?php
                            $stats = $profile->getUserDownloadStats();
                            foreach ($stats as $dev => $count) {
                                echo "<tr><td><strong>$dev</strong></td><td>$count</td></tr>";
                            }
                            ?>
                    </table></div>
            </div>
            <?php
            // unset variable so that no other profiles are displayed, if any
            // (it is an error if other profiles besides SB exist; don't show
            // them in the UI if for some reason this happened
            $profiles_for_this_idp = [];
        }
    }
    if (count($profiles_for_this_idp) > 0) { // no profiles yet.
        echo "<h2>" . _("Profiles for this institution") . "</h2>";
    }
    foreach ($profiles_for_this_idp as $profile_list) {
        echo "<div style='display: table-row; margin-bottom: 20px;'>";
        $profile_name = $profile_list->name;
        // see if there are any profile-level overrides
        $attribs = $profile_list->getAttributes();

        echo "<div class='profilebox' style='display: table-cell;'>";

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
            $buffer_eaptypediv .= $uiElements->displayName($eaptype);
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
        $buffer_headline = "<h2 style='overflow:auto;'>";

        $buffer_headline .= "<div style='float:right;'>";
        $sufficient_config = $profile_list->hasSufficientConfig();
        $showtime = $profile_list->isShowtime();
        if ($has_overrides) {
            $buffer_headline .= $uiElements->boxRemark("", _("Option override on profile level is in effect."), TRUE);
        }
        if (!$allcomplete) {
            $buffer_headline .= $uiElements->boxError("", _("The information in this profile is incomplete."), TRUE);
        }
        if ($showtime) {
            $buffer_headline .= $uiElements->boxOkay("", _("This profile is shown on the user download interface."), TRUE);
        } else if ($sufficient_config) {
            $buffer_headline .= $uiElements->boxWarning("", sprintf(_("This profile is NOT shown on the user download interface, even though we have enough information to show. To enable the profile, add the attribute \"%s\" and tick the corresponding box."), $uiElements->displayName("profile:production")), TRUE);
        }
        $buffer_headline .= "</div>";

        $buffer_headline .= sprintf(_("Profile: %s"), $profile_name) . "</h2>";

        echo $buffer_headline;

        if (array_search(\core\EAP::EAPTYPE_TTLS_PAP, $typelist) !== FALSE && array_search(\core\EAP::EAPTYPE_TTLS_GTC, $typelist) === FALSE && array_search(\core\EAP::EAPTYPE_PEAP_MSCHAP2, $typelist) === FALSE && array_search(\core\EAP::EAPTYPE_TTLS_MSCHAP2, $typelist) === FALSE) {
            /// Hmmm... IdP Supports TTLS-PAP, but not TTLS-GTC nor anything based on MSCHAPv2. That locks out Symbian users; and is easy to circumvent. Tell the admin...
            $buffer_eaptypediv .= "<p>" . sprintf(_("Read this <a href='%s'>tip</a>."), "https://wiki.geant.org/display/H2eduroam/eap-types#eap-types-choices") . "</p>";
        }

        $buffer_eaptypediv .= "</div>";
        echo $buffer_eaptypediv;
        $has_eaptypes = count($profile_list->getEapMethodsInOrderOfPreference(1));
        $hasRealmArray = $profile_list->getAttributes("internal:realm");
        $has_realm = $hasRealmArray[0]['value'];
        echo "<div class='profilemodulebuttons' style='float:right;'>";
        if (count(CONFIG['RADIUSTESTS']['UDP-hosts']) > 0 || ( count(CONFIG['RADIUSTESTS']['TLS-clientcerts']) > 0 && CONFIG['RADIUSTESTS']['TLS-discoverytag'] != "")) {
            echo "<form action='action_realmcheck.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
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

        echo "        <div class='buttongroupprofilebox' style='clear:both;'>
                          <form action='edit_profile.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                               <hr/>
                               <button type='submit' name='profile_action' value='edit'>" . _("Edit") . "</button>
                          </form>
                          <form action='edit_profile_result.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post' accept-charset='UTF-8'>
                               <button class='delete' type='submit' name='submitbutton' value='" . web\lib\admin\FormElements::BUTTON_DELETE . "' onclick=\"return confirm('" . sprintf(_("Do you really want to delete the profile %s?"), $profile_name) . "')\">
                                   " . _("Delete") . "
                               </button>
                           </form>
                      </div>";

        echo "</div>";
// dummy width to keep a little distance
        echo "<div style='width:20px;'></div>";
        if ($profile_list->isShowtime()) {
            echo "<div style='display: table-cell; text-align:center;'><p><strong>" . _("User Download Link") . "</strong></p>";
            $URL = $profile_list->getCollapsedAttributes();
            if (isset($URL['device-specific:redirect'])) {
                $displayurl = $URL['device-specific:redirect'][0];
            } else {
                $displayurl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $my_inst->identifier . "&amp;profile=" . $profile_list->identifier;
            }
            echo "<a href='$displayurl' style='white-space: nowrap; text-align: center;'>";
            $uri = "data:image/png;base64," . base64_encode(png_inject_consortium_logo(QRcode::png($displayurl, FALSE, QR_ECLEVEL_Q, 12)));
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
        // dummy div to keep a little distance
        echo "<div style='height:20px'></div>";
    }

    // the opportunity to add a new silverbullet profile is only shown if
    // a) there are not any profiles yet
    // b) federation wants this to happen
    if (count($my_inst->listProfiles()) == 0) {
        $myfed = new \core\Federation($my_inst->federation);
        if (count($myfed->getAttributes("fed:silverbullet")) > 0) {
            ?>
            <form action='edit_silverbullet.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <div>
                    <button type='submit' name='profile_action' value='new'>
                        <?php echo sprintf(_("Add %s profile ..."), \core\ProfileSilverbullet::PRODUCTNAME); ?>
                    </button>
                </div>
            </form>
            <?php
        }
    }

    // adding a normal profile is only possible if silverbullet is not in use
    // i.e. either there are no profiles or all profiles are non-silverbullet
    // this is checked by looking whether the "special" EAP method is in the
    // preference list
    $found_silverbullet = FALSE;
    foreach ($my_inst->listProfiles() as $one_profile) {
        $methods = $one_profile->getEapMethodsinOrderOfPreference();
        // silver bullet is an exclusive method; looking in the first entry of
        // the array will catch it.
        if (count($methods) > 0 && $methods[0] == \core\EAP::EAPTYPE_SILVERBULLET) {
            $found_silverbullet = TRUE;
        }
    }
    if ($found_silverbullet === FALSE) {
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
    echo $deco->footer();
    