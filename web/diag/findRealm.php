<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
/**
 * This file executes AJAX searches from diagnostics page.
 * 
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 *
 * @package Developer
 */
function check_my_nonce($nonce, $optSalt='') {
    $remote = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
    $lasthour = date("G")-1<0 ? date('Ymd').'23' : date("YmdG")-1;
    if (hash_hmac('sha256', session_id().$optSalt, date("YmdG").'1qaz2wsx3edc!QAZ@WSX#EDC'.$remote) == $nonce || 
        hash_hmac('sha256', session_id().$optSalt, $lasthour.'1qaz2wsx3edc!QAZ@WSX#EDC'.$remote) == $nonce) {
        return true;
    } else {
        return false;
    }
}
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
$loggerInstance = new \core\common\Logging();
$returnArray = [];
$headers = apache_request_headers();
$is_ajax = (isset($headers['X-Requested-With']) && $headers['X-Requested-With'] == 'XMLHttpRequest');
$nonce = filter_input(INPUT_GET, 'myNonce', FILTER_SANITIZE_STRING);
$loggerInstance->debug(4, "AJAX $nonce");
if (!$is_ajax || check_my_nonce($nonce, $_SESSION['current_page'])) {
    $loggerInstance->debug(4, 'A hostile AJAX call');
} else {
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
                $details['admins'] = base64_encode(join(',',$admins));
            } else {
                $details['admins'] = '';
            }
            $details['status'] = 1;
            $returnArray = $details;
        }
    } else { 
        if ($realmQueryType) {
            if ($realmQueryType == "co") {
                /* select countries list */
                $details = $cat->getExternalCountriesList();
                if (!empty($details)) {
                    $returnArray['status'] = 1;
                    $returnArray['time'] = $details['time'];
                    unset($details['time']);
                    $returnArray['countries'] = $details;
                } 
            }
            if ($realmQueryType == "inst") {
                if ($realmCountry) {
                    $fed = new \core\Federation(strtoupper($realmCountry));
                    $details = $fed->listExternalEntities(FALSE);
                    if (!empty($details)) {
                        $returnArray['status'] = 1;
                        $returnArray['institutions'] = $details;
                    }    
                }
            }
            if ($realmQueryType == "realm") {
                if ($realmOu) {
                    $details = $cat->getExternalDBEntityDetails($realmOu);
                    if (!empty($details)) {
                        $returnArray['status'] = 1;
                        $returnArray['realms'] = explode(',',$details['realmlist']);
                    }   
                }
            }
        }
    }
    if (empty($returnArray)) {
        $returnArray['status'] = 0;
    }
}
echo(json_encode($returnArray));

