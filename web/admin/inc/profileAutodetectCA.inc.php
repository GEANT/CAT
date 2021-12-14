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
?>
<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$languageInstance = new \core\common\Language();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();
$languageInstance->setTextDomain("web_admin");


header("Content-Type:text/html;charset=utf-8");

$validator = new \web\lib\common\InputValidation();

$my_inst = $validator->existingIdP($_GET['inst_id']);
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

?>
<h1>
    <?php echo _("Auto-Detecting root CA for a new profile"); ?>
</h1>
<hr/>
<form action='edit_profile.php?inst_id=<?php echo $my_inst->identifier; ?>' method='post' accept-charset='UTF-8'>
<table>
        <tr>
            <td>
                <strong><?php echo _("Valid RADIUS username and realm:") ?></strong>                
            </td>
            <td>
                <input type='text' width="60" name='username_to_detect'/>
            </td><!-- comment -->
            <td>
                @
            </td><!-- comment -->
            <td>
                <input type='text' width="60" name='realm_to_detect'/>
            </td><!-- comment -->
        </tr><!-- comment -->
        <tr>
            <td>          
                    <button type='submit' name='submitbutton' class='submit' value='<?php echo web\lib\common\FormElements::BUTTON_SAVE; ?>'><?php echo _("Detect root CA") ?></button>
            </td>
        </tr>
</table>
</form>