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

namespace core;

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$instMgmt = new \core\UserManagement();
$deco = new \web\lib\admin\PageDecoration();
$uiElements = new \web\lib\admin\UIElements();

echo $deco->defaultPagePrelude(sprintf(_("%s: User Management"), \config\Master::APPEARANCE['productname']));
$user = new \core\User($_SESSION['user']);
$user->edugain = $user->isFromEduGAIN();
$wizard = new \web\lib\admin\Wizard(false);
$langInstance = new \core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";
?>

<script type="text/javascript"><?php require_once "inc/overview_js.php" ?></script>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script> 
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<script type="text/javascript" src="js/wizard.js"></script> 
<link rel='stylesheet' type='text/css' href='css/wizard.css.php' />
</head>
<script>
    $(document).ready(function() {
        $("button.XXX").on("click", function(event) {
            event.preventDefault();
            var url = $(this).parent().attr('action');
            $.post(url, {submitbutton: <?php echo \web\lib\common\FormElements::BUTTON_TAKECONTROL; ?>}, function(data) {
                location.reload();
            });
        });
        
        
        $("button.self-service").on("click", function(event) {
            event.preventDefault();
            $("#spin").show();
            var form = $(this).parent();
            var url = form.attr('action');
            var info_span = form.find(".token_confirm");            
            $.ajax({
              type: "POST",
              url: url,
              myForm: form,
              data: form.serialize(),
              success: function(data) {
                            var info_span = this.myForm.find("span.token_confirm");
                            var send_button = this.myForm.find("button.self-service");
                            $("#spin").hide();
                            if (data == "SUCCESS") {
                            info_span.show();
                            send_button.hide();
                            } else {
                                alert("<?php _("Something went wrong with institution creation. Please contact your federation administrator.") ?>")
                            }
                        },
              dataType: "html"
            });
        });
        
    });
</script>
<style>
    table.inst-selection th {
        text-align: <?php echo $start ?>;
    }
</style>
    
<body>
    <?php
    echo $deco->productheader("ADMIN");
    ?>
    <div id="wizard_help_window"><img id="wizard_menu_close" src="../resources/images/icons/button_cancel.png" ALT="Close"/><div></div></div>
    <h1>
        <?php echo _("User Overview"); ?>
    </h1>
    <div class="infobox">
        <h2><?php
            $tablecaption = _("Your Personal Information");
            echo $tablecaption
            ?></h2>
        <table>
            <caption><?php echo $tablecaption; ?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable"); ?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value"); ?></th>
            </tr>

            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo str_replace('\'', '\x27', str_replace('"', '\x22', $_SESSION["user"])); ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>
    <div>
<img alt='Loading ...' src='../resources/images/icons/loading51.gif' id='spin' class='TMW' style='position:absolute;left: 50%; top: 50%; transform: translate(-100px, -50px); display:none;'>

        <?php
        if (\config\Master::DB['USER']['readonly'] === FALSE) {
            echo "<a href='edit_user.php'><button>" . _("Edit User Details") . "</button></a>";
        }

        if ($user->isFederationAdmin()) {
            echo "<form action='overview_federation.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . sprintf(_('Click here for %s management tasks'), $uiElements->nomenclatureFed) . "</button></form>";
        }
        if ($user->isSuperadmin()) {
            echo "<form action='112365365321.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . _('Click here to access the superadmin page') . "</button></form>";
        }
        ?>
    </div>
    <?php
    $instMgmt->listInstitutionsByAdmin();
    \core\common\Logging::debug_s(4, $instMgmt->currentInstitutions, "Current Inst:\n", "\n");
    $hasInst = $instMgmt->currentInstitutions['existing'];
    if (\config\ConfAssistant::CONSORTIUM['name'] == 'eduroam') {
        $target = "https://wiki.geant.org/x/25g7Bw"; // CAT manual, outdated
        if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == "LOCAL") {
            $target = "https://wiki.geant.org/x/6Zg7Bw"; // Managed IdP manual
        }
        $helptext = "<h3 style='display:inline;'>" . sprintf(_("(Need help? Refer to the <a href='%s'>%s administrator manual</a>)"), $target, $uiElements->nomenclatureParticipant) . "</h3>";
    } else {
        $helptext = "";
    }

    if (sizeof($hasInst) > 0) {
        // we need to run the Federation constructor
        $cat = new \core\CAT;
        /// first parameter: number of Identity Providers; second param is the literal configured term for 'Identity Provider' (you may or may not be able to add a plural suffix for your locale)
        echo "<h2>" . sprintf(ngettext("You are managing the following <span style='display:none'>%d </span>%s:", "You are managing the following <strong>%d</strong> %s:", sizeof($hasInst)), sizeof($hasInst), $uiElements->nomenclatureParticipant) . "</h2>";
        $instlist = [];
        $my_idps = [];
        $myFeds = [];
        $fed_count = 0;

        foreach ($hasInst as $instId) {
            $my_inst = new \core\IdP($instId);
            $inst_name = $my_inst->name;
            $fed_id = strtoupper($my_inst->federation);
            $my_idps[$fed_id][$instId] = strtolower($inst_name);
            $myFeds[$fed_id] = $cat->knownFederations[$fed_id]['name'];
            $instlist[$instId] = ["country" => strtoupper($my_inst->federation), "name" => $inst_name, "object" => $my_inst];
        }
        asort($myFeds);

        foreach ($instlist as $key => $row_id) {
            $country[$key] = $row_id['country'];
            $name[$key] = $row_id['name'];
        }
        ?>
        <table class='user_overview'>
            <caption><?php echo sprintf(_("%s Management Overview"), $uiElements->nomenclatureParticipant); ?></caption>
            <tr>
                <th scope='col'><?php echo sprintf(_("%s Name"), $uiElements->nomenclatureParticipant); ?></th>
                <th scope="col"><?php echo sprintf(_("Other admins of this %s"), $uiElements->nomenclatureParticipant); ?></th>
                <th scope='col'><?php
                    if (\config\Master::DB['INST']['readonly'] === FALSE) {
                        echo _("Management");
                    };
                    ?>
                </th>
                <th scope='col' style='background-color:red;'>
                    <?php
                    if (\config\Master::DB['INST']['readonly'] === FALSE) {
                        echo _("Danger Zone");
                    }
                    ?>
                </th>
            </tr>
            <?php
            foreach ($myFeds as $fed_id => $fed_name) {

/// nomenclature 'fed', fed name, nomenclature 'inst'
                ?>
                <tr>
                    <td colspan='4'>
                        <strong><?php echo sprintf(_("%s %s: %s list"), $uiElements->nomenclatureFed, $fed_name, $uiElements->nomenclatureParticipant); ?></strong>
                    </td>
                </tr>
                <?php
                $fedOrganisations = $my_idps[$fed_id];
                asort($fedOrganisations);
                foreach ($fedOrganisations as $index => $myOrganisation) {
                    $oneinst = $instlist[$index];
                    $the_inst = $oneinst['object'];
                    ?>
                    <tr>
                        <td>
                            <a href="overview_org.php?inst_id=<?php echo $the_inst->identifier; ?>"><?php echo $oneinst['name']; ?></a>
                        </td>
                        <td>
                            <?php
                            $admins = $the_inst->listOwners();
                            $blessedUser = FALSE;
                            foreach ($admins as $number => $username) {
                                if ($username['ID'] != $_SESSION['user']) {
                                    /*
                                     * $coadmin = new \core\User($username['ID']);
                                     * $coadmin_name = $coadmin->getAttributes('user:realname');
                                     * if (count($coadmin_name) > 0) {
                                     *     echo $coadmin_name[0]['value'] . "<br/>";
                                     *     unset($admins[$number]);
                                     *
                                     * }
                                     */
                                } else { // don't list self
                                    unset($admins[$number]);
                                    if ($username['LEVEL'] == "FED") {
                                        $blessedUser = TRUE;
                                    }
                                }
                                $otherAdminCount = count($admins); // only the unnamed remain
                            }
                            if ($otherAdminCount > 0) {
                                echo sprintf(ngettext("You and %d other user", "You and %d other users", $otherAdminCount), $otherAdminCount);
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($blessedUser && \config\Master::DB['INST']['readonly'] === FALSE) {
                                echo "<div style='white-space: nowrap;'><form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $the_inst->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'><button type='submit'>" . _("Add/Remove Administrators") . "</button></form></div>";
                            }
                            ?>
                        </td>
                        <td> <!-- danger zone --> 

                            <form action='edit_participant_result.php?inst_id=<?php echo $the_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                                <button class='delete' type='submit' name='submitbutton' value='<?php echo \web\lib\common\FormElements::BUTTON_DELETE; ?>' onclick="return confirm('<?php echo ( \config\ConfAssistant::CONSORTIUM['selfservice_registration'] === NULL ? sprintf(_("After deleting the %s, you can not recreate it yourself - you need a new invitation token from the %s administrator!"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureFed) . " " : "" ) . sprintf(_("Do you really want to delete your %s %s?"), $uiElements->nomenclatureParticipant, $the_inst->name); ?>')"><?php echo sprintf(_("Delete %s"), $uiElements->nomenclatureParticipant); ?></button>
                            </form>
                            <form action='edit_participant_result.php?inst_id=<?php echo $the_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
                                <button class='delete' type='submit' name='submitbutton' value='<?php echo \web\lib\common\FormElements::BUTTON_FLUSH_AND_RESTART; ?>' onclick="return confirm('<?php echo sprintf(_("This action will delete all properties of the %s and start over the configuration from scratch. Do you really want to reset all settings of the %s %s?"), $uiElements->nomenclatureParticipant, $uiElements->nomenclatureParticipant, $the_inst->name); ?>')"><?php echo sprintf(_("Reset all %s settings"), $uiElements->nomenclatureParticipant); ?></button>
                            </form>

                        </td>
                    </tr>
                    <?php
                }
            }
            ?>
        </table>
        <?php
    } else {
        echo "<h2>" . sprintf(_("You are not managing any %s."), $uiElements->nomenclatureParticipant) . "</h2>";
    }
    
    if (\config\Master::DB['INST']['readonly'] === FALSE) {
        if (\config\ConfAssistant::CONSORTIUM['selfservice_registration'] === NULL) {
            echo "<p>" . sprintf(_("Please ask your %s administrator to invite you to become an %s administrator."), $uiElements->nomenclatureFed, $uiElements->nomenclatureParticipant) . "</p>";
            echo "<hr/>
             <div style='white-space: nowrap;'>
                <form action='action_enrollment.php' method='get' accept-charset='UTF-8'>" .
            sprintf(_("Did you receive an invitation token to manage an %s? Please paste it here:"), $uiElements->nomenclatureParticipant) .
            "        <input type='text' id='token' name='token'/>
                    <button type='submit'>" .
            _("Go!") . "
                    </button>
                </form>
             </div>";
        }

        elseif (\config\ConfAssistant::CONSORTIUM['selfservice_registration'] === 'eduGAIN') {
            if ($user->edugain !== false) {                
                $resyncedInst = $instMgmt->currentInstitutions['resynced'];
                $newInst = $instMgmt->currentInstitutions['new'];
                $entitlementCatInst = $instMgmt->currentInstitutions['entitlement'];
                if (count($resyncedInst) > 0) {
                    $helpText = _("You can add organisations to your profile since your mail is listed as their administrator in the eduroam database.
                            There may be several reasons why you are seeing this, for instance:
                            <ul>
                            <li>your mail has been added as the admin to institutions that you did not manage before
                            <li>you have logged in via an account in a different IdP but the returned email address is the same as before
                            <li>your IdP has been modified and it has a different entityId now
                            <li>your IdP has changed it's behaviour, for instance it was previously sending the eduPersonTargetedId attribute but now it is only sending pairwise-id
                            </ul>
                            If you accept then invitation tokens will be automatically sent to your email address.");
                    print $wizard->displayHelpText($helpText);
                    
                    echo "<h3>"._("According to the information obtained from your login attributes, you are entitled to be the administrator of the following CAT institutions:")."</h3>";                        

                    echo "<table class='inst-selection'>";
                    foreach ($resyncedInst as $id) {
                            echo "<tr><td>";
                            $idp = new \core\IdP($id);
                            $names = $idp->getAttributes('general:instname');
                            $i =0;
                            foreach ($names as $onename) {
                                if ($i > 0) {
                                    echo "; ";
                                }
                                $i++;
                                echo "[".$onename['lang']."] ".$onename['value'];
                            }
?>
                </td><td><div>
                <form action='inc/sendinvite.inc.php?inst_id=<?php echo $id; ?>' method='post' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'>
                    <input type="hidden" name="mailaddr" value="<?php echo $_SESSION['auth_email'];?>"/>
                    <input type="hidden" name="self_registration"/>
                    <button type='submit' name='submitbutton' class='self-service' id='submintbutton_<?php echo $id; ?>' onclick='document.getElementById("spin").style.display = "block"' value='<?php echo \web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Send token"); ?></button><span style='display: none; font-weight: bold' class='token_confirm' id='token_confirm_<?php echo $id; ?>'><?php echo _("Token sent")?></span>
                </form>    
                </div></td></tr>
 <?php                           
                    }
                    echo "</table>";  
                }
                if (count($newInst) > 0) {
                    echo "<h3>"._("The eduroam database says you are an administrator of these following institutions, but there seem to be no matching institutions in CAT.")."</h3>";
                    echo "<table class='inst-selection'>";
                    foreach ($newInst as $inst) {
                        echo "<tr><td>";
                        $i =0;
                        foreach ($inst[1] as $lang => $name) {
                            if ($i > 0) {
                                echo "; ";
                            }
                            $i++;
                            echo "[$lang] $name";
                        }
                        ?>
                </td><td><div>
                <form action='inc/sendinvite.inc.php' method='post' accept-charset='UTF-8'>
                    <input type="hidden" name="mailaddr" value="<?php echo $_SESSION['auth_email'];?>"/>
                    <input type="hidden" name="creation" value="existing"/>
                    <input type="hidden" name="self_registration"/>
                    <input type="hidden" name="country" value="<?php echo $inst[2]; ?>"/>
                    <input type="hidden" name="externals" value="<?php echo "$inst[2]-$inst[0]"; ?>"/>
                <button type='submit' name='submitbutton' class='self-service' id='submintbutton_<?php echo $id; ?>' value='<?php echo \web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Send token"); ?></button><span style='display: none; font-weight: bold' class='token_confirm' id='token_confirm_<?php echo $id; ?>'><?php echo _("Token sent")?></span>
                </form>
                </div></td></tr>   
                   <?php }
                    echo "</table>";
                }
                if (count($entitlementCatInst) > 0) {
                    echo "<table class='inst-selection'>";
                    echo "<p>"._("According to the information obtained from your login attributes, you are entitled to be the administrator of the following CAT institutions:<p>");                        
                    foreach ($entitlementCatInst as $entitlementInst) {
                            $idp = new \core\IdP($entitlementInst[0]);
                            echo "<tr><th>";
                            $names = $idp->getAttributes('general:instname');
                            $i =0;
                            foreach ($names as $onename) {
                                if ($i > 0) {
                                    echo "; ";
                                }
                                $i++;
                                echo "[".$onename['lang']."] ".$onename['value'];
                            }
                            echo "</th><td>";
                            echo "<form action='inc/manageAdmins.inc.php?inst_id=$idp->identifier' method='post'>";                            
                            echo "<button type='submit' class='XXX' value='" . \web\lib\common\FormElements::BUTTON_TAKECONTROL . "'>" . _("take control"). "</button><br/>";
                            echo "</form>";
                            echo "</td></tr>";
                    }
                    echo "</table>";               
                }
            }       
        }
        else { // self-service registration is allowed! Yay :-)
            echo "<hr>
            <div style='white-space: nowrap;'>
        <form action='action_enrollment.php' method='get'><button type='submit' accept-charset='UTF-8'>
                <input type='hidden' id='token' name='token' value='SELF-REGISTER'/>" .
            sprintf(_("New %s Registration"), $uiElements->nomenclatureParticipant) . "
            </button>
        </form>
        </div>";
        }
        echo "<hr/>$helptext";
    }
    ?>
    <?php
    echo $deco->footer();
    
