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


require_once dirname(dirname(dirname(__FILE__)))."/config/_config.php";

// we are referring to $_SESSION later in the file
\core\CAT::sessionStart();

$jsonDir = dirname(dirname(dirname(__FILE__)))."/var/json_cache";

$loggerInstance = new \core\common\Logging();
$returnArray = [];
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_user");
$cat = new \core\CAT();
$givenRealm = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'realm')));
$outerUser = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'outeruser')));
$realmQueryType = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'type')));
$realmCountry = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'co')));
$realmOu = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'ou')));
$forTests = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'addtest')));
$token = htmlspecialchars(strip_tags(filter_input(INPUT_GET, 'token') ?? filter_input(INPUT_POST, 'token')));
if ($token && !is_dir($jsonDir.'/'.$token)) {
    mkdir($jsonDir.'/'.$token, 0777, true);
}
if ($givenRealm != '') {
    $realmElems = explode('.', $givenRealm);
    $lap = count($realmElems);
    $i = 0;
    $foundIndex = NULL;
    /* select the record matching the realm */
    while (($lap - $i) > 1) {
        $realmToCheck = implode('.', array_slice($realmElems, $i, $lap));
        $externalDB = \core\CAT::determineExternalConnection();    
        $allRealms = $externalDB->listExternalEntitiesByRealm($realmToCheck, ['inst_realm', 'contact']);
        if (count($allRealms) == 0) {
            $i += 1;
            continue;
        }
        foreach ($allRealms as $key => $realmRecord) {
            $realmList = explode(',', $realmRecord['inst_realm']);
            foreach ($realmList as $realm) {
                if ($realm == $realmToCheck) {
                    $foundIndex = $key;
                    break;
                }
            }
        }
        $details = [];
        if (is_null($foundIndex)) {
            break;
        }
        $admins = array();
        if ($allRealms[$foundIndex]['contact']) {
            $elems = explode(', ', $allRealms[$foundIndex]['contact']);
            foreach ($elems as $admin) {
                if (substr($admin, 0, 2) == 'e:') {
                    $admins[] = substr($admin, 3);
                }
            }
            $details['admins'] = base64_encode(join(',', $admins));
        } else {
            $details['admins'] = '';
        }        
        $details['status'] = 1;
        $details['realm'] = $givenRealm;   
        break;
    }
    if (is_null($foundIndex)) {
        $details['realm'] = $givenRealm;
        $details['admins'] = '';
        $details['status'] = 0;
    } 
    
    if ($forTests) {
        $rfc7585suite = new \core\diag\RFC7585Tests($givenRealm); 
        
        $testsuite = new \core\diag\RADIUSTests($givenRealm, '@'.$givenRealm);
        
        $naptr = $rfc7585suite->relevantNAPTR(); 
        if ($naptr != \core\diag\RADIUSTests::RETVAL_NOTCONFIGURED && $naptr > 0) {
            $naptr_valid = $rfc7585suite->relevantNAPTRcompliance();
            if ($naptr_valid == \core\diag\RADIUSTests::RETVAL_OK) {
                $srv = $rfc7585suite->relevantNAPTRsrvResolution();
                if ($srv > 0) {
                    $hosts = $rfc7585suite->relevantNAPTRhostnameResolution();
                }
            }
        } else {
            $naptr_valid = 0; 
            $srv = 0;
            $hosts = 0;
        } 
        $toTest = array();
        foreach ($rfc7585suite->NAPTR_hostname_records as $hostindex => $addr) {
            $ssltest = 1;
            if (isset($addr['unavailable']) && $addr['unavailable']) {
                $ssltest = 0;
            }            
            $host = ($addr['family'] == "IPv6" ? "[" : "").$addr['IP'].($addr['family'] == "IPv6" ? "]" : "").":".$addr['port'];
            $expectedName = $addr['hostname'];
            $toTest[$hostindex] = array(
                                        'host' => $host,
                                        'name' => $expectedName,
                                        'bracketaddr' => ($addr["family"] == "IPv6" ? "[".$addr["IP"]."]" : $addr["IP"]).' TCP/'.$addr['port'],
                                        'ssltest' => $ssltest                                                       
            );
        }
 
        $details['totest'] = $toTest;
        $details['rfc7585suite'] = base64_encode(serialize($rfc7585suite));
        $details['testsuite'] = base64_encode(serialize($testsuite));
        $details['naptr'] = $naptr;
        $details['naptr_valid'] = $naptr_valid;
        $details['srv'] = $srv;
        $details['hosts'] = $hosts;
    } 
    $returnArray = $details;    
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
                    $details = $fed->listExternalEntities(FALSE, core\ExternalEduroamDBData::TYPE_IDP);
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
                if ($forTests) {
                    $details['diag'] = 2;
                }
                break;
            case "hotspot":
                if ($realmCountry) {
                    $fed = new \core\Federation(strtoupper($realmCountry));
                    $details = $fed->listExternalEntities(FALSE, core\IdP::TYPE_SP);
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

$returnArray['outeruser'] = $outerUser;
$returnArray['datetime'] = date("Y-m-d H:i:s");
$loggerInstance->debug(4, $returnArray);
$json_data = json_encode($returnArray);

if ($token) {
    $loggerInstance->debug(4, 'JSON data written to ' .$jsonDir.'/'.$token);
    file_put_contents($jsonDir.'/'.$token.'/realm', $json_data);
}
header("Content-type: application/json; utf-8");
echo($json_data);

