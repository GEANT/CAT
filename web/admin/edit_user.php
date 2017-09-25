<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$deco = new \web\lib\admin\PageDecoration();
$uiElements = new web\lib\admin\UIElements();

echo $deco->defaultPagePrelude(_("Editing User Attributes"));
$user = new \core\User($_SESSION['user']);
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
</head>
<body>
    <?php echo $deco->productheader("USERMGMT"); ?>
    <h1>
        <?php _("Editing User Attributes"); ?>
    </h1>
    <div class='infobox'>
        <h2>
            <?php echo _("Current User Attributes"); ?>
        </h2>
        <table>
            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
        </table>
    </div>
    <form enctype='multipart/form-data' action='edit_user_result.php' method='post' accept-charset='UTF-8'>
        <fieldset class="option_container">
            <legend>
                <strong><?php echo _("Your attributes"); ?></strong>
            </legend>
            <?php 
            $optionDisplay = new \web\lib\admin\OptionDisplay($user->getAttributes(), "User");
            echo $optionDisplay->prefilledOptionTable("user"); 
            ?>
            <button type='button' class='newoption' onclick='getXML("user")'>
                <?php echo _("Add new option"); ?>
            </button>
        </fieldset>
        <div>
            <button type='submit' name='submitbutton' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'>
                <?php echo _("Save data"); ?>
            </button>
            <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = "overview_user.php"'>
                <?php echo _("Discard changes"); ?>
            </button>
        </div>
    </form>
    <?php
    echo $deco->footer();
    