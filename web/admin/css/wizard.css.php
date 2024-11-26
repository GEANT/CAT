<?php
/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
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


// we need to know if we are serving a RTL language so we can flip some heading
// items
header("Content-Type:text/css");
require_once dirname(dirname(dirname(__DIR__))) . "/config/_config.php";
$langInstance = new core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";
?>
@CHARSET "UTF-8";
.wizard_visible {
    display: block;
}
.wizard_hidden {
    display: none;
}

img.wizard_icon {
    float: <?php echo $end ?>;
}

#wizard_help_window {
    display: none;
    background-color: #ddd;
    position: absolute;
    left: 100px;
    top: 100px;
    width: 70%;
    z-index: 100;
    padding:10px;
    font-size: 12px;
}

#wizard_menu_close {
   position: absolute;
<?php echo $end ?>: 5px;
   top: 7px;
   cursor: pointer;
}

