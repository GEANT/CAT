<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This page displays the dashboard overview of an entire IdP.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/core/phpqrcode.php");

$uiElements = new web\lib\admin\UIElements();

/**
 * Injects the consortium logo in the middle of a given PNG.
 * 
 * Usually used on QR code PNGs - the parameters inform about the structure of
 * the QR code so that the logo does not prevent parsing of the QR code.
 * 
 * @param string $inputpngstring the PNG to edit
 * @param int $symbolsize size in pixels of one QR "pixel"
 * @param int $marginsymbols size in pixels of border around the actual QR
 * @return string the image with logo centered in the middle
 */
function png_inject_consortium_logo(string $inputpngstring, int $symbolsize = 12, int $marginsymbols = 4) {
    $loggerInstance = new \core\common\Logging();
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
    <h1><?php echo sprintf(_("Overview of %s"), $uiElements->nomenclature_inst); ?></h1>
    <div>
        <h2><?php echo sprintf(_("%s-wide settings"), $uiElements->nomenclature_inst); ?></h2>
        <?php
        echo $uiElements->instLevelInfoBoxes($my_inst);
        ?>
        <div class='infobox' style='text-align:center;'>
            <h2><?php echo sprintf(_("QR Code for %s download area"), $uiElements->nomenclature_inst); ?></h2>
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
                    <button type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_EDIT; ?>'><?php echo sprintf(_("Edit general %s details"), $uiElements->nomenclature_inst); ?></button>
                </form>
            </td>
            <td>
                <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo ( CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'] === NULL ? sprintf(_("After deleting the IdP, you can not recreate it yourself - you need a new invitation token from the %s administrator!"), $uiElements->nomenclature_fed) . " " : "" ) . sprintf(_("Do you really want to delete your %s %s?"), $uiElements->nomenclature_inst, $my_inst->name); ?>')"><?php echo sprintf(_("Delete %s"), $uiElements->nomenclature_inst); ?></button>
                </form>

            </td>
            <td>
                <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                    <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\admin\FormElements::BUTTON_FLUSH_AND_RESTART; ?>' onclick="return confirm('<?php echo sprintf(_("This action will delete all properties of your %s and start over the configuration from scratch. Do you really want to reset all settings of your %s %s?"), $uiElements->nomenclature_inst, $uiElements->nomenclature_inst, $my_inst->name); ?>')"><?php echo sprintf(_("Reset all %s settings"), $uiElements->nomenclature_inst); ?></button>
                </form>

            </td>
        </tr>
    </table>
    <hr/>
    <h2><?php echo _("Available Support actions"); ?></h2>
    <table>
        <?php
        if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] !== NULL) {
            echo "<tr>
                        <td>" . _("Check another realm's reachability") . "</td>
                        <td><form method='post' action='../diag/action_realmcheck.php?inst_id=$my_inst->identifier' accept-charset='UTF-8'>
                              <input type='text' name='realm' id='realm'>
                              <button type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>";
        }
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam") { // SW: APPROVED
            echo "<tr>
                        <td>" . sprintf(_("Check server status of European %ss"), $uiElements->nomenclature_fed) . "</td>
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
        echo "<h2>" . sprintf(_("There are not yet any profiles for your %s."), $uiElements->nomenclature_inst) . "</h2>";
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
        echo "<h2>" . sprintf(_("Profiles for this %s"), $uiElements->nomenclature_inst) . "</h2>";
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
        $buffer_headline = "<h2 style='overflow:auto;'>";

        $buffer_headline .= "<div style='float:right;'>";
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

        $buffer_headline .= "</div>";

        $buffer_headline .= sprintf(_("Profile: %s"), $profile_name) . "</h2>";

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
        if ($readiness == core\AbstractProfile::READINESS_LEVEL_SHOWTIME) {
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
        if (count($methods) > 0 && $methods[0] == \core\common\EAP::EAPTYPE_SILVERBULLET) {
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
    