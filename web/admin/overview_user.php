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
require_once "inc/click_button_js.php";
?>

<script type="text/javascript"><?php require_once "inc/overview_js.php" ?></script>


<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    echo $deco->productheader("ADMIN");
    ?>
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
    $hasInst = $instMgmt->listInstitutionsByAdmin($_SESSION['user']);


    if (\config\ConfAssistant::CONSORTIUM['name'] == 'eduroam') {
        $target = "https://wiki.geant.org/x/SwB_AQ"; // CAT manual, outdated
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
            $myFeds[$fed_id] = $cat->knownFederations[$fed_id];
            $instlist[$instId] = ["country" => strtoupper($my_inst->federation), "name" => $inst_name, "object" => $my_inst];
        }

        asort($myFeds);

        foreach ($instlist as $key => $row) {
            $country[$key] = $row['country'];
            $name[$key] = $row['name'];
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
        } else { // self-service registration is allowed! Yay :-)
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
    