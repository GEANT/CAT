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

/**
 * This page is used to save changes of the sorting order of IdP profiles by its administrator.
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 */
?>

<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$auth = new \web\lib\admin\Authentication();
$auth->authenticate();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

echo $deco->defaultPagePrelude(sprintf(_("%s: Profie ordering"), \config\Master::APPEARANCE['productname'])); 
if ($editMode !== 'fullaccess') {
    echo "<h2>"._("No write access to this IdP");
    echo $deco->footer();
    exit;
}
$handle = \core\DBConnection::handle("INST");

echo "<body>";
echo $deco->productheader("ADMIN-IDP");
printf("<h1>"._("Saved new profiles order for '%s'")."</h1>", $my_inst->name);
$q = "UPDATE profile SET preference = ? WHERE profile_id = ?";
foreach ($_POST as $key => $value) {
    if (!preg_match('/^profile-(\d+)$/', $key, $matches)) {
        continue;
    }
    $profileId = $matches[1];
    $handle->exec($q, 'ii', $value, $profileId);
}


?>

<button type="button" id="cancel" name="cancel" value="abort" onclick="javascript:window.location = 'overview_org.php?inst_id=<?php echo $my_inst->identifier?>'"><?php echo _("Continue to dashboard"); ?></button>       
<?php echo $deco->footer();