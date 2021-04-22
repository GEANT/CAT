<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once dirname((__DIR__)) . "/config/_config.php";

const TEST = [
    "education.lu" => "anonymous@education.lu",
    "canarie.ca" => "canary@canarie.ca",
    ];
foreach (TEST as $realm => $name) {
    $testsuite = new \core\diag\RADIUSTests($realm, $name);
    echo "Tested realm: $realm";
    print_r($testsuite->autodetectCAWithProbe($name));
}