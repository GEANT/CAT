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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

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
            <?php $tablecaption = _("Current User Attributes"); echo $tablecaption;?>
        </h2>
        <table>
            <caption><?php echo $tablecaption;?></caption>
            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Property Type");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Language if applicable");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Property Value");?></th>
            </tr>
            <?php echo $uiElements->infoblock($user->getAttributes(), "user", "User"); ?>
        </table>
    </div>
    <form enctype='multipart/form-data' action='edit_user_result.php' method='post' accept-charset='UTF-8'>
        <fieldset class="option_container">
            <legend>
                <strong><?php echo _("Your attributes"); ?></strong>
            </legend>
            <?php 
            $optionDisplay = new \web\lib\admin\OptionDisplay($user->getAttributes(), \core\Options::LEVEL_USER);
            // these options have no federation context, so use "DEFAULT"
            echo $optionDisplay->prefilledOptionTable("user", "DEFAULT"); 
            ?>
            <button type='button' class='newoption' onclick='getXML("user", "<?php echo $my_inst->federation ?>")'>
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
