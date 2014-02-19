<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php 
/**
 * Front-end for the user GUI
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 */

include(dirname(dirname(__FILE__))."/config/_config.php");
include_once("user/GUI.php");
$Gui = new GUI();



$profile_id = $_REQUEST['profile'];
$inst_id = $_REQUEST['idp'];
$device = $_REQUEST['device'];
$generated_for = $_REQUEST['generatedfor'];

$Gui->set_locale('devices');


if ($generated_for != "admin" && $generated_for != "user") {
    debug(2,"Invalid downloads triggered (neither for admin nor user???)");
    exit(1);
}

debug(4,"download: profile:$profile_id; inst:$inst_id; device:$device\n");

// first block will test if the user input was valid.

$p = new Profile($profile_id);

if(!$p->institution || $p->institution !== $inst_id) {
  header("HTTP/1.0 404 Not Found");
  return;
}

// now we generate the installer

$o = $Gui->generateInstaller($device,$profile_id, $generated_for);
if(! $o['link']) {
  header("HTTP/1.0 404 Not Found");
  return;
}

// send the link to the user

header("Location: ".rtrim(dirname($_SERVER['SCRIPT_NAME']),'/')."/".$o['link']);
?>
