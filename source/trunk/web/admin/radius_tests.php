<?php
/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("RADIUSTests.php");
require_once("inc/common.inc.php");

ini_set('display_errors', '0');

function disp_name($eap) {
   $D = EAP::eapDisplayName($eap);
   return $D['OUTER'] .( $D['INNER'] != '' ? '-'.$D['INNER'] : '');
}

if(! isset($_REQUEST['test_type'] ) || ! $_REQUEST['test_type'])
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
$hostindex = $_REQUEST['hostindex'];


$returnarray = array();
switch ($test_type) {
   case 'udp_login' :
      $eaps = $my_profile->getEapMethodsinOrderOfPreference(1);
      $user_name = isset($_REQUEST['username']) && $_REQUEST['username'] ? $_REQUEST['username'] : "";
      $user_password = isset($_REQUEST['password']) && $_REQUEST['password'] ? $_REQUEST['password'] : "";
      $returnarray['hostindex'] = $hostindex;
      $returnarray['result'] = array();
      $global_level = L_OK;
      foreach ($eaps as $eap) {
         if($eap  == EAP::$TLS) {
           if($_FILES['cert']['error'] == UPLOAD_ERR_OK) {
              $clientcertdata = file_get_contents($_FILES['cert']['tmp_name']);
              $privkey_pass = isset($_REQUEST['privkey_pass']) && $_REQUEST['privkey_pass'] ? $_REQUEST['privkey_pass'] : "";
              $tls_username = isset($_REQUEST['tls_username']) && $_REQUEST['tls_username'] ? $_REQUEST['tls_username'] : $user_name;
              $testresult = $testsuite->UDP_login($host, $eap, $tls_username, $privkey_pass,TRUE,TRUE, $clientcertdata);
           } else {
              $testresult = RETVAL_INCOMPLETE_DATA;
           }
         } else {
              $testresult = $testsuite->UDP_login($host, $eap, $user_name, $user_password);
         }
         $time_millisec = sprintf("%d",$testsuite->UDP_reachability_result[$host]['time_millisec'] );
         $CAs = $testsuite->UDP_reachability_result[$host]['certdata'];
           foreach ($CAs as $ca ) 
             if( $ca['type'] == 'server')
                $server = $ca['subject']['CN'];
         switch ($testresult) {
           case RETVAL_OK :
              $level = L_OK;
              $message = _("<strong>Test succesful.</strong>");
              break;
           case RETVAL_CONVERSATION_REJECT:
              $message = _("<strong>Test FAILED</strong>: the request was rejected. The most likely cause is that you have misspelt the Username and/or the Password.");
              $level = L_ERROR;
              break;
           case RETVAL_NOT_CONFIGURED:
              $level = L_ERROR;
              $message = _("This method cannot be tested");
           case RETVAL_IMMEDIATE_REJECT:
              $level = L_ERROR;
              $message = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. Either you have misspelt the Username or there is something seriously wrong with your server.");
           case RETVAL_NO_RESPONSE:
              $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
              $returnarray['level'] = L_ERROR;
              break;
           case RETVAL_SERVER_UNFINISHED_COMM: 
              $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
              $returnarray['level'] = L_ERROR;
              break;
           default:
              $level = isset($testsuite->return_codes[$testresult]['severity']) ? $testsuite->return_codes[$testresult]['severity'] : L_ERROR;
              $message =  isset($testsuite->return_codes[$testresult]['message']) ? $testsuite->return_codes[$testresult]['message'] : _("<strong>Test FAILED</strong>");
              break;
         }
         $returnarray['result'][] = array('eap'=>disp_name($eap),'testresult'=>$testresult,time_millisec=>$time_millisec, 'level'=>$level, 'message'=>$message);
         $global_level = max($global_level,$level);
      }
      $returnarray['server'] = $server;
      $returnarray['level'] = $global_level;
      break;
   case 'udp' :
      $testresult = $testsuite->UDP_reachability($host);
      $timeout = Config::$RADIUSTESTS['UDP-hosts'][$host]['timeout'];
      $returnarray['hostindex'] = $hostindex;
      $returnarray['result'] = $testresult;
      $returnarray['time_millisec'] = sprintf("%d",$testsuite->UDP_reachability_result[$host]['time_millisec'] );
      $CAs = $testsuite->UDP_reachability_result[$host]['certdata'];
      foreach ($CAs as $ca ) 
         if( $ca['type'] == 'server')
            $returnarray['server'] = $ca['subject']['CN'];
      switch ($testresult) {
         case RETVAL_CONVERSATION_REJECT:
            if (isset($testsuite->UDP_reachability_result[$host]['cert_oddities']) && count($testsuite->UDP_reachability_result[$host]['cert_oddities']) > 0) {
               $returnarray['message'] = _("<strong>Test partially successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned. Some properties of the connection attempt were sub-optimal; the list is below.");
               $returnarray['level'] = L_OK;
               $returnarray['cert_oddities'] = array();
               foreach ($testsuite->UDP_reachability_result[$host]['cert_oddities'] as $oddity) {
                  $o = array();
                  $o['code'] = $oddity;
                  $o['message'] = isset($testsuite->return_codes[$oddity]["message"]) && $testsuite->return_codes[$oddity]["message"] ? $testsuite->return_codes[$oddity]["message"] : $oddity;
                  $o['level'] = $testsuite->return_codes[$oddity]["severity"];
                  // why is this always REMARK? There is at least one significant error to show, and it transpires as an error for the overall check:
/*
                  if ($testsuite->return_codes[$oddity]["severity"] == L_ERROR) {
                      $o['level'] = L_ERROR;
                      $returnarray['level'] = L_ERROR;
                  } else {
                      $o['level'] = L_REMARK;
                      $o['level'] = $testsuite->return_codes[$oddity]["severity"];
                      if($o['level'] > L_OK)
                        $returnarray['level'] = L_WARN;
                  }
*/
                  $returnarray['level'] = max($returnarray['level'],$testsuite->return_codes[$oddity]["severity"]);
                  $returnarray['cert_oddities'][] = $o;
               }
            } else {
               $returnarray['message'] = _("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.");
               $returnarray['level'] = L_OK;
            }
            break;
         case RETVAL_IMMEDIATE_REJECT:
            $returnarray['message'] = _("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation. This is not necessarily an error: if the RADIUS server enforces that outer identities correspond to an existing username, then this result is expected. In all other cases, the server appears misconfigured or it is unreachable.");
            $returnarray['level'] = L_WARN;
            break;
         case RETVAL_NO_RESPONSE:
            $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %d seconds. Either the responsible server is down, or routing is broken!"), $timeout);
            $returnarray['level'] = L_ERROR;
            break;
         case RETVAL_SERVER_UNFINISHED_COMM: 
            $returnarray['message'] = sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $timeout);
            $returnarray['level'] = L_ERROR;
            break;
         default:
            $returnarray['message'] = _("unhandled error");
            $returnarray['level'] = L_ERROR;
            break;
      }
      break;
   case 'capath':
      $testresult = $testsuite->CApath_check($host);
      $returnarray['IP']  = $host;
      $returnarray['hostindex'] = $hostindex;
      $returnarray['time_millisec'] = sprintf("%d",$testsuite->TLS_CA_checks_result[$host]['time_millisec'] );
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
         if ($testsuite->TLS_CA_checks_result[$host]['status']==RETVAL_OK) {
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
     $returnarray['result'] = $testresult;
     break;
   case 'clients':
     $testresult = $testsuite->TLS_clients_side_check($host);
     $returnarray['IP'] = $host;
     $returnarray['hostindex'] = $hostindex;
     $k = 0;
     foreach ($testsuite->TLS_clients_checks_result[$host]['ca'] as $type=>$cli) {
        foreach ($cli as $key=>$val) {
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
      $returnarray['time_millisec'] = sprintf("%d",$testsuite->UDP_reachability_result[$host]['time_millisec'] );
  
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
}

echo(json_encode($returnarray));

