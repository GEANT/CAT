<?php
/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("Federation.php");
require_once("IdP.php");
require_once("Profile.php");
require_once("phpqrcode.php");

require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
include "inc/geo_widget.php";


$cat = defaultPagePrelude(sprintf(_("%s: IdP Dashboard"), Config::$APPEARANCE['productname']));

// let's check if the inst handle actually exists in the DB
$my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);

geo_widget_head($my_inst->federation, $my_inst->name);
?>
</head>
<body  onload='load(0)'>
    <?php
    productheader("ADMIN-IDP", $cat->lang_index);

    // Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    ?>
    <h1><?php echo _("Identity Provider Overview"); ?></h1>
    <div>
        <h2><?php echo _("IdP-wide settings"); ?></h2>
        <div class="infobox">
            <h2><?php echo _("General Institution Details"); ?></h2>
            <table>
                <tr>
                    <td>
                        <?php echo "" . _("Country") ?>
                    </td>
                    <td>
                    </td>
                    <td>
                        <strong><?php $foofed = new Federation($my_inst->federation);
                        echo Federation::$FederationList[strtoupper($my_inst->federation)];
                        ?></strong>
                    </td>
                </tr>
<?php echo infoblock($idpoptions, "general", "IdP"); ?>
            </table>
        </div>
        <div class="infobox">
            <h2><?php echo _("Global Helpdesk Details"); ?></h2>
            <table>
<?php echo infoblock($idpoptions, "support", "IdP"); ?>
            </table>
        </div>
        <div class='infobox'>
            <h2><?php echo _("Global EAP Options"); ?></h2>
            <table>
<?php echo infoblock($idpoptions, "eap", "IdP"); ?>
            </table>
        </div>
        <div class='infobox' style='text-align:center;'>
            <h2><?php echo _("Institution Download Area QR Code"); ?></h2>
            <?php
            $displayurl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $my_inst->identifier;
            $uri = "data://image/png;base64,".base64_encode(png_inject_consortium_logo(QRcode::png($displayurl, FALSE, QR_ECLEVEL_Q, 12)));
            $size = getimagesize($uri);
            echo "<img width='".($size[0]/4)."' height='".($size[1]/4)."' src='$uri' alt='QR-code'/>";
            ?>
            <br>
            <?php echo "<a href='$displayurl'>$displayurl</a>";?>
        </div>
        <?php
        $loadmap = FALSE;
        foreach ($idpoptions as $optionname => $optionvalue)
            if ($optionvalue['name'] == "general:geo_coordinates")
                $loadmap = TRUE;
        if ($loadmap)
            echo '
<div class="infobox"  style="width:270px;">
<div id="map" style="width:100%; height:150px"></div>
</div>
';
        ?>
    </div>
    <table>
        <tr>
            <td>
                <form action='edit_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post'>
                    <button type='submit' name='submitbutton' value='<?php echo BUTTON_EDIT; ?>'><?php echo _("Edit IdP-wide settings"); ?></button>
                </form>
            </td>
            <td>
                <form action='edit_idp_result.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post'>
                    <button class='delete' type='submit' name='submitbutton' value='<?php echo BUTTON_DELETE; ?>' onclick="return confirm('<?php echo sprintf(_("Do you really want to delete your IdP %s?"), $my_inst->name); ?>')"><?php echo _("Delete IdP"); ?></button>
                </form>

            </td>
        </tr>
    </table>
    <hr/>
    <h2><?php _("Available Support actions"); ?></h2>
    <table>
        <?php
        if (count(Config::$RADIUSTESTS['UDP-hosts']) > 0 || Config::$RADIUSTESTS['TLS-discoverytag'] != "")
            echo "<tr>
                        <td>" . _("Check another realm's reachability") . "</td>
                        <td><form method='post' action='action_realmcheck.php?inst_id=$my_inst->identifier'>
                              <input type='text' name='realm' id='realm'>
                              <button type='submit'>" . _("Go!") . "</button>
                            </form>
                        </td>
                    </tr>";
        if (Config::$CONSORTIUM['name'] == "eduroam") // SW: APPROVED
            echo "<tr>
                        <td>" . _("Check server status of European federations") . "</td>
                        <td>
                           <form action='http://monitor.eduroam.org'>
                              <button type='submit'>" . _("Go!") . "</button>
                           </form>
                        </td>
                    </tr>";
        ?>
    </table>
    <hr/>
    <h2><?php echo _("Profiles for this institution"); ?></h2>
    <?php
    $profiles_for_this_idp = $my_inst->listProfiles();
    if (count($profiles_for_this_idp) == 0) // no profiles yet.
        echo _("There are not yet any profiles for your institution.");

    foreach ($profiles_for_this_idp as $profile_list) {
        echo "<div style='display: table-row; margin-bottom: 20px;'>";
        $profile_name = $profile_list->name;
        // see if there are any profile-level overrides
        $attribs = $profile_list->getAttributes();

        echo "<div class='profilebox' style='display: table-cell;'>";

        // write things into a buffer; we need some function calls to determine
        // readiness - but want to display it before!

        $has_overrides = FALSE;
        foreach ($attribs as $attrib)
            if ($attrib['level'] == "Profile" && !preg_match("/^(internal:|profile:name|profile:description)/", $attrib['name']))
                $has_overrides = TRUE;

        $buffer_eaptypediv = "<div style='margin-bottom:40px; float:left;'>" . _("<strong>EAP Types</strong> (in order of preference):") . "<br/>";
        $typelist = $profile_list->getEapMethodsinOrderOfPreference();
        $allcomplete = TRUE;
        foreach ($typelist as $eaptype) {
            $buffer_eaptypediv .= display_name($eaptype);
            $completeness = $profile_list->isEapTypeDefinitionComplete($eaptype);
            if ($completeness === true) {
                $buffer_eaptypediv .= " <div class='acceptable'>" . _("OK") . "</div>";
            } else {
                $buffer_eaptypediv .= " <div class='notacceptable'>";
                $buffer_eaptypediv .= _("Information needed!");
                if (is_array($completeness)) {
                    $buffer_eaptypediv .= "<ul style='margin:1px'>";
                    foreach ($completeness as $missing_attrib) {
                        $buffer_eaptypediv .= "<li>" . display_name($missing_attrib) . "</li>";
                    }
                    $buffer_eaptypediv .= "</ul>";
                }
                $buffer_eaptypediv .= "</div>";
                $allcomplete = FALSE;
            };
            $eapattribs = $profile_list->getAttributes(0, $eaptype);
            foreach ($attribs as $attrib)
                if ($attrib['level'] == "Method" && !preg_match("/^internal:/", $attrib['name']))
                    $buffer_eaptypediv .= "<img src='../resources/images/icons/Letter-E-blue-icon.png' alt='" . _("Option override on EAP Method level is in effect.") . "'>";
            $buffer_eaptypediv .= "<br/>";
        }
        $buffer_headline = "<h2 style='overflow:auto;'>";

        $buffer_headline .= "<div style='float:right;'>";
        $sufficient_config = $profile_list->getSufficientConfig();
        $showtime = $profile_list->getShowtime();
        if ($has_overrides)
            $buffer_headline .= UI_remark("", _("Option override on profile level is in effect."), TRUE);
        if (!$allcomplete)
            $buffer_headline .= UI_error("", _("The information in this profile is incomplete."), TRUE);
        if ($showtime)
            $buffer_headline .= UI_okay("", _("This profile is shown on the user download interface."), TRUE);
        else if ($sufficient_config)
            $buffer_headline .= UI_warning("", sprintf(_("This profile is NOT shown on the user download interface, even though we have enough information to show. To enable the profile, add the attribute \"%s\" and tick the corresponding box."), display_name("profile:production")), TRUE);
        $buffer_headline .= "</div>";

        $buffer_headline .= sprintf(_("Profile: %s"), $profile_name) . "</h2>";

        echo $buffer_headline;

        if (array_search(EAP::$TTLS_PAP, $typelist) !== FALSE && array_search(EAP::$TTLS_GTC, $typelist) === FALSE && array_search(EAP::$PEAP_MSCHAP2, $typelist) === FALSE && array_search(EAP::$TTLS_MSCHAP2, $typelist) === FALSE)
        /// Hmmm... IdP Supports TTLS-PAP, but not TTLS-GTC nor anything based on MSCHAPv2. That locks out Symbian users; and is easy to circumvent. Tell the admin...
            $buffer_eaptypediv .= "<p>" . sprintf(_("Read this <a href='%s'>tip</a>."),"https://confluence.terena.org/display/H2eduroam/eap-types#eap-types-choices") . "</p>";

        $buffer_eaptypediv .= "</div>";
        echo $buffer_eaptypediv;
        $has_eaptypes = count($profile_list->getEapMethodsInOrderOfPreference(1));
        $has_realm = $profile_list->getAttributes("internal:realm");
        $has_realm = $has_realm[0]['value'];
        echo "<div class='profilemodulebuttons' style='float:right;'>";
        if (count(Config::$RADIUSTESTS['UDP-hosts']) > 0 || ( count(Config::$RADIUSTESTS['TLS-clientcerts']) > 0 && Config::$RADIUSTESTS['TLS-discoverytag'] != ""))
            echo "<form action='action_realmcheck.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post'>
                              <button type='submit' name='profile_action' value='check' " . ($has_realm ? "" : "disabled='disabled' title='" . _("The realm can only be checked if you configure the realm!") . "'") . ">
                                  " . _("Check realm reachability") . "
                              </button>
                          </form>";
        echo "<form action='overview_installers.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post'>
                              <button type='submit' name='profile_action' value='check' " . ($has_eaptypes ? "" : "disabled='disabled'  title='" . _("You have not fully configured any supported EAP types!") . "'") . ">
                                  " . _("Installer Fine-Tuning and Download") . "
                              </button>
                 </form>
                   </div>";

        echo "        <div class='buttongroupprofilebox' style='clear:both;'>
                          <form action='edit_profile.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post'>
                               <hr/>
                               <button type='submit' name='profile_action' value='edit'>" . _("Edit") . "</button>
                          </form>
                          <form action='edit_profile_result.php?inst_id=$my_inst->identifier&amp;profile_id=$profile_list->identifier' method='post'>
                               <button class='delete' type='submit' name='submitbutton' value='" . BUTTON_DELETE . "' onclick=\"return confirm('" . sprintf(_("Do you really want to delete the profile %s?"), $profile_name) . "')\">
                                   " . _("Delete") . "
                               </button>
                           </form>
                      </div>";

        echo "</div>";
// dummy width to keep a little distance
        echo "<div style='width:20px;'></div>";
        if ($profile_list->getShowtime()) {
            echo "<div style='display: table-cell; text-align:center;'><p><strong>" . _("User Download Link") . "</strong></p>";
            $URL = $profile_list->getCollapsedAttributes();
            if (isset($URL['device-specific:redirect']))
                $displayurl = $URL['device-specific:redirect'][0];
            else
                $displayurl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? 'https://' : 'http://' ) . $_SERVER['SERVER_NAME'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "?idp=" . $my_inst->identifier . "&amp;profile=" . $profile_list->identifier;
            echo "<a href='$displayurl' style='white-space: nowrap; text-align: center;'>";
            $uri = "data://image/png;base64,".base64_encode(png_inject_consortium_logo(QRcode::png($displayurl, FALSE, QR_ECLEVEL_Q, 12)));
            $size = getimagesize($uri);
            echo "<img width='".($size[0]/4)."' height='".($size[1]/4)."' src='$uri' alt='QR-code'/>";

            //echo "<nobr>$displayurl</nobr></a>";
            echo "<p>$displayurl</p></a>";
            echo "</div>";
            // dummy width to keep a little distance
            echo "<div style='width:20px;'></div>";
            echo "<div style='display: table-cell; min-width:200px;'><p><strong>" . _("User Downloads") . "</strong></p><table>";
            $stats = $profile_list->getUserDownloadStats();
            foreach ($stats as $dev => $count)
                echo "<tr><td><strong>$dev</strong></td><td>$count</td></tr>";
            echo "</table></div>";
            
            
        }
        echo "</div>";
        // dummy div to keep a little distance
        echo "<div style='height:20px'></div>";
    }
    ?>
    <form action='edit_profile.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post'>
        <div>
            <button type='submit' name='profile_action' value='new'>
                <?php echo _("Add new profile ..."); ?>
            </button>
        </div>
    </form>
    <?php
    footer();
    ?>
