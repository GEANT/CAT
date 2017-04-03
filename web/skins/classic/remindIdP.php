<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once(dirname(dirname(__DIR__)) . "/admin/inc/common.inc.php");

echo "<pre>";
print_r(\core\User::findLoginIdPByEmail($_GET['mail']));
echo "<pre>";