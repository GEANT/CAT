<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

require_once ("autoloader.php");
$old_include_path = get_include_path();
set_include_path(dirname(__DIR__));
require_once("packageRoot.php");
include(ROOT."/config/config.php");  
set_include_path($old_include_path . PATH_SEPARATOR . ROOT . "/core" . PATH_SEPARATOR . ROOT);