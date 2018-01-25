<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */


require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

CAT_session_start();

$safeText = ["options"=>["regexp"=>"/^[\w\d-]+$/"]];
$key1 = filter_input(INPUT_GET, 'key', FILTER_VALIDATE_REGEXP, $safeText);
$key2 = $_SESSION['remindIdP'];
if (!$key1 || $key1 != $key2) {
    print("wrong usage");
    exit;
}

$providers = \core\User::findLoginIdPByEmail(filter_input(INPUT_GET, 'mail', FILTER_SANITIZE_EMAIL));
if (!$providers) {
    echo(json_encode(['status' => 0]));
    exit;
}

echo(json_encode(['status' => 1, 'data' => $providers]));
