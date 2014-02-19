<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
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
require_once("inc/option_parse.inc.php");

if (isset($_POST['submitbutton']) && $_POST['submitbutton'] == BUTTON_DELETE && isset($_GET['inst_id'])) {
    require_once("inc/auth.inc.php");
    $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
    $inst_id = $my_inst->identifier;
    // delete the IdP and send user to enrollment
    $my_inst->destroy();
    CAT::writeAudit($_SESSION['user'], "DEL", "IdP ".$inst_id);
    header("Location:overview_user.php");
}

defaultPagePrelude(sprintf(_("IdP enrollment wizard (step 2 completed)"), Config::$APPEARANCE['productname']));
?>
</head>
<body>

    <?php
    productheader();
    $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
    if (isset($_POST['submitbutton'])) {
        if (( $_POST['submitbutton'] == BUTTON_SAVE || $_POST['submitbutton'] == BUTTON_CONTINUE) && isset($_POST['option']) && isset($_POST['value'])) { // here we go
            $inst_name = $my_inst->name;
            echo "<h1>" . sprintf(_("Submitted attributes for IdP '%s'"), $inst_name) . "</h1>";
            $remaining_attribs = $my_inst->beginflushAttributes();

            echo "<table>";
            $killlist = processSubmittedFields($my_inst, $remaining_attribs);
            echo "</table>";
            // print_r($killlist);
            $my_inst->commitFlushAttributes($killlist);
            
            CAT::writeAudit($_SESSION['user'], "MOD", "IdP ".$my_inst->identifier." - attributes changed");

            // re-instantiate ourselves... profiles need fresh data
            
            $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
            
            foreach ($my_inst->listProfiles() as $index => $profile) {
                $profile->prepShowtime();
            }
            
            if ($_POST['submitbutton'] == BUTTON_SAVE)
                echo "<br/><form method='post' action='overview_idp.php?inst_id=$my_inst->identifier'><button type='submit'>" . _("Continue to dashboard") . "</button></form>";
            else if ($_POST['submitbutton'] == BUTTON_CONTINUE)
                echo "<br/><form method='post' action='edit_profile.php?inst_id=$my_inst->identifier'><button type='submit'>" . _("Continue to profile definition") . "</button></form>";
        }
    }
    include "inc/admin_footer.php";
    ?>
