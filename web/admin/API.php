<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

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

$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();

function return_error($code, $description) {
    echo "<CAT-API-Response>\n";
    echo "  <error>\n    <code>" . $code . "</code>\n    <description>$description</description>\n  </error>\n";
    echo "</CAT-API-Response>\n";
}

function cmpSequenceNumber($left, $right) {
        $pat = "/^S([0-9]+)(-.*)?$/";
        $rep = "$1";
        $leftNum = (int) preg_replace($pat, $rep, $left);
        $rightNum = (int) preg_replace($pat, $rep, $right);
        return ($left != $leftNum && $right != $rightNum) ?
                $leftNum - $rightNum :
                strcmp($left, $right);
    }

    
echo "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n";

if (!isset(CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys']) || count(CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys']) == 0) {
    return_error(ERROR_API_DISABLED, "API is disabled in this instance of CAT");
    exit(1);
}

if (!isset($_POST['APIKEY'])) {
    return_error(ERROR_NO_APIKEY, "POST did not contain an APIKEY");
    exit(1);
}

foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['registration_API_keys'] as $key => $fed_name) {
    if ($_POST['APIKEY'] == $key) {
        $mode = "API";
        $federation = $fed_name;
        $checkval = "OK-NEW";
    }
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

$sanitised_action = $validator->string($_POST['ACTION']);

switch ($sanitised_action) {
    case 'NEWINST':
        // fine... we need two parameters for that:
        // mail address, inst name
        if (!isset($_POST['NEWINST_PRIMARYADMIN'])) {
            return_error(ERROR_MISSING_PARAMETER, "POST missed at least one required parameter (NEWINST_PRIMARYADMIN)");
            exit(1);
        }
        // alright: create the IdP, fill in attributes
        $mgmt = new \core\UserManagement();
        $fed = new \core\Federation($federation);
        $idp = new \core\IdP($fed->newIdP("PENDING", "API", $validator->string($_POST['NEWINST_PRIMARYADMIN'])));

        // ensure seq. number asc. order for options (S1, S2...)
        uksort($_POST['option'], ["cmpSequenceNumber"]);

        $instWideOptions = $_POST;
        foreach ($instWideOptions['option'] as $optindex => $optname) {
            if (!preg_match("/^general:/", $optname) && !preg_match("/^support:/", $optname) && !preg_match("/^eap:/", $optname)) {
                unset($instWideOptions['option'][$optindex]);
            }
        }
        // now process all inst-wide options    
        $optionParser->processSubmittedFields($idp, $instWideOptions, $_FILES, 0, "");
        // same thing for profile options
        $profileWideOptions = $_POST;
        foreach ($profileWideOptions['option'] as $optindex => $optname) {
            if (!preg_match("/^profile:/", $optname)) {
                unset($profileWideOptions['option'][$optindex]);
            }
        }
        // if we do have profile-level options - create a profile and fill in the values!
        if (count($profileWideOptions['option']) > 0) {
            $newprofile = $idp->newProfile("RADIUS");
            processSubmittedFields($newprofile, $profileWideOptions, $_FILES, [], 0, 0, TRUE);
            // sift through the options to find API ones (these are not caught by pSF() )
            $therealm = "";
            $theanonid = "anonymous";
            $useAnon = FALSE;
            $stringValuesFiltered = filter_input(INPUT_POST,'value', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);
            $intValuesFiltered = filter_input(INPUT_POST,'value', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
            $pref = 0;
            foreach ($_POST['option'] as $optindex => $optname) {
                switch ($optname) {
                    case "profile-api:anon":
                        // I rather work directly with _POST, but some code 
                        // paths trigger Scrutinizer's security warnings
                        // so relying on the pre-filtered input for those places
                        if (array_key_exists($optindex . "-0", $stringValuesFiltered)) {
                            $theanonid = $validator->string($stringValuesFiltered[$optindex . "-0"]);
                        }
                        break;
                    case "profile-api:realm":
                        if (array_key_exists($optindex . "-0", $stringValuesFiltered)) {
                            $therealm = $validator->realm($stringValuesFiltered[$optindex . "-0"]);
                        }
                        break;
                    case "profile-api:useanon":
                        if (isset($_POST['value'][$optindex . "-3"]) && $validator->boolean($_POST['value'][$optindex . "-3"]) === TRUE) {
                            $useAnon = TRUE;
                        }
                        break;
                    case "profile-api:eaptype":
                        
                        if (isset($intValuesFiltered[$optindex . "-0"])) {
                            $filteredType = $intValuesFiltered[$optindex . "-0"];
                            if ($filteredType <0 || $filteredType >8) {
                                break;
                            }
                            $newprofile->addSupportedEapMethod(new \core\common\EAP($filteredType), $pref);
                            $pref = $pref + 1;
                        }
                        break;
                    default:
                        break;
                }
            }
            if ($therealm != "") {
                $newprofile->setRealm($theanonid . "@" . $therealm);
                if ($useAnon) {
                    $newprofile->setAnonymousIDSupport(true);
                }
            }
            // re-instantiate $profile, we need to do completion checks and need fresh data for isEapTypeDefinitionComplete()
            $profile = ProfileFactory::instantiate($newprofile->identifier);
            $profile->prepShowtime();
        }
        // generate the token
        $newtoken = $mgmt->createToken(true, $validator->string($_POST['NEWINST_PRIMARYADMIN']), $idp);
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
        $wannabeidp = $validator->IdP($_POST['INST_IDENTIFIER']);
        if (!$wannabeidp instanceof \core\IdP) {
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
    default:
        return_error(ERROR_INVALID_ACTION, "POST contained an unknown ACTION");
        exit(1);
}