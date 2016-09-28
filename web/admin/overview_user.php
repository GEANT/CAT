<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("UserManagement.php");
require_once("Federation.php");
require_once("IdP.php");
require_once("User.php");

require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/input_validation.inc.php");
require_once("inc/common.inc.php");

$instMgmt = new UserManagement();
$cat = defaultPagePrelude(sprintf(_("%s: User Management"), CONFIG['APPEARANCE']['productname']));
$user = new User($_SESSION['user']);
?>
<!-- JQuery --> 
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<!-- JQuery --> 
<script type="text/javascript"><?php require_once("inc/overview_js.php") ?></script>
<script src="js/popup_redirect.js" type="text/javascript"></script>
</head>
<body>
    <?php
    productheader("ADMIN", CAT::getLang());
    ?>
    <h1>
        <?php echo _("User Overview"); ?>
    </h1>
    <div class="infobox">
        <h2><?php echo _("Your Personal Information"); ?></h2>
        <table>
            <?php echo infoblock($user->getAttributes(), "user", "User"); ?>
            <tr>
                <td>
                    <?php echo "" . _("Unique Identifier") ?>
                </td>
                <td>
                </td>
                <td>
                    <span class='tooltip' style='cursor: pointer;' onclick='alert("<?php echo $_SESSION["user"]; ?>")'><?php echo _("click to display"); ?></span>
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
            echo "<form action='overview_federation.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . _('Click here to manage your federations') . "</button></form>";
        }
        if ($user->isSuperadmin()) {
            echo "<form action='112365365321.php' method='GET' accept-charset='UTF-8'><button type='submit'>" . _('Click here to access the superadmin page') . "</button></form>";
        }
        ?>
    </div>
    <?php
    $hasInst = $instMgmt->listInstitutionsByAdmin($_SESSION['user']);

    if (CONFIG['CONSORTIUM']['name'] == 'eduroam') {
        $helptext = "&nbsp;<h3 style='display:inline;'>" . sprintf(_("(Need help? Refer to the <a href='%s'>IdP administrator manual</a>)"), "https://wiki.geant.org/x/SwB_AQ") . "</h3>";
    } else {
        $helptext = "";
    }

    if (sizeof($hasInst) > 0) {
        // we need to run the Federation constructor
        $unused = new Federation("LU");
        echo "<h2>" . sprintf(ngettext("You are managing the following institution:", "You are managing the following <strong>%d</strong> institutions:", sizeof($hasInst)), sizeof($hasInst)) . "</h2>";
        echo $helptext;
        $instlist = [];
        $my_idps = [];
        $myFeds = [];
        $fed_count = 0;
        echo "<table class='user_overview'>";

        foreach ($hasInst as $instId) {
            $my_inst = new IdP($instId);
            $inst_name = $my_inst->name;
            $fed_id = strtoupper($my_inst->federation);
            $my_idps[$fed_id][$instId] = strtolower($inst_name);
            $myFeds[$fed_id] = $unused::$federationList[$fed_id];
            $instlist[$instId] = ["country" => strtoupper($my_inst->federation), "name" => $inst_name, "object" => $my_inst];
        }

        asort($myFeds);

        foreach ($instlist as $key => $row) {
            $country[$key] = $row['country'];
            $name[$key] = $row['name'];
        }
        echo "<tr><th>" . _("Institution Name") . "</th><th>" . _("Other admins of this institution") . "</th><th>" . _("Administrator Management") . "</th></tr>";
        foreach ($myFeds as $fed_id => $fed_name) {
            echo "<tr><td colspan='3'><strong>" . sprintf(_("Institutions in federation %s"), $fed_name) . "</strong></td></tr>";

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
                        $coadmin = new User($username['ID']);
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
        echo "<h2>" . _("You are not managing any institutions.") . "</h2>";
    }
    if (CONFIG['CONSORTIUM']['selfservice_registration'] === NULL) {
        echo "<p>" . _("Please ask your federation administrator to invite you to become an institution administrator.") . "</p>";
        echo "<hr/>
             <div style='white-space: nowrap;'>
                <form action='action_enrollment.php' method='get' accept-charset='UTF-8'>" .
        _("Did you receive an invitation token to manage an institution? Please paste it here:") .
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
        _("Register New Institution!") . "
            </button>
        </form>
        </div>";
    }
    ?>
    <?php
    footer();
    