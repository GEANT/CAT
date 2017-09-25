<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * This page edits a federation.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$auth = new \web\lib\admin\Authentication();
$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();


$fedPost = $_POST['fed_id'];


$my_fed = $validator->Federation($fedPost, $_SESSION['user']);
$fed_options = $my_fed->getAttributes();

echo $deco->defaultPagePrelude(sprintf(_("%s: Editing %s '%s'"), CONFIG['APPEARANCE']['productname'], $uiElements->nomenclature_fed, $my_fed->name));
$langObject = new \core\common\Language();
?>
<script src="js/XHR.js" type="text/javascript"></script>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 
</head>
<body>

    <?php echo $deco->productheader("FEDERATION"); ?>

    <h1>
        <?php
        printf(_("Editing %s information for '%s'"), $uiElements->nomenclature_fed, $my_fed->name);
        ?>
    </h1>
    <div class='infobox'>
        <h2><?php echo sprintf(_("%s Properties"),$uiElements->nomenclature_fed); ?></h2>
        <table>
            <tr>
                <td><?php echo _("Country:"); ?></td>
                <td></td>
                <td><strong><?php
                        echo $my_fed->name;
                        ?></strong></td>
            </tr>
            <?php echo $uiElements->infoblock($fed_options, "fed", "FED"); ?>
        </table>
    </div>
    <?php
    echo "<form enctype='multipart/form-data' action='edit_federation_result.php?fed_id=$my_fed->identifier" . "' method='post' accept-charset='UTF-8'>
              <input type='hidden' name='MAX_FILE_SIZE' value='" . CONFIG['MAX_UPLOAD_SIZE'] . "'>";
    ?>
    <fieldset class="option_container">
        <legend><strong><?php echo sprintf(_("%s Properties"),$uiElements->nomenclature_fed); ?></strong></legend>
        <?php
        $optionDisplay = new \web\lib\admin\OptionDisplay($fed_options, "FED");
        echo $optionDisplay->prefilledOptionTable("fed");
        ?>
        <button type='button' class='newoption' onclick='getXML("fed")'><?php echo _("Add new option"); ?></button>
    </fieldset>
    <?php
    echo "<div><button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_SAVE . "'>" . _("Save data") . "</button> <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_federation.php\"'>" . _("Discard changes") . "</button></div></form>";
    echo $deco->footer();
    