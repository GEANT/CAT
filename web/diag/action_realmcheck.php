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

require_once dirname(dirname(__DIR__)) . "/config/_config.php";

$loggerInstance = new \core\common\Logging();

$deco = new \web\lib\admin\PageDecoration();
$validator = new \web\lib\common\InputValidation();
$gui = new \web\lib\user\Gui();
$gui->languageInstance->setTextDomain("diagnostics");
echo $deco->defaultPagePrelude(sprintf(_("Sanity check for dynamic discovery of realms"), \config\Master::APPEARANCE['productname']));

$ourlocale = $gui->languageInstance->getLang();

$my_profile = NULL;
$testedProfile = NULL;
$check_realm = FALSE; // we will need to populate this with a real realm below, or have to die horribly.

$error_message = '';
$user = NULL;
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
}
$inst_id = filter_input(INPUT_GET, 'inst_id', FILTER_VALIDATE_INT);
$profile_id = filter_input(INPUT_GET, 'profile_id', FILTER_VALIDATE_INT);
$realm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'realm', FILTER_SANITIZE_STRING);
if ($inst_id && $profile_id) {
    $my_inst = $validator->existingIdP($inst_id, $user);
    $my_profile = $validator->existingProfile($profile_id, $my_inst->identifier);
    if (!$my_profile instanceof \core\ProfileRADIUS) {
        throw new Exception("realm checks are only supported for RADIUS Profiles!");
    }
    $testedProfile = $my_profile;
} else {
    $testCandidate = \core\AbstractProfile::profileFromRealm($realm);
    if ($testCandidate !== FALSE) {
        $testedProfile = core\ProfileFactory::instantiate($testCandidate);
    }
}
$orrealm = array();
if ($testedProfile !== NULL) {
    $orrealm = $testedProfile->getAttributes("media:openroaming");
    $checkrealm = $testedProfile->getAttributes("internal:realm");
    if (count($checkrealm) > 0) {
        // checking our own stuff. Enable thorough checks
        $check_realm = $checkrealm[0]['value'];
        $testsuite = new \core\diag\RADIUSTests($check_realm, $testedProfile->getRealmCheckOuterUsername(), $testedProfile->getEapMethodsinOrderOfPreference(1), $testedProfile->getCollapsedAttributes()['eap:server_name'], $testedProfile->getCollapsedAttributes()["eap:ca_file"]);
        $dnsChecks = new \core\diag\RFC7585Tests($check_realm);
        if (count($orrealm)) {
            $dnsChecksOR = new \core\diag\RFC7585Tests($check_realm, "aaa+auth:radius.tls.tcp");
        }
    } else {
        $error_message = _("You asked for a realm check, but we don't know the realm for this profile!") . "</p>";
    }
} else { // someone else's realm, and we don't know anything about it... only shallow checks
    $check_realm = $validator->realm($realm ?? $_SESSION['check_realm'] ?? "");
    if ($check_realm !== FALSE) {
        $_SESSION['check_realm'] = $check_realm;
        $testsuite = new \core\diag\RADIUSTests($check_realm, "@" . $check_realm);
        $dnsChecks = new \core\diag\RFC7585Tests($check_realm);
    } else {
        $error_message = _("No valid realm name given, cannot execute any checks!");
    }
}
$translate1 = _("STATIC");
$translate2 = _("DYNAMIC");
$errorstate = [];
?>
<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />

<!-- JQuery -->
<script type="text/javascript" src="../external/jquery/jquery.js"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script>
<script type="text/javascript">
    var L_OK = <?php echo \core\common\Entity::L_OK ?>;
    var L_WARN = <?php echo \core\common\Entity::L_WARN ?>;
    var L_ERROR = <?php echo \core\common\Entity::L_ERROR ?>;
    var L_REMARK = <?php echo \core\common\Entity::L_REMARK ?>;
    var icons = new Array();
    /*
     icons[L_OK] = '../resources/images/icons/Checkmark-lg-icon.png';
     icons[L_WARN] = '../resources/images/icons/Exclamation-yellow-icon.png';
     icons[L_ERROR] = '../resources/images/icons/Exclamation-orange-icon.png';
     icons[L_REMARK] = '../resources/images/icons/Star-blue.png';
     */
    icons[L_OK] = '../resources/images/icons/Quetto/check-icon.png';
    icons[L_WARN] = '../resources/images/icons/Quetto/danger-icon.png';
    icons[L_ERROR] = '../resources/images/icons/Quetto/no-icon.png';
    icons[L_REMARK] = '../resources/images/icons/Quetto/info-icon.png';
    var icon_loading = '../resources/images/icons/loading51.gif';
    var tmp_content;
    var lang = '<?php echo $gui->languageInstance->getLang(); ?>'
    var states = new Array();
    states['PASS'] = "<?php echo _("PASS") ?>";
    states['FAIL'] = "<?php echo _("FAIL") ?>";
    var clientcert = "<?php echo _("Client certificate:") ?>";
    var expectedres = "<?php echo _("expected result: ") ?>";
    var accepted = "<?php echo _("Server accepted this client certificate") ?>";
    var falseaccepted = "<?php echo _("Server accepted this client certificate, but should not have") ?>";
    var notaccepted = "<?php echo _("Server did not accept this client certificate") ?>";
    var notacceptedwithreason = "<?php echo _("Server did not accept this client certificate - reason") ?>";
    var restskipped = "<?php echo _("Rest of tests for this CA skipped") ?>";
    var listofcas = "<?php echo _("You should update your list of accredited CAs") ?>";
    var getitfrom = "<?php echo _("Get it from here.") ?>";
    var listsource = "<?php echo \config\Diagnostics::RADIUSTESTS['accreditedCAsURL'] ?>";
    var moretext = "<?php echo _("more") . "&raquo;" ?>";
    var lesstext = "<?php echo "&laquo" ?>";
    var morealltext = "<?php echo _("Show detailed information for all tests") ?>";
    var unknownca_code = "<?php echo \core\diag\RADIUSTests::CERTPROB_UNKNOWN_CA ?>";
    var refused_code = "<?php echo \core\diag\RADIUSTests::RETVAL_CONNECTION_REFUSED ?>";
    var refused_info = "<?php echo _("Connection refused") ?>";
    var global_info = new Array();
    global_info[L_OK] = "<?php echo _("All tests passed."); ?>";
    global_info[L_WARN] = "<?php echo _("There were some warnings."); ?>";
    global_info[L_ERROR] = "<?php echo _("There were some errors."); ?>";
    global_info[L_REMARK] = "<?php echo _("There were some remarks."); ?>";
    var servercert = new Array();
    var arefailed = 0;
    var running_ajax_stat = 0;
    var running_ajax_dyn = 0;
    var global_level_udp = L_OK;
    var global_level_dyn = L_OK;
    var global_level_openroaming = L_OK;
    servercert['title'] = "<?php echo _("Server certificate") ?>";
    servercert['subject'] = "<?php echo _("Subject") ?>";
    servercert['issuer'] = "<?php echo _("Issuer") ?>";
    servercert['subjectaltname'] = "<?php echo _("SubjectAltName") ?>";
    servercert['policies'] = "<?php echo _("Certificate policies") ?>";
    servercert['crlDistributionPoint'] = "<?php echo _("crlDistributionPoint") ?>";
    servercert['authorityInfoAccess'] = "<?php echo _("authorityInfoAccess") ?>";
    var lessalltext = "<?php echo _("Hide detailed information for all tests") ?>";
    var addresses = new Array();
    var clients_level = L_OK;
    var openroamingclients_level = L_OK;
    var server_cert = new Object();
    server_cert.subject = "<?php echo _("Subject:") ?>";
    server_cert.issuer = "<?php echo _("Issuer:") ?>";
    server_cert.validFrom = "<?php echo _("Valid from:") ?>";
    server_cert.validTo = "<?php echo _("Valid to:") ?>";
    server_cert.serialNumber = "<?php echo _("Serial number:") ?>";
    server_cert.sha1 = "<?php echo _("SHA1 fingerprint:") ?>";
    var not_known_server = "<?php echo _("Connected to undetermined server") ?>";
    $(document).ready(function () {
        $('.caresult, .eap_test_results, .udp_results').on('click', '.morelink', function () {
            if ($(this).hasClass('less')) {
                $(this).removeClass('less');
//             $(this).html(moretext);
                $(this).html($(this).attr('moretext'));
//             $(this).html(moretext) = moretext;
                $('.moreall').removeClass('less');
                $('.moreall').html(morealltext);
            } else {
                $(this).attr('moretext', $(this).html());
                $(this).addClass('less');
                $(this).html(lesstext);
            }
            $(this).parent().prev().toggle();
            $(this).prev().toggle();
            return false;
        });
        $(".moreall").click(function () {
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

        $(function () {
            $("#tabs").tabs();
        });
        $("#submit_credentials").click(function (event) {
            event.preventDefault();
            var missing = 0;
            $(".mandatory").each(function (index) {
                if ($.trim($(this).val()) === '') {
                    $(this).addClass('missing_input');
                    missing = 1;
                } else
                    $(this).removeClass('missing_input');
            });
            if (missing) {
                alert("<?php echo _("Some required input is missing!") ?>");
                return;
            }
            $("#disposable_credential_container").hide();
            $("#live_login_results").show();
            run_login();
        });
    });

    function clients(data, status) {
        consortium = data.consortium;
        if (consortium == 'eduroam') {
            consortium = '';
        }
        var srefused = 0;
        show_debug(data);
        cliinfo = '<ol>';
        for (var key in data.ca) {
            srefused = 0;
            cliinfo = cliinfo + '<li>' + clientcert + ' <b>' + data.ca[key].clientcertinfo.from + '</b>' + ', ' + data.ca[key].clientcertinfo.message + '<br>(CA: ' + data.ca[key].clientcertinfo.issuer + ')';
            cliinfo = cliinfo + '<ul>';
            for (var c in data.ca[key].certificate) {
                if (data.ca[key].certificate[c].returncode === refused_code) {
                    srefused = 1;
                    arefailed = 1;
                }
            }
            if (srefused === 0) {
                for (var c in data.ca[key].certificate) {
                    cliinfo = cliinfo + '<li><i>' + data.ca[key].certificate[c].message + ', ' + expectedres + states[data.ca[key].certificate[c].expected] + '</i>';
                    cliinfo = cliinfo + '<ul style=\"list-style-type: none;\">';
                    level = data.ca[key].certificate[c].returncode;
                    if (level < 0) {
                        level = L_ERROR;
                        arefailed = 1;
                    }
                    add = '';
                    if (data.ca[key].certificate[c].expected === 'PASS') {
                        if (data.ca[key].certificate[c].connected === 1)
                            state = accepted;
                        else {
                            if (data.ca[key].certificate[c].reason === unknownca_code)
                                add = '<br>' + listofcas + ' <a href=\"' + listsource + '\">' + getitfrom + '</a>';
                            state = notacceptedwithreason + ': ' + data.ca[key].certificate[c].resultcomment;
                        }
                    } else {
                        if (data.ca[key].certificate[c].connected === 1) {
                            level = L_WARN;
                            state = falseaccepted;
                        } else {
                            level = L_OK;
                            state = notaccepted + ': ' + data.ca[key].certificate[c].resultcomment;
                        }
                    }
                    cliinfo = cliinfo + '<li><table><tbody><tr><td class="icon_td"><img class="icon" src="' + icons[level] + '" style="width: 24px;"></td><td>' + state;
                    cliinfo = cliinfo + ' <?php echo "(" . sprintf(_("elapsed time: %sms."), "'+data.ca[key].certificate[c].time_millisec+'&nbsp;") . ")"; ?>' + add + '</td></tr>';
                    cliinfo = cliinfo + '</tbody></table></ul></li>';
                    if (data.ca[key].certificate[c].finalerror === 1) {
                        cliinfo = cliinfo + '<li>' + restskipped + '</li>';
                    }
                }
            }
            if (consortium == '') {
                clients_level = Math.max(clients_level, level);
                global_level_dyn = Math.max(global_level_dyn, level);
            } else {
                openroamingclients_level = Math.max(openroamingclients_level, level);
                global_level_openroaming = Math.max(global_level_openroaming, level);
            }

            cliinfo = cliinfo + '</ul>';
        }
        cliinfo = cliinfo + '</ol>';
        resultname = '#';
        if (consortium == '') {
            resultname = resultname + 'dynamic'
        } else {
            resultname = resultname + 'openroaming'
        }
        if (srefused > 0) {
            cliinfo = refused_info;
            $('#' + consortium + 'srcclient' + data.hostindex).html('<p>' + cliinfo + '</p>');
            $('#' + consortium + 'srcclient' + data.hostindex + "_img").attr('src', icons[L_ERROR]);
            $(resultname + '_pass').hide();
            $(resultname + '_fail').show();
        } else {
            if (arefailed) {
                $(resultname + '_pass').hide();
                $(resultname + '_fail img').attr('src', icons[clients_level]);
                $(resultname + '_fail').show();
            } else {
                $(resultname + '_pass').show();
                $(resultname + '_fail').hide();
            }
            $('#' + consortium + 'clientresults' + data.hostindex).html('<p>' + cliinfo + '</p>');
        }
        if (consortium == '') {
            running_ajax_dyn--;
        } else {
            running_ajax_openroaming--;
        }
        ajax_end();

    }

    function capath(data, status) {
        show_debug(data);
        consortium = data.consortium;
        if (consortium == 'eduroam') {
            consortium = '';
        }
        srcca = '#' + consortium + 'srcca';
        clientstest = '#' + consortium + 'clientstest';
        $(srcca + data.hostindex).html('');
        var newhtml = '<p>' + data.message + '</p>';
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
        if (consortium == '') {
            global_level_dyn = Math.max(global_level_dyn, data.level);
        } else {
            global_level_openroaming = Math.max(global_level_openroaming, data.level);
        }
        $(srcca + data.hostindex).html('<div>' + data.message + '</div>' + more);
        $(srcca + data.hostindex + "_img").attr('src', icons[data.level]);
        if ((addresses[data.ip] === 0) && $(clientstest).is(':hidden')) {
            $(clientstest).show();
        }
        if (consortium == '') {
            running_ajax_dyn--;
        } else {
            running_ajax_openroaming--;
        }
        ajax_end();
    }

    function udp(data, status) {
        show_debug(JSON.stringify(data));
        var v = data.result[0];
        $("#src" + data.hostindex + "_img").attr('src', icons[v.level]);
        if (v.server !== 0) {
            $("#src" + data.hostindex).html('<strong>' + v.server + '</strong><br/><?php printf(_("elapsed time: %sms."), "'+v.time_millisec+'&nbsp;") ?><p>' + v.message + '</p>');
            var cert_data = "<tr class='server_cert'><td>&nbsp;</td><td colspan=2><div><dl class='server_cert_list'>";
            $.each(server_cert, function (l, s) {
                cert_data = cert_data + "<dt>" + s + "</dt><dd>" + v.server_cert[l] + "</dd>";
            });
            var ext = '';
            $.each(v.server_cert.extensions, function (l, s) {
                if (ext !== '')
                    ext = ext + '<br>';
                ext = ext + '<strong>' + l + ': </strong>' + s;
            });
            cert_data = cert_data + "<dt><?php echo _("Extensions") ?></dt><dd>" + ext + "</dd></dl>";
            cert_data = cert_data + "<a href='' class='morelink'><?php echo _("show server certificate details") ?>&raquo;</a></div></tr>";
            if (v.level > L_OK && v.cert_oddities !== undefined) {
                $.each(v.cert_oddities, function (j, w) {
                    $("#src" + data.hostindex).append('<tr class="results_tr"><td>&nbsp;</td><td class="icon_td"><img src="' + icons[w.level] + '"></td><td>' + w.message + '</td></tr>');
                });
            }
            $("#src" + data.hostindex).append(cert_data);
        } else {
            $("#src" + data.hostindex).html('<strong>' + not_known_server + '</strong><br/><?php printf(_("elapsed time: %sms."), "'+v.time_millisec+'&nbsp;") ?><p>' + v.message + '</p>');
            if (v.level > L_OK && v.cert_oddities !== undefined) {
                $.each(v.cert_oddities, function (j, w) {
                    $("#src" + data.hostindex).append('<tr class="results_tr"><td>&nbsp;</td><td class="icon_td"><img src="' + icons[w.level] + '"></td><td>' + w.message + '</td></tr>');
                });
            }
        }
        global_level_udp = Math.max(global_level_udp, v.level);
        $(".server_cert").show();
        running_ajax_stat--;
        ajax_end();
    }
    
    function ajax_end() {
        if (running_ajax_stat === 0) {
            $("#main_static_ico").attr('src', icons[global_level_udp]);
            $("#main_static_result").html(global_info[global_level_udp] + ' ' + "<?php echo _("See the appropriate tab for details.") ?>");
            $("#main_static_result").show();
        }
        if (running_ajax_dyn === 0) {
            $("#main_dynamic_ico").attr('src', icons[global_level_dyn]);
            $("#main_dynamic_result").html(global_info[global_level_dyn] + ' ' + "<?php echo _("See the appropriate tab for details.") ?>");
            $("#main_dynamic_result").show();
        }
        if (running_ajax_openroaming === 0) {
            $("#main_openroaming_ico").attr('src', icons[global_level_openroaming]);
            $("#main_openroaming_result").html(global_info[global_level_openroaming] + ' ' + "<?php echo _("See the appropriate tab for details.") ?>");
            $("#main_openroaming_result").show();
        }
    }

    function udp_login(data, status) {
        show_debug(data);
        $("#live_src" + data.hostindex + "_img").hide();
        $.each(data.result, function (i, v) {
            var o = '<table><tr><td colspan=2>';
            var cert_data = '';
            if (v.server !== 0) {
                o = o + '<strong>' + v.server + '</strong><p>';
                cert_data = "<tr><td>&nbsp;</td><td><p><strong><?php echo _("Server certificate details:") ?></strong><dl class='udp_login'>";
                $.each(server_cert, function (l, s) {
                    cert_data = cert_data + "<dt>" + s + "</dt><dd>" + v.server_cert[l] + "</dd>";
                });

                var ext = '';
                $.each(v.server_cert.extensions, function (l, s) {
                    if (ext !== '')
                        ext = ext + '<br>';
                    ext = ext + '<strong>' + l + ': </strong>' + s;
                });

                cert_data = cert_data + "<dt><?php echo _("Extensions") ?></dt><dd>" + ext + "</dd></dl></td></tr>";

            }
            o = o + v.message + '</td></tr>';
            if (v.level > L_OK && v.cert_oddities != undefined) {
                $.each(v.cert_oddities, function (j, w) {
                    o = o + '<tr><td class="icon_td"><img src="' + icons[w.level] + '"></td><td>' + w.message + '</td></tr>';
                });
            }
            o = o + cert_data + '</table>';
            $("#eap_test" + data.hostindex).append('<strong><img style="position: relative; top: 2px;" src="' + icons[v.level] + '"><span style="position: relative; top: -5px; left: 1em">' + v.eap + ' &ndash; <?php printf(_("elapsed time: %sms."), "'+v.time_millisec+'&nbsp;") ?></span></strong><div class="more" style="padding-left: 40px"><div class="morecontent"><div style="display:none; background: #eee;">' + o + '</div><a href="" class="morelink">' + moretext + '</a></div></div>');
        });
    }

    function run_login() {
        $("#debug_out").html('');
        $(".eap_test_results").empty();
        var formData = new FormData($('#live_form')[0]);
<?php
foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
    print "
$(\"#live_src" . $hostindex . "_img\").attr('src',icon_loading);
$(\"#live_src" . $hostindex . "_img\").show();
$.ajax({
    url: 'radius_tests.php?src=0&hostindex=$hostindex&realm='+realm,
    type: 'POST',
    success: udp_login,
    error: udp_login,
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

    function run_udp() {
        running_ajax_stat = 0;
        $("#main_static_ico").attr('src', icon_loading);
        $("#main_static_result").html("");
        $("#main_static_result").hide();
        global_level_udp = L_OK;
        $("#debug_out").html('');
        $("#static_tests").show();
        $(".results_tr").remove();
        $(".server_cert").hide();
<?php
foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
    if ($testedProfile !== NULL) {
        $extraarg = "profile_id: " . $testedProfile->identifier . ", ";
    } else {
        $extraarg = "";
    }
    print "
$(\"#src" . $hostindex . "_img\").attr('src',icon_loading);
$(\"#src$hostindex\").html('');
running_ajax_stat++;
$.get('radius_tests.php',{test_type: 'udp', $extraarg realm: realm, src: $hostindex, lang: '" . $gui->languageInstance->getLang() . "', hostindex: '$hostindex'  }, udp, 'json');

";
}
?>
    }

    function eee() {
        alert("Unexpected error");
    }

    function show_debug(text) {
        // comment out the line below if you want to see debug output from tests
        return;
        var t = $("#debug_out").html();
        $("#debug_out").html(t + "<p>" + JSON.stringify(text));
        $("#debug_out").show();
    }
</script>
</head>
<body>
    <?php
    echo $deco->productheader("ADMIN");

    if ($check_realm === FALSE) {
        print "<p>$error_message</p>";
    } else {
        print "<h1>" . sprintf(_("Realm testing for: %s"), $check_realm) . "</h1>\n";
        ?>
        <div id="debug_out" style="display: none"></div>
        <div id="tabs" style="min-width: 600px; max-width:1000px">
            <ul>
                <li><a href="#tabs-1"><?php echo _("Overview") ?></a></li>
                <li><a href="#tabs-2"><?php echo _("Static connectivity tests") ?></a></li>
                <li id="tabs-d-li"><a href="#tabs-3"><?php echo _("Dynamic connectivity tests") ?></a></li>
                <li id="tabs-o-li"><a href="#tabs-4"><?php echo _("OpenRoaming connectivity tests") ?></a></li>
                <li id="tabs-through"><a href="#tabs-n"><?php echo _("Live login tests") ?></a></li>
            </ul>
            <div id="tabs-1">
                <fieldset class='option_container'>
                    <legend>
                        <strong><?php echo _("Overview") ?></strong>
                    </legend>
            <?php
                $naptrs = array();
                foreach (array("", "openroaming") as $dynType) {
                    // NAPTR existence check
                    if ($dynType == "") {
                        $rfc7585suite = $dnsChecks;
                        echo "<strong>" . _("DNS checks") . "</strong><div>";
                    } else {
                        if (count($orrealm) == 0) {
                            continue;
                        }
                        $rfc7585suite = $dnsChecksOR;
                        echo "<strong>" . _("OpenRoaming DNS checks") . "</strong><div>";
                    }
                    $naptr = $rfc7585suite->relevantNAPTR();
                    if ($naptr == \core\diag\RADIUSTests::RETVAL_NOTCONFIGURED) {
                        if ($dynType == "") {
                        	echo "<tr><td>" . _("Dynamic discovery test is not configured") . "</td><td>";
                        } else {
                        	echo "<tr><td>" . _("OpenRoaming connectivity test is not configured") . "</td><td>";
                        }
                    } else {
                        echo "<table>";
                        // output in friendly words
                        echo "<tr><td>" . _("Checking NAPTR existence:") . "</td><td>";
                        switch ($naptr) {
                            case \core\diag\RFC7585Tests::RETVAL_NONAPTR:
                                echo _("This realm has no NAPTR records.");
                                break;
                            case \core\diag\RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR:
                                echo _("This realm has NAPTR records, but none are related to this roaming consortium.");
                                break;
                            default: // if none of the possible negative retvals, then we have matching NAPTRs
                                printf(_("This realm has %d NAPTR records relating to this roaming consortium."), $naptr);
                        }
                        echo "</td></tr>";

                        // compliance checks for NAPTRs
                        if ($naptr > 0) {
                            echo "<tr><td>" . _("Checking NAPTR compliance (flag = S and regex = {empty}):") . "</td><td>";
                            $naptr_valid = $rfc7585suite->relevantNAPTRcompliance();
                            switch ($naptr_valid) {
                                case \core\diag\RADIUSTests::RETVAL_OK:
                                    echo _("No issues found.");
                                    break;
                                case \core\diag\RADIUSTests::RETVAL_INVALID:
                                    printf(_("At least one NAPTR with invalid content found!"));
                                    break;
                            }
                            echo "</td></tr>";
                        }
                        // SRV resolution
                        if ($naptr > 0 && $naptr_valid == \core\diag\RADIUSTests::RETVAL_OK) {
                            $srv = $rfc7585suite->relevantNAPTRsrvResolution();
                            echo "<tr><td>" . _("Checking SRVs:") . "</td><td>";
                            switch ($srv) {
                                case \core\diag\RADIUSTests::RETVAL_SKIPPED:
                                    echo _("This check was skipped.");
                                    break;
                                case \core\diag\RADIUSTests::RETVAL_INVALID:
                                    printf(_("At least one NAPTR with invalid content found!"));
                                    break;
                                default: // print number of successfully retrieved SRV targets
                                    printf(_("%d host names discovered."), $srv);
                            }
                            echo "</td></tr>";
                        }
                        // IP addresses for the hosts
                        if ($naptr > 0 && $naptr_valid == \core\diag\RADIUSTests::RETVAL_OK && $srv > 0) {
                            $hosts = $rfc7585suite->relevantNAPTRhostnameResolution();
                            echo "<tr><td>" . _("Checking IP address resolution:") . "</td><td>";
                            switch ($srv) {
                                case \core\diag\RADIUSTests::RETVAL_SKIPPED:
                                    echo _("This check was skipped.");
                                    break;
                                case \core\diag\RADIUSTests::RETVAL_INVALID:
                                    printf(_("At least one hostname could not be resolved!"));
                                    break;
                                default: // print number of successfully retrieved SRV targets
                                    printf(_("%d IP addresses resolved."), $hosts);
                            }
                            echo "</td></tr>";
                        }

                        echo "</table><br/>";
                        if ($dynType == "") {
                            if (count($testsuite->listerrors()) == 0) {
                                echo sprintf(_("Realm is <strong>%s</strong> "), _(($naptr > 0 ? "DYNAMIC" : "STATIC"))) . _("with no DNS errors encountered. Congratulations!");
                            } else {
                                echo sprintf(_("Realm is <strong>%s</strong> "), _(($naptr > 0 ? "DYNAMIC" : "STATIC"))) . _("but there were DNS errors! Check them!") . " " . _("You should re-run the tests after fixing the errors; more errors might be uncovered at that point. The exact error causes are listed below.");
                                echo "<div class='notacceptable'><table>";
                                foreach ($testsuite->listerrors() as $details) {
                                    echo "<tr><td>" . $details['TYPE'] . "</td><td>" . $details['TARGET'] . "</td></tr>";
                                }
                                echo "</table></div>";
                            }
                        }
                        echo '</div><hr>';
                  
                        echo '<script type="text/javascript">';
                        if ($dynType == "") {
                            echo '
              function run_dynamic() {
                 running_ajax_dyn = 0;
                 $("#main_dynamic_ico").attr("src",icon_loading);
                 $("#main_dynamic_result").html("");
                 $("#main_dynamic_result").hide();
                 global_level_dyn = L_OK;
                 $("#dynamic_tests").show();
              ';
                        foreach ($rfc7585suite->NAPTR_hostname_records as $hostindex => $addr) {
                            $host = ($addr['family'] == "IPv6" ? "[" : "") . $addr['IP'] . ($addr['family'] == "IPv6" ? "]" : "") . ":" . $addr['port'];
                            $expectedName = $addr['hostname'];
                            print "
                            running_ajax_dyn++;
                            $.ajax({url:'radius_tests.php', data:{test_type: 'capath', realm: realm, src: '$host', lang: '" . $gui->languageInstance->getLang() . "', hostindex: '$hostindex', expectedname: '$expectedName' }, error: eee, success: capath, dataType: 'json'}); 
                            running_ajax_dyn++;
                            $.ajax({url:'radius_tests.php', data:{test_type: 'clients', realm: realm, src: '$host', lang: '" . $gui->languageInstance->getLang() . "', hostindex: '$hostindex' }, error: eee, success: clients, dataType: 'json'}); 
                       ";
                        }
                        echo "}
              </script>";
                        } else {
                   echo ' 
              function run_openroaming() {
                 running_ajax_openroaming = 0;
                 $("#main_openroaming_ico").attr("src",icon_loading);
                 $("#main_openroaming_result").html("");
                 $("#main_openroaming_result").hide();
                 global_level_or = L_OK;
                 $("#openroaming_tests").show();
              ';
                        foreach ($rfc7585suite->NAPTR_hostname_records as $hostindex => $addr) {
                            $host = ($addr['family'] == "IPv6" ? "[" : "") . $addr['IP'] . ($addr['family'] == "IPv6" ? "]" : "") . ":" . $addr['port'];
                            $expectedName = $addr['hostname'];
                            print "
                            running_ajax_openroaming++;
                            $.ajax({url:'radius_tests.php', data:{test_type: 'openroamingcapath', realm: realm, src: '$host', lang: '" . $gui->languageInstance->getLang() . "', hostindex: '$hostindex', expectedname: '$expectedName' }, error: eee, success: capath, dataType: 'json'}); 
                       ";
                        }
                        echo "}
              </script>";
                        } 
                    }
                    if ($dynType == '') {
                         $naptrs[0] = $naptr;
                     } else {
                         $naptrs[1] = $naptr;
                     }
                }
                    echo "<strong>" . _("Static connectivity tests") . "</strong>
         <table><tr>
         <td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='main_static_ico' class='icon'></td><td id='main_static_result' style='display:none'>&nbsp;</td>
         </tr></table>";
                    if ($naptrs[0] > 0) {
                        echo "<hr><strong>" . _("Dynamic connectivity tests") . "</strong>
         <table><tr>
         <td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='main_dynamic_ico' class='icon'></td><td id='main_dynamic_result' style='display:none'>&nbsp;</td>
         </tr></table>";
                    }
                    if (count($orrealm) && ($naptrs[1] > 0)) {
                      echo "<hr><strong>" . _("OpenRoaming connectivity tests") . "</strong>
         <table><tr>
         <td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='main_openroaming_ico' class='icon'></td><td id='main_openroaming_result' style='display:none'>&nbsp;</td>
         </tr></table>";
                    }
                    ?>

                </fieldset>

            </div>
            <div id="tabs-2">
                <button id="run_s_tests" onclick="run_udp()"><?php echo _("Repeat static connectivity tests") ?></button>
                <p>
                <fieldset class="option_container" id="static_tests">
                    <legend><strong> <?php echo _("STATIC connectivity tests"); ?> </strong> </legend>
                    <?php
                    echo _("This check sends a request for the realm through various entry points of the roaming consortium infrastructure. The request will contain the 'Operator-Name' attribute, and will be larger than 1500 Bytes to catch two common configuration problems.<br/>Since we don't have actual credentials for the realm, we can't authenticate successfully - so the expected outcome is to get an Access-Reject after having gone through an EAP conversation.");
                    print "<p>";
                    foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
                        print "<hr>";
                        printf(_("Testing from: %s"), "<strong>" . \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$hostindex]['display_name'] . "</strong>");
                        print "<table id='results$hostindex'  style='width:100%' class='udp_results'>
<tr>
<td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='src" . $hostindex . "_img'></td>
<td id='src$hostindex' colspan=2>
" . _("testing...") . "
</td>
</tr>
</table>";
                    }
                    ?>
                </fieldset>


            </div>
            <?php
            for ($i=3; $i<5; $i++) {
                if ($i == 3 && $naptrs[0] <= 0) {
                   continue;
                }
                if ($i == 4) {
                   if ((count($orrealm) == 0) || ($naptrs[1] <= 0)) {
                       continue;
                   }
                }
                if ($i == 3) {
                    $rfc7585suite = $dnsChecks;
                } else {
                    $rfc7585suite = $dnsChecksOR;
                }
            ?>
                <div id="tabs-<?php echo $i;?>">
                    <button id="run_<?php if ($i==3) echo 'd'; else echo 'o';?>_tests"; onclick="run_<?php if ($i==3) echo 'dynamic'; else echo 'openroaming';?>()"><?php if ($i==3) echo _("Repeat dynamic connectivity tests"); else echo _("Repeat OpenRoaming connectivity tests");?></button>

                <?php
                    echo "<div id='";
                    if ($i==3) { echo 'dynamic'; } else { echo 'openroaming'; }
                    echo "_tests'><fieldset class='option_container'>
                <legend><strong>";
                    if ($i==3) {
                     echo _("DYNAMIC connectivity tests");
                    } else {
                     echo _("OpenRoaming connectivity tests");
                    }
                    echo  "</strong></legend>";
                    $prefix1 = 'dynamic';
                    $prefix2 = '';
                    if ($i == 4) {
                    	$prefix1 = 'openroaming';
                    	$prefix2 = $prefix1;
                    }
                    $resultstoprint = [];
                    if (count($rfc7585suite->NAPTR_hostname_records) > 0) {
                        $resultstoprint[] = '<div style="align:right; display: none;" id="' . $prefix1 . '_result_fail">' . _("Some errors were found during the tests, see below") . '</div><div style="align:right; display: none;" id="' . $prefix1 . '_result_pass">' . _("All tests passed, congratulations!") . '</div>';
                        $resultstoprint[] = '<div style="align:right;"><a href="" class="moreall">' . _('Show detailed information for all tests') . '</a></div>' . '<p><strong>' . _("Checking server handshake...") . "</strong><p>";
                        foreach ($rfc7585suite->NAPTR_hostname_records as $hostindex => $addr) {
                            $bracketaddr = ($addr["family"] == "IPv6" ? "[" . $addr["IP"] . "]" : $addr["IP"]);
                            $resultstoprint[] = '<p><strong>' . $bracketaddr . ' TCP/' . $addr['port'] . '</strong>';
                            $resultstoprint[] = '<ul style="list-style-type: none;" class="caresult"><li>';
                            $resultstoprint[] = "<table id='" . $prefix2 . "caresults$hostindex'  style='width:100%'>
<tr>
<td class='icon_td'><img src='../resources/images/icons/loading51.gif' id='" . $prefix2 . "srcca$hostindex" . "_img'></td>
<td id='" . $prefix2 . "srcca$hostindex'>
" . _("testing...") . "
</td>
</tr>
</table>";
                            $resultstoprint[] = '</li></ul>';
                        }
                        $clientstest = [];
                        foreach ($rfc7585suite->NAPTR_hostname_records as $hostindex => $addr) {
                            $clientstest[] = '<p><strong>' . $addr['IP'] . ' TCP/' . $addr['port'] . '</strong></p><ol>';
                            $clientstest[] = "<span id='" . $prefix2 . "clientresults$hostindex$clinx'><table style='width:100%'>
<tr>
<td class='icon_td'>";
                            if ($i == 4 ) {
                                $clientstest[] = "<!--";
                            }
                            $clientstest[] = "<img src='../resources/images/icons/loading51.gif' id='" . $prefix2 . "srcclient$hostindex" . "_img'></td>
<td id='" . $prefix2 . "srcclient$hostindex'>
" . _("testing...");

                            if ($i == 4 ) {
                                $clientstest[] = "-->" . _("not implemented yet");
                            }
                            $clientstest[] = "</td></tr></table></span>";
                            $clientstest[] = '</ol>';
                        }
                        echo '<div style="align:right;">';
                        echo join('', $resultstoprint);
                        echo '<span id="' . $prefix2 . 'clientstest" style="display: none;"><p><hr><b>' . _('Checking if certificates from  CAs are accepted...') . '</b><p>' . _('A few client certificates will be tested to check if servers are resistant to some certificate problems.') . '<p>';
                        print join('', $clientstest);
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
                if ($my_profile !== NULL) {
                    echo "<div id='tabs-n'><fieldset class='option_container'>
                <legend><strong>" . _("Live login test") . "</strong></legend>";
                    $prof_compl = $my_profile->getEapMethodsinOrderOfPreference(1);
                    if (count($prof_compl) > 0) {
                        $passwordReqired = FALSE;
                        $clientCertRequired = FALSE;
                        foreach ($prof_compl as $eap) {
                            if ($eap->isPasswordRequired()) {
                                $passwordReqired = TRUE;
                            }
                            if ($eap->isClientCertRequired()) {
                                $clientCertRequired = TRUE;
                            }
                        }
                        echo "<div id='disposable_credential_container'><p>" . _("If you enter an existing login credential here, you can test the actual authentication from various checkpoints all over the world.") . "</p>
                    <p>" . _("The test will use all EAP types you have set in your profile information to check whether the right CAs and server names are used, and of course whether the login with these credentials and the given EAP type actually worked. If you have set anonymous outer ID, the test will use that.") . "</p>
                    <p>" . _("Note: the tool purposefully does not offer you to save these credentials, and they will never be saved in any way on the server side. Please use only <strong>temporary test accounts</strong> here; permanently valid test accounts in the wild are considered harmful!") . "</p></div>
                    <form enctype='multipart/form-data' id='live_form' accept-charset='UTF-8'>
                    <input type='hidden' name='test_type' value='udp_login'>
                    <input type='hidden' name='lang' value='" . $gui->languageInstance->getLang() . "'>
                    <input type='hidden' name='profile_id' value='" . $my_profile->identifier . "'>
                    <table id='live_tests'>";
// if any password based EAP methods are available enable this section
                        if ($passwordReqired) {
                            echo "<tr><td colspan='2'><strong>" . _("Password-based EAP types") . "</strong></td></tr>
                        <tr><td>" . _("Real (inner) username:") . "</td><td><input type='text' id='username' class='mandatory' name='username'/></td></tr>";
                            echo "<tr><td>" . _("Anonymous outer ID (optional):") . "</td><td><input type='text' id='outer_username' name='outer_username'/></td></tr>";
                            echo "<tr><td>" . _("Password:") . "</td><td><input type='text' id='password' class='mandatory' name='password'/></td></tr>";
                        }
                        // ask for cert + privkey if TLS-based method is active
                        if ($clientCertRequired) {
                            echo "<tr><td colspan='2'><strong>" . _("Certificate-based EAP types") . "</strong></td></tr>
                        <tr><td>" . _("Certificate file (.p12 or .pfx):") . "</td><td><input type='file' id='cert' accept='application/x-pkcs12' name='cert'/></td></tr>
                        <tr><td>" . _("Certificate password, if any:") . "</td><td><input type='text' id='privkey' name='privkey_pass'/></td></tr>
                        <tr><td>" . _("Username, if different from certificate Subject:") . "</td><td><input type='text' id='tls_username' name='tls_username'/></td></tr>";
                        }
                        echo "<tr><td colspan='2'><button id='submit_credentials'>" . _("Submit credentials") . "</button></td></tr></table></form>";
                        echo "<div id='live_login_results' style='display:none'>";
                        foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {
                            print "<hr>";
                            printf(_("Testing from: %s"), "<strong>" . \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$hostindex]['display_name'] . "</strong>");
                            print "<span style='position:relative'><img src='../resources/images/icons/loading51.gif' id='live_src" . $hostindex . "_img' style='width:24px; position: absolute; left: 20px; bottom: 0px; '></span>";
                            print "<div id='eap_test$hostindex' class='eap_test_results'></div>";
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
            }

            if (isset($_POST['comefrom'])) {
                $return = htmlspecialchars_decode($_POST['comefrom']) . ( $inst_id ? "?inst_id=" . $inst_id : "" );
                echo "<form method='post' action='$return' accept-charset='UTF-8'>
                    <button type='submit' name='submitbutton' value='" . web\lib\common\FormElements::BUTTON_CLOSE . "'>" . sprintf(_("Return to %s administrator area"), core\common\Entity::$nomenclature_idp) . "</button>"
                . "</form>";
            }
            if ($check_realm !== FALSE) {
                echo "<script>


            var realm = '$check_realm';
            run_udp();";

                if ($naptrs[0] > 0) {
                    echo "run_dynamic();";
                } else {
                    echo '$("#tabs-d-li").hide();';
                }
                if (($naptrs[1] > 0) && count($orrealm) > 0) {
                    echo "run_openroaming();";
                } else {
                    echo '$("#tabs-o-li").hide();';
                }
                if ($my_profile === NULL) {
                    echo '$("#tabs-through").hide();';
                }
                echo "</script>";
            }
            
            echo $deco->footer();
