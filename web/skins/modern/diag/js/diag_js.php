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
 ?>
<script>
    var L_OK = <?php echo \core\common\Entity::L_OK ?>;
    var L_WARN = <?php echo \core\common\Entity::L_WARN ?>;
    var L_ERROR = <?php echo \core\common\Entity::L_ERROR ?>;
    var L_REMARK = <?php echo \core\common\Entity::L_REMARK ?>;
    var global_info = new Array();
    global_info[L_OK] = "<?php echo _("All connectivity tests passed"); ?>";
    global_info[L_WARN] = "<?php echo _("There were some warnings from connectivity tests"); ?>";
    global_info[L_ERROR] = "<?php echo _("There were some errors from connectivity tests"); ?>";
    function countryAddSelect(selecthead, select, type) {
        if (selecthead !== '') {
            select = selecthead + select + '</td>';
        }
        $('#select_'+type+'_country').hide();
        var shtml = '';
        if (type === 'idp' || type === 'sp') {
            shtml = '<table><tbody><tr id="row_'+type+'_country"></tr>';
            shtml = shtml + '<tr id="row_'+type+'_institution" style="visibility: collapse;">';
            shtml = shtml + '<td>' + <?php echo '"'._("Select institiution:").'"'; ?> + '</td><td></td></tr>';
            if (type === 'idp') {
                shtml = shtml + '<tr id="row_idp_realm"></tr>';
            }
            shtml = shtml + '</tbody></table>';  
            $('#select_' + type+'_area').html(shtml);
            $('#select_' + type+'_area').show();
            $('#row_' + type+'_country').append(select);
        } else {
            shtml = '<div id="inst_asp_area"></div>';
            $('#select_' + type+'_area').html(select + shtml);
            $('#select_' + type+'_area').show();
        }  
        reset_footer();
    }
    function countrySelection(type1) {
        var type2;
        if (type1 === 'sp' || type1 === 'asp') {
            type2 = 'idp';
        }
        if (type1 === 'idp') {
            type2 = 'sp';
        }
        var options = '';
        var selecthead = '';
        if (type1 === 'sp' || type1 === 'idp') {
            selecthead = <?php echo '"<td>'._("Select country or region:").' </td>"'; ?>;
            selecthead = selecthead + '<td>\n';
        }
        var select = '<select id="' + type1 + '_country" name="' + type1 + '_country" style="margin-left:0px; width:400px;">';
        if ($("#"+type2+"_country").is('select')) {
            options = ($('#'+type2+'_country').html());
            countryAddSelect(selecthead, select + options + '</select>', type1);
        } else {
            var comment = <?php echo '"<br><br>'._("Fetching country/region list").'..."'; ?>;
            inProgress(1, comment);
            $.ajax({
                url: "findRealm.php",
                data: {type: "co", lang: lang},
                dataType: "json",
                success:function(data) {
                    if (data.status) {
                        inProgress(0);
                        options = '<option value=""></option>';
                        var countries = data.countries;
                        for (var key in countries) {
                            options  = options + '<option value="'+key+'">' + countries[key] + '</option>';
                        }
                        countryAddSelect(selecthead, select + options + '</select>', type1);
                    }
                },
                error:function() {
                    passed = false;
                    inProgress(0);
                    alert('error');
                }
            });
        }  
    }
    function isDomain(realm) {
        realm = trimRealm(realm);
        if (realm.indexOf('.') == -1) {
            return false;
        }
        var re = new RegExp(/^((([0-9]{1,3}\.){3}[0-9]{1,3})|(([a-zA-Z0-9]+(([\-]?[a-zA-Z0-9]+)*\.)+)*[a-zA-Z]{2,}))$/);
        if (re.test(realm)) {
            return true;
        }
        return false;
    }
    function testSociopath(realm, answer) {
        var comment = <?php echo '"'._("Testing realm").'..."'; ?>; 
        inProgress(1, comment);
        if ($('#tested_realm').length == 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'tested_realm',
                value: realm
            }).appendTo('form');
        }  
        $.ajax({
            url: "processSociopath.php",
            data: {answer: answer},
            dataType: "json",
            success:function(data) {
                $('#start_test_area').hide();
                if (data) {
                    inProgress(0);
                    if (data['NEXTEXISTS']) {
                        if ($('#sociopath_queries').html() == '') {
                            var query = '';
                            if ($('#tested_realm').length == 0) {
                                query = '<input type="hidden" id="tested_realm" value="' + realm + '">';
                            }    
                            query = query + '<div id="current_query">'+data['TEXT']+'</div>';
                            query = query + '<div><button class="diag_button" id="answer_yes">' + <?php echo '"'._("Yes").'"'; ?> + '</button>';
                            query = query + '<button style="margin-left:20px;" class="diag_button" id="answer_no">' + <?php echo '"'._("No").'"'; ?> + '</button>';
                            query = query + '<button style="margin-left:20px;" class="diag_button" id="answer_noidea">' + <?php echo '"'._("I don't know").'"'; ?> + '</button></div>';
                            $('#sociopath_queries').html(query);
                            $('#sociopath_query_area').show();
                        }
                        else {
                            $('#current_query').html(data['TEXT']);
                        }
                        reset_footer();
                   } else {
                        var realm = $('#tested_realm').val();
                        $('#tested_realm').remove();
                        $('#sociopath_query_area').hide();
                        $('#sociopath_queries').html('');
                        $('#start_test_area').show();
                        $('#after_stage_1').hide();
                        $('#before_stage_1').show();
                        $('#realm_by_select').show();
                        $('#position_info').show();
                        finalVerdict(realm, data['SUSPECTS']);
                        reset_footer();
                   }
                }
                
            },
            error:function() {
                inProgress(0);
                alert('error');
            }
       }); 
    }
    function finalVerdict(realm, verdict) {
        var title = <?php echo '"'._("Diagnostic tests results for selected realm").'"'; ?>;
        result = '<div class="padding">';
        result = result + '<div><h3>';
        result = result + <?php echo '"'._("The result for tested realm:").' "'; ?> + realm;
        result = result + '</h3></p><div style="padding: 5px;"><div style="padding: 0px;">';
        result = result + <?php echo '"'._("The system identified").'" '; ?>  + ' ';
        result = result + Object.keys(verdict).length + ' ';
        result = result + <?php echo '"'._("suspected areas which potentially can cause a problem.").'"'; ?> + '<br>';
        result = result + <?php echo '"'._("Next to the problem description we show a speculated probability of this event.").'"'; ?>;
        result = result + '</div><div style="padding: 5px;"><table>';
        k = 1;
        for (key in verdict) {
            result = result + '<tr><td>' + k + '.</td>';
            k = k + 1;
            if (key === 'INFRA_DEVICE') {
                result = result + '<td>' + <?php echo '"'._("Your device configuration is broken").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_SP_80211') {
                result = result + '<td>' + <?php echo '"'._("The Wi-Fi network in your vicinity has quality issues").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_SP_LAN') {
                result = result + '<td>' + <?php echo '"'._("The network environment around you is broken").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_SP_RADIUS') {
                result = result + '<td>' + <?php echo '"'._("The RADIUS server of your service provider is the source of the problem").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_IDP_AUTHBACKEND') {
                result = result + '<td>' + <?php echo '"'._("The RADIUS server in your home institution is currently unable to authenticate you").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_NRO_SP') {
                result = result + '<td>' + <?php echo '"'._("The national server in the country/region you are visiting is not functioning correctly").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_LINK_ETLR_NRO_SP') {
                result = result + '<td>' + <?php echo '"'._("The link between the national server of the country/region you are visiting and the top-level server is broken").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_LINK_ETLR_NRO_IdP') {
                result = result + '<td>' + <?php echo '"'._("The link between the national server of your home country/region and the top-level server is broken").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_ETLR') {
                result = result + '<td>' + <?php echo '"'._("The communication to the top-level server is down").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_NRO_IdP') {
                result = result + '<td>' + <?php echo '"'._("The national server in your home country/region is not functioning properly.").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_IdP_RADIUS') {
                result = result + '<td>' + <?php echo '"'._("The RADIUS server of your home institution is the source of the problem").'"'; ?> + '</td>';
            }
            if (key === 'INFRA_NONEXISTENTREALM') {
                result = result + '<td>' + <?php echo '"'._("This realm does not exist").'"'; ?> + '</td>';
            }
            result = result + '<td style="padding-left: 5px;">' + (verdict[key] * 100).toFixed(2) + "%</td></tr>";
        }
        result = result + '</table></div></div>';
        result = result + '</div>';
        result = result + '</div></div>';
        showInfo(result, title);
    }
    function formatMAC(e) {
        var r = /([a-f0-9]{2})([a-f0-9]{2})/i,
        str = e.target.value.replace(/[^a-f0-9]/ig, "");
        while (r.test(str)) {
            str = str.replace(r, '$1' + ':' + '$2');
        }
        e.target.value = str.slice(0, 17);
        if (e.target.value.length == 17) {
            activate_send();
        }
    };
    function isEmail(email, emptyuser) {
        if (typeof emptyuser === 'undefined') {
            re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        } else {
            re = /^((([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))|)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        }
        return re.test(email);
    };
    function isOperatorName(str) {
        var re = /^(?=.{1,254}$)((?=[a-z0-9-]{1,63}\.)(xn--+)?[a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,63}$/;
        return re.test(str);
    }
    function inProgress(s, comment) {
        var b = true;
        if (s === 1) {
            var x = getWindowHCenter() - 16;
            var h = ($("body").height() - 128)/2;
            $("#loading_ico").css('left',x+'px');
            $("#loading_ico").css('top',h+'px');
            $("#loading_ico").show();
            if (typeof comment !== 'undefined') {
                $("#load_comment").html(comment);
            } else {
                $("#load_comment").html("");
            }
        } else {
            $("#loading_ico").hide();
            b = false;
            $("#load_comment").html("");
        }
        var catForm = document.forms['cat_form'];
        if (!catForm) {
            catForm = document.cat_form;
        }
        var elements = catForm.elements;
        for (var i = 0, len = elements.length; i < len; ++i) {
            elements[i].disabled = b;
        }
    }
    function trimRealm(r) {
        if (r.substring(0,1) == '@') {
            return r.substring(1);
        }
        return r;
    }
    function dec2hex (dec) {
        return ('0' + dec.toString(16)).substr(-2)
    }

    // generateId :: Integer -> String
    function generateId (len) {
        var arr = new Uint8Array((len || 40) / 2)
        window.crypto.getRandomValues(arr)
        return Array.from(arr, dec2hex).join('')
    }
    function runConnectionTests(data, realm, user, token, wherefrom) {
        dynamic_req = null;
        udp_req = null;
        var running = <?php echo '"<img style=\'vertical-align:middle\' src='."'../resources/images/icons/loading51.gif' width='24' height='24'/><i>"._('Running connectivity tests for this realm').'...</i>"'; ?>;
        var testresult = "<a target='_blank' href='show_realmcheck.php?norefresh=1&token=" + token + "'>" + <?php echo '"'._("New tests results are available, click to see").'"'; ?> + '</a>';
        if (wherefrom == 'diag') {
            $('#tests_info_area').css('color', 'black');
            $('#tests_info_area').html(running);
        } 
        if (wherefrom == 'show') {
            $('#run_tests').hide();
            $('#test_area').html(running);
        }
        console.log(wherefrom);
        udp_req = run_udp(realm, user, token);
        if (data.totest && data.totest.length > 0) {
            dynamic_req = run_dynamic(realm, data.totest, token);
        }
        static_ready = 0;
        dynamic_ready = 0;
        if (udp_req) {
            global_level_stat = 0;
            udp_req.forEach(function(req) {
                req.done(function( data ) {
                    if (wherefrom == 'diag') {
                        global_level_stat = Math.max(global_level_stat, data.result[0].level);
                        static_ready = static_ready + 1;
                        if (static_ready == udp_req.length) {
                            if (dynamic_req == null || (dynamic_req && dynamic_ready == dynamic_req.length*2)) {
                                var level = global_level_stat;
                                if (dynamic_req) {
                                    level = Math.max(global_level_dyn, level);
                                }
                                show_tests_result(token, level);
                            }
                        }
                    }
                    if (wherefrom == 'show') {
                        static_ready = static_ready + 1;
                        if (static_ready == udp_req.length) {
                            if (dynamic_req == null || (dynamic_req && dynamic_ready == dynamic_req.length*2)) {
                                $('#test_area').html(testresult);
                            }
                        }
                    }   
                });
            });           
        }
        if (dynamic_req) {
            global_level_dyn = 0;
            dynamic_req.forEach(function(req) {
                req['capath'].done(function( msg ) {
                    if (wherefrom == 'diag') {
                        global_level_dyn = Math.max(global_level_dyn, msg.level);
                        dynamic_ready = dynamic_ready + 1;
                        var level = global_level_dyn;
                        if  (dynamic_ready == dynamic_req.length*2 && (static_ready == udp_req.length)) {
                            level = Math.max(global_level_stat, level);   
                            show_tests_result(token, level); 
                        }
                    }
                    if (wherefrom == 'show') {
                        dynamic_ready = dynamic_ready + 1;
                        if  (dynamic_ready == dynamic_req.length*2 && (static_ready == udp_req.length)) {   
                            $('#test_area').html(testresult);
                        }
                    }
                });
                req['clients'].done(function( msg ) {
                    if (wherefrom == 'diag') {
                        global_level_dyn = Math.max(global_level_dyn, msg.result);
                        dynamic_ready = dynamic_ready + 1;
                        var level = global_level_dyn;
                        if  (dynamic_ready == dynamic_req.length*2 && (static_ready == udp_req.length)) {
                            level = Math.max(global_level_stat, level);
                            show_tests_result(token, level); 
                        }
                    }
                    if (wherefrom == 'show') {
                        dynamic_ready = dynamic_ready + 1;
                        if  (dynamic_ready == dynamic_req.length*2 && (static_ready == udp_req.length)) {
                            $('#test_area').html(testresult);
                        }
                    }
                });
            });
        }
    }
    function runRealmCheck(realm, user, lang) {
        var token = generateId();
        $.ajax({
            url: "findRealm.php",
            data: {realm: realm, outeruser: user, lang: lang, addtest: 1, token: token},
            dataType: "json",
            success:function(data) {
                var realmFound = 0;
                if (data.status) {
                    realmFound = 1;
                }
                console.log("now run connection tests");
                runConnectionTests(data, realm, user, token, 'show');
            },
            error: function (error) {
                alert('Error');
            }
        });
           
    }
    
    function run_udp(realm, user, token) {
        var requests = Array();
    <?php
    foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $hostindex => $host) {;
        print "
        requests[$hostindex] = $.ajax({
                url: 'radius_tests.php', data: {test_type: 'udp', realm: realm, outer_user: user, src: $hostindex, lang: 'en', hostindex: '$hostindex', token: token}, dataType: 'json'});
        ";
    }
    ?>
        return requests;
    }
    function run_dynamic(realm, dyn, token) {
        var requests = Array();
        dyn.forEach(function(srv, index) { 
            requests[index] = Array();
            requests[index]['capath'] = $.ajax({url:'radius_tests.php', data:{test_type: 'capath', realm: realm, src: srv.host, lang: 'en', hostindex: index, expectedname: srv.name, token: token }, dataType: 'json'}); 
            requests[index]['clients'] = $.ajax({url:'radius_tests.php', data:{test_type: 'clients', realm: realm, src: srv.host, lang: 'en', hostindex: index, token: token }, dataType: 'json'});
        });
        return requests;
    }
    function show_tests_result(token, level) {
        $('#tests_info_area').html(global_info[level] + ': ' + "<a target='_blank' href='show_realmcheck.php?norefresh=1&token=" + token + "'>" + <?php echo '"'._("See details").'"'; ?> + '</a>');
        if (level > 0) {
            $('#tests_info_area').css('color', 'red');
            $('#tests_result').val('1');
        } else {
            $('#tests_info_area').css('color', 'black');
            $('#tests_result').val('0');
        }
        var info = global_info[level] + ': ' + "<a target='_blank' href='show_realmcheck.php?norefresh=1&token=" + token + "'>" + <?php echo '"'._("See details").'"'; ?> + '</a>';
        if (level == 0) {
            info = info + '<br>' + <?php echo "'"._("If you want to report your problem, fill fields bellow.")."'"; ?>;
        }
        $('#tests_info_area').html(info);
        
    }
    function activate_send () {
        if ($('#timestamp').val().length > 0  && $('#mac').val().length == 17 && $('#email').val().length > 0 && isEmail($('#email').val())) {
            if ($('#send_query_to_idp').length > 0) {
                $('#send_query_to_idp').removeClass('hidden_row').addClass('visible_row');
            } else {
                if ($('#opname').val().length > 0 && $('#outer_id').val().length > 0) {
                    if (isOperatorName($('#opname').val()) && isEmail($('#email').val(), true)  && $('#send_query_to_sp').length > 0) {
                        $('#send_query_to_sp').removeClass('hidden_row').addClass('visible_row');
                    }
                } else {
                    $('#send_query_to_sp').removeClass('visible_row').addClass('hidden_row');
                }
            }
        } else {
            if ($('#send_query_to_idp').length > 0) {
                $('#send_query_to_idp').removeClass('visible_row').addClass('hidden_row');
            }
            if ($('#send_query_to_sp').length > 0) {
                $('#send_query_to_sp').removeClass('visible_row').addClass('hidden_row');
            }
        }
    }
    function show_sp_block() {
        var t = $('input[name=problem_type]:checked').val();
        var par = '';
        if ($('#sp_abuse').html() === '') {
            $.get("adminQuery.php?type=sp&lang="+lang, function(data, status) {
                $('#sp_abuse').html(data);
                $('#sp_abuse').show();         
                if (t == 0) {
                    $('#sp_problem_selector').hide();
                } else {
                    $('#sp_problem_selector').show();
                }
                $('#idp_problem').html('');
                reset_footer();
            });   
        } else {
            if (t == 0) {
                $('#sp_problem_selector').hide();
            } else {
                $('#sp_problem_selector').show();
            }
        }
    }
    function clear_sp_question() {
        $('#sp_questions > tbody  > tr').each(function() {
            if ($(this).attr('class') == 'visible_row') {
                $(this).removeClass('visible_row').addClass('hidden_row');
            }
            if ($(this).attr('class') == 'error_row') {
                $(this).remove();
            }
            $(this).children('td').each(function() {
                $(this).children('input').each(function() {
                    if ($(this).prop('tagName').toLowerCase() === 'input' ||
                            $(this).prop('tagName').toLowerCase() === 'textarea') {
                        if ($(this).attr('id') !== 'admin_realm') {
                            $(this).val('');
                        }
                    }
                });
                $(this).children('textarea').each(function() {    
                    $(this).val('');
                });
            });
        });
    }
</script>
