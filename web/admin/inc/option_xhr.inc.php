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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

const DO_NOT_DISPLAY = [
    "general" => ["general:geo_coordinates"],
    "user" => ["user:fedadmin"],
    "eap" => [],
    "support" => [],
    "profile" => [],
    "media" => [],
    "fed" => [],
    "device-specific" => [],
    "eap-specific" => [],
];

if (!isset($_GET["class"]) || !array_key_exists($_GET["class"], DO_NOT_DISPLAY)) {
    throw new Exception("Unknown type of option!");
}

// XHR call: language isn't set yet ... so do it
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");
$optioninfo = \core\Options::instance();

// add one option of the specified class

$list = array_diff($optioninfo->availableOptions($_GET["class"]), DO_NOT_DISPLAY[$_GET['class']]);

$optionDisplay = new \web\lib\admin\OptionDisplay($list, $_GET['class']);
echo $optionDisplay->optiontext($list);
