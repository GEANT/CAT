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
 * This page is used create a duplicate of a RADIUS profile by its administrator.
 * The new profile will have the display names replaces with one velue set by
 * the admin during duplication. The production-ready flag will be removed.
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


function copyRow($row, $feldsArray, $table, $dbHandle) {
    $fieldsList = implode(',', array_keys($row));
    foreach ($row as $key => $value) {
        if ($feldsArray[$key] == 's') {
            if ($value === null) {
                $row[$key] = 'NULL';
            } else {
                $e1 = str_replace('\\', '\\\\', $value);
                $e2 = str_replace('"', '\"', $e1);
                $row[$key] = '"'.$e2.'"';
            }
        }
    }
    $valuesList = implode(',', array_values($row));
    $insert = 'INSERT INTO '.$table.' ('.$fieldsList.') VALUES ('.$valuesList.')';
    $dbHandle->exec($insert);
}

function runSelect($profileId, $feldsArray, $table, $dbHandle) {
    $fieldsList = implode(',', array_keys($feldsArray));
    $query = 'SELECT '.$fieldsList.' FROM '.$table.' WHERE profile_id=?';
    return $dbHandle->exec($query, "i", $profileId);
}

$fields = [
    'inst_id'=>'i',
    'realm'=>'s',
    'use_anon_outer'=>'i',
    'showtime'=>'i',
    'sufficient_config'=>'i',
    'checkuser_value'=>'s',
    'verify_userinput_suffix'=>'i',
    'hint_userinput_suffix'=>'i',
    'openroaming'=>'i',
    'preference'=>'i'
];

$optionsFields = [
    'profile_id'=>'i',
    'eap_method_id'=>'i',
    'device_id'=>'s',
    'option_name'=>'s',
    'option_value'=>'s',
    'option_lang'=>'s'
];

$eapFields = [
    'profile_id'=>'i',
    'eap_method_id'=>'i',
    'preference'=>'i'
];

[$my_inst, $editMode] = $validator->existingIdPInt($_POST['inst_id'], $_SESSION['user']);
echo $deco->defaultPagePrelude(sprintf(_("%s: Profile duplication (Step 2)"), \config\Master::APPEARANCE['productname']));
echo "<body>";
echo $deco->productheader("ADMIN-IDP");
if ($editMode !== 'fullaccess') {
    echo "<h2>"._("No write access to this IdP");
    exit;
}
if (isset($_POST['profile_id'])) {
    $my_profile = $validator->existingProfile($_POST['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page is only for editing RADIUS profiles!");
    } 
}

$newProfileName =  $validator->string($_POST['new_profile'], true);
$origProfileName = $validator->string($_POST['orig_profile_name'], true);
$handle = \core\DBConnection::handle("INST");

$result = runSelect($my_profile->identifier, $fields, 'profile', $handle);
$row = $result->fetch_assoc();
$row['showtime']= 0;
$row['preference'] = 1000;
copyRow($row, $fields, 'profile', $handle);
$newProfileId = $handle->lastID();

$result = runSelect($my_profile->identifier, $optionsFields, 'profile_option', $handle);
while ($row = $result->fetch_assoc()) {
    $row['profile_id'] = $newProfileId;
    if ($row['option_name'] == 'profile:name' || $row['option_name'] == 'profile:production') {
        continue;
    }
    copyRow($row, $optionsFields, 'profile_option', $handle);
}

$row = [
    'profile_id'=>$newProfileId,
    'option_name'=>'profile:name',
    'option_value'=>$newProfileName,
    'option_lang'=>'C'
];
copyRow($row, $optionsFields, 'profile_option', $handle);

$result = runSelect($my_profile->identifier, $eapFields, 'supported_eap', $handle);
while ($row = $result->fetch_assoc()) {
    $row['profile_id'] = $newProfileId;
    copyRow($row, $optionsFields, 'supported_eap', $handle);
}

printf("<h1>"._("Copied %s to %s")."</h1>", $origProfileName, $newProfileName); ?>

<button type="button" id="cancel" name="cancel" value="abort" onclick="javascript:window.location = 'overview_org.php?inst_id=<?php echo $my_inst->identifier?>'"><?php echo _("Continue to dashboard"); ?></button>       
<?php echo $deco->footer();