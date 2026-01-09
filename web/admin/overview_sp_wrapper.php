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
 * This page displays the dashboard overview of an entire IdP.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 */
?>
<?php
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";


$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$uiElements = new web\lib\admin\UIElements();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);
$myfed = new \core\Federation($my_inst->federation);
echo $deco->defaultPagePrelude(sprintf(_("%s: %s Dashboard"), \config\Master::APPEARANCE['productname'], $uiElements->nomenclatureParticipant));
?>
<script src="js/XHR.js"></script>
<script src="js/popup_redirect.js"></script>
<script src="../external/jquery/jquery-ui.js"></script>
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<body >
    <?php
    echo $deco->productheader("ADMIN-SP");
    ?>
    <img alt='Loading ...' src='../resources/images/icons/loading51.gif' id='spin' class='TMW' style='position:absolute;left: 50%; top: 50%; transform: translate(-100px, -50px); display:none;'>
    <?php
// Sanity check complete. Show what we know about this IdP.
    $idpoptions = $my_inst->getAttributes();
    if ($editMode == 'readonly') {
        $editLabel = _("View ...");
    }
    if ($editMode == 'fullaccess') {
        $editLabel = _("Edit ...");
    }
    
    $silverbulletFedAttr = $myfed->getAttributes("fed:silverbullet");
    if (\core\CAT::hostedSPEnabled() && count($silverbulletFedAttr) > 0 && preg_match("/SP/", $my_inst->type)) {
        switch ($silverbulletFedAttr[0]['value']) {
            case 'all':
                include "overview_sp.php";
                break;
            case 'fedadmin-only':
                $user = new \core\User($_SESSION['user']);
                if ($user->isFederationAdmin($my_inst->federation)) {
                    include "overview_sp.php";
                }
                break;
            default:
                break;
        }        
    }    
    
