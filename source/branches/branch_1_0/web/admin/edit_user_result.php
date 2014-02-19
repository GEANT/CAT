<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("inc/admin_header.php");
require_once("inc/option_parse.inc.php");

defaultPagePrelude(_("User Attributes - Summary of submitted data"));
$user = new User($_SESSION['user']);
?>
</head>
<body>
    <?php
    productheader();
    if (!isset($_POST['submitbutton']) || $_POST['submitbutton'] != BUTTON_SAVE) { // what are we supposed to do?
        echo "<p>" . _("The page was called with insufficient data. Please report this as an error.") . "</p>";
        include "inc/admin_footer.php";
        exit(0);
    };
    ?>
    <h1>
        <?php _("Submitted attributes"); ?>
    </h1>
    <?php
    $remaining_attribs = $user->beginflushAttributes();

    if (isset($_POST['option']))
        foreach ($_POST['option'] as $opt_id => $optname)
            if ($optname == "user:fedadmin") {
                echo "Security violation: user tried to make himself federation administrator!";
                exit(1);
            }
    ?>
    <table>
        <?php
        $killlist = processSubmittedFields($user, $remaining_attribs);
        $user->commitFlushAttributes($killlist);
        CAT::writeAudit($_SESSION['user'], "MOD", "User attributes changed");
        ?>
    </table>
    <br/>
    <form method='post' action='overview_user.php'>
        <button type='submit'>
            <?php echo _("Continue to user overview page"); ?>
        </button>
    </form>
    <?php
    include "inc/admin_footer.php";
    ?>
