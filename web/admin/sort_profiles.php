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
 * This page is used to change the sorting order of IdP profiles by its administrator.
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 */
?>

<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$auth = new \web\lib\admin\Authentication();
$uiElements = new \web\lib\admin\UIElements();
$auth->authenticate();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

echo $deco->defaultPagePrelude(sprintf(_("%s: Profile ordering for '%s'"), \config\Master::APPEARANCE['productname'], $my_inst->name));
require_once "inc/click_button_js.php";

?>
<style>
    span.hidden {
        display: none;
    }
    #cat_profiiles {
        background: green;
        padding: 5px;
    }
    li.hidden {
        background-color: lightblue;
    }
    
</style>
<!-- JQuery --> 
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<script type="text/javascript" src="../external/jquery/jquery-migrate.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script> 
<!-- EAP sorting code -->
<script>

$(function () {
    $("#sortable").sortable({
        connectWith: "ol.eapmethods",
        tolerance: 'pointer',
        stop: function (event, ui) {
            $(".profiles").removeAttr('value');
            $("#sortable").children().each(function (index) {
                var v = $(this).children().first().html();
                $("#profile-" + v).val(index+1);
            });
        }
    }).disableSelection();   
});

</script>

<link rel='stylesheet' type='text/css' href='css/eapSorter.css.php' />
<!-- EAP sorting code end -->

<?php
echo "<body>";
echo $deco->productheader("ADMIN-IDP");
if ($editMode !== 'fullaccess') {
    echo "<h2>"._("No write access to this IdP");
    echo $deco->footer();
    exit;
}

$profiles_for_this_idp = $my_inst->listProfiles();

?>
<h1><?php printf(_("Change the order of profiles for '%s'"), $my_inst->name); ?></h1>

   <div class="profilebox">
       <?php echo _("Pofiles with orange background are not visible in the public download interface"); ?><p>
        <table style="border:none">
            <caption><?php echo _("EAP type support"); ?></caption>
            <tr>
                <td id="cat_profiiles">
                    <ol id="sortable" class="eapmethods">
                        <?php
                        $D = [];
                        $prio = 1;
                        foreach ($profiles_for_this_idp as $profile) {
                            if ($profile->getAttributes("profile:production")[0]['value'] !== 'on') {
                                $li_class = 'style="background-color:orange"';
                            } else {
                                $li_class = '';
                            }
                            print '<li '.$li_class.'><span class="hidden">'.$profile->identifier.'</span>'.$profile->name."</li>\n";
                            $D[$profile->identifier] = $prio;
                            $prio++;
                        }
                        ?>
                    </ol>
                </td>
                <td rowspan=3 style="text-align:center; width:12em; padding:1em">
                    <?php echo _('Use "drag &amp; drop" to arrange the profiles order.'); ?>
                </td>
            </tr>
        </table>
    </div>
    <form action="sort_profiles_result.php?inst_id=<?php echo $my_inst->identifier?>" method='post' accept-charset='UTF-8'>
        <button type=submit">Save profiles order</button>
        <button type="button" class="delete" id="cancel" name="cancel" value="abort" onclick="javascript:window.location = 'overview_org.php?inst_id=<?php echo $my_inst->identifier?>'"><?php echo _("Cancel") ?></button>       
    <?php
    foreach ($D as $id=>$priority) {
        echo "<input type='hidden' class='profiles' id='profile-$id' name='profile-$id' value='$priority'>";
    }
  ?>
</form>
<?php echo $deco->footer();





