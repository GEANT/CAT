<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Federation.php");
require_once("IdP.php");
require_once("Profile.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("inc/option_html.inc.php");

$cat = defaultPagePrelude(sprintf(_("%s: IdP Enrollment Wizard (Step 3)"), Config::$APPEARANCE['productname']));
?>
<script src="js/option_expand.js" type="text/javascript"></script>
<!-- JQuery --> 
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script> 
<!-- EAP sorting code -->
<style>
    li.eap1 {list-style-type: none;}
    ol.eapmethods { list-style-position: inside; margin: 0; padding: 0px; padding-top: 20px; padding-bottom: 0px; width: 20em; }
    ol.eapmethods li{
        background: #CCF;
        border-left-style: inset;
        border-left-width: 1px;
        border-left-color: #8BBACB;
        border-top-style: inset;
        border-top-width: 1px;
        border-top-color: #8BBACB;
        border-right-style: outset;
        border-right-width: 2px;
        border-right-color: #043D52;
        border-bottom-style: outset;
        border-bottom-width: 2px;
        border-bottom-color: #043D52;
        border-radius: 6px;
        box-shadow: 4px 4px 4px #888888;
        background-image:url('../resources/images/icons/strzalka5.png');
        background-repeat:no-repeat;
        background-position:95% 50%;
        margin: 2px 0px 2px 0px;
        padding: 3px;
        padding-left: 1em;
        padding-right: 0px;
    }

    table.eaptable td {
        background:#F0F0F0;
    }

    table.eaptable th {
        background:#F0F0F0;
    }

    #eap_bottom_row td {
        border-top-color: #888;
        border-top-style: solid;
        border-top-width: 2px;
    }

    #eap_bottom_row th {
        border-top-color: #888;
        border-top-style: solid;
        border-top-width: 2px;
    }

    #supported_eap {
        background: green;
        padding: 5px;
    }

    #unsupported_eap {
        background: red;
        padding: 5px;
    }

</style>
<script>
    $(function() {
        $( "#sortable1, #sortable2" ).sortable({
            connectWith: "ol.eapmethods",
            tolerance: 'pointer',
            out: function(event, ui) {
                ui.item.toggleClass("eap1");
            },
            stop: function(event, ui) {
                $(".eapm").removeAttr('value');
                $(".eapmv").removeAttr('value');
                $( "#sortable1" ).children().each(function(index){
                    i = index + 1;
                    v = $(this).html();
                    $("#EAP-"+v).val(v);
                    $("#EAP-"+v+"-priority").val(i);
                });
            }
        }).disableSelection();
    });
</script>
<!-- EAP sorting code end -->
<?php
// initialize inputs
$my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
$my_profile = FALSE;
if (isset($_GET['profile_id'])) { // oh! We should edit an existing profile, not create a new one!
    $my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);
    $wizard_style = FALSE;
    $edit_mode = TRUE;
    $anon_local = $my_profile->getAttributes("internal:anon_local_value");
    $anon_local = $anon_local[0]['value'];
    $use_anon = $my_profile->getAttributes("internal:use_anon_outer");
    if (count($use_anon) > 0)
        $use_anon = $use_anon[0]['value'];
    else
        $use_anon = FALSE;
    $realm = $my_profile->getAttributes("internal:realm");
    $realm = $realm[0]['value'];
    $prefill_name = $my_profile->name;
    $prefill_methods = $my_profile->getEapMethodsinOrderOfPreference();
    $profile_options = $my_profile->getAttributes();
    $blacklisted = $my_profile->getAttributes("device-specific:redirect", 0, 0); // blacklist for all devices?
    // print_r($blacklisted);
    if (count($blacklisted) > 0) {
        $blacklisted = unserialize($blacklisted[0]['value']);
        $blacklisted = $blacklisted['content'];
    }
    else
        $blacklisted = FALSE;
} else {
    $wizard_style = TRUE;
    $edit_mode = FALSE;
    $anon_local = "anonymous";
    $use_anon = FALSE;
    $realm = "";
    $prefill_name = "";
    $prefill_methods = [];
    $profile_options = [];
    $blacklisted = FALSE;
}

$idpoptions = $my_inst->getAttributes();
?>
</head>
<body>
    <?php
    productheader("ADMIN-IDP", CAT::$lang_index);
    ?>
    <h1>
        <?php
        if ($wizard_style)
            echo _("Step 3: Defining a user group profile");
        else
            printf(_("Edit profile '%s' ..."), $prefill_name);
        ?>
    </h1>
    <div class='infobox'>
        <h2><?php echo _("General Institution Details"); ?></h2>
        <table>
            <tr>
                <td>
                    <?php echo "" . _("Country:") ?>
                </td>
                <td>
                </td>
                <td>
                    <strong><?php 
                    $my_fed = new Federation($my_inst->federation);
                    echo $my_fed::$FederationList[strtoupper($my_inst->federation)]; 
                    ?></strong>
                </td>
            </tr>
            <?php echo infoblock($idpoptions, "general", "IdP"); ?>
        </table>
    </div>
    <div class="infobox">
        <h2><?php echo _("Media Properties"); ?></h2>
        <table><?php echo infoblock($idpoptions, "media", "IdP"); ?></table>
    </div>
    <div class="infobox">
        <h2><?php echo _("Global Helpdesk Details"); ?></h2>
        <table><?php echo infoblock($idpoptions, "support", "IdP"); ?></table>
    </div>
    <div class='infobox'>
        <h2><?php echo _("Global EAP Options"); ?></h2>
        <table><?php echo infoblock($idpoptions, "eap", "IdP"); ?></table>
    </div>
    <?php
    echo "<form enctype='multipart/form-data' action='edit_profile_result.php?inst_id=$my_inst->identifier" . ($edit_mode ? "&amp;profile_id=" . $my_profile->identifier : "") . "' method='post' accept-charset='UTF-8'>
                <input type='hidden' name='MAX_FILE_SIZE' value='" . Config::$MAX_UPLOAD_SIZE . "'>";
    ?>
    <fieldset class="option_container">
        <legend>
            <strong><?php echo _("General Profile properties"); ?></strong>
        </legend>
        <?php
        if ($wizard_style)
            echo "<p>" . _("We will now define a profile for your user group(s).  You can add as many profiles as you like by choosing the appropriate button on the end of the page. After we are done, the wizard is finished and you will be taken to the main IdP administration page.") . "</p>";
        ?>
        <h3><?php echo _("Profile Name and RADIUS realm"); ?></h3>
        <?php
        if ($wizard_style) {
            echo "<p>" . _("First of all we need a name for the profile. This will be displayed to end users, so you may want to choose a descriptive name like 'Professors', 'Students of the Faculty of Bioscience', etc.") . "</p>";
            echo "<p>" . _("Optionally, you can provide a longer descriptive text about who this profile is for. If you specify it, it will be displayed on the download page after the user has selected the profile name in the list.") . "</p>";
            echo "<p>" . _("You can also tell us your RADIUS realm. ");
            if (count(Config::$RADIUSTESTS['UDP-hosts']) > 0 || Config::$RADIUSTESTS['TLS-discoverytag'] != "" )
                printf(_("This is useful if you want to use the sanity check module later, which tests reachability of your realm in the %s infrastructure. "), CONFIG::$CONSORTIUM['name']);
            echo _("It is required to enter the realm name if you want to support anonymous outer identities (see below).") . "</p>";
        }
        ?>

        <table id="expandable_profile_options">
            <?php
            $prepopulate = [];
            if ($edit_mode) {
                $existing_attribs = $my_profile->getAttributes();

                foreach ($existing_attribs as $existing_attribute)
                    if ($existing_attribute['level'] == "Profile")
                        $prepopulate[] = $existing_attribute;
            }
            add_option("profile", $prepopulate);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultProfileOptions()'><?php echo _("Add new option"); ?></button>
        <table>
            <?php
            ?>
            <tr>

                <td>
                    <label for="realm">
                        <?php echo _("Realm:"); ?>
                    </label>
                </td>
                <td>
                    <?php echo "<input id='realm' name='realm' value='$realm' onkeyup='
                                 if (this.value.length > 0)
                                      { this.form.elements[\"anon_support\"].removeAttribute(\"disabled\");
                                        document.getElementById(\"anon_support_label\").removeAttribute(\"style\");
                                      } else
                                      { this.form.elements[\"anon_support\"].checked = false;
                                        this.form.elements[\"anon_support\"].setAttribute(\"disabled\", \"disabled\");
                                        this.form.elements[\"anon_local\"].setAttribute(\"disabled\", \"disabled\");
                                        document.getElementById(\"anon_support_label\").setAttribute(\"style\", \"color:#999999\");
                                      };'/>"; ?>

                </td>

            </tr>

        </table>
        <h3><?php echo _("Anonymity Support"); ?></h3>

        <?php
        if ($wizard_style)
            echo "<p>" . sprintf(_("Some installers support a feature called 'Anonymous outer identity'. If you don't know what this is, please read <a href='%s'>this article</a>. Do you want us to generate installers with anonymous outer identities where available? You need to fill out the 'Realm' field above for this to work.") . _("If you enable this feature, we will by default use the anonymous id 'anonymous@realm' in the device configurations. You can optionally change that by typing in the local anonymisation part in the text field."),"https://confluence.terena.org/display/H2eduroam/eap-types") . "</p>";
        ?>
        <p>

            <?php
            echo "<label><span id='anon_support_label' style='" . ($realm == "" ? "color:#999999" : "" ) . "'>" . _("Enable Anonymous Outer Identity:") . "</span>
                             <input type='checkbox' " . ($use_anon != FALSE ? "checked" : "" ) . ($realm == "" ? "disabled" : "" ) . " name='anon_support' onclick='
                              if (this.form.elements[\"anon_support\"].checked != true) {
                                this.form.elements[\"anon_local\"].setAttribute(\"disabled\", \"disabled\");
                              } else {
                                this.form.elements[\"anon_local\"].removeAttribute(\"disabled\");
                              };'/></label>
                             <input type='text' " . ($use_anon == FALSE ? "disabled" : "" ) . " name='anon_local' value='$anon_local'/>";
            ?>
        </p>

        <h3><?php echo _("Installer Download Location"); ?></h3>

        <?php
        if ($wizard_style)
            echo "<p>" . _("The CAT has a download area for end users. There, they will, for example, learn about the support pointers you entered earlier. The CAT can also immediately offer the installers for the profile for download. If you don't want that, you can instead enter a web site location where you want your users to be redirected to. You, as the administrator, can still download the profiles to place them on that page (see the 'Compatibility Matrix' button on the dashboard).") . "</p>";
        ?>
        <p>

            <?php
            echo "<span id='redirect_label' style='" . ($realm == "" ? "color:#999999" : "" ) . "'><label for='redirect'>" . _("Redirect end users to own web page:") . "</label></span>
                          <input type='checkbox'  name='redirect' id='redirect' " . ($blacklisted === FALSE ? "" : "checked " ) . "onclick='
                              if (this.form.elements[\"redirect\"].checked != true) {
                                this.form.elements[\"redirect_target\"].setAttribute(\"disabled\", \"disabled\");
                              } else {
                                this.form.elements[\"redirect_target\"].removeAttribute(\"disabled\");
                              };'/>
                          <input type='text' name='redirect_target' " . ($blacklisted !== FALSE ? "value='$blacklisted'" : "disabled" ) . "/>";
            ?>
        </p>

    </fieldset>
    <fieldset class="option_container">
        <legend><strong><?php echo _("Supported EAP types"); ?></strong></legend>
        <?php
        if ($wizard_style)
            echo "<p>" . _("Now, we need to know which EAP types your IdP supports. If you support multiple EAP types, you can assign every type a priority (1=highest). This tool will always generate an automatic installer for the EAP type with the highest priority; only if the user's device can't use that EAP type, we will use an EAP type further down in the list.") . "</p>";
        ?>
        <?php

        function priority($eap_type, $isenabled, $priority) {
            echo "<td><select id='$eap_type-priority' name='$eap_type-priority' " . (!$isenabled ? "disabled='disabled'" : "") . ">";
            for ($a = 1; $a < 7; $a = $a + 1)
                echo "<option id='$eap_type-$a' value='$a' " . ( $isenabled && $a == $priority ? "selected" : "" ) . ">$a</option>";
            echo "</select></td>";
        }

        function inherited_options($idpwideoptions, $eap_type, $is_visible) {
            echo "<td><div style='" . (!$is_visible ? "visibility:hidden" : "") . "' class='inheritedoptions' id='$eap_type-inherited-global'>";

            $eapoptions = [];

            foreach ($idpwideoptions as $option)
                if ($option['level'] == "IdP" && preg_match('/^eap/', $option['name']))
                    $eapoptions[] = $option['name'];

            $eapoptions = array_count_values($eapoptions);

            if (count($eapoptions) > 0) {
                echo "<strong>" . _("EAP options inherited from Global level:") . "</strong><br />";
                foreach ($eapoptions as $optionname => $count)
                    /// option count and enumeration
                    /// Example: "(3x) Server Name"
                    printf(_("(%dx) %s") . "<br />", $count, display_name($optionname));
            }

            echo "</div></td>";
        }

        $methods = EAP::listKnownEAPTypes();
        ?>

        <?php
// new EAP sorting code  

        foreach ($methods as $a) {
            $display = display_name($a);
            $enabled = FALSE;
            if ($edit_mode)
                foreach ($prefill_methods as $prio => $value) {
                    if (display_name($a) == display_name($value)) {
                        $enabled = TRUE;
                        $countactive = $prio + 1;
                    }
                }
        }
        ?>
        <div>
            <table style="border:none">
                <tr>
                    <th style="vertical-align:top; padding:1em">
                        <?php echo _('Supported EAP types for this profile'); ?>
                    </th>
                    <td id="supported_eap">
                        <ol id="sortable1" class="eapmethods">
                            <?php
                            $D = [];
                            foreach ($prefill_methods as $prio => $value) {
                                print '<li>' . display_name($value) . "</li>\n";
                                $D[display_name($value)] = $prio;
                            }
                            ?>
                        </ol>
                    </td>
                    <td rowspan=3 style="text-align:center; width:12em; padding:1em">
                        <?php echo _('Use "drag &amp; drop" to mark an EAP method and move it to the supported (green) area. Prioritisation is done automatically, depending on where you "drop" the method.'); ?>
                    </td>
                </tr>
                <tr id="eap_bottom_row">
                    <td colspan="2"> </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding:1em">
                        <?php echo _('Unsupported EAP types'); ?>
                    </th>
                    <td style="vertical-align:top" id="unsupported_eap">
                        <ol id="sortable2" class="eapmethods">
                            <?php
                            foreach ($methods as $a) {
                                $display = display_name($a);
                                if (!isset($D[display_name($a)]))
                                    print '<li class="eap1">' . display_name($a) . "</li>\n";
                            }
                            ?>
                        </ol>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        foreach ($methods as $a) {
            $display = display_name($a);
            $v = isset($D[$display]) ? $D[$display] : '';
            print '<input type="hidden" class="eapm" name="' . $display . '" id="EAP-' . $display . '" value="' . $display . '">';
            print '<input type="hidden" class="eapmv" name="' . $display . '-priority" id="EAP-' . $display . '-priority" value="' . $v . '">';
        }
        ?>
        <br style="clear:both;" />
    </fieldset>
    <fieldset class="option_container" id="helpdesk_override">
        <legend><strong><?php echo _("Helpdesk Details for this profile"); ?></strong></legend>
        <p>
            <?php
            $idp_options = $my_inst->getAttributes();
            $has_support_options = [];
            foreach ($idp_options as $idp_option)
                if (preg_match("/^support:/", $idp_option['name']))
                    $has_support_options[$idp_option['name']] = "SET";
            if (count($has_support_options) > 0) {
                $text = "<ul>";
                foreach ($has_support_options as $key => $value)
                    $text .= "<li><strong>".display_name($key) ."</strong></li>";
                $text .= "</ul>";
                printf(ngettext("The option %s is already defined IdP-wide. If you set it here on profile level, this setting will override the IdP-wide one.", "The options %s are already defined IdP-wide. If you set them here on profile level, these settings will override the IdP-wide ones.",count($has_support_options)), $text);
            }
            ?>
        </p>
        <table id="expandable_support_options">
            <?php
            $prepopulate = [];
            if ($edit_mode) {
                $existing_attribs = $my_profile->getAttributes();
                foreach ($existing_attribs as $existing_attribute)
                    if ($existing_attribute['level'] == "Profile")
                        $prepopulate[] = $existing_attribute;
            }
            add_option("support", $prepopulate);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultSupportOptions()'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <fieldset class="option_container" id="eap_override">
        <legend><strong><?php echo _("EAP Details for this profile"); ?></strong></legend>
        <p>
            <?php
            $has_eap_options = [];
            foreach ($idp_options as $idp_option)
                if (preg_match("/^eap:/", $idp_option['name']))
                    $has_eap_options[$idp_option['name']] = "SET";
            if (count($has_eap_options) > 0) {
                $text = "<ul>";
                foreach ($has_eap_options as $key => $value)
                    $text .= "<li><strong>" . display_name($key) . "</strong></li>";
                $text .= "</ul>";
                printf(ngettext("The option %s is already defined IdP-wide. If you set it here on profile level, this setting will override the IdP-wide one.", "The options %s are already defined IdP-wide. If you set them here on profile level, these settings will override the IdP-wide ones.",count($has_eap_options)), $text);
            }
            ?>
        </p>
        <table id="expandable_eapserver_options">
            <?php
            $prepopulate = [];
            if ($edit_mode) {
                $existing_attribs = $my_profile->getAttributes();
                foreach ($existing_attribs as $existing_attribute)
                    if ($existing_attribute['level'] == "Profile")
                        $prepopulate[] = $existing_attribute;
            }
            add_option("eap", $prepopulate);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultEapServerOptions()'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    if ($wizard_style)
        echo "<p>" . _("When you are sure that everything is correct, please click on 'Save data' and you will be taken to your IdP Dashboard page.") . "</p>";
    echo "<p><button type='submit' name='submitbutton' value='".BUTTON_SAVE."'>" . _("Save data") . "</button><button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_idp.php?inst_id=$my_inst->identifier\"'>" . _("Discard changes") . "</button></p></form>";
    footer();
    ?>
