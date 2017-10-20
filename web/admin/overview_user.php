<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
namespace core;

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$instMgmt = new \core\UserManagement();
$deco = new \web\lib\admin\PageDecoration();
$uiElements = new \web\lib\admin\UIElements();

echo $deco->defaultPagePrelude(sprintf(_("%s: User Management"), CONFIG['APPEARANCE']['productname']));
$user = new \core\User($_SESSION['user']);
?>
<!-- JQuery --> 
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<!-- JQuery --> 
<script type="text/javascript"><?php require_once("inc/overview_js.php") ?></script>
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
        <h2><?php echo _("Your Personal Information"); ?></h2>
        <table>
            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo str_replace('\'','\x27',str_replace('"','\x22', $_SESSION["user"])); ?>")'><?php echo _("click to display"); ?></span>
                </td>
            </tr>
        </table>
    </div>
    <div>
        <?php
        if (!CONFIG['DB']['userdb-readonly']) {
            echo "<a href='edit_user.php'><button>" . _("Edit User Details") . "</button></a>";
        }

        if ($user->isFederationAdmin()) {
            echo "<form action='overview_federation.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . sprintf(_('Click here to manage your %ss'),$uiElements->nomenclature_fed) . "</button></form>";
        }
        if ($user->isSuperadmin()) {
            echo "<form action='112365365321.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . _('Click here to access the superadmin page') . "</button></form>";
        }
        ?>
    </div>
    <?php
    $hasInst = $instMgmt->listInstitutionsByAdmin($_SESSION['user']);

    if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == 'eduroam') {
        $helptext = "&nbsp;<h3 style='display:inline;'>" . sprintf(_("(Need help? Refer to the <a href='%s'>IdP administrator manual</a>)"), "https://wiki.geant.org/x/SwB_AQ") . "</h3>";
    } else {
        $helptext = "";
    }

    if (sizeof($hasInst) > 0) {
        // we need to run the Federation constructor
        $cat = new \core\CAT;
        echo "<h2>" . sprintf(ngettext("You are managing the following %s:", "You are managing the following <strong>%d</strong> %ss:", sizeof($hasInst)), sizeof($hasInst), $uiElements->nomenclature_inst) . "</h2>";
        echo $helptext;
        $instlist = [];
        $my_idps = [];
        $myFeds = [];
        $fed_count = 0;
        echo "<table class='user_overview'>";

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
        echo "<tr><th>" . sprintf(_("%s Name"), $uiElements->nomenclature_inst) . "</th><th>" . sprintf(_("Other admins of this %s"), $uiElements->nomenclature_inst) . "</th><th>" . _("Administrator Management") . "</th></tr>";
        foreach ($myFeds as $fed_id => $fed_name) {
            echo "<tr><td colspan='3'><strong>" . sprintf(_("%s %s: %s list"), $uiElements->nomenclature_fed, $fed_name, $uiElements->nomenclature_inst) . "</strong></td></tr>";

            $fed_idps = $my_idps[$fed_id];
            asort($fed_idps);
            foreach ($fed_idps as $index => $my_idp) {
                $oneinst = $instlist[$index];
                $the_inst = $oneinst['object'];

                echo "<tr><td><a href='overview_idp.php?inst_id=$the_inst->identifier'>" . $oneinst['name'] . "</a></td><td>";
                echo "<input type='hidden' name='inst' value='$the_inst->identifier'>";
                $admins = $the_inst->owner();
                $blessedUser = FALSE;
                foreach ($admins as $number => $username) {
                    if ($username['ID'] != $_SESSION['user']) {
                        $coadmin = new \core\User($username['ID']);
                        $coadmin_name = $coadmin->getAttributes('user:realname');
                        if (count($coadmin_name) > 0) {
                            echo $coadmin_name[0]['value'] . "<br/>";
                            unset($admins[$number]);
                        }
                    } else { // don't list self
                        unset($admins[$number]);
                        if ($username['LEVEL'] == "FED") {
                            $blessedUser = TRUE;
                        }
                    }
                }
                $otherAdminCount = count($admins); // only the unnamed remain
                if ($otherAdminCount > 0) {
                    echo ngettext("other user", "other users", $otherAdminCount);
                }
                echo "</td><td>";
                if ($blessedUser) {
                    echo "<div style='white-space: nowrap;'><form method='post' action='inc/manageAdmins.inc.php?inst_id=" . $the_inst->identifier . "' onsubmit='popupRedirectWindow(this); return false;' accept-charset='UTF-8'><button type='submit'>" . _("Add/Remove Administrators") . "</button></form></div>";
                }
                echo "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<h2>" . sprintf(_("You are not managing any %s."), $uiElements->nomenclature_inst) . "</h2>";
    }
    if (CONFIG_CONFASSISTANT['CONSORTIUM']['selfservice_registration'] === NULL) {
        echo "<p>" . sprintf(_("Please ask your %s administrator to invite you to become an %s administrator."), $uiElements->nomenclature_fed, $uiElements->nomenclature_inst) . "</p>";
        echo "<hr/>
             <div style='white-space: nowrap;'>
                <form action='action_enrollment.php' method='get' accept-charset='UTF-8'>" .
        sprintf(_("Did you receive an invitation token to manage an %s? Please paste it here:"), $uiElements->nomenclature_inst) .
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
        sprintf(_("Register new %s!"),$uiElements->nomenclature_inst) . "
            </button>
        </form>
        </div>";
    }
    ?>
    <?php
    echo $deco->footer();
    