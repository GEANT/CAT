<?php
/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("IdP.php");
require_once("Profile.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("inc/admin_header.php");

$certkeys = array(
    'eduPKI' => _('eduPKI'),
    'non-eduPKI-accredited' => _('non eduPKI, but accredited'),
    'NCU' => _('Nicolaus Copernicus University'),
    'ACCREDITED' => _('accredited'),
    'NONACCREDITED' => _('non-accredited'),
    'CORRECT' => _('correct certificate'),
    'WRONGPOLICY' => _('certificate with wrong policy OID'),
    'EXPIRED' => _('expired certificate'),
    'REVOKED' => _('revoked certificate'),
    'PASS' => _('pass'),
    'FAIL' => _('fail'),
    'non-eduPKI-accredited' => _("eduroam-accredited CA (now only for tests)"),
);


function naptr($realm) {
    if (Config::$RADIUSTESTS['TLS-discoverytag'] == "") {
        echo "<p>" . _("This check is not configured.");
        return FALSE;
    }
    $NAPTRs = dns_get_record($realm . ".", DNS_NAPTR);
    if ($NAPTRs !== FALSE && count($NAPTRs) > 0) {
        echo "<p>" . sprintf(_("This realm has %d NAPTR records, "), count($NAPTRs));
        $NAPTRs_consortium = array();
        foreach ($NAPTRs as $naptr) {
            if ($naptr["services"] == Config::$RADIUSTESTS['TLS-discoverytag'])
                $NAPTRs_consortium[] = $naptr;
        }

        if (count($NAPTRs_consortium) > 0) {
            echo sprintf(_("out of which %d are %s NAPTR records."), count($NAPTRs_consortium), Config::$CONSORTIUM['name']) . "</p>";
            return $NAPTRs_consortium;
        } else {
            echo sprintf(_("but none are %s NAPTR records."), Config::$CONSORTIUM['name']) . "</p>";
            return FALSE;
        }
    } else {
        echo "<p>" . _("There are no NAPTR records for this realm.") . "<p>";
        return FALSE;
    }
}

function format_compliance($NAPTRs, &$errors) {
    $format_errors = array();
    if (Config::$CONSORTIUM['name'] == "eduroam") { // SW: APPROVED
        echo "<p>" . _("Checking NAPTR format compliance: flag = S and regex = (empty) ...") . "</p>";
        foreach ($NAPTRs as $edupointer) {
// must be "s" type for SRV
            if ($edupointer["flags"] != "s" && $edupointer["flags"] != "S") {
                echo "<p>" . _("<strong>Error:</strong> eduroam discovery profile only supports 'S' type NAPTRs.") . "</p>";
                $format_errors[] = "FLAG";
            }
// no regex
            if ($edupointer["regex"] != "") {
                echo "<p>" . _("<strong>Error:</strong> eduroam discovery profile only supports NAPTRs with empty regex.") . "</p>";
                $format_errors[] = "REGEX";
            }
        }
    }
    if (count($format_errors) > 0) {
        $errors = array_merge($errors, $format_errors);
        return FALSE;
    } else
        return TRUE;
}

function srv_resolution($NAPTRs, &$errors) {
    echo "<p>" . _("Trying to resolve the SRVs into host names ... ") . "</p>";
    $SRV_errors = array();
    $SRV_targets = array();

    foreach ($NAPTRs as $edupointer) {
        $temp_result = dns_get_record($edupointer["replacement"], DNS_SRV);
        if ($temp_result === FALSE || count($temp_result) == 0) {
            echo "<p>" . sprintf(_("<strong>Error:</strong> SRV entry %s could not be resolved!"), $edupointer["replacement"]) . "</p>";
            $SRV_errors[] = "SRV_NO_TARGET";
        } else
            foreach ($temp_result as $res)
                $SRV_targets[] = array("hostname" => $res["target"], "port" => $res["port"]);
    }
    echo "<p>" . sprintf(_("%d host names discovered."), count($SRV_targets)) . "</p>";
    $errors = array_merge($errors, $SRV_errors);
    return $SRV_targets;
}

function name_resolution($hosts, &$errors) {
    $ip_addresses = array();
    $resolution_errors = array();

    foreach ($hosts as $server) {
        $host_resolution_6 = dns_get_record($server["hostname"], DNS_AAAA);
        $host_resolution_4 = dns_get_record($server["hostname"], DNS_A);
        $host_resolution = array_merge($host_resolution_6, $host_resolution_4);
        if ($host_resolution === FALSE || count($host_resolution) == 0) {
            echo "<p>" . sprintf(_("<strong>Error:</strong> Host name %s could not be resolved!"), $server["hostname"]) . "</p>";
            $resolution_errors[] = "HOST_NO_ADDRESS";
        } else
            foreach ($host_resolution as $address)
                if (isset($address["ip"]))
                    $ip_addresses[] = array("family" => "IPv4", "IP" => $address["ip"], "port" => $server["port"], "status" => "");
                else
                    $ip_addresses[] = array("family" => "IPv6", "IP" => $address["ipv6"], "port" => $server["port"], "status" => "");
    }

    echo "<p>" . sprintf(_("%d IP addresses discovered."), count($ip_addresses)) . "</p>";
    $errors = array_merge($errors, $resolution_errors);
    return $ip_addresses;
}

function are_verified_sites($ip_addresses) {
    foreach ($ip_addresses as $addr) {
        if ($addr["status"] == "OK")
            return 1;
    }
    return 0;
}

function certificate_get_issuer($data) {
    $issuer = "";
    foreach ($data['issuer'] as $key => $val)
        if (is_array($val))
            foreach ($val as $v)
                $issuer .= "/$key=$v";
        else
            $issuer .= "/$key=$val";
    return $issuer;
}

function certificate_get_field($data, $field) {
    if ($data['extensions'][$field]) {
        return $data['extensions'][$field];
    }
    return "";
}

function check_policy($data) {
    $oids = array();
    if ($data['extensions']['certificatePolicies']) {
        foreach (Config::$RADIUSTESTS['TLS-acceptableOIDs'] as $key => $oid)
            if (preg_match("/Policy: $oid/", $data['extensions']['certificatePolicies']))
                $oids[$key] = $oid;
    }
    return $oids;
}

defaultPagePrelude(_("Sanity check for dynamic discovery of realms"));
?>
<script src="js/option_expand.js" type="text/javascript"></script>
<!-- JQuery -->
<script type="text/javascript" src="../external/jquery/jquery.js"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script>
<script>
    $(document).ready(function() {
        var moretext = '<?php echo _("more") . "&raquo;" ?>';
        var lesstext = '<?php echo "&laquo" ?>';
        var morealltext = '<?php echo _("Show detailed information for all tests") ?>';
        var lessalltext = '<?php echo _("Hide detailed information for all tests") ?>';
        $('.more').each(function() {
            var content = $(this).html();
            var xxx = content.indexOf('XXXXXXXXXXXXXXXXXXXX');

            if(content.length > xxx) {

                var c = content.substr(0, xxx);
                var h = content.substr(xxx+20, content.length - (xxx+20));
    
                var html = c + '<span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">'+moretext+'</a></span>';

                $(this).html(html);
            }

        });

        $(".morelink").click(function(){
            if($(this).hasClass("less")) {
                $(this).removeClass("less");
                $(this).html(moretext);
                $(".moreall").removeClass("less");
                $(".moreall").html(morealltext);
            } else {
                $(this).addClass("less");
                $(this).html(lesstext);
            }
            $(this).parent().prev().toggle();
            $(this).prev().toggle();
            return false;
        });

        $(".moreall").click(function(){
            if ($(this).hasClass("less")) {
                $(this).removeClass("less");
                $(this).html(morealltext);
                $('.morelink').removeClass("less");
                $('.morelink').html(moretext);
                $('.morelink:parent').prev().hide();
                $('.morelink').prev().hide();
            } else {
                $(this).addClass("less");
                $(this).html(lessalltext);
                $('.morelink').addClass("less");
                $('.morelink').html(lesstext);
                $('.morelink:parent').prev().show();
                $('.morelink').prev().show();
            }
            return false;
        });
    });
</script>
</head>
<body>
    <?php
    productheader();
    // let's check if the inst and profile handles actually exists in the DB
    $my_inst = valid_IdP($_GET['inst_id'], $_SESSION['user']);
    if (isset($_GET['profile_id']))
        $my_profile = valid_Profile($_GET['profile_id'], $my_inst->identifier);
    else
        $my_profile = NULL;
    if ($my_profile != NULL) {
        $cr = $my_profile->getAttributes("internal:realm");
        if ($cr) {
            // checking our own stuff. Enable thorough checks
            $check_thorough = TRUE;
            $check_realm = $cr[0]['value'];
            $check_prof_id = $my_profile->identifier;
            $check_anon_id = "";
            if ($my_profile->use_anon_outer) {
                $local = $my_profile->getAttributes("internal:anon_local_value");
                $anon_local = $local[0]['value'];
                $check_anon_id = "-A $anon_local@$check_realm";
            }
        } else {
            echo "<p>" . _("You asked for a realm check, but we don't know the realm for this profile!") . "</p>";
        }
    } else // someone else's realm... only shallow checks
    if (isset($_POST['realm']) && $_POST['realm'] != "") {
        $check_thorough = FALSE;
        $check_realm = valid_Realm($_POST['realm']);
        $check_anon_id = "";
        if ($check_realm == FALSE) {
            echo "<p>" . _("No valid realm name given, cannot execute any checks!") . "</p>";
            exit(1);
        }
    } else {
        echo "<p>" . _("No valid realm name given, cannot execute any checks!") . "</p>";
        exit(1);
    }

    $translate = _("STATIC");
    $translate = _("DYNAMIC");
    $errorstate = array();
    $result = "STATIC";
    ?>
    <h1><?php printf(_("Checking realm %s"), $check_realm); ?></h1>
    <form method='post' action='inc/credentialcheck.php?inst_id=<?php echo $my_inst->identifier; ?>&amp;profile_id=<?php echo $my_profile->identifier; ?>' onsubmit='doCredentialCheck(this); return false;'>
        <fieldset class='option_container'>
            <legend>
                <strong><?php echo _("DNS checks"); ?></strong>
            </legend>
            <?php
            if ($consortium_NAPTRs = naptr($check_realm)) {
                $result = "DYNAMIC";
                format_compliance($consortium_NAPTRs, $errorstate);
                if (count($errorstate) > 0)
                    echo "<p>" . _("Skipping further discovery checks because of previous errors!") . "</p>";
                else {
                    $hostnames = srv_resolution($consortium_NAPTRs, $errorstate);
                    $ip_addresses = name_resolution($hostnames, $errorstate);
                }
            }
            ?>
            <table>
                <?php
                if (count($errorstate) == 0)
                    echo UI_okay(sprintf(_("Realm is <strong>%s</strong> "), _($result)) . _("with no DNS errors encountered. Congratulations!"));
                else
                    echo UI_error(sprintf(_("Realm is <strong>%s</strong> "), _($result)) . _("but there were DNS errors! Check them!"));
                ?>
            </table>
            <?php
            if (count($errorstate) > 0) {
                echo "<ul>";
                foreach ($errorstate as $token)
                    echo "<li>" . $token . "</li>";
                echo "</ul>";
            }
            ?>


        </fieldset>
        <fieldset class='option_container'>
            <legend>
                <strong>
                    <?php // always do the static reachability checks
                    echo _("STATIC connectivity tests"); ?>
                </strong>
            </legend>
            <?php
            echo sprintf(_("This check sends a request for the realm through various entry points of the %s infrastructure. The request will contain the 'Operator-Name' attribute, and will be larger than 1500 Bytes to catch two common configuration problems.<br/>Since we don't have actual credentials for the realm, we can't authenticate successfully - so the expected outcome is to get an Access-Reject after having gone through an EAP conversation."), Config::$CONSORTIUM['name']);
            if (count(Config::$RADIUSTESTS['UDP-hosts']) == 0)
                echo _("This check is not configured.");
            foreach (Config::$RADIUSTESTS['UDP-hosts'] as $host) {
                echo "<p>" . sprintf(_("Checking from <strong>%s</strong>: "), $host['display_name']) . "</p>";
                flush();
                $packetflow = array();
                $cmdline = "rad_eap_test -c -H " . $host['ip'] . " -P 1812 -S " . $host['secret'] . " -M 22:44:66:CA:20:00 $check_anon_id -u cat-connectivity-test@" . $check_realm . " -p nopassword -e TTLS -m WPA-EAP -t " . $host['timeout'] . " | grep 'RADIUS message:' | cut -d ' ' -f 3 | cut -d '=' -f 2";
                debug(4, "Shallow reachability check: $cmdline\n");
                $time_start = microtime(true);
                exec($cmdline, $packetflow);
                $time_stop = microtime(true);
                $measure = " (" . sprintf(_("elapsed time: %d"), ($time_stop - $time_start) * 1000) . " ms)";
                $packetcount = array_count_values($packetflow);
                echo "<table>";
                // check if RADIUS request gets rejected timely
                // this means we expect to see Requests, Challenges and Accepts, and the numbers should add up
                // the lack of a challenge means premature rejection - not good
                if (!isset($packetcount[3])) { // no final reject. hm.
                    if (isset($packetcount[11])) // but there was an Access-Challenge
                        echo UI_error(sprintf(_("<strong>Test FAILED</strong>: there was a bidirectional RADIUS conversation, but it did not finish after %d seconds!"), $host['timeout']) . $measure);
                    else
                        echo UI_error(sprintf(_("<strong>Test FAILED</strong>: no reply from the RADIUS server after %s seconds. Either the responsible server is down, or routing is broken!"), $host['timeout']) . $measure);
                }
                elseif (isset($packetcount[3]) && !isset($packetcount[11])) {
                    echo UI_error(_("<strong>Test FAILED</strong>: the request was rejected immediately, without EAP conversation! Either the responsible server is down, or routing is broken!") . $measure);
                } elseif (isset($packetcount[3]) && isset($packetcount[11]) && $packetcount[1] == $packetcount[11] + $packetcount[3]) {
                    echo UI_okay(_("<strong>Test successful</strong>: a bidirectional RADIUS conversation with multiple round-trips was carried out, and ended in an Access-Reject as planned.") . $measure);
                }
                else
                    echo UI_error(sprintf(_("<strong>Strange</strong>: there is a packet count mismatch. Maybe bad connectivity and retransmits? I had %d Access-Requests, %d Access-Challenges and %d Access-Rejects."), $packetcount[1], $packetcount[11], $packetcount[3]) . $measure);
                echo "</table>";
            }
            ?>
        </fieldset>

        <?php
        if ($result == "DYNAMIC") {
            echo "<fieldset class='option_container'>
                <legend><strong>" . _("DYNAMIC connectivity tests") . "</strong></legend>";

            $printedres = '';
            $goterror = 0;
            if (count($ip_addresses) > 0) {
                $printedres .= '<div style="align:right;"><a href="" class="moreall">' . _("Show detailed information for all tests") . "</a></div>";
                $printedres .= '<p><strong>' . _("Checking server handshake...") . "</strong><p>";
                foreach ($ip_addresses as $key => $addr) {
                    if ($addr['family'] == "IPv6") {
                        $printedres .= "<strong>" . $addr['IP'] . " TCP/" . $addr['port'] . "</strong><ul style='list-style-type: none;'><li>" . _("Due to OpenSSL limitations, it is not possible to check IPv6 addresses at this time.") . "</li></ul>";
                        continue;
                    }

                    $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
                    $printedres .= "<p><strong>" . $bracketaddr . " TCP/" . $addr['port'] . "</strong>";
                    $opensslbabble = array();
                    debug(4, Config::$PATHS['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ 2>&1\n");
                    $time_start = microtime(true);
                    exec(Config::$PATHS['openssl'] . " s_client -connect " . $bracketaddr . ":" . $addr['port'] . " -tls1 -CApath " . CAT::$root . "/config/ca-certs/ 2>&1", $opensslbabble);
                    $time_stop = microtime(true);
                    $measure = " (" . sprintf(_("elapsed time: %d"), ($time_stop - $time_start) * 1000) . " ms)";
                    $printedres .= "<ul style='list-style-type: none;'><li><table>";
                    // echo "Result: ".print_r($opensslbabble);
                    if (preg_match('/verify error:num=19/', implode($opensslbabble))) {
                        $printedres .= UI_error(_("<strong>ERROR</strong>: the server presented a certificate which is from an unknown authority!") . $measure);
                        $ip_addresses[$key]["status"] = "FAILED";
                        $goterror = 1;
                    }
                    if (preg_match('/verify return:1/', implode($opensslbabble))) {
                        $printedres .= UI_okay(_("Completed.") . $measure);
                        $printedres .= "<tr><td></td><td><div class=\"more\">";
                        $ip_addresses[$key]["status"] = "OK";
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
                    $printedres .= "</table></li></ul>";
                }
                if (are_verified_sites($ip_addresses)) {
                    if (is_array(Config::$RADIUSTESTS['TLS-clientcerts']) && count(Config::$RADIUSTESTS['TLS-clientcerts']) > 0) {
                        $printedres .= "<p><hr><b>" . _("Checking if certificates from  CAs are accepted...") . "</b><p>";
                        $printedres .= _("A few client certificates will be tested to check if servers are resistant to some certificate problems.").'<p>' ;
/*
                        foreach (Config::$RADIUSTESTS['TLS-clientcerts'] as $cert) {
                            $data = openssl_x509_parse(file_get_contents(CAT::$root . "/config/cli-certs/" . $cert['public']));
                            $cn = preg_replace("/.*\/CN=(.*)/", "$1", $data['name']);
                            echo '<li><b><i>' . $cert['display_name'] . '</i></b>';
                            echo ', ' . _("expected result: ") . $cert['expected'];
                            echo '<div class="more">XXXXXXXXXXXXXXXXXXXX';
                            echo '<ul>';
                            echo '<li>' . _("Subject") . ': ' . $data['name'];
                            echo '<li>' . _("Issuer") . ': ' . certificate_get_issuer($data) . '<br>';
                            $oids = check_policy($data);
                            if (!empty($oids)) {
                                echo '<li>' . _("Certificate policies") . ':';
                                foreach ($oids as $k => $o)
                                    echo " $o ($k)";
                            }
                            echo '<li>' . _("Valid until") . ':';
                            $validTo = DateTime::createFromFormat('ymdHi', substr($data['validTo'], 0, 10));
                            echo $validTo->format('Y-m-d H:i') . " GMT";
                            echo '</ul></div>';
                        }
                        print "</ol>";
*/
                        foreach ($ip_addresses as $addr) {
                            if ($addr['status'] != "OK") {
                                $printedres .= "<strong>" . $addr['IP'] . " TCP/" . $addr['port'] . "</strong><ul style='list-style-type: none;'><li>" . _("Tests skipped because of previous errors.") . "</li></ul>";
                                continue;
                            }
                            if ($addr['family'] == "IPv6") {
                                $printedres .= "<strong>" . $addr['IP'] . " TCP/" . $addr['port'] . "</strong><ul style='list-style-type: none;'><li>" . _("Due to OpenSSL limitations, it is not possible to check IPv6 addresses at this time.") . "</li></ul>";
                                continue;
                            }

                            $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
                            $printedres .= "<p><strong>" . $bracketaddr . " TCP/" . $addr['port'] . "</strong>";
                            $printedres .= '<ol>';
                            foreach (Config::$RADIUSTESTS['TLS-clientcerts'] as $type => $tlsclient) {
                              $printedres .= '<li>' . _("Client certificate from") . ': <b><i>' . $certkeys[$type]. '</i></b>, ' . $certkeys[$tlsclient['status']] . '<br>(CA: ' . $tlsclient['issuerCA'] .')<ul>';
                              
                              foreach ($tlsclient['certificates'] as $cert) {
                                $finalerror = 0;
                                $opensslbabble = array();
                                $openssl_cmd = Config::$PATHS['openssl'] . ' s_client -connect ' . $bracketaddr . ':' . $addr['port'] . ' -tls1 -CApath ' . CAT::$root . '/config/ca-certs/ -cert ' . CAT::$root . '/config/cli-certs/' . $cert['public'] . ' -key ' . CAT::$root . '/config/cli-certs/' . $cert['private'] . " 2>&1";
                                debug(4, $openssl_cmd."\n");
                                $printedres .= '<li><i>' . $certkeys[$cert['status']] . ', ' .  _("expected result: ") . $certkeys[$cert['expected']] . '</i>';
                                $time_start = microtime(true);
                                exec($openssl_cmd, $opensslbabble, $result);
                                $time_stop = microtime(true);
                                $measure = " (" . sprintf(_("elapsed time: %d"), ($time_stop - $time_start) * 1000) . " ms)";
                                $printedres .= "<ul style='list-style-type: none;'><li><table>";
                                $output = implode($opensslbabble);
                                $unknownca = 0;
                                if ($result==0) 
                                    $connected = 1;
                                else {
                                    $connected = 0;
                                    if (preg_match('/sslv3 alert certificate expired/', $output))
                                        $res_comment = _("certificate expired");
                                    elseif (preg_match('/sslv3 alert certificate revoked/', $output))
                                        $res_comment = _("certificate was revoked");
                                    elseif (preg_match('/SSL alert number 46/', $output)) 
                                        $res_comment = _("bad policy");
                                    elseif (preg_match('/tlsv1 alert unknown ca/', $output)) {
                                        $res_comment = _("unknown authority");
                                        $unknownca = 1;
                                    } else 
                                        $res_comment = _("unknown authority or no certificate policy or another problem");
                                }
                                if ($cert['expected'] == 'PASS') {
                                    if ($connected)
                                        $printedres .= UI_okay(_("Server accepted this client certificate") . " " . $measure);
                                    else {
                                        if ($unknownca) {
                                           $add =  '<br>' . _('You should update your list of accredited CAs');
                                           if (isset(Config::$RADIUSTESTS['accreditedCAsURL'])) 
                                               $add .= ' ' . '<a href="' . Config::$RADIUSTESTS['accreditedCAsURL'] . '"> ' . _('from here') . '</a>';
                                        }
                                        $printedres .= UI_error(_("Server did not accept this client certificate - reason") . ": " . $res_comment . " " . $measure . $add);
                                        if (($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) 
                                            $finalerror = 1;
                                        $goterror = 1;
                                    }
                                } else {
                                    if ($connected) {
                                        $printedres .= UI_error(_("Server accepted this client certificate, but should not have"));
                                        $goterror = 1;
                                    } else {
                                        if ($unknownca && ($tlsclient['status'] == 'ACCREDITED') && ($cert['status'] == 'CORRECT')) {
                                             $printedres .= UI_error(_("Server did not accept this client certificate") . ": " . $res_comment . " " . $measure);
                                             $finalerror = 1;
                                             $goterror = 1;
                                         } else 
                                             $printedres .= UI_okay(_("Server did not accept this client certificate") . ": " . $res_comment . " " . $measure);
                                    }
				}
                                $printedres .= "</table></li></ul>";
                                if ( $finalerror) {
                                    $printedres .= '<li>' . _("Rest of tests for this CA skipped");
                                    break;
                                }
                            }
                            $printedres .= '</ul>';
                          }
                          $printedres .= '</ol>';
                        }
                    }
                }
                echo '<table>';
                if ($goterror) 
                    echo UI_error(_("Some errors were found during the tests, see below"));
                else
                   echo UI_okay(_("All tests passed, congratulations!"));
                echo '</table>';
                echo '<div style="align:right;">';
		echo $printedres;
                echo '</div>';
            }
            echo "</fieldset>";
        }
        // further checks TBD:
        //     check if accepts certificates from all accredited CAs
        //     check if doesn't accept revoked certificates
        //     check if RADIUS request gets rejected timely
        //     check if truncates/dies on Operator-Name
        if ($check_thorough) {
            echo "<fieldset class='option_container'>
                <legend><strong>" . _("Live login test") . "</strong></legend>";
            $prof_compl = $my_profile->getEapMethodsinOrderOfPreference(1);
            if (count($prof_compl) > 0) {

                echo "<div id='disposable_credential_container'><p>" . _("If you enter an existing login credential here, you can test the actual authentication from various checkpoints all over the world.") . "</p>
                    <p>" . _("The test will use all EAP types you have set in your profile information to check whether the right CAs and server names are used, and of course whether the login with these credentials and the given EAP type actually worked. If you have set anonymous outer ID, the test will use that.") . "</p>
                    <p>" . _("Note: the tool purposefully does not offer you to save these credentials, and they will never be saved in any way on the server side. Please use only <strong>temporary test accounts</strong> here; permanently valid test accounts in the wild are considered harmful!") . "</p>
                    <table>
                        <tr><td colspan='2'><strong>" . _("For all EAP types") . "</strong></td></tr>
                        <tr><td>" . _("Username:") . "</td><td><input type='text' id='username' name='username'/></td></tr>";

                // ask for password if PW-based EAP method is active

                if (in_array(EAP::$PEAP_MSCHAP2, $prof_compl) ||
                        in_array(EAP::$TTLS_MSCHAP2, $prof_compl) ||
                        in_array(EAP::$TTLS_PAP, $prof_compl)
                )
                    echo "<tr><td colspan='2'><strong>" . _("Password-based EAP types") . "</strong></td></tr>
                        <tr><td>" . _("Password:") . "</td><td><input type='text' id='password' name='password'/></td></tr>";
                // ask for cert + privkey if TLS-based method is active
                if (in_array(EAP::$TLS, $prof_compl))
                    echo "<tr><td colspan='2'><strong>" . _("Certificate-based EAP types") . "</strong></td></tr>
                        <tr><td>" . _("Certificate file:") . "</td><td><input type='file' id='cert' name='cert'/></td></tr>
                        <tr><td>" . _("Private key, if any:") . "</td><td><input type='text' id='privkey' name='privkey'/></td></tr>";
                echo "<tr><td colspan='2'><button type='submit'>" . _("Submit credentials") . "</button></td></tr></table></div>";
            } else {// no EAP methods fully defined
                echo "Live Login Checks require at least one fully configured EAP type.";
            }
            echo "</fieldset>";
        }
        ?>
    </form>
    <form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>'>
        <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE; ?>'><?php echo _("Return to dashboard"); ?></button>
    </form>

    <?php include "inc/admin_footer.php"; ?>
