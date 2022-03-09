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
 * This page is used to edit a RADIUS profile by its administrator.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();
// initialize inputs
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
$myfed = new \core\Federation($my_inst->federation);

if (!isset($_GET['deployment_id'])) {
    if (isset($_POST['consortium']) && ( $_POST['consortium'] == "eduroam" ||
            ( $_POST['consortium'] == "OpenRoaming" && count($myfed->getAttributes("fed:openroaming")) > 0 )
            )
    ) {
        $my_inst->newDeployment(\core\AbstractDeployment::DEPLOYMENTTYPE_MANAGED, $_POST['consortium']);
        header("Location: overview_org.php?inst_id=" . $my_inst->identifier);
        exit(0);
    } else {
        throw new Exception("Desired consortium for Managed SP needs to be specified, and allowed!");
    }
}
// if we have come this far, we are editing an existing deployment

$deployment = $validator->existingDeploymentManaged($_GET['deployment_id'], $my_inst);
error_log('MGW edit_hotspot '.serialize($_POST));
if (isset($_POST['submitbutton'])) {
    switch ($_POST['submitbutton']) {
        case web\lib\common\FormElements::BUTTON_TERMSOFUSE_NEEDACCEPTANCE:
            if (count($deployment->getAttributes("hiddenmanagedsp:tou_accepted")) == 0) {
                //terms of use popup, going interactive
                echo $deco->defaultPagePrelude(sprintf(_("%s: %s Terms of Use"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureHotspot));
                require_once "inc/click_button_js.php";
                ?>
                </head>
                <body>
                    <?php
                    echo $deco->productheader("ADMIN-SP");
                    ?>
                    <h1>
                        <?php
                        echo _("Terms of Use Acceptance");
                        ?>
                    </h1>
                    <div id="sb-popup-message" >
                        <div id="overlay"></div>
                        <div id="msgbox">
                            <div style="top: 100px;">
                                <div class="graybox">
                                    <h1><?php echo sprintf(_("%s - Terms of Use"), core\DeploymentManaged::PRODUCTNAME); ?></h1>
                                    <div class="containerbox" style="position: relative;">
                                        <hr>
                                        <?php echo $deployment->termsAndConditions; ?>
                                        <hr>
                                        <form enctype='multipart/form-data' action='edit_hotspot.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;deployment_id=<?php echo $deployment->identifier; ?>' method='post' accept-charset='UTF-8'>
                                            <div style="position: relative; padding-bottom: 5px;">
                                                <input type="checkbox" name="agreement" value="true"> <label><?php echo _("I have read and agree to the terms."); ?></label>
                                            </div>
                                            <button type="submit" name="submitbutton" value="<?php echo \web\lib\common\FormElements::BUTTON_TERMSOFUSE_ACCEPTED ?>"><?php echo _("Continue"); ?></button>
                                            <button class="delete" type="submit" name="command" value="<?php echo \web\lib\common\FormElements::BUTTON_CLOSE ?>"><?php echo _("Abort"); ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    echo $deco->footer();
                }
                exit(0);
            case web\lib\common\FormElements::BUTTON_TERMSOFUSE_ACCEPTED:
                if (isset($_POST['agreement']) && $_POST['agreement'] == "true") {
                    $deployment->addAttribute("hiddenmanagedsp:tou_accepted", NULL, 1);
                }
                header("Location: overview_org.php?inst_id=" . $my_inst->identifier);
                exit(0);
            case web\lib\common\FormElements::BUTTON_DELETE:
                $response = $deployment->setRADIUSconfig();
                if (in_array('OK', $response)) {
                    $deployment->deactivate();
                }
                header("Location: overview_org.php?inst_id=" . $my_inst->identifier . '&' . urldecode(http_build_query($response)));
                exit(0);
            case web\lib\common\FormElements::BUTTON_REMOVESP:
                error_log("MGW Remove deployment ".serialize($deployment));
                $deployment->remove();
                header("Location: overview_org.php?inst_id=" . $my_inst->identifier);
                exit(0);
            case web\lib\common\FormElements::BUTTON_ACTIVATE:
                if (count($deployment->getAttributes("hiddenmanagedsp:tou_accepted")) > 0) {
                    $response = $deployment->setRADIUSconfig();
                    if (in_array('OK', $response)) {
                        $deployment->activate();
                    }
                    header("Location: overview_org.php?inst_id=" . $my_inst->identifier . '&' . urldecode(http_build_query($response)));
                    exit(0);
                } else {
                    throw new Exception("Activate button pushed without acknowledged ToUs!");
                }
            case web\lib\common\FormElements::BUTTON_SAVE:
                $optionParser = new web\lib\admin\OptionParser();
                $postArray = $_POST;
                if (isset($postArray['vlan'])) {
                    $postArray['option']['S1234567890'] = "managedsp:vlan#int##";
                    $postArray['value']['S1234567890-integer'] = $postArray['vlan'];
                }
                if (isset($postArray['opname'])) {
                    $postArray['option']['S1234567891'] = "managedsp:operatorname#string##";
                    $postArray['value']['S1234567891-string'] = $postArray['opname'];
                }
                $optionParser->processSubmittedFields($deployment, $postArray, $_FILES);
                // if ToU were already accepted, keep them (would otherwise be auto-deleted
                if (count($deployment->getAttributes("hiddenmanagedsp:tou_accepted")) > 0) {
                    $deployment->addAttribute("hiddenmanagedsp:tou_accepted", NULL, 1);
                }
                // reinstantiate object with new values
                $deploymentReinstantiated = $validator->existingDeploymentManaged($deployment->identifier, $my_inst);
                if ($deploymentReinstantiated->status == core\DeploymentManaged::ACTIVE) {
                    $deploymentReinstantiated->status = core\DeploymentManaged::INACTIVE;
                    $response = $deploymentReinstantiated->setRADIUSconfig();
                } else {
                    $response = ['NOOP', 'NOOP'];
                }
                header("Location: overview_org.php?inst_id=" . $my_inst->identifier . '&' . urldecode(http_build_query($response)));
                exit(0);
            default:
                throw new Exception("Unknown button action requested!");
        }
    }
    if (isset($_POST['command'])) {
        switch ($_POST['command']) {
        case web\lib\common\FormElements::BUTTON_CLOSE:
            header("Location: overview_org.php?inst_id=" . $my_inst->identifier);
            exit(0);
        default:
            header("Location: overview_org.php?inst_id=" . $my_inst->identifier);
            exit(0);
        }
    }
    $vlan = $deployment->getAttributes("managedsp:vlan")[0]['value'] ?? NULL;
    $opname = $deployment->getAttributes("managedsp:operatorname")[0]['value'] ?? "";

    echo $deco->defaultPagePrelude(sprintf(_("%s: Enrollment Wizard (Step 3)"), \config\Master::APPEARANCE['productname']));
    require_once "inc/click_button_js.php";
    ?>
    <script src="js/XHR.js" type="text/javascript"></script>
    <script src="js/option_expand.js" type="text/javascript"></script>

</head>
<body>
    <?php
    echo $deco->productheader("ADMIN-SP");
    ?>
    <h1>
        <?php
        printf(_("Editing %s deployment"), $uiElements->nomenclatureHotspot);
        ?>
    </h1>
    <?php
    echo $uiElements->instLevelInfoBoxes($my_inst);
    $deploymentOptions = $deployment->getAttributes();
    echo "<form enctype='multipart/form-data' action='edit_hotspot.php?inst_id=$my_inst->identifier&amp;deployment_id=$deployment->identifier' method='post' accept-charset='UTF-8'>
                <input type='hidden' name='MAX_FILE_SIZE' value='" . \config\Master::MAX_UPLOAD_SIZE . "'>";
    $optionDisplay = new \web\lib\admin\OptionDisplay($deploymentOptions, \core\Options::LEVEL_PROFILE);
    ?>
    <fieldset class='option_container' id='managedsp_override'>
        <legend>
            <strong>
                <?php
                $tablecaption = _("Options for this deployment");
                echo $tablecaption;
                ?>
            </strong>
        </legend>
        <table>
            <caption><?php echo $tablecaption; ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value"); ?></th>
            </tr>
            <tr>
                <!-- input for Operator-Name override-->
                <td>
                    <span id='opname_label'>
                        <?php echo _("Custom Operator-Name:"); ?>
                    </span>
                </td>
                <td>
                    <input type='text' width="20" name="opname" value="<?php echo $opname; ?>"/>
                </td>
            </tr>
            <tr>
                <!-- input for VLAN identifier for home users-->
                <td>
                    <span id='vlan_label'>
                        <?php echo sprintf(_("VLAN tag for own users%s:"), ($vlan === NULL ? "" : " " . _("(unset with '0')"))); ?>
                    </span>
                </td>
                <td>
                    <input type='number' width="4" name='vlan' <?php
                    if ($vlan !== NULL) {
                        echo "value='$vlan'";
                    }
                    ?>/>
                </td>    
            </tr>
            <tr>
        </table>
        <?php
        echo $optionDisplay->prefilledOptionTable("managedsp", $my_inst->federation);
        ?>
        <button type='button' class='newoption' onclick='getXML("managedsp", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button>
    </fieldset>

    <?php
    echo "<p><button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button><button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_org.php?inst_id=$my_inst->identifier\"'>" . _("Discard changes") . "</button></p></form>";
    echo $deco->footer();

    