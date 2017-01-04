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
require_once("option_html.inc.php");

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_GET["class"])) {
    // XHR call: language isn't set yet ... so do it
    $languageInstance = new \core\Language();
    $languageInstance->setTextDomain("web_admin");
    $optioninfo = Options::instance();
    // add one option of the specified class

    $list = $optioninfo->availableOptions($_GET["class"]);

    switch ($_GET['class']) {
        case "general":
            $blacklist_item = array_search("general:geo_coordinates", $list);
            if ($blacklist_item !== FALSE) {
                unset($list[$blacklist_item]);
                $list = array_values($list);
            }
            break;
        case "user":
            $blacklist_item = array_search("user:fedadmin", $list);
            if ($blacklist_item !== FALSE) {
                unset($list[$blacklist_item]);
                $list = array_values($list);
            }
            break;
        case "eap":
        case "support":
        case "profile":
        case "media":
        case "fed":
            $list = array_values($list);
            break;
        case "device-specific":
        case "eap-specific":
            break;
        default:
            throw new Exception("Unknown type of option!");
    }

    echo optiontext(0, $list);
}