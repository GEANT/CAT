<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Federation.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_html.inc.php");
require_once("inc/auth.inc.php");
authenticate();

$my_fed = valid_Fed($_GET['fed_id'], $_SESSION['user']);
$fed_options = $my_fed->getAttributes();

$cat = defaultPagePrelude(sprintf(_("%s: Editing Federation '%s'"), Config::$APPEARANCE['productname'], $my_fed->identifier));

?>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 
</head>
<body>

<?php productheader("FEDERATION", CAT::$lang_index); ?>

    <h1>
<?php
    printf(_("Editing Federation information for '%s'"), $my_fed->identifier);
?>
    </h1>
    <div class='infobox'>
        <h2><?php echo _("Federation Properties"); ?></h2>
        <table>
            <tr>
                <td><?php echo _("Country:"); ?></td>
                <td></td>
                <td><strong><?php
        echo Federation::$FederationList[strtoupper($my_fed->identifier)];
?></strong></td>
            </tr>
                        <?php echo infoblock($fed_options, "fed", "FED"); ?>
        </table>
    </div>
<?php
echo "<form enctype='multipart/form-data' action='edit_federation_result.php?fed_id=$my_fed->identifier" . "' method='post' accept-charset='UTF-8'>
              <input type='hidden' name='MAX_FILE_SIZE' value='" . Config::$MAX_UPLOAD_SIZE . "'>";

?>
    <fieldset class="option_container">
        <legend><strong><?php echo _("Federation Properties"); ?></strong></legend>
        <table id="expandable_fed_options">
        <?php
        add_option("fed", $fed_options);
        ?>
        </table>
        <button type='button' class='newoption' onclick='addDefaultFedOptions()'><?php echo _("Add new option"); ?></button>
    </fieldset>
<?php
    echo "<div><button type='submit' name='submitbutton' value='" . BUTTON_SAVE . "'>" . _("Save data") . "</button> <button type='button' class='delete' name='abortbutton' value='abort' onclick='javascript:window.location = \"overview_federation.php?fed_id=$my_fed->identifier\"'>" . _("Discard changes") . "</button></div></form>";
footer();
?>
        
