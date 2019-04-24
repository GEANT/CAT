<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * This file executes AJAX searches from diagnostics page.
 * 
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 *
 * @package Developer
 */

/**
 * 
 * @param string $nonce   the nonce that was sent to the page and is to be verified
 * @param string $optSalt an optional salt value
 * @return boolean
 */

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

// we are referring to $_SESSION later in the file
CAT_session_start();

$loggerInstance = new \core\common\Logging();
$returnArray = [];

$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_user");
$cat = new \core\CAT();
$realmByUser = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
$realmQueryType = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
$realmCountry = filter_input(INPUT_GET, 'co', FILTER_SANITIZE_STRING);
$realmOu = filter_input(INPUT_GET, 'ou', FILTER_SANITIZE_STRING);
if (!empty($realmByUser)) {
    /* select the record matching the realm */
    $details = $cat->getExternalDBEntityDetails(0, $realmByUser);
    if (!empty($details)) {
        $admins = array();
        if (!empty($details['admins'])) {
            foreach ($details['admins'] as $admin) {
                $admins[] = $admin['email'];
            }
            $details['admins'] = base64_encode(join(',', $admins));
        } else {
            $details['admins'] = '';
        }
        $details['status'] = 1;
        $returnArray = $details;
    }
} else {
    if ($realmQueryType) {
        switch ($realmQueryType) {
            case "co":
                /* select countries list */
                $details = $cat->getExternalCountriesList();
                if (!empty($details)) {
                    $returnArray['status'] = 1;
                    $returnArray['time'] = $details['time'];
                    unset($details['time']);
                    $returnArray['countries'] = $details;
                }
                break;
            case "inst":
                if ($realmCountry) {
                    $fed = new \core\Federation(strtoupper($realmCountry));
                    $details = $fed->listExternalEntities(FALSE, \core\Federation::EDUROAM_DB_TYPE_IDP);
                    if (!empty($details)) {
                        $returnArray['status'] = 1;
                        $returnArray['institutions'] = $details;
                    }
                }
                break;
            case "realm":
                if ($realmOu) {
                    $details = $cat->getExternalDBEntityDetails($realmOu);
                    if (!empty($details)) {
                        $returnArray['status'] = 1;
                        $returnArray['realms'] = explode(',', $details['realmlist']);
                    }
                }
                break;
            case "hotspot":
                if ($realmCountry) {
                    $fed = new \core\Federation(strtoupper($realmCountry));
                    $details = $fed->listExternalEntities(FALSE, \core\Federation::EDUROAM_DB_TYPE_SP);
                    if (!empty($details)) {
                        $returnArray['status'] = 1;
                        $returnArray['hotspots'] = $details;
                    }
                }
                break;
            default:
                throw new Exception("Unknown realmQueryType");
        }
    }
}
if (empty($returnArray)) {
    $returnArray['status'] = 0;
}
$loggerInstance->debug(2, $returnArray);

echo(json_encode($returnArray));

