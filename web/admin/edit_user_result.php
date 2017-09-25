<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$loggerInstance = new \core\common\Logging();
$deco = new \web\lib\admin\PageDecoration();
$optionParser = new \web\lib\admin\OptionParser();

echo $deco->pageheader(_("User Attributes - Summary of submitted data"), "USERMGMT");

$user = new \core\User($_SESSION['user']);
if (!isset($_POST['submitbutton']) || $_POST['submitbutton'] != web\lib\common\FormElements::BUTTON_SAVE) { // what are we supposed to do?
    echo "<p>" . _("The page was called with insufficient data. Please report this as an error.") . "</p>";
    echo $deco->footer();
    exit(0);
}
?>
<h1>
    <?php _("Submitted attributes"); ?>
</h1>
<?php
// be cautious: there is one attribute which the user hasn't sent (because it is set for him out-of-band)
// which needs to be preserved: user:fedadmin. The following code path is less tested than the rest because
// the eduroam deployment leaves fedadmin privilege management entirely to the eduroam Service Provider Proxy
//  and eduroam DB

if (isset($_POST['option'])) {
    foreach ($_POST['option'] as $opt_id => $optname) {
        if ($optname == "user:fedadmin") {
            echo "Security violation: user tried to make himself " . CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation'] . " administrator!";
            exit(1);
        }
    }
}
$salvageFedPrivs = [];
if (CONFIG['DB']['userdb-readonly'] === FALSE) { // we are actually writing user properties ourselves
    $federations = $user->getAttributes("user:fedadmin");
    foreach ($federations as $federation) {
        $salvageFedPrivs[] = $federation['value'];
    }
}

// add any salvaged fedops privileges ourselves. Adding things to POST
// is maybe a bit ugly, but it works.

$i = 0;
foreach ($salvageFedPrivs as $oneFed) {
    $_POST['option']["S123456789".$i] = "user:fedadmin#string##";
    $_POST['value']["S123456789".$i."-0"] = $oneFed;
    $i++;
}
?>
<table>
    <?php
    echo $optionParser->processSubmittedFields($user, $_POST, $_FILES);
    $loggerInstance->writeAudit($_SESSION['user'], "MOD", "User attributes changed");
    ?>
</table>
<br/>
<form method='post' action='overview_user.php' accept-charset='UTF-8'>
    <button type='submit'>
        <?php echo _("Continue to user overview page"); ?>
    </button>
</form>
<?php
echo $deco->footer();
