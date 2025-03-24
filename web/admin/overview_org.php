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
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
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
 * @param string                    $editMode 'fullaccess', 'readonly'
 * @throws Exception
 */
function displayRadiusPropertyWidget(&$theProfile, $readonly, &$uiElements, $editMode) {
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
                    $buffer_eaptypediv .= "<img src='../resources/images/icons/Tabler/square-rounded-letter-e-blue.svg' alt='" . _("Options on EAP Method/Device level are in effect.") . "'>";
                }
            }
            $buffer_eaptypediv .= "<br/>";
        }
        $buffer_eaptypediv .= "</div>";

        $buffer_headline = "<div style='float:right;padding-left:10px'>";
        $readiness = $theProfile->readinessLevel();
        if ($has_overrides) {
            $buffer_headline .= $uiElements->boxRemark("", _("Option override on profile level is in effect."), TRUE);
        }
        $buffer_headline .= "<br/>";
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
        if ($theProfile->isRedirected()) {
            $iconData = $uiElements->iconData('PROFILES_REDIRECTED');
            $iconData['text'] = _("Profile redirected");
            $buffer_headline .= "<br/>" . $uiElements->catIcon(($iconData));
            
        } 
        
        $certStatus = $theProfile->certificateStatus();
        switch ($certStatus) {
            case core\AbstractProfile::CERT_STATUS_OK:
                $iconData = $uiElements->iconData('CERT_STATUS_OK');
                $buffer_headline .= "<br/>" . $uiElements->catIcon(($iconData));
                break;
            case core\AbstractProfile::CERT_STATUS_WARN:
                $iconData = $uiElements->iconData('CERT_STATUS_WARN');
                $buffer_headline .= "<br/>" . $uiElements->catIcon(($iconData));                
                break;
            case core\AbstractProfile::CERT_STATUS_ERROR:
                $iconData = $uiElements->iconData('CERT_STATUS_ERROR');
                $buffer_headline .= "<br/>" . $uiElements->catIcon(($iconData));
                break;            
        }
        $buffer_headline .= "</div>";

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
            <?php 
                if ($editMode == 'readonly') {
                    $editLabel = _("View");
                }
                if ($editMode == 'fullaccess') {
                    $editLabel = _("Edit");
                }
            if ($readonly === FALSE) { ?>
                <div style='margin-right: 200px; display: ruby'>
                    <form action='edit_profile.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <hr/>
                        <button type='submit' name='profile_action' value='edit'><?php echo $editLabel; ?></button>
                    </form>
                    <?php if ($editMode == 'fullaccess') { ?>
                    <form action='edit_profile_result.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button class='delete' type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo sprintf(_("Do you really want to delete the profile %s?"), $profile_name); ?>')">
                            <?php echo _("Delete") ?>
                        </button>
                    </form>
                    <form action='duplicate_profile.php?inst_id=<?php echo $theProfile->institution; ?>&amp;profile_id=<?php echo $theProfile->identifier; ?>' method='post' accept-charset='UTF-8'>
                        <button type='submit' name='profile_duplicate'>
                            <?php echo _("Duplicate this profile"); ?>
                        </button>
                    </form>
                    <?php } ?>
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


$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();
echo $deco->defaultPagePrelude(sprintf(_("%s: %s Dashboard"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant));
require_once "inc/click_button_js.php";

// let's check if the inst handle actually exists in the DB
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

$myfed = new \core\Federation($my_inst->federation);

// delete stored realm

if (isset($_SESSION['check_realm'])) {
    unset($_SESSION['check_realm']);
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, TRUE);
echo $mapCode->htmlHeadCode();
?>
<script src="js/XHR.js"></script>
<script src="js/popup_redirect.js"></script>
<script src="../external/jquery/jquery-ui.js"></script>
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<style>
    #yourBtn {
  width: 150px;
  padding: 10px;
  -webkit-border-radius: 0px;
  -moz-border-radius: 0px;
  border: 1px  solid #000;
  font-family: Arial;
  font-size: 13px;
  text-align: center;
  background-color: yellow;
}
</style>
<script src="../external/jquery/DataTables/datatables.js"></script>
<link type="text/css"  rel="stylesheet" href="../external/jquery/DataTables/datatables.css"  media="all" />
<script>
$(document).ready(function() {    
    $("img.cat-icon").tooltip();
    $("table.downloads").DataTable({
          "dom": 't',
          "pageLength": 100,
          "columnDefs": [
        { orderSequence: ['asc'], targets: [0] },
        { orderSequence: ['desc', 'asc'], targets: [1] },
        { orderSequence: ['desc', 'asc'], targets: [2] }
        ]
    });
});

</script>

<body <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    echo $deco->productheader("ADMIN-PARTICIPANT");

// Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    if ($editMode == 'readonly') {
        $editLabel = _("View ...");
    }
    if ($editMode == 'fullaccess') {
        $editLabel = _("Edit ...");
    }
    ?>
    <h1><?php echo sprintf(_("%s Overview"), $uiElements->nomenclatureParticipant); ?></h1>
    <hr/>
    <div>
        <h2 style='display: flex;'><?php echo sprintf(_("%s general settings"), $uiElements->nomenclatureParticipant); ?>&nbsp;
            <form action='edit_participant.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                <button type='submit' name='submitbutton' value='<?php echo \web\lib\common\FormElements::BUTTON_EDIT; ?>'><?php echo $editLabel; ?></button>
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
                    // the button is greyed out if there's no support email address configured...
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
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == "LOCAL" && $editMode === 'fullaccess') {
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
        <?php if(count($profiles_for_this_idp) > 1 && $readonly === FALSE && $editMode === 'fullaccess') { ?>
                    <form method='post' action='sort_profiles.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
                        <div>
                            <button type='submit' name='profile_sorting'>
                                <?php echo _("Change the order of profiles"); ?>
                            </button>
                        </div>
                    </form>  <p>
        <?php }
        if (count($profiles_for_this_idp) == 0) { // no profiles yet.
            printf(_("There are not yet any profiles for your %s."), $uiElements->nomenclatureIdP);
        }
        //print '<pre>'; print_r($profiles_for_this_idp); print '</pre>';
// if there is one profile and it is of type Silver Bullet, display a very
// simple widget with just a "Manage" button
        foreach ($profiles_for_this_idp as $profilecount => $profile_list) {
            ?>
            <div style='display: table-row_id; margin-bottom: 20px;'>
                <div class='profilebox' style='display: table-cell; min-width: 650px;'>
                    <?php
                    switch (get_class($profile_list)) {
                        case "core\ProfileSilverbullet":
                            displaySilverbulletPropertyWidget($profile_list, $readonly, $uiElements);
                            break;
                        case "core\ProfileRADIUS":
                            displayRadiusPropertyWidget($profile_list, $readonly, $uiElements, $editMode);
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
                    <table class="downloads" id="downloads_<?php echo $profilecount ?>">
                        <thead><tr>
                        <?php
                        echo "<th>"._("Device")."</th><th>"._("global")."</th><th>"._("this month")."</th></tr></thead><tbody>";
                        $stats = $profile_list->getUserDownloadStats();
                        foreach ($stats as $dev => $count) {
                            if (isset($count['monthly'])) {
                                echo "<tr><td><strong>$dev</strong></td><td>".$count['current']."</td><td>".$count['monthly']."</td></tr>";
                            }
                        }
                        ?>
                        </tbody>
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
    if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL" && count($myfed->getAttributes("fed:silverbullet")) > 0 && preg_match("/SP/", $my_inst->type)) {
        include "overview_sp.php";
    }
    echo $deco->footer();
    
