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

echo $deco->defaultPagePrelude(sprintf(_("%s: IdP Enrollment Wizard (Step 3)"), \config\Master::APPEARANCE['productname']));
require_once "inc/click_button_js.php";
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>

<!-- JQuery --> 
<script type="text/javascript" src="../external/jquery/jquery-migrate.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script> 
<!-- EAP sorting code -->
<script type="text/javascript" src="js/eapSorter.js"></script> 
<link rel='stylesheet' type='text/css' href='css/eapSorter.css' />
<!-- EAP sorting code end -->
<?php
// initialize inputs
$my_inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
$fed = new \core\Federation($my_inst->federation);
$anonLocal = "anonymous";
$useAnon = FALSE;
$checkuserOuter = FALSE;
$checkuserValue = "anonymous";
$verify = TRUE; // default to check the verify-realm box for new profiles 
$hint = FALSE;
$realm = "";
$prefill_name = "";
$blacklisted = FALSE;

if (isset($_GET['profile_id'])) { // oh! We should edit an existing profile, not create a new one!
    $wizardStyle = FALSE;
    $my_profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page is only for editing RADIUS profiles!");
    }

    $useAnon = $my_profile->getAttributes("internal:use_anon_outer");
    if (count($useAnon) > 0) {
        $useAnon = $useAnon[0]['value'];
        $anonLocal = $my_profile->getAttributes("internal:anon_local_value")[0]['value'];
    }

    $checkuserOuter = $my_profile->getAttributes("internal:checkuser_outer");
    if (count($checkuserOuter) > 0) {
        $checkuserOuter = $checkuserOuter[0]['value'];
        $checkuserValue = $my_profile->getAttributes("internal:checkuser_value")[0]['value'];
    }

    $verify = $my_profile->getAttributes("internal:verify_userinput_suffix")[0]['value'];
    $hint = $my_profile->getAttributes("internal:hint_userinput_suffix")[0]['value'];
    $realm = $my_profile->getAttributes("internal:realm")[0]['value'];

    $prefill_name = $my_profile->name;
    $prefill_methods = $my_profile->getEapMethodsinOrderOfPreference();
    $profile_options = $my_profile->getAttributes();
    // is there a general redirect? it is one which have device = 0
    $blacklistEntries = $my_profile->getAttributes("device-specific:redirect");
    $blacklisted = FALSE;
    foreach ($blacklistEntries as $oneEntry) {
        if (!isset($oneEntry['device']) || $oneEntry['device'] === NULL) { // don't act on device-level redirects
            $blacklisted = $oneEntry['value'];
        }
    }
} else {
    $loggerInstance = new \core\common\Logging();
    $wizardStyle = TRUE;
    $prefill_methods = [];
    $minting = $fed->getAttributes("fed:minted_ca_file");
    $temp_profile = NULL;
    $profile_options = [];
    if (count($minting) > 0) {
        $temp_profile = $my_inst->newProfile(core\AbstractProfile::PROFILETYPE_RADIUS);
        foreach ($minting as $oneMint) {
            $temp_profile->addAttribute("eap:ca_file", $oneMint['lang'], base64_encode($oneMint['value']));
            $my_profile = new \core\ProfileRADIUS($temp_profile->identifier);
            $profile_options = $my_profile->getAttributes();
        }
    }
    if (isset($_POST['username_to_detect']) && isset($_POST['realm_to_detect'])) {
        $detectRealm = $validator->string($_POST['realm_to_detect']);
        $localname = $validator->string($_POST['username_to_detect']);
        $checker = new \core\diag\RADIUSTests($detectRealm, $localname);
        $detectionResult = $checker->autodetectCAWithProbe($localname . "@" . $detectRealm);
        $loggerInstance->debug(2, "CA Auto-Detection yields:");
        $loggerInstance->debug(2, $detectionResult);
        if ($detectionResult['ROOT_CA'] !== NULL) { // we are lucky!
            $temp_profile = $my_inst->newProfile(core\AbstractProfile::PROFILETYPE_RADIUS);
            $temp_profile->addAttribute("eap:ca_file", "C", base64_encode($detectionResult['ROOT_CA']));
            $temp_profile->addAttribute("eap:server_name", "C", $detectionResult['NAME']);
            $temp_profile->setRealm($detectRealm);
            // We have created a RADIUS profile, not SilverBullet, so that function is guaranteed to exist
            $temp_profile/** @scrutinizer ignore-call */->setRealmCheckUser(TRUE, $localname);
            $my_profile = new \core\ProfileRADIUS($temp_profile->identifier);
            $profile_options = $my_profile->getAttributes();
            $realm = $my_profile->getAttributes("internal:realm")[0]['value'];
            $checkuserOuter = TRUE;
            $checkuserValue = $my_profile->getAttributes("internal:checkuser_value")[0]['value'];
        }
    }
    if ($temp_profile !== NULL) {
    }
}
?>
</head>
<body>
    <?php
    echo $deco->productheader("ADMIN-IDP");
    ?>
    <h1>
        <?php
        if ($wizardStyle) {
            echo _("Step 3: Defining a user group profile");
        } else {
            printf(_("Edit profile '%s' ..."), $prefill_name);
        }
        ?>
    </h1>
    <?php
    echo $uiElements->instLevelInfoBoxes($my_inst);

    echo "<form enctype='multipart/form-data' action='edit_profile_result.php?inst_id=$my_inst->identifier" . ($my_profile !== NULL ? "&amp;profile_id=" . $my_profile->identifier : "") . "' method='post' accept-charset='UTF-8'>
                <input type='hidden' name='MAX_FILE_SIZE' value='" . \config\Master::MAX_UPLOAD_SIZE . "'>";
    $optionDisplay = new \web\lib\admin\OptionDisplay($profile_options, \core\Options::LEVEL_PROFILE);
    ?>
    <fieldset class="option_container">
        <legend>
            <strong><?php echo _("General Profile properties"); ?></strong>
        </legend>
        <?php
        if ($wizardStyle) {
            echo "<p>" . _("We will now define a profile for your user group(s).  You can add as many profiles as you like by choosing the appropriate button on the end of the page. After we are done, the wizard is finished and you will be taken to the main IdP administration page.") . "</p>";
        }
        ?>
        <h3><?php echo _("Profile Name and RADIUS realm"); ?></h3>
        <?php
        if ($wizardStyle) {
            echo "<p>" . _("First of all we need a name for the profile. This will be displayed to end users, so you may want to choose a descriptive name like 'Professors', 'Students of the Faculty of Bioscience', etc.") . "</p>";
            echo "<p>" . _("Optionally, you can provide a longer descriptive text about who this profile is for. If you specify it, it will be displayed on the download page after the user has selected the profile name in the list.") . "</p>";
            echo "<p>" . _("You can also tell us your RADIUS realm. ");
            if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] !== NULL) {
                printf(_("This is useful if you want to use the sanity check module later, which tests reachability of your realm in the %s infrastructure. "), \config\ConfAssistant::CONSORTIUM['display_name']);
            }
            echo _("It is required to enter the realm name if you want to support anonymous outer identities (see below).") . "</p>";
        }

        echo $optionDisplay->prefilledOptionTable("profile", $my_inst->federation);
        ?>
        <button type='button' class='newoption' onclick='getXML("profile", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button>
        <table>
            <caption><?php echo _("Basic Realm Information"); ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Realm:"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Realm input field"); ?></th>
            </tr>
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
                                        
                                        this.form.elements[\"checkuser_support\"].removeAttribute(\"disabled\");
                                        document.getElementById(\"checkuser_label\").removeAttribute(\"style\");
                                        
                                        document.getElementById(\"verify_label\").removeAttribute(\"style\");
                                        document.getElementById(\"hint_label\").removeAttribute(\"style\");

                                      } else
                                      { this.form.elements[\"anon_support\"].checked = false;
                                        this.form.elements[\"anon_support\"].setAttribute(\"disabled\", \"disabled\");
                                        this.form.elements[\"anon_local\"].setAttribute(\"disabled\", \"disabled\");
                                        document.getElementById(\"anon_support_label\").setAttribute(\"style\", \"color:#999999\");
                                        
                                        this.form.elements[\"checkuser_support\"].checked = false;
                                        this.form.elements[\"checkuser_support\"].setAttribute(\"disabled\", \"disabled\");
                                        this.form.elements[\"checkuser_local\"].setAttribute(\"disabled\", \"disabled\");
                                        document.getElementById(\"checkuser_label\").setAttribute(\"style\", \"color:#999999\");
                                                                                
                                        this.form.elements[\"hint_support\"].checked = false;
                                        this.form.elements[\"hint_support\"].setAttribute(\"disabled\", \"disabled\");
                                        
                                        document.getElementById(\"hint_label\").setAttribute(\"style\", \"color:#999999\");
                                      };'/>"; ?>
                </td>
            </tr>
        </table>
        <h3><?php echo _("Realm Options"); ?></h3>

        <?php
        if ($wizardStyle) {
            echo "<p>" . sprintf(_("Some installers support a feature called 'Anonymous outer identity'. If you don't know what this is, please read <a href='%s'>this article</a>."), "https://confluence.terena.org/display/H2eduroam/eap-types") . "</p>";
            echo "<p>" . _("On some platforms, the installers can suggest username endings and/or verify the user input to contain the realm suffix.") . "</p>";
            echo "<p>" . _("The realm check feature needs to know an outer ID which actually gets a chance to authenticate. If your RADIUS server lets only select usernames pass, it is useful to supply the information which of those (outer ID) username we can use for testing.") . "</p>";
        }
        ?>
        <p>


            <!-- UI table to align elements-->
        <table>
            <caption><?php echo _("Username Handling Options"); ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Option name"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Option checkbox"); ?></th>
            </tr>
            <tr>
                <th colspan="2" style="text-align: left;"><?php echo _("Outer Identity Handling"); ?></th>
            </tr>
            <tr>
                <!-- checkbox and input field for anonymity support, available only when realm is known-->
                <td>
                    <span id='anon_support_label' style='<?php echo ($realm == "" ? "color:#999999" : "" ); ?>'>
                        <?php echo _("Enable Anonymous Outer Identity:"); ?>
                    </span>
                </td>
                <td>
                    <input type='checkbox' <?php echo ($useAnon != FALSE ? "checked" : "" ) . ($realm == "" ? " disabled" : "" ); ?> name='anon_support' onclick='
                            if (this.form.elements["anon_support"].checked !== true) {
                                this.form.elements["anon_local"].setAttribute("disabled", "disabled");
                            } else {
                                this.form.elements["anon_local"].removeAttribute("disabled");
                            }
                            ;'/>
                    <input type='text' <?php echo ($useAnon == FALSE ? "disabled" : "" ); ?> name='anon_local' value='<?php echo $anonLocal; ?>'/>
                </td>
            </tr>
            <tr>
                <!-- checkbox and input field for check realm outer id, available only when realm is known-->
                <td>
                    <span id='checkuser_label' style='<?php echo ($realm == "" ? "color:#999999" : "" ); ?>'>
                        <?php echo _("Use special Outer Identity for realm checks:"); ?>
                    </span>
                </td>
                <td>
                    <input type='checkbox' <?php echo ($checkuserOuter != FALSE ? "checked" : "" ) . ($realm == "" ? " disabled" : "" ); ?> name='checkuser_support' onclick='
                            if (this.form.elements["checkuser_support"].checked !== true) {
                                this.form.elements["checkuser_local"].setAttribute("disabled", "disabled");
                            } else {
                                this.form.elements["checkuser_local"].removeAttribute("disabled");
                            }
                            ;'/>
                    <input type='text' <?php echo ($checkuserOuter == FALSE ? "disabled" : "" ); ?> name='checkuser_local' value='<?php echo $checkuserValue; ?>'/>
                </td>
            </tr>
            <tr>
                <th colspan="2" style="border-top: 2px solid; text-align: left;"><?php echo _("Inner Identity (Username) Handling"); ?></th>
            </tr>
            <tr>
                <!-- checkbox for "verify-->
                <td>
                    <span id='verify_label'>
                        <?php echo _("Enforce realm suffix in username"); ?>
                    </span>
                </td>
                <td>
                    <input type='checkbox' <?php
                    echo ($verify != FALSE ? "checked" : "" );
                    ?> name='verify_support' onclick='
                                if (this.form.elements["verify_support"].checked !== true || this.form.elements["realm"].value.length == 0) {
                                    this.form.elements["hint_support"].setAttribute("disabled", "disabled");
                                } else {
                                    this.form.elements["hint_support"].removeAttribute("disabled");
                                }
                                ;'/>
                </td>
            </tr>
            <tr>
                <td>
                    <span id='hint_label' style='<?php echo ($realm == "" ? "color:#999999" : "" ); ?>'>
                        <?php echo _("Enforce exact realm in username"); ?>
                    </span>
                </td>
                <td>
                    <input type='checkbox' <?php echo ($verify == FALSE ? "disabled" : "" ); ?> name='hint_support' <?php echo ( $hint != FALSE ? "checked" : "" ); ?> />
                </td>
            </tr>
        </table>
    </p>

    <h3><?php echo _("Installer Download Location"); ?></h3>

    <?php
    if ($wizardStyle) {
        echo "<p>" . _("The CAT has a download area for end users. There, they will, for example, learn about the support pointers you entered earlier. The CAT can also immediately offer the installers for the profile for download. If you don't want that, you can instead enter a web site location where you want your users to be redirected to. You, as the administrator, can still download the profiles to place them on that page (see the 'Compatibility Matrix' button on the dashboard).") . "</p>";
    }
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
    if ($wizardStyle) {
        echo "<p>" . _("Now, we need to know which EAP types your IdP supports. If you support multiple EAP types, you can assign every type a priority (1=highest). This tool will always generate an automatic installer for the EAP type with the highest priority; only if the user's device can't use that EAP type, we will use an EAP type further down in the list.") . "</p>";
    }
    ?>
    <?php
    $methods = \core\common\EAP::listKnownEAPTypes();
    ?>

    <?php
// new EAP sorting code  

    foreach ($methods as $a) {
        $display = $a->getPrintableRep();
        $enabled = FALSE;
        foreach ($prefill_methods as $prio => $value) {
            if ($a->getPrintableRep() == $value->getPrintableRep()) {
                $enabled = TRUE;
                $countactive = $prio + 1;
            }
        }
    }
    ?>
    <div>
        <table style="border:none">
            <caption><?php echo _("EAP type support"); ?></caption>
            <tr>
                <th scope="row" style="vertical-align:top; padding:1em">
                    <?php echo _('Supported EAP types for this profile'); ?>
                </th>
                <td id="supported_eap">
                    <ol id="sortable1" class="eapmethods">
                        <?php
                        $D = [];
                        foreach ($prefill_methods as $prio => $value) {
                            print '<li>' . $value->getPrintableRep() . "</li>\n";
                            $D[$value->getPrintableRep()] = $prio;
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
                <th scope="row" style="vertical-align:top; padding:1em">
                    <?php echo _('Unsupported EAP types'); ?>
                </th>
                <td style="vertical-align:top" id="unsupported_eap">
                    <ol id="sortable2" class="eapmethods">
                        <?php
                        foreach ($methods as $a) {
                            if ($a == \core\common\EAP::EAPTYPE_SILVERBULLET) {
                                continue;
                            }
                            $display = $a->getPrintableRep();
                            if (!isset($D[$a->getPrintableRep()])) {
                                print '<li class="eap1">' . $a->getPrintableRep() . "</li>\n";
                            }
                        }
                        ?>
                    </ol>
                </td>
            </tr>
        </table>
    </div>
    <?php
    foreach ($methods as $a) {
        $display = $a->getPrintableRep();
        $v = isset($D[$display]) ? $D[$display] : '';
        print '<input type="hidden" class="eapm" name="' . $display . '" id="EAP-' . $display . '" value="' . $display . '">';
        print '<input type="hidden" class="eapmv" name="' . $display . '-priority" id="EAP-' . $display . '-priority" value="' . $v . '">';
    }
    ?>
    <br style="clear:both;" />
</fieldset>
<?php
$idp_options = $my_inst->getAttributes();

$optionsAlreadySet = array_column($idp_options, "name");

$has_support_options = [];
$has_media_options = [];
$has_eap_options = [];
$support_text = "";
$media_text = "";
$eap_text = "";

foreach ($optionsAlreadySet as $optionNames) {
    if (preg_match("/^support:/", $optionNames)) {
        $has_support_options[$optionNames] = "SET";
        $support_text .= "<li><strong>" . $uiElements->displayName($optionNames) . "</strong></li>";
    }
    if (preg_match("/^media:/", $optionNames)) {
        $has_media_options[$optionNames] = "SET";
        $media_text .= "<li><strong>" . $uiElements->displayName($optionNames) . "</strong></li>";
    }
}
$fields = [
    "support" => _("Helpdesk Details for this profile"),
    "eap" => _("EAP Details for this profile"),
    "media" => _("Media Properties for this profile")];

foreach ($fields as $name => $description) {
    echo "<fieldset class='option_container' id='" . $name . "_override'>
    <legend><strong>$description</strong></legend>
    <p>";

    if (count(${"has_" . $name . "_options"}) > 0) {
        printf(ngettext("The option %s is already defined IdP-wide. If you set it here on profile level, this setting will override the IdP-wide one.", "The options %s are already defined IdP-wide. If you set them here on profile level, these settings will override the IdP-wide ones.", count(${"has_" . $name . "_options"})), "<ul>" . ${$name . "_text"} . "</ul>");
    }

    echo "</p>";

    echo $optionDisplay->prefilledOptionTable($name, $my_inst->federation);
    ?>
    <button type='button' class='newoption' onclick='getXML("<?php echo $name ?>", "<?php echo $my_inst->federation ?>")'><?php echo _("Add new option"); ?></button>
    <?php
    echo "</fieldset>";
}

if ($wizardStyle) {
    echo "<p>" . _("When you are sure that everything is correct, please click on 'Save data' and you will be taken to your IdP Dashboard page.") . "</p>";
}
echo "<p><button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button><button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_org.php?inst_id=$my_inst->identifier\"'>" . _("Discard changes") . "</button></p></form>";
echo $deco->footer();
