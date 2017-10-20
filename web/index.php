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
 * Skin selection for user pages
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Core
 */
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

$Gui = new \web\lib\user\Gui();
// ... unless overwritten by direct GET/POST parameter in the request or a SESSION setting
// ... with last resort being the default skin (first one in the configured skin list is the default)
error_reporting(E_ALL | E_STRICT);
$Gui->langObject->setTextDomain("web_user");
$Gui->loggerInstance->debug(4, "\n---------------------- index.php START --------------------------\n");
$Gui->defaultPagePrelude();

// and now, serve actual data
include("skins/".$Gui->skinObject->skin."/index.php");
