<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Federation.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("inc/admin_header.php");
require_once("inc/option_html.inc.php");
require_once("inc/geo_widget.php");
require_once("inc/auth.inc.php");

// how do we determine if we should go into wizard mode? It's all in the URL
if (isset($_GET['wizard']) && $_GET['wizard'] == "true") {
    $wizard_style = TRUE;
} else {
    $wizard_style = FALSE;
}
$my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
$idpoptions = $my_inst->getAttributes();
$inst_name = $my_inst->name;

if ($wizard_style)
    defaultPagePrelude(sprintf(_("%s: IdP enrollment wizard (step 2)"), Config::$APPEARANCE['productname']));
else
    defaultPagePrelude(sprintf(_("%s: Editing IdP '%s'"), Config::$APPEARANCE['productname'], $inst_name));
// let's check if the inst handle actually exists in the DB and user is authorised


?>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<?php
$additional = FALSE;
foreach ($idpoptions as $optionname => $optionvalue)
    if ($optionvalue['name'] == "general:geo_coordinates")
        $additional = TRUE;
geo_widget_head($my_inst->federation, $inst_name)
?>
<script>
    $(document).ready(function() {
        $(".location_button").click(function(event) {
            event.preventDefault();
            marker_index = $(this).attr("id").substr(11) - 1;
            marks[marker_index].setOptions({icon: icon_red});
            setTimeout('marks[marker_index].setOptions({icon: icon})',1000);
        });

        $("#address").keypress(function(event) {
            if ( event.which == 13 ) {
                event.preventDefault();
                getAddressLocation();
            }
                 
        });

    });
</script>
</head>
<body onload='load(1)'>

    <?php productheader(); ?>

    <h1>
        <?php
        if ($wizard_style)
            echo _("Step 2: General Information about your IdP");
        else
            printf(_("Editing IdP information for '%s'"), $inst_name);
        ?>
    </h1>
    <div class='infobox'>
        <h2><?php echo _("General Institution Properties"); ?></h2>
        <table>
            <tr>
                <td><?php echo _("Country:"); ?></td>
                <td></td>
                <td><strong><?php
        new Federation("blablub");
        echo Federation::$FederationList[strtoupper($my_inst->federation)];
        ?></strong></td>
            </tr>
            <?php echo infoblock($idpoptions, "general", "IdP"); ?>
        </table>
    </div>
    <?php
    echo "<form enctype='multipart/form-data' action='edit_idp_result.php?inst_id=$my_inst->identifier" . ($wizard_style ? "&wizard=true" : "") . "' method='post'>
              <input type='hidden' name='MAX_FILE_SIZE' value='" . Config::$MAX_UPLOAD_SIZE . "'>";

    if ($wizard_style)
        echo "<p>" . 
_("Hello, newcomer. Your institution is new to us. This wizard will ask you several questions about your IdP, so that we can generate beautiful profiles for you in the end. All of the information below is optional, but it is important to fill out as many fields as possible for the benefit of your end users.") . "</p>";
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("General Information"); ?></strong></legend>
        <?php
        if ($wizard_style) {
            echo "<p>" . 
_("This is the place where you can describe your institution in a fine-grained way. The solicited information is used as follows:") . "</p>
                      <ul>
                         <li>" . _("<strong>Logo</strong>: When you submit a logo, we will embed this logo into all installers where a custom logo is possible. We accept any image format, but for best results, we suggest SVG. If you don't upload a logo, we will use the generic logo instead (see top-right corner of this page).") . "</li>
                         <li>" . _("<strong>Terms of Use</strong>: Some installers support displaying text to the user during installation time. If so, we will make that happen if you upload an RTF file or plain text file to display.") . "</li>";
            
            echo "<li>";
            echo "<strong>".( count(Config::$CONSORTIUM['ssid'])>0 ? _("Additional SSIDs:") : _("SSIDs:"))." </strong>";
            if (count(Config::$CONSORTIUM['ssid']) > 0) {
                    $ssidlist = "";
                    foreach (Config::$CONSORTIUM['ssid'] as $ssid)
                        $ssidlist .= ", '<strong>".$ssid."</strong>'";
                    $ssidlist = substr($ssidlist, 2);
                    echo sprintf(ngettext("We will always configure this SSID for WPA2/AES: %s.","We will always configure these SSIDs for WPA2/AES: %s.",count(Config::$CONSORTIUM['ssid'])),$ssidlist);
                    if (Config::$CONSORTIUM['tkipsupport'])
                        echo " "._("They will also be configured for WPA/TKIP if the device supports multiple encryption types.");
                    echo "<br/>".sprintf(_("It is also possible to define custom additional SSIDs with the options '%s' and '%s' below."),display_name("general:SSID"), display_name("general:SSID_with_legacy"));
            } else {
            echo _("Please configure which SSIDs should be configured in the installers.");
            }
            echo " "._("By default, we will only configure the SSIDs with WPA2/AES encryption. By using the '(with WPA/TKIP)' option you can specify that we should include legacy support for WPA/TKIP where possible.");
            echo "</li>";
            echo "</ul>";
        }
        ?>
        <table id="expandable_inst_options">
            <?php
            add_option("general", $idpoptions);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultInstOptions()'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    $additional = FALSE;

    foreach ($idpoptions as $optionname => $optionvalue) {
        if ($optionvalue['name'] == "general:geo_coordinates") {
            $additional = TRUE;
        }
    }
    geo_widget_body($wizard_style, $additional);
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("Helpdesk Details for all users"); ?></strong></legend>
        <?php
        if ($wizard_style)
            echo "<p>" . 
_("If your IdP provides a helpdesk for its users, it would be nice if you would tell us the pointers to this helpdesk. Some site installers might be able to signal this information to the user if he gets stuck.") . "</p>
        <p>" . 
_("If you enter a value here, it will be added to the site installers for all your users, and will be displayed on the download page. If you operate separate helpdesks for different user groups (we call this 'profiles'), or operate no help desk at all (shame on you!), you can also leave any of these fields empty and optionally specify per-profile helpdesk information later in this wizard.") . "</p>";
        ?>

        <table id="expandable_support_options">
            <?php
            add_option("support", $idpoptions);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultSupportOptions()'><?php echo _("Add new option"); ?></button></fieldset>
    <fieldset class="option_container">
        <legend><strong><?php echo _("EAP details for all users"); ?></strong></legend>
        <?php
        if ($wizard_style)
            echo "<p>" . _("Most EAP methods need server-side authentication details, like the CA certificate and/or server name(s) of your authentication servers. If all the EAP methods you support work with the same CA and or Common Names of servers, you can enter them here and they will be added as trust anchors in all profiles. If the details differ per profile or per EAP-type, you can also enter them in the individual profiles later.") . "</p>
        <p>" . sprintf(_("<strong>Note well: </strong>The server-side validation is a cornerstone of %s; without it, users are subject to man-in-the-middle attacks! We will not generate site installers without Trusted CA anchors and server names."), Config::$CONSORTIUM['name']) . "</p>";
        ?>
        <table id="expandable_eapserver_options">
            <?php
            add_option("eap", $idpoptions);
            ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultEapServerOptions()'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    if ($wizard_style) {
        echo "<p>" . sprintf(_("When you are sure that everything is correct, please click on %sContinue ...%s"),"<button type='submit' name='submitbutton' value='".BUTTON_CONTINUE."'>","</button>")."</p></form>";
    } else {
        echo "<div><button type='submit' name='submitbutton' value='".BUTTON_SAVE."'>" . _("Save data") . "</button> <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_idp.php?inst_id=$my_inst->identifier\"'>" . _("Discard changes") . "</button></div></form>";
    }
    include "inc/admin_footer.php";
    ?>
        
