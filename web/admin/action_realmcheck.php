<?php
/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");
require_once("IdP.php");
require_once("Profile.php");
require_once("RADIUSTests.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");


/* these are already defined in RADIUSTests.php, which we include
define("L_OK",0);
define("L_WARN",1);
define("L_ERROR",2);
*/


$cat = defaultPagePrelude(_("Sanity check for dynamic discovery of realms"));
$error_message ='';
$my_inst = valid_IdP($_REQUEST['inst_id'], $_SESSION['user']);

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
      $testsuite = new RADIUSTests($check_realm, $my_profile->identifier);
   } else {
      $error_message = _("You asked for a realm check, but we don't know the realm for this profile!") . "</p>";
   }
} else { // someone else's realm... only shallow checks
   $check_realm = valid_Realm($_REQUEST['realm']);
   if($check_realm)
      $testsuite = new RADIUSTests($check_realm);
   else
      $error_message = _("No valid realm name given, cannot execute any checks!");
}

$translate = _("STATIC");
$translate = _("DYNAMIC");
$errorstate = array();
?>
<link rel="stylesheet" type="text/css" href="../external/jquery/css/redmond/jquery-ui-1.10.3.custom.css" />

<!-- JQuery -->
<script type="text/javascript" src="../external/jquery/jquery.js"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script>
<script type="text/javascript">
   var icons = new Array();
   icons[0] = '../resources/images/icons/Checkmark-lg-icon.png';
   icons[1] = '../resources/images/icons/Exclamation-yellow-icon.png';
   icons[2] = '../resources/images/icons/Exclamation-orange-icon.png';
   icons[3] = '../resources/images/icons/Star-blue.png';
   var icon_loading ='../resources/images/icons/loading51.gif';
   var tmp_content;
   var lang = '<?php echo $cat->lang_index; ?>'
   var L_OK = 0;
   var L_WARN = 1;
   var L_ERROR = 2;
   var L_REMARK = 3;
   var states = new Array();
   states['PASS'] = '<?php echo _("pass") ?>';
   states['FAIL'] = '<?php echo _("fail") ?>';
   var clientcert = '<?php echo _("Client certificate:") ?>';
   var expectedres = '<?php echo _("expected result: ") ?>';
   var accepted = '<?php echo _("Server accepted this client certificate") ?>';
   var falseaccepted = '<?php echo _("Server accepted this client certificate, but should not have") ?>';
   var notaccepted = '<?php echo _("Server did not accept this client certificate") ?>';
   var notacceptedwithreason = '<?php echo _("Server did not accept this client certificate - reason") ?>';
   var restskipped = '<?php echo _("Rest of tests for this CA skipped") ?>';
   var listofcas = '<?php echo _("You should update your list of accredited CAs") ?>';
   var getitfrom = '<?php echo _("Get it from here.") ?>';
   var listsource = '<?php echo Config::$RADIUSTESTS['accreditedCAsURL'] ?>';
   var moretext = '<?php echo _("more") . "&raquo;" ?>';
   var lesstext = '<?php echo "&laquo" ?>';
   var morealltext = '<?php echo _("Show detailed information for all tests") ?>';
   var unknownca_code =  '<?php echo CERTPROB_UNKNOWN_CA ?>';
   var refused_code =  '<?php echo RETVAL_CONNECTION_REFUSED ?>';
   var refused_info =  '<?php echo _("Connection refused") ?>';
   var servercert =  new Array();
   var arefailed = 0;
   servercert['title'] = '<?php echo _("Server certificate") ?>';
   servercert['subject'] = '<?php echo _("Subject") ?>';
   servercert['issuer'] = '<?php echo _("Issuer") ?>';
   servercert['subjectaltname'] = '<?php echo _("SubjectAltName") ?>';
   servercert['policies'] = '<?php echo _("Certificate policies") ?>';
   servercert['crlDistributionPoint'] = '<?php echo _("crlDistributionPoint") ?>';
   servercert['authorityInfoAccess'] = '<?php echo _("authorityInfoAccess") ?>';
   var lessalltext = '<?php echo _("Hide detailed information for all tests") ?>';
   var addresses = new Array();
   $(document).ready(function() {
      $('.caresult').on('click', '.morelink', function() {
          if ($(this).hasClass('less')) {
             $(this).removeClass('less');
             $(this).html(moretext);
             $('.moreall').removeClass('less');
             $('.moreall').html(morealltext);
          } else {
             $(this).addClass('less');
             $(this).html(lesstext);
          }
          $(this).parent().prev().toggle();
          $(this).prev().toggle();
          return false;
      });
      $(".moreall").click(function() {
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

  $(function() {
    $( "#tabs" ).tabs();
  });
  $("#submit_credentials").click(function(event) {
    event.preventDefault();
  $("#disposable_credential_container").hide();
  $("#live_login_results").show();
    if($.trim($("#username").val()) == '' || $.trim($("#username").val()) == '') {
      alert('<?php echo _("missing username and/or password") ?>');
      return;
    }
  run_login($("#username").val(),$("#password").val());
  } );
});

function clients(data,status){
cliinfo = '<ol>';
for (var key in data.ca) {
   srefused = 0;
   cliinfo = cliinfo + '<li>' + clientcert + ' <b>' + data.ca[key].clientcertinfo.from + '</b>' + ', ' + data.ca[key].clientcertinfo.message + '<br>(CA: ' + data.ca[key].clientcertinfo.issuer + ')';
   cliinfo = cliinfo + '<ul>';
   for (var c in data.ca[key].certificate) {
    if (data.ca[key].certificate[c].returncode==refused_code) {
        srefused = 1;
        arefailed = 1;
    }
   }
   if (srefused==0) {
      for (var c in data.ca[key].certificate) {
       cliinfo = cliinfo + '<li><i>' + data.ca[key].certificate[c].message + ', ' + expectedres + states[data.ca[key].certificate[c].expected] + '</i>';
       cliinfo = cliinfo + '<ul style=\"list-style-type: none;\">';
       level = data.ca[key].certificate[c].returncode;
       if (level < 0) {
           level = 2;
           arefailed = 1;
       }
    add = '';
    if (data.ca[key].certificate[c].expected == 'PASS') {
     if (data.ca[key].certificate[c].connected==1) 
         state = accepted;
     else {
         if (data.ca[key].certificate[c].reason == unknownca_code)  
             add = '<br>' + listofcas + ' <a href=\"' + listsource + '\">' + getitfrom + '</a>';
         state = notacceptedwithreason + ': ' + data.ca[key].certificate[c].resultcomment;
     }
    } else {
     if (data.ca[key].certificate[c].connected==1) 
         state = falseaccepted;
     else {
         level = 0;
         state = notaccepted + ': ' + data.ca[key].certificate[c].resultcomment;
     }
 }
 cliinfo = cliinfo + '<li><table><tbody><tr><td class="icon_td"><img class="icon" src="' + icons[level] +'" style="width: 24px;"></td><td>' + state;
 cliinfo = cliinfo + ' <?php echo "(".sprintf(_("elapsed time: %sms."),"'+data.ca[key].certificate[c].time_millisec+'&nbsp;").")"; ?>' + add + '</td></tr>';
 cliinfo = cliinfo + '</tbody></table></ul></li>';
 if (data.ca[key].certificate[c].finalerror==1) {
     cliinfo = cliinfo + '<li>' + restskipped + '</li>';
 }
}
} 
cliinfo = cliinfo + '</ul>';
}
cliinfo = cliinfo + '</ol>';
if (srefused>0) {
    cliinfo = refused_info;
    $("#srcclient$hostindex").html('<p>'+cliinfo+'</p>');
    $("#srcclient"+data.hostindex+"_img").attr('src',icons[L_ERROR]);
    $("#dynamic_result_pass").hide();
    $("#dynamic_result_fail").show();
} else {
if (arefailed) {
    $("#dynamic_result_pass").hide();
    $("#dynamic_result_fail").show();
}  else {
    $("#dynamic_result_pass").show();
    $("#dynamic_result_fail").hide();
}
$("#clientresults"+data.hostindex).html('<p>'+cliinfo+'</p>');
}
}

function capath(data,status){
   var newhtml = '<p>'+data.message+'</p>';
   var more = '';
   addresses[data.ip] = data.result;
   if (data.certdata) {
       more = more + '<tr><td></td><td><div class="more">';
       certdesc = '<br>' + servercert['title'] + '<ul>';
       if (data.certdata.subject) {
           certdesc = certdesc + '<li>' + servercert['subject'] + ': ' + data.certdata.subject;
    }
    if (data.certdata.issuer) {
        certdesc = certdesc + '<li>' + servercert['issuer'] + ': ' + data.certdata.issuer;
    }
    if (data.certdata.extensions) {
        if (data.certdata.extensions.subjectaltname) {
            certdesc = certdesc + '<li>' + servercert['subjectaltname'] + ': ' + data.certdata.extensions.subjectaltname;
        }
        if (data.certdata.extensions.policies) {
            certdesc = certdesc + '<li>' + servercert['policies'] + ': ' + data.certdata.extensions.policies;
        }
        if (data.certdata.extensions.crlDistributionPoints) {
            certdesc = certdesc + '<li>' + servercert['crldistributionpoints'] + ': ' + data.certdata.extensions.crldistributionpoints;
        }
        if (data.certdata.extensions.authorityInfoAccess) {
            certdesc = certdesc + '<li>' + servercert['authorityInfoAccess'] + ': ' + data.certdata.authorityInfoAccess;
        }
    }
    certdesc = certdesc + '</ul>';
    more = more + '<span class="morecontent"><span>' + certdesc + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span></td></tr>';
   } 
   $("#srcca"+data.hostindex).html('<div>'+data.message+'</div>'+more);
   $("#srcca"+data.hostindex+"_img").attr('src',icons[data.level]);
   if ((addresses[data.ip] == 0 ) && $('#clientstest').is(':hidden')) {
     $('#clientstest').show();
   }
}

function udp(data,status) {
   $("#src"+data.hostindex).html('<?php printf(_("Connected to: %s"), "<strong>'+data.server+'</strong>")?><br/><?php printf(_("elapsed time: %sms."),"'+data.time_millisec+'&nbsp;") ?><p>'+data.message+'</p>');
   $("#src"+data.hostindex+"_img").attr('src',icons[data.level]);
   $.each(data.cert_oddities,function(i,v) {
      $("#results"+data.hostindex).append('<tr class="results_tr"><td>&nbsp;</td><td class="icon_td"><img src="'+icons[v.level]+'"></td><td>'+v.message+'</td></tr>');
   });
}

function udp_login(data, status) {
   $("#live_src"+data.hostindex).html('<?php printf(_("Connected to: %s"), "<strong>'+data.server+'</strong>")?><p>');
   $("#live_src"+data.hostindex+"_img").attr('src',icons[data.level]);
   $.each(data.result,function(i,v) {
      $("#live_results"+data.hostindex).append('<tr class="live_results_tr"><td>&nbsp;</td><td class="icon_td"><img src="'+icons[v.level]+'"></td><td>'+v.eap+' &ndash; <?php printf(_("elapsed time: %sms."),"'+v.time_millisec+'&nbsp;") ?> '+v.message+'</td></tr>');
   });
}

function run_login (username,password) {
   $(".live_results_tr").remove();
   var formData = new FormData($('#live_form')[0]);
<?php
foreach (Config::$RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
   print "
$(\"#live_src".$hostindex."_img\").attr('src',icon_loading);
$(\"#live_src$hostindex\").html('');
    $.ajax({
        url: 'radius_tests.php?src=$hostindex&hostindex=$hostindex&realm='+realm,
        type: 'POST',
        success: udp_login,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        dataType: 'json'
    });
";
}

?>
}

function run_udp () {
   $("#static_tests").show();
   $(".results_tr").remove();
<?php
foreach (Config::$RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
    if ($check_thorough)
        $extraarg = "profile_id: ".$my_profile->identifier.", ";
    else
        $extraarg = "";
    
   print "
$(\"#src".$hostindex."_img\").attr('src',icon_loading);
$(\"#src$hostindex\").html('');

$.get('radius_tests.php',{test_type: 'udp', $extraarg realm: realm, src: $hostindex, lang: '".$cat->lang_index."', hostindex: '$hostindex'  }, udp, 'json');
";
}
?>
}
</script>
   <?php
    productheader("ADMIN", $cat->lang_index);
    print "<h1>".sprintf(_("Realm testing for: %s"),$check_realm)."</h1>\n";
    if($error_message) {
        print "<p>$error_message</p>";
        return;
    }
?>
<div id="tabs" style="min-width: 600px; max-width:800px">
  <ul>
    <li><a href="#tabs-1"><?php echo _("DNS checks") ?></a></li>
    <li><a href="#tabs-2"><?php echo _("Static connectivity tests") ?></a></li>
    <li id="tabs-d-li""><a href="#tabs-3"><?php echo _("Dynamic connectivity tests") ?></a></li>
    <li id="tabs-through"><a href="#tabs-4"><?php echo _("Live login tests") ?></a></li>
  </ul>
  <div id="tabs-1">
      <?php
      // NAPTR existence check
      $naptr = $testsuite->NAPTR();
      if ($naptr != RETVAL_NOTCONFIGURED) {
          echo "<fieldset class='option_container'>
               <legend>
                   <strong>" . _("DNS checks") . "</strong>
               </legend>
               <table>";

                  // output in friendly words
          echo "<tr><td>" . _("Checking NAPTR existence:") . "</td><td>";
          switch ($naptr) {
             case RETVAL_NONAPTR:
                echo _("This realm has no NAPTR records.");
                break;
             case RETVAL_ONLYUNRELATEDNAPTR:
                printf(_("This realm has NAPTR records, but none are associated with %s."), Config::$CONSORTIUM['name']);
                break;
             default: // if none of the possible negative retvals, then we have matching NAPTRs
                printf(_("This realm has %d %s NAPTR records."), $naptr, Config::$CONSORTIUM['name']);
           }
           echo "</td></tr>";

           // compliance checks for NAPTRs

           if ($naptr > 0) {
              echo "<tr><td>" . _("Checking NAPTR compliance (flag = S and regex = {empty}):") . "</td><td>";
              $naptr_valid = $testsuite->NAPTR_compliance();
              switch ($naptr_valid) {
                 case RETVAL_OK:
                    echo _("No issues found.");
                    break;
                 case RETVAL_INVALID:
                    printf(_("At least one NAPTR with invalid content found!"));
                    break;
              }
              echo "</td></tr>";
            }

            // SRV resolution

            if ($naptr > 0 && $naptr_valid == RETVAL_OK) {
              $srv = $testsuite->NAPTR_SRV();
               echo "<tr><td>" . _("Checking SRVs:") . "</td><td>";
               switch ($srv) {
                  case RETVAL_SKIPPED:
                     echo _("This check was skipped.");
                     break;
                  case RETVAL_INVALID:
                     printf(_("At least one NAPTR with invalid content found!"));
                     break;
                  default: // print number of successfully retrieved SRV targets
                     printf(_("%d host names discovered."), $srv);
               }
               echo "</td></tr>";
            }
            // IP addresses for the hosts
            if ($naptr > 0 && $naptr_valid == RETVAL_OK && $srv > 0) {
               $hosts = $testsuite->NAPTR_hostnames();
               echo "<tr><td>" . _("Checking IP address resolution:") . "</td><td>";
               switch ($srv) {
                  case RETVAL_SKIPPED:
                     echo _("This check was skipped.");
                     break;
                  case RETVAL_INVALID:
                     printf(_("At least one hostname could not be resolved!"));
                     break;
                  default: // print number of successfully retrieved SRV targets
                     printf(_("%d IP addresses resolved."), $hosts);
               }
               echo "</td></tr>";
            }

            echo "</table><table>";
              if (count($testsuite->listerrors()) == 0) {
                echo UI_okay(sprintf(_("Realm is <strong>%s</strong> "), _(($naptr > 0 ? "DYNAMIC" : "STATIC"))) . _("with no DNS errors encountered. Congratulations!"));
                echo "</table>";
              } else {
                echo UI_error(sprintf(_("Realm is <strong>%s</strong> "), _(($naptr > 0 ? "DYNAMIC" : "STATIC"))) . _("but there were DNS errors! Check them!") . " " . _("You should re-run the tests after fixing the errors; more errors might be uncovered at that point. The exact error causes are listed below."));
                echo "</table><div class='notacceptable'><table>";
                foreach ($testsuite->listerrors() as $details)
                   echo "<tr><td>" . $details['TYPE'] . "</td><td>" . $details['TARGET'] . "</td></tr>";
                echo "</table></div>";
              }

              echo '<script type="text/javascript">
              function run_dynamic() {
                 $("#dynamic_tests").show();
              ';
                  foreach ($testsuite->NAPTR_hostname_records as $hostindex => $addr) {
                      $host = '';
                      if ($addr['family'] == "IPv6") $host .= '[';
                      $host .= $addr['IP'];
                      if ($addr['family'] == "IPv6") $host .= ']';
                      $host .= ':' . $addr['port'];
                      print "
                            $.get('radius_tests.php', {test_type: 'capath', realm: realm, src: '$host', lang: '".$cat->lang_index."', hostindex: '$hostindex' },  capath, 'json'); 
                            $.get('radius_tests.php', {test_type: 'clients', realm: realm, src: '$host', lang: '".$cat->lang_index."', hostindex: '$hostindex' },  clients , 'json'); 
                       ";
                   }
              echo "}
              </script>
                    </fieldset>";
         }
?>


</div>
  <div id="tabs-2">
     <button id="run_s_tests" onclick="run_udp()"><?php echo _("Repeat static connectivity tests") ?></button>
     <p>
     <fieldset class="option_container" id="static_tests">
     <legend><strong> <?php echo _("STATIC connectivity tests");?> </strong> </legend>
<?php
     echo sprintf(_("This check sends a request for the realm through various entry points of the %s infrastructure. The request will contain the 'Operator-Name' attribute, and will be larger than 1500 Bytes to catch two common configuration problems.<br/>Since we don't have actual credentials for the realm, we can't authenticate successfully - so the expected outcome is to get an Access-Reject after having gone through an EAP conversation."), Config::$CONSORTIUM['name']);
print "<p>";

foreach (Config::$RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
  print "<hr>";
printf(_("Testing from: %s"), "<strong>".Config::$RADIUSTESTS['UDP-hosts'][$hostindex]['display_name']."</strong>");
print "<table id='results$hostindex'  style='width:100%'>
<tr>
<td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='src".$hostindex."_img'></td>
<td id='src$hostindex' colspan=2>
"._("testing...")."
</td>
</tr>
</table>
";
}
?>
</fieldset>


  </div>

<?php
              if ($naptr > 0) {
?>
  <div id="tabs-3">
<button id="run_d_tests" onclick="run_dynamic()"><?php echo _("Repeat dynamic connectivity tests") ?></button>

<?php
                  echo "<div id='dynamic_tests'><fieldset class='option_container'>
                <legend><strong>" . _("DYNAMIC connectivity tests") . "</strong></legend>";

                  $resultstoprint = array();
                  if (count($testsuite->NAPTR_hostname_records)>0) {
                      $resultstoprint[] = '<table style="align:right; display: none;" id="dynamic_result_fail">' .  UI_error(_("Some errors were found during the tests, see below")) . '</table><table style="align:right; display: none;" id="dynamic_result_pass">' . UI_okay(_("All tests passed, congratulations!")) . '</table>';
                      $resultstoprint[] = '<div style="align:right;"><a href="" class="moreall">' . _('Show detailed information for all tests') . '</a></div>' . '<p><strong>' . _("Checking server handshake...") . "</strong><p>";
                      foreach ($testsuite->NAPTR_hostname_records as $hostindex => $addr) {
                          if ($addr['family'] == "IPv6") {
                              $resultstoprint[] = '<strong>' . $addr['IP'] . ' TCP/' . $addr['port'] . "</strong><ul style='list-style-type: none;'><li>" . _('Due to OpenSSL limitations, it is not possible to check IPv6 addresses at this time.') . '</li></ul>';
                              continue;
                          }
                          $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
                          $resultstoprint[] = '<p><strong>' . $bracketaddr . ' TCP/' . $addr['port'] . '</strong>';
                          $resultstoprint[] = '<ul style="list-style-type: none;" class="caresult"><li>';
                          $resultstoprint[] = "<table id='caresults$hostindex'  style='width:100%'>
<tr>
<td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='srcca".$hostindex."_img'></td>
<td id='srcca$hostindex'>
"._("testing...")."
</td>
</tr>
</table>";
                          $resultstoprint[] = '</li></ul>';
                     }    
                     $clientstest = array();
                     foreach ($testsuite->NAPTR_hostname_records as $hostindex => $addr) {
                          //$clientsres = $testsuite->TLS_client_side_tests();
                          if ($addr['family'] == 'IPv6') {
                                  $clientstest[] = '<p><strong>' . $addr['IP'] . ' TCP/' . $addr['port'] . '</strong></p>' .  "<ul style='list-style-type: none;'><li>" . _('Due to OpenSSL limitations, it is not possible to check IPv6 addresses at this time.') . '</li></ul>';
                                  continue;
                          }
                          $clientstest[] = '<p><strong>' . $addr['IP'] . ' TCP/' . $addr['port'] . '</strong></p><ol>';
                          $clientstest[] = "<span id='clientresults$hostindex$clinx'><table style='width:100%'>
<tr>
<td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='srcclient".$hostindex."_img'></td>
<td id='srcclient$hostindex'>
"._("testing...")."
</td>
</tr>
</table></span>";
                          $clientstest[] = '</ol>';
                      }
                      echo '<div style="align:right;">';
                      echo join('',$resultstoprint);
                      echo '<span id="clientstest" style="display: none;"><p><hr><b>' . _('Checking if certificates from  CAs are accepted...') . '</b><p>' .  _('A few client certificates will be tested to check if servers are resistant to some certificate problems.') . '<p>';
                      print join('',$clientstest);
                      echo '</span>';
                      echo '</div>';
                  }
                  echo "</fieldset></div></div>";
              }
              // further checks TBD:
              //     check if accepts certificates from all accredited CAs
              //     check if doesn't accept revoked certificates
              //     check if RADIUS request gets rejected timely
              //     check if truncates/dies on Operator-Name
              if ($check_thorough) {
                  echo "<div id='tabs-4'><fieldset class='option_container'>
                <legend><strong>" . _("Live login test") . "</strong></legend>";
                  $prof_compl = $my_profile->getEapMethodsinOrderOfPreference(1);
                  if (count($prof_compl) > 0) {

                      echo "<div id='disposable_credential_container'><p>" . _("If you enter an existing login credential here, you can test the actual authentication from various checkpoints all over the world.") . "</p>
                    <p>" . _("The test will use all EAP types you have set in your profile information to check whether the right CAs and server names are used, and of course whether the login with these credentials and the given EAP type actually worked. If you have set anonymous outer ID, the test will use that.") . "</p>
                    <p>" . _("Note: the tool purposefully does not offer you to save these credentials, and they will never be saved in any way on the server side. Please use only <strong>temporary test accounts</strong> here; permanently valid test accounts in the wild are considered harmful!") . "</p></div>
                    <form enctype='multipart/form-data' id='live_form' accept-charset='UTF-8'>
                    <input type='hidden' name='test_type' value='udp_login'>
                    <input type='hidden' name='lang' value='".$cat->lang_index."'>
                    <input type='hidden' name='profile_id' value='".$my_profile->identifier."'>
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
                        <tr><td>" . _("Certificate file (.p12 or .pfx):") . "</td><td><input type='file' id='cert' accept='application/x-pkcs12' name='cert'/></td></tr>
                        <tr><td>" . _("Certificate password, if any:") . "</td><td><input type='text' id='privkey' name='privkey_pass'/></td></tr>
                        <tr><td>" . _("Login username, if different from Usename above:") . "</td><td><input type='text' id='tls_username' name='tls_username'/></td></tr>";
                      echo "<tr><td colspan='2'><button id='submit_credentials'>" . _("Submit credentials") . "</button></td></tr></table></form>";
                      echo "<div id='live_login_results' style='display:none'>";
                      foreach (Config::$RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
                        print "<hr>";
                      printf(_("Testing from: %s"), "<strong>".Config::$RADIUSTESTS['UDP-hosts'][$hostindex]['display_name']."</strong>");
                      print "<table id='live_results$hostindex'  style='width:100%'>
                      <tr>
                      <td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='live_src".$hostindex."_img'></td>
                      <td id='live_src$hostindex' colspan=2>
                      "._("testing...")."
                      </td>
                      </tr>
                      </table>
";
}
                      echo "</div>";

                  } else {// no EAP methods fully defined
                      echo "Live Login Checks require at least one fully configured EAP type.";
                  }
                  echo "</fieldset></div>";
              }
echo "
</div>
";
              ?>
    <form method='post' action='overview_idp.php?inst_id=<?php echo $my_inst->identifier; ?>' accept-charset='UTF-8'>
        <button type='submit' name='submitbutton' value='<?php echo BUTTON_CLOSE; ?>'><?php echo _("Return to dashboard"); ?></button>
    </form>
    <script>
    var realm = '<?php echo $check_realm; ?>';
    run_udp();
<?php
    if ($naptr > 0) 
       echo "run_dynamic();";
    else
       echo '$("#tabs-d-li").hide();';
    if (!$check_thorough) 
       echo '$("#tabs-through").hide();';
?>
</script>
    <?php footer() ?>

</body>

