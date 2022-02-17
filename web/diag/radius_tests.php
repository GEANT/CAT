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

require_once dirname(dirname(dirname(__FILE__)))."/config/_config.php";

$loggerInstance = new \core\common\Logging();
$validator = new \web\lib\common\InputValidation();
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("diagnostics");

$jsonDir = dirname(dirname(dirname(__FILE__)))."/var/json_cache";

$additional_message = [
    \core\common\Entity::L_OK => '',
    \core\common\Entity::L_REMARK => _("Some properties of the connection attempt were sub-optimal; the list is below."),
    \core\common\Entity::L_WARN => _("Some properties of the connection attempt were sub-optimal; the list is below."),
    \core\common\Entity::L_ERROR => _("Some configuration errors were observed; the list is below."),
];

/**
 * returns the friendly name of an EAP type
 * 
 * @param array $eap array representation of the EAP type to be returned
 * @return string the friendly name
 */
function disp_name($eap)
{
    $displayName = \core\common\EAP::eapDisplayName($eap);
    return $displayName['OUTER'].($displayName['INNER'] != '' ? '-'.$displayName['INNER'] : '');
}

if (!isset($_REQUEST['test_type']) || !$_REQUEST['test_type']) {
    throw new Exception("No test type specified!");
}

const VALID_TEST_TYPES = ['udp_login', 'udp', 'capath', 'clients', 'openroamingcapath', 'openroamingclients'];

$test_type = 'INVALID'; // will throw Exception if not replaced with correct
foreach (VALID_TEST_TYPES as $index => $oneType) {
    if ($_REQUEST['test_type'] == $oneType) {
        $test_type = VALID_TEST_TYPES[$index]; // from constant -> definitely not user-tainted
    }
}

$check_realm = $validator->realm($_REQUEST['realm']);

if ($check_realm === FALSE) {
    throw new Exception("Invalid realm was submitted!");
}

if (isset($_REQUEST['profile_id'])) {
    $my_profile = $validator->existingProfile($_REQUEST['profile_id']);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("RADIUS Tests can only be performed on RADIUS Profiles (d'oh!)");
    }
    $testsuite = new \core\diag\RADIUSTests($check_realm, $my_profile->getRealmCheckOuterUsername(), $my_profile->getEapMethodsinOrderOfPreference(1), $my_profile->getCollapsedAttributes()['eap:server_name'], $my_profile->getCollapsedAttributes()['eap:ca_file']);
} else {
    $my_profile = NULL;
    if (isset($_REQUEST['outer_user'])) {
        $testsuite = new \core\diag\RADIUSTests($check_realm, $_REQUEST['outer_user'].'@'.$check_realm);
    } else {
        $testsuite = new \core\diag\RADIUSTests($check_realm, '@'.$check_realm);
    }
}

$hostindex = $_REQUEST['hostindex'];
if (!is_numeric($hostindex)) {
    throw new Exception("The requested host index is not numeric!");    
}

$token = '';
if (isset($_REQUEST['token'])) {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
}

$posted_host = $_REQUEST['src'];
if (is_numeric($posted_host)) { // UDP tests, this is an index to the test host in config
    $host = filter_var(\config\Diagnostics::RADIUSTESTS['UDP-hosts'][$hostindex]['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    $expectedName = "IRRELEVANT-UDP";
} else { // dynamic discovery host, potentially unvetted user input
    // contains port number; needs to be redacted for filter_var to work
    // in any case, it's a printable string, so filter it initially
    $filteredHost = filter_input(INPUT_GET, 'src', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'src', FILTER_SANITIZE_STRING);
    $hostonly1 = preg_replace('/:[0-9]*$/', "", $filteredHost);
    $hostonly2 = preg_replace('/^\[/', "", $hostonly1);
    $hostonly3 = preg_replace('/\]$/', "", $hostonly2);
    $hostonly = filter_var($hostonly3, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    // check if this is a valid IP address
    if ($hostonly === FALSE) {
        throw new Exception("The configured test host ($hostonly) is not a valid IP address from acceptable IP ranges!");
    }
    // host IP address testing passed. So let's take our port number back
    $host = $filteredHost;
    $expectedName = filter_input(INPUT_GET, 'expectedname', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'expectedname', FILTER_SANITIZE_STRING);
}

$returnarray = [];
$timeout = \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$hostindex]['timeout'];
$consortiumName = 'eduroam';
switch ($test_type) {
    case 'udp_login':
        $i = 0;
        $returnarray['hostindex'] = $hostindex;
        $eaps = $my_profile->getEapMethodsinOrderOfPreference(1);
        $user_name = $validator->syntaxConformUser(isset($_REQUEST['username']) && $_REQUEST['username'] ? $_REQUEST['username'] : "");
        $outer_user_name = $validator->syntaxConformUser(isset($_REQUEST['outer_username']) && $_REQUEST['outer_username'] ? $_REQUEST['outer_username'] : $user_name);
        $testsuite->setOuterIdentity($outer_user_name);
        $user_password = isset($_REQUEST['password']) && $_REQUEST['password'] ? $_REQUEST['password'] : ""; //!!
        $returnarray['result'] = [];
        foreach ($eaps as $eap) {
            if ($eap->getIntegerRep() == \core\common\EAP::INTEGER_TLS) {
                $run_test = TRUE;
                if ($_FILES['cert']['error'] == UPLOAD_ERR_OK) {
                    $clientcertdata = file_get_contents($_FILES['cert']['tmp_name']);
                    $privkey_pass = isset($_REQUEST['privkey_pass']) && $_REQUEST['privkey_pass'] ? $_REQUEST['privkey_pass'] : ""; //!!
                    if (isset($_REQUEST['tls_username']) && $_REQUEST['tls_username']) {
                        $tls_username = $validator->syntaxConformUser(filter_input(INPUT_POST, 'tls_username', FILTER_SANITIZE_STRING));
                    } else {
                        if (openssl_pkcs12_read($clientcertdata, $certs, $privkey_pass)) {
                            $mydetails = openssl_x509_parse($certs['cert']);
                            if (isset($mydetails['subject']['CN']) && $mydetails['subject']['CN']) {
                                $tls_username = $mydetails['subject']['CN'];
                                $loggerInstance->debug(4, "PKCS12-CN=$tls_username\n");
                            } else {
                                $testresult = \core\diag\RADIUSTests::RETVAL_INCOMPLETE_DATA;
                                $run_test = FALSE;
                            }
                        } else {
                            $testresult = \core\diag\RADIUSTests::RETVAL_WRONG_PKCS12_PASSWORD;
                            $run_test = FALSE;
                        }
                    }
                } else {
                    $testresult = \core\diag\RADIUSTests::RETVAL_INCOMPLETE_DATA;
                    $run_test = FALSE;
                }
                if ($run_test) {
                    $loggerInstance->debug(4, "TLS-USERNAME=$tls_username\n");
                    $testresult = $testsuite->udpLogin($hostindex, $eap->getArrayRep(), $tls_username, $privkey_pass, TRUE, TRUE, $clientcertdata);
                }
            } else {
                $testresult = $testsuite->udpLogin($hostindex, $eap->getArrayRep(), $user_name, $user_password);
            }
            $returnarray['result'][$i] = $testsuite->consolidateUdpResult($hostindex);
            $returnarray['result'][$i]['eap'] = $eap->getPrintableRep();
            $returnarray['returncode'][$i] = $testresult;


            switch ($testresult) {
                case \core\diag\RADIUSTests::RETVAL_OK:
                    $level = $returnarray['result'][$i]['level'];
                    switch ($level) {
                        case \core\common\Entity::L_OK:
                            $message = _("<strong>Test successful.</strong>");
                            break;
                        case \core\common\Entity::L_REMARK:
                        case \core\common\Entity::L_WARN:
                            $message = _("<strong>Test partially successful</strong>: authentication succeded.").' '.$additional_message[$level];
                            break;
                        case \core\common\Entity::L_ERROR:
                            $message = _("<strong>Test FAILED</strong>: authentication succeded.").' '.$additional_message[$level];
                            break;
                    }
                    break;
                case \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT:
                    $message = _("<strong>Test FAILED</strong>: the request was rejected. The most likely cause is that you have misspelt the Username and/or the Password.");
                    $level = \core\common\Entity::L_ERROR;
                    break;
                case \core\diag\RADIUSTests::RETVAL_NOTCONFIGURED:
                    $level = \core\common\Entity::L_ERROR;
                    $message = _("This method cannot be tested");
                    break;
                case \core\diag\RADIUSTests::RETVAL_IMMEDIATE_REJECT:
                    $level = \core\common\Entity::L_ERROR;
                    $message = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. Either you have misspelt the Username or there is something seriously wrong with your server.");
                    unset($returnarray['result'][$i]['cert_oddities']);
                    $returnarray['result'][$i]['server'] = 0;
                    break;
                case \core\diag\RADIUSTests::RETVAL_NO_RESPONSE:
                    $level = \core\common\Entity::L_ERROR;
                    $message = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
                    unset($returnarray['result'][$i]['cert_oddities']);
                    $returnarray['result'][$i]['server'] = 0;
                    break;
                case \core\diag\RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM:
                    $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
                    $returnarray['level'] = \core\common\Entity::L_ERROR;
                    break;
                default:
                    $level = isset($testsuite->returnCodes[$testresult]['severity']) ? $testsuite->returnCodes[$testresult]['severity'] : \core\common\Entity::L_ERROR;
                    $message = isset($testsuite->returnCodes[$testresult]['message']) ? $testsuite->returnCodes[$testresult]['message'] : _("<strong>Test FAILED</strong>");
                    $returnarray['result'][$i]['server'] = 0;
                    break;
            }
            $returnarray['result'][$i]['level'] = $level;
            $returnarray['result'][$i]['message'] = $message;
            $i++;
        }
        break;
    case 'udp':
        $i = 0;
        $returnarray['hostindex'] = $hostindex;
        $testresult = $testsuite->udpReachability($hostindex);
        $returnarray['result'][$i] = $testsuite->consolidateUdpResult($hostindex);
        $returnarray['result'][$i]['eap'] = 'ALL';
        $returnarray['returncode'][$i] = $testresult;
        // a failed check may not have gotten any certificate, be prepared for that
        switch ($testresult) {
            case \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT:
                $level = $returnarray['result'][$i]['level'];
                if ($level > \core\common\Entity::L_OK) {
                    $message = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.").' '.$additional_message[$level];
                } else {
                    $message = _("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.");
                }
                break;
            case \core\diag\RADIUSTests::RETVAL_IMMEDIATE_REJECT:
                $message = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. This is not necessarily an error: if the RADIUS server enforces that outer identities correspond to an existing username, then this result is expected (Note: you could configure a valid outer identity in your profile settings to get past this hurdle). In all other cases, the server appears misconfigured or it is unreachable.");
                $level = \core\common\Entity::L_WARN;
                break;
            case \core\diag\RADIUSTests::RETVAL_NO_RESPONSE:
                $returnarray['result'][$i]['server'] = 0;
                $message = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
                $level = \core\common\Entity::L_ERROR;
                break;
            case \core\diag\RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM:
                $message = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
                $level = \core\common\Entity::L_ERROR;
                break;
            default:
                $message = _("unhandled error");
                $level = \core\common\Entity::L_ERROR;
                break;
        }
        $loggerInstance->debug(4, "SERVER=".$returnarray['result'][$i]['server']."\n");
        $returnarray['result'][$i]['level'] = $level;
        $returnarray['result'][$i]['message'] = $message;
        break;
    case 'openroamingcapath':
        $consortiumName = 'openroaming';
    case 'capath':
        $rfc6614suite = new \core\diag\RFC6614Tests([$host], $expectedName, $consortiumName);
        $testresult = $rfc6614suite->cApathCheck($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
        $returnarray['consortium'] = $consortiumName;
        // the host member of the array may not be set if RETVAL_SKIPPED was
        // returned (e.g. IPv6 host), be prepared for that
        if (!isset($rfc6614suite->TLS_CA_checks_result[$host])) {
            $returnarray['result'] = $testresult;
            break;
        }
        // we tried to contact someone, and know how long that took
        $returnarray['time_millisec'] = sprintf("%d", $rfc6614suite->TLS_CA_checks_result[$host]['time_millisec']);
        $timeDisplay = ' ('.sprintf(_("elapsed time: %d"), $rfc6614suite->TLS_CA_checks_result[$host]['time_millisec']).'&nbsp;ms)';
        if (isset($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity']) && ($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity'] == \core\diag\RADIUSTests::CERTPROB_UNKNOWN_CA)) {
            $returnarray['message'] = _("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!").$timeDisplay;
            $returnarray['level'] = \core\common\Entity::L_ERROR;
            $returnarray['result'] = $testresult;
            break;
        }
        // probably all is well, but we override this default later if need be
        $returnarray['message'] = $rfc6614suite->returnCodes[$rfc6614suite->TLS_CA_checks_result[$host]['status']]["message"];
        $returnarray['level'] = \core\common\Entity::L_OK;
        // override if the connection was with a mismatching server name
        if (isset($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity']) && ($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity'] == \core\diag\RADIUSTests::CERTPROB_DYN_SERVER_NAME_MISMATCH)) {
            $returnarray['message'] = _("<strong>WARNING</strong>: the server name as discovered in the SRV record does not match any name in the server certificate!").$timeDisplay;
            $returnarray['level'] = \core\common\Entity::L_WARN;
        }
        switch ($rfc6614suite->TLS_CA_checks_result[$host]['status']) {
            case \core\diag\RADIUSTests::RETVAL_CONNECTION_REFUSED:
                $returnarray['level'] = \core\common\Entity::L_ERROR;
                break;
            case \core\diag\RADIUSTests::RETVAL_SERVER_UNFINISHED_COMM:
                $returnarray['level'] = \core\common\Entity::L_ERROR;
                break;
            case \core\diag\RADIUSTests::RETVAL_OK:
            $returnarray['certdata'] = [];
            $returnarray['certdata']['subject'] = $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['subject'];
            $returnarray['certdata']['issuer'] = $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['issuer'];
            $returnarray['certdata']['extensions'] = [];
            if (isset($rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'])) {
                $returnarray['certdata']['extensions']['subjectaltname'] = $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'];
            }
            if (isset($rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['policyoid'])) {
                $returnarray['certdata']['extensions']['policies'] = join(' ', $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['policyoid']);
            }
            if (isset($rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['crlDistributionPoint'])) {
                $returnarray['certdata']['extensions']['crldistributionpoints'] = $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['crlDistributionPoint'];
            }
            if (isset($rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['authorityInfoAccess'])) {
                $returnarray['certdata']['extensions']['authorityinfoaccess'] = $rfc6614suite->TLS_CA_checks_result[$host]['certdata']['extensions']['authorityInfoAccess'];
            }
            break;
            default:
                $returnarray['message'] .= $timeDisplay;
        }
        
        $returnarray['cert_oddities'] = [];
        $returnarray['result'] = $testresult;
        break;
    case 'openroamingclient':
        $consortiumName = 'openroaming';
    case 'clients':
        $rfc6614suite = new \core\diag\RFC6614Tests([$host], $expectedName, $consortiumName);
        $testresult = $rfc6614suite->tlsClientSideCheck($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
        $returnarray['consortium'] = $consortiumName;
        $k = 0;
        // the host member of the array may not exist if RETVAL_SKIPPED came out
        // (e.g. no client cert to test with). Be prepared for that
        if (isset($rfc6614suite->TLS_clients_checks_result[$host])) {
            foreach ($rfc6614suite->TLS_clients_checks_result[$host]['ca'] as $type => $cli) {
                foreach ($cli as $key => $val) {
                    $returnarray['ca'][$k][$key] = $val;
                }
                $k++;
            }
        }
        $returnarray['result'] = $testresult;
        break;
    default:
        throw new Exception("Unknown test requested: default case reached!");
}
$returnarray['datetime'] = date("Y-m-d H:i:s");
if (!is_dir($jsonDir.'/'.$token)) {
    mkdir($jsonDir.'/'.$token, 0777, true);
}
$json_data = json_encode($returnarray);
if ($token != '') {
    file_put_contents($jsonDir.'/'.$token.'/'.$test_type.'_'.$hostindex, $json_data);
}
echo($json_data);