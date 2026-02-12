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

$wizard = new \web\lib\admin\Wizard($wizardStyle);
$wizard->setMessages();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);
$idpoptions = $my_inst->getAttributes();
$inst_name = $my_inst->name;

if ($wizardStyle) {
    echo $deco->defaultPagePrelude(sprintf(_("%s: %s enrollment wizard (step 2)"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant));
} else {
    echo $deco->defaultPagePrelude(sprintf(_("%s: Editing %s '%s'"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant, $inst_name));
}
// let's check if the inst handle actually exists in the DB and user is authorised
if ($editMode == 'readonly') {
    print('<style>'
            . 'button.newoption {visibility: hidden}'
            . '#submitbutton {visibility: hidden} '
            . 'button.delete {visibility: hidden} '
            . 'input {pointer-events: none} '
            . '.ui-sortable-handle {pointer-events: none}'
            . '</style>');
}
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script> 
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<script type="text/javascript" src="js/wizard.js"></script> 
<link rel='stylesheet' type='text/css' href='css/wizard.css.php' />

<?php
$additional = FALSE;
if ($editMode == 'fullaccess') {
    foreach ($idpoptions as $optionname => $optionvalue) {
        if ($optionvalue['name'] == "general:geo_coordinates") {
            $additional = TRUE;
        }
    }
}
$mapCode = web\lib\admin\AbstractMap::instance($my_inst, $editMode == 'readonly');

echo $mapCode->htmlHeadCode();
?>
</head>
<?php
?>
<body <?php echo $mapCode->bodyTagCode(); ?>>
    <?php
    echo $deco->productheader("ADMIN-PARTICIPANT");
    ?>
    <div id="wizard_help_window"><img id="wizard_menu_close" src="../resources/images/icons/button_cancel.png" ALT="Close"/><div></div></div>
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
        echo $wizard->displayHelp("idp_general");
        echo $optionDisplay->prefilledOptionTable("general", $my_inst->federation);
        ?>
        <button type='button' class='newoption' onclick='getXML("general", "<?php echo $my_inst->federation; ?>")'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    echo $mapCode->htmlShowtime($wizardStyle, $additional);
    if ((\core\CAT::radiusProfilesEnabled() || \core\CAT::hostedIDPEnabled()) && $my_inst->type != "SP") {
        ?>
        <fieldset class="option_container">
            <legend><strong><?php echo _("Media Properties"); ?></strong></legend>
            <?php
            echo $wizard->displayHelp("media");
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
        echo $wizard->displayHelp("support");
        echo $optionDisplay->prefilledOptionTable("support", $fed->tld);
        ?>
        <button type='button' class='newoption' onclick='getXML("support", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button></fieldset>
    <?php
    if ($editMode === 'readonly') {
        $discardLabel = _("Return");
    }
    if ($editMode === 'fullaccess') {
        $discardLabel = _("Discard changes");
    }
    if ($wizardStyle) {
        echo "<p>" . sprintf(_("When you are sure that everything is correct, please click on %sContinue ...%s"), "<button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_CONTINUE . "'>", "</button>") . "</p></form>";
    } else {
        echo "<div><button type='submit' id='submitbutton' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button> <button type='button' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_org.php?inst_id=$my_inst->identifier\"'>".$discardLabel."</button></div></form>";
    }
    echo $deco->footer();
    