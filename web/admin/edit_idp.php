<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new \web\lib\admin\UIElements();

$auth->authenticate();

// how do we determine if we should go into wizard mode? It's all in the URL
if (isset($_GET['wizard']) && $_GET['wizard'] == "true") {
    $wizardStyle = TRUE;
} else {
    $wizardStyle = FALSE;
}
$my_inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
$idpoptions = $my_inst->getAttributes();
$inst_name = $my_inst->name;

if ($wizardStyle) {
    echo $deco->defaultPagePrelude(sprintf(_("%s: IdP enrollment wizard (step 2)"), CONFIG['APPEARANCE']['productname']));
} else {
    echo $deco->defaultPagePrelude(sprintf(_("%s: Editing IdP '%s'"), CONFIG['APPEARANCE']['productname'], $inst_name));
}
// let's check if the inst handle actually exists in the DB and user is authorised
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 

<?php
$additional = FALSE;
foreach ($idpoptions as $optionname => $optionvalue) {
    if ($optionvalue['name'] == "general:geo_coordinates") {
        $additional = TRUE;
    }
}
$widget = new \web\lib\admin\GeoWidget();

echo $widget->insertInHead($my_inst->federation, $inst_name);
?>
<script>
    $(document).ready(function () {
        $(".location_button").click(function (event) {
            event.preventDefault();
            marker_index = $(this).attr("id").substr(11) - 1;
            marks[marker_index].setOptions({icon: icon_red});
            setTimeout('marks[marker_index].setOptions({icon: icon})', 1000);
        });

        $("#address").keypress(function (event) {
            if (event.which === 13) {
                event.preventDefault();
                getAddressLocation();
            }

        });

    });
</script>
</head>
<body onload='load(1)'>
    <?php 
    $langObject = new \core\common\Language();
    echo $deco->productheader("ADMIN-IDP"); ?>

    <h1>
        <?php
        if ($wizardStyle) {
            echo _("Step 2: General Information about your IdP");
        } else {
            printf(_("Editing IdP information for '%s'"), $inst_name);
        }
        ?>
    </h1>
    <div class='infobox'>
        <h2><?php echo sprintf(_("General %s properties"),$uiElements->nomenclature_inst); ?></h2>
        <table>
            <tr>
                <td><?php echo _("Country:"); ?></td>
                <td></td>
                <td><strong><?php
                        $fed = new \core\Federation($my_inst->federation);
                        echo $fed->name;
                        ?></strong></td>
            </tr>
            <?php echo $uiElements->infoblock($idpoptions, "general", "IdP"); ?>
        </table>
    </div>
    <?php
    echo "<form enctype='multipart/form-data' action='edit_idp_result.php?inst_id=$my_inst->identifier" . ($wizardStyle ? "&wizard=true" : "") . "' method='post' accept-charset='UTF-8'>
              <input type='hidden' name='MAX_FILE_SIZE' value='" . CONFIG['MAX_UPLOAD_SIZE'] . "'>";

    if ($wizardStyle) {
        echo "<p>" .
        sprintf(_("Hello, newcomer. Your %s is new to us. This wizard will ask you several questions about your IdP, so that we can generate beautiful profiles for you in the end. All of the information below is optional, but it is important to fill out as many fields as possible for the benefit of your end users."), $uiElements->nomenclature_inst ) . "</p>";
    }
    $optionDisplay = new web\lib\admin\OptionDisplay($idpoptions, "IdP");
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("General Information"); ?></strong></legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" .
            sprintf(_("This is the place where you can describe your %s in a fine-grained way. The solicited information is used as follows:"), $uiElements->nomenclature_inst) . "</p>
                      <ul>
                         <li>" . _("<strong>Logo</strong>: When you submit a logo, we will embed this logo into all installers where a custom logo is possible. We accept any image format, but for best results, we suggest SVG. If you don't upload a logo, we will use the generic logo instead (see top-right corner of this page).") . "</li>
                         <li>" . _("<strong>Terms of Use</strong>: Some installers support displaying text to the user during installation time. If so, we will make that happen if you upload an RTF file or plain text file to display.") . "</li>";

            echo "</ul>";
        }
        echo $optionDisplay->prefilledOptionTable("general");
        ?>
        <button type='button' class='newoption' onclick='getXML("general")'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    echo $widget->insertInBody($wizardStyle, $additional);
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("Media Properties"); ?></strong></legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" .
            sprintf(_("In this section, you define on which media %s should be configured on user devices."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']) . "</p>
          <ul>";
            echo "<li>";
            echo "<strong>" . ( count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0 ? _("Additional SSIDs:") : _("SSIDs:")) . " </strong>";
            if (count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0) {
                $ssidlist = "";
                foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'] as $ssid) {
                    $ssidlist .= ", '<strong>" . $ssid . "</strong>'";
                }
                $ssidlist = substr($ssidlist, 2);
                echo sprintf(ngettext("We will always configure this SSID for WPA2/AES: %s.", "We will always configure these SSIDs for WPA2/AES: %s.", count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])), $ssidlist);
                if (CONFIG_CONFASSISTANT['CONSORTIUM']['tkipsupport']) {
                    echo " " . _("They will also be configured for WPA/TKIP if the device supports multiple encryption types.");
                }
                echo "<br/>" . sprintf(_("It is also possible to define custom additional SSIDs with the options '%s' and '%s' below."), $uiElements->displayName("media:SSID"), $uiElements->displayName("media:SSID_with_legacy"));
            } else {
                echo _("Please configure which SSIDs should be configured in the installers.");
            }
            echo " " . _("By default, we will only configure the SSIDs with WPA2/AES encryption. By using the '(with WPA/TKIP)' option you can specify that we should include legacy support for WPA/TKIP where possible.");
            echo "</li>";

            echo "<li>";
            echo "<strong>" . ( count(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid']) > 0 ? _("Additional Hotspot 2.0 / Passpoint Consortia:") : _("Hotspot 2.0 / Passpoint Consortia:")) . " </strong>";
            if (count(CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi']) > 0) {
                $consortiumlist = "";
                foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi'] as $oi) {
                    $consortiumlist .= ", '<strong>" . $oi . "</strong>'";
                }
                $consortiumlist = substr($consortiumlist, 2);
                echo sprintf(ngettext("We will always configure this Consortium OI: %s.", "We will always configure these Consortium OIs: %s.", count(CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi'])), $consortiumlist);

                echo "<br/>" . sprintf(_("It is also possible to define custom additional OIs with the option '%s' below."), $uiElements->displayName("media:consortium_OI"));
            } else {
                echo _("Please configure which Consortium OIs should be configured in the installers.");
            }
            echo "</li>";
            echo "<li><strong>" . _("Support for wired IEEE 802.1X:") . " </strong>"
            . _("If you want to configure your users' devices with IEEE 802.1X support for wired ethernet, please check the corresponding box. Note that this makes the installation process a bit more difficult on some platforms (Windows: needs administrator privileges; Apple: attempting to install a profile with wired support on a device without an active wired ethernet card will fail).") .
            "</li>";
            echo "<li><strong>" . _("Removal of bootstrap/onboarding SSIDs:") . " </strong>"
            . _("If you use a captive portal to distribute configurations, you may want to unconfigure/disable that SSID after the bootstrap process. With this option, the SSID will either be removed, or be defined as 'Only connect manually'.")
            . "</li>";
            echo "</ul>";
        }
        echo $optionDisplay->prefilledOptionTable("media");
        ?>
        <button type='button' class='newoption' onclick='getXML("media")'><?php echo _("Add new option"); ?></button></fieldset>

    <fieldset class="option_container">
        <legend><strong><?php echo _("Helpdesk Details for all users"); ?></strong></legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" .
            _("If your IdP provides a helpdesk for its users, it would be nice if you would tell us the pointers to this helpdesk. Some site installers might be able to signal this information to the user if he gets stuck.") . "</p>
        <p>" .
            _("If you enter a value here, it will be added to the site installers for all your users, and will be displayed on the download page. If you operate separate helpdesks for different user groups (we call this 'profiles'), or operate no help desk at all (shame on you!), you can also leave any of these fields empty and optionally specify per-profile helpdesk information later in this wizard.") . "</p>";
        }
        echo $optionDisplay->prefilledOptionTable("support");
        ?>

        <button type='button' class='newoption' onclick='getXML("support")'><?php echo _("Add new option"); ?></button></fieldset>
    <?php
    if ($wizardStyle) {
        echo "<p>" . sprintf(_("When you are sure that everything is correct, please click on %sContinue ...%s"), "<button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_CONTINUE . "'>", "</button>") . "</p></form>";
    } else {
        echo "<div><button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button> <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_idp.php?inst_id=$my_inst->identifier\"'>" . _("Discard changes") . "</button></div></form>";
    }
    echo $deco->footer();
    