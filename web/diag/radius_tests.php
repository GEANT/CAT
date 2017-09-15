<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

$loggerInstance = new \core\common\Logging();
$validator = new \web\lib\common\InputValidation();
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");



$additional_message = [
    \core\common\Entity::L_OK => '',
    \core\common\Entity::L_REMARK => _("Some properties of the connection attempt were sub-optimal; the list is below."),
    \core\common\Entity::L_WARN => _("Some properties of the connection attempt were sub-optimal; the list is below."),
    \core\common\Entity::L_ERROR => _("Some configuration errors were observed; the list is below."),
];

function disp_name($eap) {
    $displayName = EAP::eapDisplayName($eap);
    return $displayName['OUTER'] . ( $displayName['INNER'] != '' ? '-' . $displayName['INNER'] : '');
}

if (!isset($_REQUEST['test_type']) || !$_REQUEST['test_type']) {
    throw new Exception("No test type specified!");
}

$test_type = $_REQUEST['test_type'];

$check_realm = $validator->realm($_REQUEST['realm']);

if ($check_realm === FALSE) {
    throw new Exception("Invalid realm was submitted!");
}

if (isset($_REQUEST['profile_id'])) {
    $my_profile = $validator->Profile($_REQUEST['profile_id']);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("RADIUS Tests can only be performed on RADIUS Profiles (d'oh!)");
    }
    $testsuite = new \core\diag\RADIUSTests($check_realm, $my_profile->getRealmCheckOuterUsername(), $my_profile->getEapMethodsinOrderOfPreference(1), $my_profile->getCollapsedAttributes()['eap:server_name'], $my_profile->getCollapsedAttributes()['eap:ca_file']);
} else {
    $my_profile = NULL;
    $testsuite = new \core\diag\RADIUSTests($check_realm, "@".$check_realm);
}


$hostindex = $_REQUEST['hostindex'];
if (!is_numeric($hostindex)) {
    throw new Exception("The requested host index is not numeric!");
}

$posted_host = $_REQUEST['src'];
if (is_numeric($posted_host)) { // UDP tests, this is an index to the test host in config
    $host = filter_var(CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$hostindex]['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
} else { // dynamic discovery host, potentially unvetted user input
    // contains port number; needs to be redacted for filter_var to work
    // in any case, it's a printable string, so filter it initially
    
    $filteredHost = filter_input(INPUT_GET,'src', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST,'src', FILTER_SANITIZE_STRING);
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
    
}



$returnarray = [];
$timeout = CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][$hostindex]['timeout'];
switch ($test_type) {
    case 'udp_login' :
        $i = 0;
        $returnarray['hostindex'] = $hostindex;
        $eaps = $my_profile->getEapMethodsinOrderOfPreference(1);
        $user_name = $validator->User(isset($_REQUEST['username']) && $_REQUEST['username'] ? $_REQUEST['username'] : "");
        $outer_user_name = $validator->User(isset($_REQUEST['outer_username']) && $_REQUEST['outer_username'] ? $_REQUEST['outer_username'] : $user_name);
        $user_password = isset($_REQUEST['password']) && $_REQUEST['password'] ? $_REQUEST['password'] : ""; //!!
        $returnarray['result'] = [];
        foreach ($eaps as $eap) {
            if ($eap->getIntegerRep() == \core\common\EAP::INTEGER_TLS) {
                $run_test = TRUE;
                if ($_FILES['cert']['error'] == UPLOAD_ERR_OK) {
                    $clientcertdata = file_get_contents($_FILES['cert']['tmp_name']);
                    $privkey_pass = isset($_REQUEST['privkey_pass']) && $_REQUEST['privkey_pass'] ? $_REQUEST['privkey_pass'] : ""; //!!
                    if (isset($_REQUEST['tls_username']) && $_REQUEST['tls_username']) {
                        $tls_username = $validator->User($_REQUEST['tls_username']);
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
                    $testresult = $testsuite->UDP_login($hostindex, $eap, $tls_username, $privkey_pass, TRUE, TRUE, $clientcertdata);
                }
            } else {
                $testresult = $testsuite->UDP_login($hostindex, $eap, $user_name, $user_password);
            }
            $returnarray['result'][$i] = $testsuite->consolidateUdpResult($hostindex);
            $returnarray['result'][$i]['eap'] = $eap->getPrintableRep();
            $returnarray['returncode'][$i] = $testresult;


            switch ($testresult) {
                case \core\diag\RADIUSTests::RETVAL_OK :
                    $level = $returnarray['result'][$i]['level'];
                    switch ($level) {
                        case \core\common\Entity::L_OK :
                            $message = _("<strong>Test successful.</strong>");
                            break;
                        case \core\common\Entity::L_REMARK :
                        case \core\common\Entity::L_WARN :
                            $message = _("<strong>Test partially successful</strong>: authentication succeded.") . ' ' . $additional_message[$level];
                            break;
                        case \core\common\Entity::L_ERROR :
                            $message = _("<strong>Test FAILED</strong>: authentication succeded.") . ' ' . $additional_message[$level];
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
    case 'udp' :
        $i = 0;
        $returnarray['hostindex'] = $hostindex;
        $testresult = $testsuite->UDP_reachability($hostindex);
        $returnarray['result'][$i] = $testsuite->consolidateUdpResult($hostindex);
        $returnarray['result'][$i]['eap'] = 'ALL';
        $returnarray['returncode'][$i] = $testresult;
        // a failed check may not have gotten any certificate, be prepared for that
        switch ($testresult) {
            case \core\diag\RADIUSTests::RETVAL_CONVERSATION_REJECT:
                $level = $returnarray['result'][$i]['level'];
                if ($level > \core\common\Entity::L_OK) {
                    $message = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.") . ' ' . $additional_message[$level];
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
        $loggerInstance->debug(4, "SERVER=" . $returnarray['result'][$i]['server'] . "\n");
        $returnarray['result'][$i]['level'] = $level;
        $returnarray['result'][$i]['message'] = $message;
        break;
    case 'capath':
        $rfc6614suite = new \core\diag\RFC6614Tests([$host]);
        $testresult = $rfc6614suite->cApathCheck($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
        // the host member of the array may not be set if RETVAL_SKIPPED was
        // returned (e.g. IPv6 host), be prepared for that
        if (isset($rfc6614suite->TLS_CA_checks_result[$host])) {
            $returnarray['time_millisec'] = sprintf("%d", $rfc6614suite->TLS_CA_checks_result[$host]['time_millisec']);
            if (isset($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity']) && ($rfc6614suite->TLS_CA_checks_result[$host]['cert_oddity'] == \core\diag\RADIUSTests::CERTPROB_UNKNOWN_CA)) {
                $returnarray['message'] = _("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!") . ' (' . sprintf(_("elapsed time: %d"), $rfc6614suite->TLS_CA_checks_result[$host]['time_millisec']) . '&nbsp;ms)';
                $returnarray['level'] = \core\common\Entity::L_ERROR;
            } else {
                $returnarray['message'] = $rfc6614suite->returnCodes[$rfc6614suite->TLS_CA_checks_result[$host]['status']]["message"];
                $returnarray['level'] = \core\common\Entity::L_OK;
                if ($rfc6614suite->TLS_CA_checks_result[$host]['status'] != \core\diag\RADIUSTests::RETVAL_CONNECTION_REFUSED) {
                    $returnarray['message'] .= ' (' . sprintf(_("elapsed time: %d"), $rfc6614suite->TLS_CA_checks_result[$host]['time_millisec']) . '&nbsp;ms)';
                } else {
                    $returnarray['level'] = \core\common\Entity::L_ERROR;
                }
                if ($rfc6614suite->TLS_CA_checks_result[$host]['status'] == \core\diag\RADIUSTests::RETVAL_OK) {
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
                }
                $returnarray['cert_oddities'] = [];
            }
        }
        $returnarray['result'] = $testresult;
        break;
    case 'clients':
        $rfc6614suite = new \core\diag\RFC6614Tests([$host]);
        $testresult = $rfc6614suite->TLS_clients_side_check($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
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
    case 'tls':
        $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
        $opensslbabble = [];
        $loggerInstance->debug(4, CONFIG['PATHS']['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . ROOT . "/config/ca-certs/ 2>&1\n");
        $time_start = microtime(true);
        exec(CONFIG['PATHS']['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . ROOT . "/config/ca-certs/ 2>&1", $opensslbabble);
        $time_stop = microtime(true);
        $measure = ($time_stop - $time_start) * 1000;
        $returnarray['result'] = $testresult;
        $returnarray['time_millisec'] = sprintf("%d", $testsuite->UDP_reachability_result[$host]['time_millisec']);

        if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
            $printedres .= "<tr><td>"._("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!") . $measure ."</td></tr>";
            $my_ip_addrs[$key]["status"] = "FAILED";
            $goterror = 1;
        }
        if (preg_match('/verify return:1/', implode($opensslbabble))) {
            $printedres .= "<tr><td>"._("Completed.") . $measure . "</td></tr>";
            $printedres .= "<tr><td></td><td><div class=\"more\">";
            $my_ip_addrs[$key]["status"] = "OK";
            $servercertRaw = implode("\n", $opensslbabble);
            $servercert = preg_replace("/.*(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\n).*/s", "$1", $servercertRaw);
            $printedres .= 'XXXXXXXXXXXXXXXXXXXX<br>' . _("Server certificate") . '<ul>';
            $data = openssl_x509_parse($servercert);
            $printedres .= '<li>' . _("Subject") . ': ' . $data['name'];
            $printedres .= '<li>' . _("Issuer") . ': ' . certificate_get_issuer($data);
            if (($altname = certificate_get_field($data, 'subjectAltName'))) {
                $printedres .= '<li>' . _("SubjectAltName") . ': ' . $altname;
            }
            $oids = check_policy($data);
            if (!empty($oids)) {
                $printedres .= '<li>' . _("Certificate policies") . ':';
                foreach ($oids as $k => $o) {
                    $printedres .= " $o ($k)";
                }
            }
            if (($crl = certificate_get_field($data, 'crlDistributionPoints'))) {
                $printedres .= '<li>' . _("crlDistributionPoints") . ': ' . $crl;
            }
            if (($ocsp = certificate_get_field($data, 'authorityInfoAccess'))) {
                $printedres .= '<li>' . _("authorityInfoAccess") . ': ' . $ocsp;
            }
            $printedres .= '</ul></div></tr></td>';
        }
        break;
    default:
        throw new Exception("Unknown test requested: default case reached!");
}

echo(json_encode($returnarray));

