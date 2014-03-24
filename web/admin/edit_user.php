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
require_once("User.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_html.inc.php");

$cat = defaultPagePrelude(_("Editing User Attributes"));
$user = new User($_SESSION['user']);
?>
<script src="js/option_expand.js" type="text/javascript"></script>
</head>
<body>
    <?php productheader("USERMGMT",$cat->lang_index); ?>
    <h1>
        <?php _("Editing User Attributes"); ?>
    </h1>
    <div class='infobox'>
        <h2>
            <?php echo _("Current User Attributes"); ?>
        </h2>
        <table>
            <?php echo infoblock($user->getAttributes(), "user", "User"); ?>
        </table>
    </div>
    <form enctype='multipart/form-data' action='edit_user_result.php' method='post' accept-charset='UTF-8'>
        <fieldset class="option_container">
            <legend>
                <strong><?php echo _("Your attributes"); ?></strong>
            </legend>
            <table id="expandable_user_options">
                <?php add_option("user", $user->getAttributes()); ?>
            </table>
            <button type='button' class='newoption' onclick='addDefaultUserOptions()'>
                <?php echo _("Add new option"); ?>
            </button>
        </fieldset>
        <div>
            <button type='submit' name='submitbutton' value='<?php echo BUTTON_SAVE;?>'>
                <?php echo _("Save data"); ?>
            </button>
            <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location="overview_user.php"'>
                <?php echo _("Discard changes"); ?>
            </button>
        </div>
    </form>
    <?php
    footer();
    ?>
        
