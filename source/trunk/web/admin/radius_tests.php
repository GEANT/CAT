<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("RADIUSTests.php");
require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");


ini_set('display_errors', '0');

$additional_message = array (
   L_OK => '',
   L_REMARK => _("Some properties of the connection attempt were sub-optimal; the list is below."),
   L_WARN => _("Some properties of the connection attempt were sub-optimal; the list is below."),
   L_ERROR => _("Some configuration errors were observed; the list is below."),
 
);

function disp_name($eap) {
    $D = EAP::eapDisplayName($eap);
    return $D['OUTER'] . ( $D['INNER'] != '' ? '-' . $D['INNER'] : '');
}

function printDN($dn) {
  $out = '';
  foreach (array_reverse($dn) as $k => $v) {
      if($out)
        $out .= ',';
      $out .= "$k=$v";
  }
  return($out);
}

function printTm($tm) {
  return(gmdate(DateTime::COOKIE,$tm));
}



function process_result($testsuite,$host) {
    $ret = array();
    $server_info = array();
    $udp_result = $testsuite->UDP_reachability_result[$host];

    foreach ($udp_result['certdata'] as $certdata) {
       if($certdata['type'] != 'server' )
          continue;
       $server_cert = array (
          'subject' => printDN($certdata['subject']),
          'issuer' => printDN($certdata['issuer']),
          'validFrom' => printTm($certdata['validFrom_time_t']),
          'validTo' => printTm($certdata['validTo_time_t']),
          'serialNumber' => $certdata['serialNumber'].sprintf(" (0x%X)",$certdata['serialNumber']),
          'sha1' => $certdata['sha1'],
          'extensions' => $certdata['extensions']
       );
    }
    if(isset($udp_result['incoming_server_names'][0]) ) {
        $ret['server'] = sprintf(_("Connected to %s."), $udp_result['incoming_server_names'][0]);
         $ret['server_cert'] = $server_cert;
    }
    else
        $ret['server'] = 0;
    $ret['level'] = L_OK;
    $ret['time_millisec'] = sprintf("%d", $udp_result['time_millisec']);
    if (isset($udp_result['cert_oddities']) && count($udp_result['cert_oddities']) > 0) {
        $ret['message'] = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned. Some properties of the connection attempt were sub-optimal; the list is below.");
        $ret['cert_oddities'] = array();
        foreach ($udp_result['cert_oddities'] as $oddity) {
            $o = array();
            $o['code'] = $oddity;
            $o['message'] = isset($testsuite->return_codes[$oddity]["message"]) && $testsuite->return_codes[$oddity]["message"] ? $testsuite->return_codes[$oddity]["message"] : $oddity;
            $o['level'] = $testsuite->return_codes[$oddity]["severity"];
            $ret['level'] = max($ret['level'], $testsuite->return_codes[$oddity]["severity"]);
            $ret['cert_oddities'][] = $o;
        }
    } else {
        $ret['message'] = _("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.");
    }
    return $ret;
}

if (!isset($_REQUEST['test_type']) || !$_REQUEST['test_type'])
    exit;

$Cat = new CAT();
$Cat->set_locale("web_admin");
$test_type = $_REQUEST['test_type']; 
$check_realm = valid_Realm($_REQUEST['realm']); 
if (isset($_REQUEST['profile_id'])) {
    $my_profile = valid_Profile($_REQUEST['profile_id']); 
    $check_realm = valid_Realm($_REQUEST['realm'], $_REQUEST['profile_id']); 
    $testsuite = new RADIUSTests($check_realm, $my_profile->identifier);
} else {
    $my_profile = NULL;
    $check_realm = valid_Realm($_REQUEST['realm']); 
    $testsuite = new RADIUSTests($check_realm);
}
$host = $_REQUEST['src'];
if(!preg_match('/^[0-9\.:]*$/',$host))
   exit;

$hostindex = $_REQUEST['hostindex']; 
if(!is_numeric($hostindex))
  exit;


$returnarray = array();
$timeout = Config::$RADIUSTESTS['UDP-hosts'][$hostindex]['timeout'];
switch ($test_type) {
    case 'udp_login' :
        $i = 0;
        $returnarray['hostindex'] = $hostindex;
        $eaps = $my_profile->getEapMethodsinOrderOfPreference(1);
        $user_name = valid_user(isset($_REQUEST['username']) && $_REQUEST['username'] ? $_REQUEST['username'] : "");
        $outer_user_name = valid_user(isset($_REQUEST['outer_username']) && $_REQUEST['outer_username'] ? $_REQUEST['outer_username'] : "");
        $user_password = isset($_REQUEST['password']) && $_REQUEST['password'] ? $_REQUEST['password'] : ""; //!!
        $returnarray['result'] = array();
        foreach ($eaps as $eap) {
            if ($eap == EAP::$TLS) {
                $run_test = TRUE;
                if ($_FILES['cert']['error'] == UPLOAD_ERR_OK) {
                    $clientcertdata = file_get_contents($_FILES['cert']['tmp_name']);
                    $privkey_pass = isset($_REQUEST['privkey_pass']) && $_REQUEST['privkey_pass'] ? $_REQUEST['privkey_pass'] : ""; //!!
                    if(isset($_REQUEST['tls_username']) && $_REQUEST['tls_username']) {
                        $tls_username = valid_user($_REQUEST['tls_username']);
                    } else {
                        if(openssl_pkcs12_read($clientcertdata,$certs,$privkey_pass)) {
                            $mydetails = openssl_x509_parse($certs['cert']);
                            if(isset($mydetails['subject']['CN']) && $mydetails['subject']['CN']) {
                                $tls_username=$mydetails['subject']['CN'];
                                debug(4,"PKCS12-CN=$tls_username\n");
                            } else {
                                $testresult = RETVAL_INCOMPLETE_DATA;
                                $run_test = FALSE;
                            }
                        } else {
                            $testresult = RETVAL_WRONG_PKCS12_PASSWORD;
                            $run_test = FALSE;
                        }
                    }
                } else {
                    $testresult = RETVAL_INCOMPLETE_DATA;
                    $run_test = FALSE;
                }
                    if($run_test) {
                        debug(4,"TLS-USERNAME=$tls_username\n");
                        $testresult = $testsuite->UDP_login($hostindex, $eap, $tls_username, $privkey_pass,'', TRUE, TRUE, $clientcertdata);
                    }
            } else {
                $testresult = $testsuite->UDP_login($hostindex, $eap, $user_name, $user_password,$outer_user_name);
            }
        $returnarray['result'][$i] = process_result($testsuite,$hostindex);
        $returnarray['result'][$i]['eap'] = display_name($eap);
        $returnarray['returncode'][$i] = $testresult;

            switch ($testresult) {
                case RETVAL_OK :
                    $level = $returnarray['result'][$i]['level'];
                    switch($level) {
                         case L_OK :
                             $message = _("<strong>Test successful.</strong>");
                             break;
                         case L_REMARK :
                         case L_WARN :
                             $message = _("<strong>Test partially successful</strong>: authentication succeded.") . ' ' . $additional_message[$level];
                             break;
                         case L_ERROR :
                             $message = _("<strong>Test FAILED</strong>: authentication succeded.") . ' ' . $additional_message[$level];
                             break;
                    }
                    break;
                case RETVAL_CONVERSATION_REJECT:
                    $message = _("<strong>Test FAILED</strong>: the request was rejected. The most likely cause is that you have misspelt the Username and/or the Password.");
                    $level = L_ERROR;
                    break;
                case RETVAL_NOT_CONFIGURED:
                    $level = L_ERROR;
                    $message = _("This method cannot be tested");
                    break;
                case RETVAL_IMMEDIATE_REJECT:
                    $level = L_ERROR;
                    $message = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. Either you have misspelt the Username or there is something seriously wrong with your server.");
                    unset($returnarray['result'][$i]['cert_oddities']);
                    $returnarray['result'][$i]['server'] = 0;
                    break;
                case RETVAL_NO_RESPONSE:
                    $level = L_ERROR;
                    $message = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
                    unset($returnarray['result'][$i]['cert_oddities']);
                    $returnarray['result'][$i]['server'] = 0;
                    break;
                case RETVAL_SERVER_UNFINISHED_COMM:
                    $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
                    $returnarray['level'] = L_ERROR;
                    break;
                default:
                    $level = isset($testsuite->return_codes[$testresult]['severity']) ? $testsuite->return_codes[$testresult]['severity'] : L_ERROR;
                    $message = isset($testsuite->return_codes[$testresult]['message']) ? $testsuite->return_codes[$testresult]['message'] : _("<strong>Test FAILED</strong>");
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
        $returnarray['result'][$i] = process_result($testsuite,$hostindex);
        $returnarray['result'][$i]['eap'] = 'ALL';
        $returnarray['returncode'][$i] = $testresult;
        // a failed check may not have gotten any certificate, be prepared for that
        switch ($testresult) {
            case RETVAL_CONVERSATION_REJECT:
                $level = $returnarray['result'][$i]['level'];
                if($level > L_OK)
                    $message = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.") . ' ' . $additional_message[$level];
                else
                    $message = _("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.");
                break;
            case RETVAL_IMMEDIATE_REJECT:
                $message = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. This is not necessarily an error: if the RADIUS server enforces that outer identities correspond to an existing username, then this result is expected (Note: you could configure a valid outer identity in your profile settings to get past this hurdle). In all other cases, the server appears misconfigured or it is unreachable.");
                $level= L_WARN;
                break;
            case RETVAL_NO_RESPONSE:
                    $returnarray['result'][$i]['server'] = 0;
                $message = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
                $level = L_ERROR;
                break;
            case RETVAL_SERVER_UNFINISHED_COMM:
                $message = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
                $level = L_ERROR;
                break;
            default:
                $message = _("unhandled error");
                $level= L_ERROR;
                break;
        }
        $returnarray['result'][$i]['level'] = $level;
        $returnarray['result'][$i]['message'] = $message;
        break;
    case 'capath':
        $testresult = $testsuite->CApath_check($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
        // the host member of the array may not be set if RETVAL_SKIPPED was
        // returned (e.g. IPv6 host), be prepared for that
        if (isset($testsuite->TLS_CA_checks_result[$host])) {
            $returnarray['time_millisec'] = sprintf("%d", $testsuite->TLS_CA_checks_result[$host]['time_millisec']);
            if (isset($testsuite->TLS_CA_checks_result[$host]['cert_oddity']) && ($testsuite->TLS_CA_checks_result[$host]['cert_oddity'] == CERTPROB_UNKNOWN_CA)) {
                $returnarray['message'] = _("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!") . ' (' . sprintf(_("elapsed time: %d"), $testsuite->TLS_CA_checks_result[$host]['time_millisec']) . '&nbsp;ms)';
                $returnarray['level'] = L_ERROR;
            } else {
                $returnarray['message'] = $testsuite->return_codes[$testsuite->TLS_CA_checks_result[$host]['status']]["message"];
                $returnarray['level'] = L_OK;
                if ($testsuite->TLS_CA_checks_result[$host]['status'] != RETVAL_CONNECTION_REFUSED)
                    $returnarray['message'] .= ' (' . sprintf(_("elapsed time: %d"), $testsuite->TLS_CA_checks_result[$host]['time_millisec']) . '&nbsp;ms)';
                else
                    $returnarray['level'] = L_ERROR;
                if ($testsuite->TLS_CA_checks_result[$host]['status'] == RETVAL_OK) {
                    $returnarray['certdata'] = array();
                    $returnarray['certdata']['subject'] = $testsuite->TLS_CA_checks_result[$host]['certdata']['subject'];
                    $returnarray['certdata']['issuer'] = $testsuite->TLS_CA_checks_result[$host]['certdata']['issuer'];
                    $returnarray['certdata']['extensions'] = array();
                    if (isset($testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname']))
                        $returnarray['certdata']['extensions']['subjectaltname'] = $testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['subjectaltname'];
                    if (isset($testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['policyoid']))
                        $returnarray['certdata']['extensions']['policies'] = join(' ', $testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['policyoid']);
                    if (isset($testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['crlDistributionPoint']))
                        $returnarray['certdata']['extensions']['crldistributionpoints'] = $testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['crlDistributionPoint'];
                    if (isset($testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['authorityInfoAccess']))
                        $returnarray['certdata']['extensions']['authorityinfoaccess'] = $testsuite->TLS_CA_checks_result[$host]['certdata']['extensions']['authorityInfoAccess'];
                }
                $returnarray['cert_oddities'] = array();
            }
        }
        $returnarray['result'] = $testresult;
        break;
    case 'clients':
        $testresult = $testsuite->TLS_clients_side_check($host);
        $returnarray['IP'] = $host;
        $returnarray['hostindex'] = $hostindex;
        $k = 0;
        // the host member of the array may not exist if RETVAL_SKIPPED came out
        // (e.g. no client cert to test with). Be prepared for that
        if (isset($testsuite->TLS_clients_checks_result[$host]))
            foreach ($testsuite->TLS_clients_checks_result[$host]['ca'] as $type => $cli) {
                foreach ($cli as $key => $val) {
                    $returnarray['ca'][$k][$key] = $val;
                }
                $k++;
            }
        $returnarray['result'] = $testresult;
        break;
    case 'tls':
        $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
        $opensslbabble = array();
        debug(4, Config::$PATHS['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ 2>&1\n");
        $time_start = microtime(true);
        exec(Config::$PATHS['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ 2>&1", $opensslbabble);
        $time_stop = microtime(true);
        $measure = ($time_stop - $time_start) * 1000;
        $returnarray['result'] = $testresult;
        $returnarray['time_millisec'] = sprintf("%d", $testsuite->UDP_reachability_result[$host]['time_millisec']);

        if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
            $printedres .= UI_error(_("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!") . $measure);
            $my_ip_addrs[$key]["status"] = "FAILED";
            $goterror = 1;
        }
        if (preg_match('/verify return:1/', implode($opensslbabble))) {
            $printedres .= UI_okay(_("Completed.") . $measure);
            $printedres .= "<tr><td></td><td><div class=\"more\">";
            $my_ip_addrs[$key]["status"] = "OK";
            $servercert = implode("\n", $opensslbabble);
            $servercert = preg_replace("/.*(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\n).*/s", "$1", $servercert);
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
                foreach ($oids as $k => $o)
                    $printedres .= " $o ($k)";
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
      exit;
}

echo(json_encode($returnarray));

