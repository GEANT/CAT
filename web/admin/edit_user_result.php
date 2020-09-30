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
            echo "Security violation: user tried to make himself " . \config\ConfAssistant::CONSORTIUM['nomenclature_federation'] . " administrator!";
            exit(1);
        }
    }
}
$salvageFedPrivs = [];
if (\config\Master::DB['USER']['readonly'] === FALSE) { // we are actually writing user properties ourselves
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
    $_POST['value']["S123456789".$i."-string"] = $oneFed;
    $i++;
}
?>
<h1><?php $tablecaption = _("Submitted attributes for this user"); echo $tablecaption; ?></h1>
<table>
            <caption><?php echo $tablecaption;?></caption>
            <tr>
                            <tr>
                <th class="wai-invisible" scope="col"><?php echo _("Overall Result");?></th>
                <th class="wai-invisible" scope="col"><?php echo _("Details");?></th>
            </tr>
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
