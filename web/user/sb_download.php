<?php 
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php 
/**
 * Download for 
 * This file is obsolete and left for backwards compatibility reasons only
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */

include(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("UserAPI.php");
require_once("Logging.php");
require_once('ProfileFactory.php');
require_once('../admin/inc/input_validation.inc.php');
$API = new UserAPI();
$loggerInstance = new Logging();

$profileId = ( isset($_REQUEST['profile']) ? $_REQUEST['profile'] : FALSE );
$instId = ( isset($_REQUEST['idp']) ? $_REQUEST['idp'] : FALSE );
$device = ( isset($_REQUEST['device']) ? $_REQUEST['device'] : FALSE );
$generatedFor = ( isset($_REQUEST['generatedfor']) ? $_REQUEST['generatedfor'] : 'user' );

if ($generatedFor != "admin" && $generatedFor != "user") {
    $loggerInstance->debug(2,"Invalid downloads triggered (neither for admin nor user???)");
    print("Invalid downloads triggered (neither for admin nor user???)");
    exit(1);
}

$loggerInstance->debug(4,"download: profile:$profile_id; inst:$instId; device:$device\n");

$cleanToken = NULL;
$password = NULL;

if (isset($_SESSION['individualtoken']) && isset($_SESSION['importpassword'])) {
    $cleanToken = valid_token($_SESSION['individualtoken']);
    // TODO validate that token actually exists and is unused
    $password = valid_string_db($_SESSION['importpassword']);
}

// first block will test if the user input was valid.

$p = ProfileFactory::instantiate($profileId);

if(!$p->institution || $p->institution !== $instId) {
  header("HTTP/1.0 404 Not Found");
  return;
}

// now we generate the installer

$API->downloadInstaller($device,$profileId, $generatedFor, $cleanToken, $password);
