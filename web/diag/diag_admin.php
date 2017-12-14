<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
$Gui = new \web\lib\user\Gui();
$skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $fedskin[0] ?? CONFIG['APPEARANCE']['skins'][0]);
include("../skins/" . $skinObject->skin . "/diag/diag_admin.php");

