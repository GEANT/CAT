<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
require_once(dirname(dirname(__DIR__)) . "/config/_config.php");

$telepath = new \core\diag\Telepath($_GET['realm'], $_GET['visited']);
$validator = new \web\lib\common\InputValidation();

echo "<pre>";
echo "Testing ".$validator->realm(filter_input(INPUT_GET,'realm', FILTER_SANITIZE_STRING))." in ".$validator->string(filter_input(INPUT_GET, 'visited', FILTER_SANITIZE_STRING));
print_r($telepath->magic());
echo "</pre>";