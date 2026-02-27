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
 * This page is used to prepare creation of a duplicate of a RADIUS profile by
 * its administrator.
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
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

echo $deco->defaultPagePrelude(sprintf(_("%s: Profile duplication (Step 1)"), \config\Master::APPEARANCE['productname']));
echo "<body>";
echo $deco->productheader("ADMIN-IDP");
if ($editMode !== 'fullaccess') {
    echo "<h2>"._("No write access to this IdP");
    echo $deco->footer();
    exit;
}

if (isset($_GET['profile_id'])) {
    $my_profile = $validator->existingProfile($_GET['profile_id'], $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("This page is only for duplicating RADIUS profiles!");
    }  
}

$prefill_name = $my_profile->name;
echo "<h1>";
    printf(_("Duplicate profile '%s' ..."), $prefill_name);
    echo "</h1>";
    
    echo _("New profile default name: ");
    ?>
    <form action="duplicate_profile_result.php" method='post' accept-charset='UTF-8'>
    <input type="text" id="new_profile" name="new_profile" size="50"><!-- comment -->
    <p>
        
        <?php echo _("The names from the source profile will be replaced with the one you provide.") ?>
    </p>
    <p><button type="submit" id="save_profile" name="save_profile"><?php echo _("Create duplicate") ?></button>
    <button type="button" class="delete" id="cancel" name="cancel" value="abort" onclick="javascript:window.location = 'overview_org.php?inst_id=<?php echo $my_inst->identifier?>'"><?php echo _("Cancel") ?></button>       
    <input type="hidden" name="orig_profile_name" value="<?php echo "$prefill_name"; ?>">
    <input type="hidden" name="profile_id" value="<?php echo $my_profile->identifier ?>">
    <input type="hidden" name="inst_id" value="<?php echo $my_inst->identifier ?>">
    
    </form>

<?php echo $deco->footer(); ?>