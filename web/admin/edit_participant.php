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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

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
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
$idpoptions = $my_inst->getAttributes();
$inst_name = $my_inst->name;

if ($wizardStyle) {
    echo $deco->defaultPagePrelude(sprintf(_("%s: %s enrollment wizard (step 2)"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant));
} else {
    echo $deco->defaultPagePrelude(sprintf(_("%s: Editing %s '%s'"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant, $inst_name));
}
require_once "inc/click_button_js.php";
// let's check if the inst handle actually exists in the DB and user is authorised
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery-migrate.js"></script> 

<?php
$additional = FALSE;
foreach ($idpoptions as $optionname => $optionvalue) {
    if ($optionvalue['name'] == "general:geo_coordinates") {
        $additional = TRUE;
    }
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, FALSE);

echo $mapCode->htmlHeadCode();
?>
</head>
<?php
?>
<body <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    $langObject = new \core\common\Language();
    echo $deco->productheader("ADMIN-PARTICIPANT");
    ?>

    <h1>
        <?php
        if ($wizardStyle) {
            printf(_("Step 2: General Information about your %s"), $uiElements->nomenclatureParticipant);
        } else {
            printf(_("Editing %s information for '%s'"), $uiElements->nomenclatureParticipant, $inst_name);
        }
        ?>
    </h1>
    <div class='infobox'>
        <h2><?php $tablecaption = sprintf(_("%s general properties"), $uiElements->nomenclatureParticipant);
        echo $tablecaption; ?></h2>
        <table>
            <caption><?php echo $tablecaption; ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value"); ?></th>
            </tr>

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
    echo "<form enctype='multipart/form-data' action='edit_participant_result.php?inst_id=$my_inst->identifier" . ($wizardStyle ? "&wizard=true" : "") . "' method='post' accept-charset='UTF-8'>
              <input type='hidden' name='MAX_FILE_SIZE' value='" . \config\Master::MAX_UPLOAD_SIZE . "'>";

    if ($wizardStyle) {
        echo "<p>" .
        sprintf(_("Hello, newcomer. The %s is new to us. This wizard will ask you several questions about it, so that we can generate beautiful profiles for you in the end. All of the information below is optional, but it is important to fill out as many fields as possible for the benefit of your end users."), $uiElements->nomenclatureParticipant) . "</p>";
    }
    $optionDisplay = new web\lib\admin\OptionDisplay($idpoptions, \core\Options::LEVEL_IDP);
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("General Information"); ?></strong></legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" .
            _("Some properties are valid across all deployment profiles. This is the place where you can describe those properties in a fine-grained way. The solicited information is used as follows:") . "</p>
                      <ul>
                         <li>" . _("<strong>Logo</strong>: When you submit a logo, we will embed this logo into all installers where a custom logo is possible. We accept any image format, but for best results, we suggest SVG. If you don't upload a logo, we will use the generic logo instead (see top-right corner of this page).") . "</li>
                         <li>" . sprintf(_("<strong>Name</strong>: The %s may have names in multiple languages. It is recommended to always populate at least the 'default/other' language, as it is used as a fallback if the system does not have a name in the exact language the user requests a download in."), $uiElements->nomenclatureParticipant) . "</li>";
            echo "</ul>";
        }
        echo $optionDisplay->prefilledOptionTable("general", $my_inst->federation);
        ?>
        <button type='button' class='newoption' onclick='getXML("general", "<?php echo $my_inst->federation; ?>")'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    echo $mapCode->htmlShowtime($wizardStyle, $additional);
    if ($my_inst->type != "SP") {
        ?>
        <fieldset class="option_container">
            <legend><strong><?php echo _("Media Properties"); ?></strong></legend>
            <?php
            if ($wizardStyle) {
                echo "<p>" .
                sprintf(_("In this section, you define on which media %s should be configured on user devices."), \config\ConfAssistant::CONSORTIUM['display_name']) . "</p>
          <ul>";
                echo "<li>";
                echo "<strong>" . ( count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0 ? _("Additional SSIDs:") : _("SSIDs:")) . " </strong>";
                if (count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0) {
                    $ssidlist = "";
                    foreach (\config\ConfAssistant::CONSORTIUM['ssid'] as $ssid) {
                        $ssidlist .= ", '<strong>" . $ssid . "</strong>'";
                    }
                    $ssidlist = substr($ssidlist, 2);
                    echo sprintf(ngettext("We will always configure this SSID for WPA2/AES: %s.", "We will always configure these SSIDs for WPA2/AES: %s.", count(\config\ConfAssistant::CONSORTIUM['ssid'])), $ssidlist);
                    echo "<br/>" . sprintf(_("It is also possible to define custom additional SSIDs with the option '%s' below."), $uiElements->displayName("media:SSID"));
                } else {
                    echo _("Please configure which SSIDs should be configured in the installers.");
                }
                echo " " . _("By default, we will only configure the SSIDs with WPA2/AES encryption. By using the '(with WPA/TKIP)' option you can specify that we should include legacy support for WPA/TKIP where possible.");
                echo "</li>";

                echo "<li>";
                echo "<strong>" . ( count(\config\ConfAssistant::CONSORTIUM['ssid']) > 0 ? _("Additional Hotspot 2.0 / Passpoint Consortia:") : _("Hotspot 2.0 / Passpoint Consortia:")) . " </strong>";
                if (count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']) > 0) {
                    $consortiumlist = "";
                    foreach (\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'] as $oi) {
                        $consortiumlist .= ", '<strong>" . $oi . "</strong>'";
                    }
                    $consortiumlist = substr($consortiumlist, 2);
                    echo sprintf(ngettext("We will always configure this Consortium OI: %s.", "We will always configure these Consortium OIs: %s.", count(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'])), $consortiumlist);

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
            echo $optionDisplay->prefilledOptionTable("media", $fed->tld);
            ?>
            <button type='button' class='newoption' onclick='getXML("media", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button>
        </fieldset>
        <?php
    }
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("Helpdesk Details for all users"); ?></strong></legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" . _("This section can be used to upload specific Terms of Use for your users and to display details of how your users can reach your local helpdesk.") . "</p>";

            if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == "LOCAL") {
                echo "<p>" .
                sprintf(_("Do you provide helpdesk services for your users? If so, it would be nice if you would tell us the pointers to this helpdesk."), $uiElements->nomenclatureParticipant) . "</p>" .
                "<p>" .
                _("If you enter a value here, it will be added to the installers for all your users, and will be displayed on the download page. If you operate separate helpdesks for different user groups (we call this 'profiles') specify per-profile helpdesk information later in this wizard. If you operate no help desk at all, just leave these fields empty.") . "</p>";
                if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
                    echo "<p>" . sprintf(_("For %s deployments, providing at least a local e-mail contact is required."), config\ConfAssistant::SILVERBULLET['product_name']) . " " . _("This is the contact point for your organisation. It may be displayed publicly.") . "</p>";
                }
            } elseif (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
                echo "<p>" . _("Providing at least a local support e-mail contact is required.") . " " . _("This is the contact point for your end users' level 1 support.") . "</p>";
            }
        }
        echo $optionDisplay->prefilledOptionTable("support", $fed->tld);
        ?>

        <button type='button' class='newoption' onclick='getXML("support", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button></fieldset>
    <?php
    if ($wizardStyle) {
        echo "<p>" . sprintf(_("When you are sure that everything is correct, please click on %sContinue ...%s"), "<button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_CONTINUE . "'>", "</button>") . "</p></form>";
    } else {
        echo "<div><button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button> <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_user.php\"'>" . _("Discard changes") . "</button></div></form>";
    }
    echo $deco->footer();
    