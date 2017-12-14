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
/*$headers = apache_request_headers();
$is_ajax = (isset($headers['X-Requested-With']) && $headers['X-Requested-With'] == 'XMLHttpRequest');
$nonce = filter_input(INPUT_GET, 'myNonce', FILTER_SANITIZE_STRING);
$loggerInstance->debug(4, "AJAX $nonce");
 */
$is_ajax = True;
if (!$is_ajax) { /*|| check_my_nonce($nonce, $_SESSION['current_page'])) {*/
    $loggerInstance->debug(4, 'A hostile AJAX call');
} else {
    $languageInstance = new \core\common\Language();
    $languageInstance->setTextDomain("web_user");
    $cat = new \core\CAT();
    $realm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_STRING);
    $mac = filter_input(INPUT_GET, 'mac', FILTER_SANITIZE_STRING);
    $freetext = filter_input(INPUT_GET, 'freetext', FILTER_SANITIZE_STRING);
    $timestamp = filter_input(INPUT_GET, 'timestamp', FILTER_SANITIZE_STRING);
    $idpcontact = filter_input(INPUT_GET, 'idpcontact', FILTER_SANITIZE_STRING);
    $returnArray = array();
    $returnArray['realm'] = $realm;
    $returnArray['spcontact'] = $email;
    $returnArray['mac'] = $mac;
    $returnArray['description'] = $freetext;
    $returnArray['timestamp'] = $timestamp;
    $returnArray['idpcontact'] = base64_decode($idpcontact);
    $returnArray['status'] = 1;
}
echo(json_encode($returnArray));

