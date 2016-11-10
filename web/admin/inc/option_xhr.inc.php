<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("CAT.php");
require_once("Options.php");

require_once("option_html.inc.php");

session_start();

if (isset($_GET["class"])) {
    // XHR call: language isn't set yet ... so do it
    $languageInstance = new Language();
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