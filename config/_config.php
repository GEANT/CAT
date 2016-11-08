<?php
/**
 * This file sets a few paths. Usually, you don't have to change anything here!
 *
 * @package Configuration
 */

/**
 * 
 */

require_once ("autoloader.php");
$old_include_path = get_include_path();
set_include_path(dirname(__DIR__));
require_once("packageRoot.php");
include(ROOT."/config/config.php");  
set_include_path($old_include_path . PATH_SEPARATOR . ROOT . "/core" . PATH_SEPARATOR . ROOT);