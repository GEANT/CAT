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

require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

\core\CAT::sessionStart();

if (!isset($_GET["class"] )) {
    throw new Exception("Unknown type of option!");
}

if (!isset($_GET["fedid"])) {
    throw new Exception("Unknown federation context!");
}


// XHR call: language isn't set yet ... so do it
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");

// add one option of the specified class

$list = web\lib\admin\OptionDisplay::enumerateOptionsToDisplay($_GET["class"], $_GET['fedid']);

$optionDisplay = new \web\lib\admin\OptionDisplay($list);
echo $optionDisplay->optiontext(array_values($list));
