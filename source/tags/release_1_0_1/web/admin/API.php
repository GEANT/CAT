<?php

/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("UserManagement.php");
require_once("CAT.php");
require_once("inc/input_validation.inc.php");

// no SAML auth on this page. The API key authenticates the entity

define("ERROR_API_DISABLED", 1);
define("ERROR_NO_APIKEY", 2);
define("ERROR_INVALID_APIKEY", 3);
define("ERROR_MISSING_PARAMETER", 4);
define("ERROR_NO_ACTION", 5);
define("ERROR_INVALID_ACTION", 6);

$checkval = "FAIL";
$mode = "API";

function return_error($code, $description) {
    echo "<CAT-API-Response>\n";
    echo "  <error>\n    <code>".$code."</code>\n    <description>$description</description>\n  </error>\n";
    echo "</CAT-API-Response>\n";
}

echo "<?xml>\n";

if (!isset(Config::$CONSORTIUM['registration_API_keys']) || count(Config::$CONSORTIUM['registration_API_keys']) == 0) {
    return_error(ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}

if (!isset($_POST['APIKEY'])) {
    return_error(ERROR_NO_APIKEY,"POST did not contain an APIKEY");
    exit(1);
}

foreach (Config::$CONSORTIUM['registration_API_keys'] as $key => $fed_name)
    if ($_POST['APIKEY'] == $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }

if ($checkval == "FAIL") {
    return_error(ERROR_INVALID_APIKEY,"APIKEY is invalid");
    exit(1);
}
    
// it's a valid admin; what does he want to do?

if (!isset($_POST['ACTION'])) {
    return_error(ERROR_NO_ACTION,"POST did not contain the desired ACTION");
    exit(1);
}

$sanitised_action = valid_string_db($_POST['ACTION']);

switch($sanitised_action) {
    case 'NEWINST':
        // fine... we need two parameters for that:
        // mail address, inst name
        if (!isset($_POST['NEWINST_PRIMARYADMIN']) || !isset($_POST['NEWINST_NAME'])) {
            return_error(ERROR_MISSING_PARAMETER,"POST missed at least one required parameter (NEWINST_PRIMARYADMIN, NEWINST_NAME)");
            exit(1);
        }
        // alright: create the token and send the URL back
        $mgmt = new UserManagement();
        $newtoken = $mgmt->createToken(true, valid_string_db($_POST['NEWINST_PRIMARYADMIN']), valid_string_db($_POST['NEWINST_NAME']), 0, $federation);
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=$newtoken";
        echo "<CAT-API-Response>\n";
        echo "  <success action='NEWINST'>\n    <enrollment_URL>$URL</enrollment_URL>\n  </success>\n";
        echo "</CAT-API-Response>\n";
            exit(0);
        break;
    default:
        return_error(ERROR_INVALID_ACTION,"POST contained an unknown ACTION");
        exit(1);
}