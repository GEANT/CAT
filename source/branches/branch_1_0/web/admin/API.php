<?php

/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("UserManagement.php");
require_once("CAT.php");
require_once("inc/input_validation.inc.php");
require_once("inc/option_parse.inc.php");
require_once("inc/common.inc.php");

// no SAML auth on this page. The API key authenticates the entity

define("ERROR_API_DISABLED", 1);
define("ERROR_NO_APIKEY", 2);
define("ERROR_INVALID_APIKEY", 3);
define("ERROR_MISSING_PARAMETER", 4);
define("ERROR_INVALID_PARAMETER", 5);
define("ERROR_NO_ACTION", 6);
define("ERROR_INVALID_ACTION", 7);

$checkval = "FAIL";
$mode = "API";

function return_error($code, $description) {
    echo "<CAT-API-Response>\n";
    echo "  <error>\n    <code>" . $code . "</code>\n    <description>$description</description>\n  </error>\n";
    echo "</CAT-API-Response>\n";
}

echo "<?xml>\n";

if (!isset(Config::$CONSORTIUM['registration_API_keys']) || count(Config::$CONSORTIUM['registration_API_keys']) == 0) {
    return_error(ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}

if (!isset($_POST['APIKEY'])) {
    return_error(ERROR_NO_APIKEY, "POST did not contain an APIKEY");
    exit(1);
}

foreach (Config::$CONSORTIUM['registration_API_keys'] as $key => $fed_name)
    if ($_POST['APIKEY'] == $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }

if ($checkval == "FAIL") {
    return_error(ERROR_INVALID_APIKEY, "APIKEY is invalid");
    exit(1);
}

// it's a valid admin; what does he want to do?

if (!isset($_POST['ACTION'])) {
    return_error(ERROR_NO_ACTION, "POST did not contain the desired ACTION");
    exit(1);
}

$sanitised_action = valid_string_db($_POST['ACTION']);

switch ($sanitised_action) {
    case 'NEWINST':
        // fine... we need two parameters for that:
        // mail address, inst name
        if (!isset($_POST['NEWINST_PRIMARYADMIN'])) {
            return_error(ERROR_MISSING_PARAMETER, "POST missed at least one required parameter (NEWINST_PRIMARYADMIN)");
            exit(1);
        }
        // alright: create the IdP, fill in attributes
        $mgmt = new UserManagement();
        $fed = new Federation($federation);
        $idp = new IdP($fed->newIdP("PENDING", "API", valid_string_db($_POST['NEWINST_PRIMARYADMIN'])));
        print_r($_POST);
        // that's a bit unpleasant... processSubmittedFields reads directly from
        // POST, but I need to do some sanitising first.
        // TODO For 1.1, make sure that pSF gets is field as a parameter, not implicitly via POST
        $original_post = $_POST;
        foreach ($_POST['option'] as $optindex => $optname)
            if (!preg_match("/^general:/", $optname) && !preg_match("/^support:/", $optname) && !preg_match("/^eap:/", $optname))
                unset($_POST['option'][$optindex]);
        // now process all inst-wide options    
        processSubmittedFields($idp, Array(), 0, 0, TRUE);
        $_POST = $original_post;
        // same thing for profile options
        foreach ($_POST['option'] as $optindex => $optname)
            if (!preg_match("/^profile:/", $optname) || $optname == "profile:QR-user")
                unset($_POST['option'][$optindex]);
        // if we do have profile-level options - create a profile and fill in the values!
        if (count($_POST['option']) > 0) {
            $newprofile = $idp->newProfile();
            processSubmittedFields($newprofile, Array(), 0, 0, TRUE);
            $_POST = $original_post;
            // sift through the options to find API ones (these are not caught by pSF() )
            $therealm = "";
            $theanonid = "anonymous";
            $use_anon = FALSE;
            foreach ($_POST['option'] as $optindex => $optname) {
                switch ($optname) {
                    case "profile-api:anon":
                        if (isset($_POST['value'][$optindex . "-0"]))
                            $theanonid = valid_string_db($_POST['value'][$optindex . "-0"]);
                        break;
                    case "profile-api:realm":
                        if (isset($_POST['value'][$optindex . "-0"]) && valid_Realm($_POST['value'][$optindex . "-0"]))
                            $therealm = $_POST['value'][$optindex . "-0"];
                        break;
                    case "profile-api:useanon":
                        if (isset($_POST['value'][$optindex . "-3"]) && valid_boolean($_POST['value'][$optindex . "-3"]) == "on")
                            $use_anon = TRUE;
                        break;
                    case "profile-api:eaptype":
                        $pref = 0;
                        if (isset($_POST['value'][$optindex . "-0"]) &&
                                is_numeric($_POST['value'][$optindex . "-0"]) &&
                                $_POST['value'][$optindex . "-0"] >= 1 &&
                                $_POST['value'][$optindex . "-0"] <= 7) {
                            switch ($_POST['value'][$optindex . "-0"]) {
                                case 1:
                                    $newprofile->addSupportedEapMethod(EAP::$TTLS_PAP, $pref);
                                    break;
                                case 2:
                                    $newprofile->addSupportedEapMethod(EAP::$PEAP_MSCHAP2, $pref);
                                    break;
                                case 3:
                                    $newprofile->addSupportedEapMethod(EAP::$TLS, $pref);
                                    break;
                                case 4:
                                    $newprofile->addSupportedEapMethod(EAP::$FAST_GTC, $pref);
                                    break;
                                case 5:
                                    $newprofile->addSupportedEapMethod(EAP::$TTLS_GTC, $pref);
                                    break;
                                case 6:
                                    $newprofile->addSupportedEapMethod(EAP::$TTLS_MSCHAP2, $pref);
                                    break;
                                case 7:
                                    $newprofile->addSupportedEapMethod(EAP::$PWD, $pref);
                                    break;
                            }
                            $pref = $pref + 1;
                        }
                        break;
                    default:
                        break;
                }
            }
            if ($therealm != "") {
                $newprofile->setRealm($theanonid . "@" . $therealm);
                if ($use_anon) {
                    $newprofile->setAnonymousIDSupport(true);
                }
            }
            // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()
            $profile = new Profile($newprofile->identifier);
            $profile->prepShowtime();
        }
        $_POST = $original_post;
        // generate the token
        $newtoken = $mgmt->createToken(true, valid_string_db($_POST['NEWINST_PRIMARYADMIN']), $idp);
        // and send it back to the caller
        $URL = "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/action_enrollment.php?token=$newtoken";
        echo "<CAT-API-Response>\n";
        echo "  <success action='NEWINST'>\n    <enrollment_URL>$URL</enrollment_URL>\n    <inst_unique_id>" . $idp->identifier . "</inst_unique_id>\n  </success>\n";
        echo "</CAT-API-Response>\n";
        exit(0);
        break;
    case 'ADMINCOUNT':
        if (!isset($_POST['INST_IDENTIFIER'])) {
            return_error(ERROR_MISSING_PARAMETER, "Parameter missing (INST_IDENTIFIER)");
            exit(1);
        }
        $wannabeidp = valid_IdP($_POST['INST_IDENTIFIER']);
        if (!$wannabeidp instanceof IdP) {
            return_error(ERROR_INVALID_PARAMETER, "Parameter invalid (INST_IDENTIFIER)");
            exit(1);
        }
        if (strtoupper($wannabeidp->federation) != strtoupper($federation)) {
            return_error(ERROR_INVALID_PARAMETER, "Parameter invalid (INST_IDENTIFIER)");
            exit(1);
        }
        echo "<CAT-API-Response>\n";
        echo "  <success action='ADMINCOUNT'>\n    <number_of_admins>" . count($wannabeidp->owner()) . "</number_of_admins>\n  </success>\n";
        echo "</CAT-API-Response>\n";
        exit(0);
        break;
    case 'STATISTICS':
        echo "<CAT-API-Response>\n";
        echo "  <success action='STATISTICS'>\n";
        echo "    <statistics>\n";
        echo Federation::downloadStats($federation);
        echo "    </statistics>\n";
        echo "  </success>\n";
        echo "</CAT-API-Response>\n";
        break;
    default:
        return_error(ERROR_INVALID_ACTION, "POST contained an unknown ACTION");
        exit(1);
}