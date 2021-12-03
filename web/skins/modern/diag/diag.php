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

$Gui->defaultPagePrelude();
$_SESSION['current_page'] = $_SERVER['SCRIPT_NAME'];
?>
<!-- JQuery -->
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery.js"); ?>"></script>
<script type="text/javascript" src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery-ui.js"); ?>"></script>
<script type='text/javascript' src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/Timepicker/jquery-ui-timepicker-addon.js"); ?>"></script>
<script type='text/javascript' src="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/Timepicker/jquery-ui-sliderAccess.js"); ?>"></script>
<link type="text/css"  rel="stylesheet" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/jquery-ui-1.12.1.custom/jquery-ui.css"); ?>" media="all" />
<link type="text/css"  rel="stylesheet" href="<?php echo $Gui->skinObject->findResourceUrl("EXTERNAL", "jquery/Timepicker/jquery-ui-timepicker-addon.css"); ?>"  media="all" />
<script type="text/javascript">
    var recognisedOS = '';
    var downloadMessage;
    var recognisedOShs20 = 0;
    var noDisco = 1;
    var sbPage = 1;
    var lang = "<?php echo($Gui->languageInstance->getLang()) ?>";
    var dir = "<?php echo dirname(__DIR__); ?>";
    
<?php
$profile_list_size = 1;

$divs = new \web\skins\modern\Divs($Gui);
$visibility = 'index';
$operatingSystem = $Gui->detectOS();
$Gui->loggerInstance->debug(4, $operatingSystem);
$uiElements = new web\lib\admin\UIElements();
if ($operatingSystem) {
    print "recognisedOS = '" . $operatingSystem['device'] . "';\n";
}
require dirname(__DIR__) . '/user/js/cat_js.php';

?>
</script>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS", "cat-user.css"); ?>" />
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS", "diag.css", "diag"); ?>" />
</head>
<body>
<div id='wrap' style='background-image:url("<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "beta.png"); ?>");'>
<form id="cat_form" name="cat_form" accept-charset="UTF-8" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="POST">
<?php
require dirname(__DIR__) . '/diag/js/diag_js.php';
echo $divs->divHeading($visibility);
$Gui->languageInstance->setTextDomain("diagnostics");
?>
<div id="main_page">
    <div id="loading_ico">
          <span id='load_comment'></span><br><img src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/loading51.gif"); ?>" alt="Loading stuff ..."/>
    </div>
    <div id="info_overlay"> <!-- device info -->
        <div id="info_window"></div>
        <img id="info_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/button_cancel.png"); ?>" ALT="Close"/>
    </div>
    <div id="main_menu_info" style="display:none"> <!-- stuff triggered form main menu -->
          <img id="main_menu_close" class="close_button" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "icons/button_cancel.png"); ?>" ALT="Close"/>
          <div id="main_menu_content"></div>
    </div>
    <div id="main_body">
        <div id="user_page">
            <?php echo $divs->divPagetitle(_("Diagnostics site") . " (<span style='color:red'>beta</span>)", ""); ?>
            <div id="user_info" style='padding-top: 10px;'>
            <div id='diagnostic_choice'>
                <?php echo _("The diagnostics system will do its best to identify and resolve your problems!") . ' ' . _("Please help the system by answering the questions as precisely as possible.") . "<br/>" . _("Are you a") . ' '; ?>
                <input type='radio' name='diagnostic_usertype' value='0'><?php echo _("end-user") . ' ' . _("or"); ?>   
                <input type='radio' name='diagnostic_usertype' value='1' <?php if ($admin == 1) { echo " checked"; } ?> > <?php echo _("eduroam administrator") .'?'; ?>
            </div>
            <div id='diagnostic_enduser' style='display: none;'>
                <h2><?php echo _("Tools for End Users"); ?></h2>
                <p>
                <?php 
                    echo _("To resolve your problem a real-time diagnostics for your realm must be performed.");
                ?>
                </p>
                <?php
                    echo '<div id="before_stage_1"><h3>' . _("The system needs some information on your home institution - issuer of your account") . '</h3>';
                    echo _("What is the realm part of your user account (the part behind the @ of 'your.username@<strong>realm.tld</strong>):");
                ?>
                <input type='text' name='user_realm' id='user_realm' value=''>
                <?php
                    echo '<div id="realm_by_select"><br/>' . _("alternatively") . '<br/>';
                    echo _("You can select your home institution from the following list") . '<br/>';
                    echo '<div id="select_idp_country"><a href="" id="idp_countries_list">';    
                    echo '<span id="realmselect">' . _("Click to select your country/region and organisation") . '</span></a></div>';
                ?>
                <div id="select_idp_area" style="display:none;">
                </div>
                </div>
                <div id="position_info">
                    <?php
                        echo '<h3>' . _("Optionally, to improve tests, you can provide information on your current location") . '</h3>';
                        echo '<div id="select_sp_country"><a href="" id="sp_countries_list">';    
                        echo '<span id="spselect">' . _("Click to select a location in which you have an eduroam problem") . '</span></a></div>';
                    ?>
                    <div id="select_sp_area" style="display:none;">
                    </div>
                </div>
                </div>
                <div id="after_stage_1" style="display:none;">
                    <h3><?php echo _("Testing realm")." "; ?><span id="realm_name"></span></h3>
                    <?php echo _("First stage completed."); ?>
                    <br>
                </div>
                <div id="sociopath_query_area" style="margin-top:20px; display:none;">
                    <strong>
                        <?php echo _("To narrow down the problem, please answer the following few questions."); ?>
                    </strong>
                    <div id="sociopath_queries"></div>
                </div>
                <div id="start_test_area" style="padding-top: 10px; padding-bottom: 5px; display:none; text-align:center;">
                    <button id='realmtest' accesskey="T" class='diag_button' type='button'><?php echo _("Run tests"); ?>
                    </button>
                </div>
            </div>
            <div id='diagnostic_admin' style='display: <?php if (!$admin) { echo 'none'; } ?> ;'>
                <h2><?php echo _("Tools for eduroam admins"); ?></h2>
                <?php
                    echo '<input type="hidden" id="isadmin" value="';
                    if ($isauth) {
                        echo "1\">";
                        echo "<div id='admin_test_area' style='display: ";
                        if (!$admin) {
                            echo 'none';
                        }
                        echo ";'>";
                        echo '<h3>' . _("What is the nature of the problem?") . '</h3>';
                        echo '<input type="radio" name="problem_type" value="0"' . (($sp==1)? ' checked' : '') . '>';
                        echo _("I suspect a technical problem at a particular IdP") . '<br>';
                        echo '<input type="radio" name="problem_type" value="1">';
                        echo _("I want to report abuse of network resources by a roaming user") . '<br>';
                        echo '<input type="radio" name="problem_type" value="2">';
                        echo _("I suspect a technical problem for one of my users at a particular SP");
                        echo "<div id='idp_contact_area'></div>";
                        echo "<div id='sp_abuse'></div>";
                        echo "<div id='idp_problem'></div>";
                        echo "</div>"; 
                    } else {
                        echo "0\">";
                        echo _("This service is for authenticated admins only.") . '<br>';
                        echo "<a href=\"diag.php?admin=1\">" .
                             _("eduroam® admin access is needed") . "</a>";
                    }
                ?>
            </div> 
            </div>
                <input type="hidden" name="lang" id="lang"/>
        </div>
    </div>
    </div>
   </form>
    <div id="vertical_fill">&nbsp;</div>
    <?php echo $divs->divFooter(); ?>
</div>

<script>

    $(document).ready(function () {
        if ($('input[name=problem_type]').prop('checked')) {
            show_sp_block();     
        }
    });
    $(document).keypress(
        function(event){
            if (event.which == '13') {
                event.preventDefault();
            }
    });
    $('input[name="diagnostic_usertype"]').click(function() {   
        var t = $('input[name=diagnostic_usertype]:checked').val();
        if (t > 0) {
            $('#diagnostic_enduser').hide();
            $('input[name=problem_type]').each(function() {
                $(this).prop('checked', false);
            });
            $('#user_realm').val('');
            $('#select_idp_area').html('');
            $('#select_idp_country').show();
            $('#select_sp_area').html('');
            $('#sociopath_queries').show();
            $('#diagnostic_admin').show();
            if ($('#isadmin').val() === "1") {
                $('#admin_test_area').show();
            }
        } else {
            $('#diagnostic_admin').hide();
            $('#idp_contact_area').html('');
            $('#sp_abuse').html('');
            $('#idp_problem').html('');
            $('#diagnostic_enduser').show();
        }
        reset_footer();
    });
    $('#user_realm').bind('change keyup blur input', function(e)  {
        if (isDomain($('#user_realm').val())) {
            $('#start_test_area').show();
        } else {
            $('#start_test_area').hide();
            $('#select_idp_area').hide();
            $('#select_idp_area').html('');
            $('#select_idp_country').show();
        }
    });
    $('#idp_countries_list').click(function(event){
        event.preventDefault();
        $('#start_test_area').hide();
        $('#user_realm').val("");
        countrySelection("idp");
        return false;
    });
    $(document).on('click', '#sp_countries_list, #asp_countries_list' , function(event) {
        event.preventDefault();
        var t = $(this).attr('id').substring(0, $(this).attr('id').indexOf('_'));
        countrySelection(t);
        return false;
    });
    $(document).on('change', '#idp_country' , function() {
        var comment = <?php echo '"' . _("Fetching institutions list") . '..."'; ?>;  
        var id = $(this).attr('id');
        var k = id.indexOf('_');
        var type = id.substr(0,k);
        co=$('#'+type+'_country').val();
        if (co !== "") {
            inProgress(1, comment);
            $.ajax({
                url: "findRealm.php",
                data: {type: 'inst', co: co, lang: lang},
                dataType: "json",
                success:function(data) {
                    if (data.status === 1) {
                        inProgress(0);
                        var institutions = data.institutions;
                        var shtml = '';
                        var select = '';
                        if (type !== 'asp') {
                            shtml = <?php echo '"<td>' . _("Select institution:") . '</td><td>"'; ?>;
                        }
                        select = '<select id="' + type + '_inst" name="' + type + '_inst" style="margin-left:0px; width:400px;"><option value=""></option>';
                        for (var i in institutions) {
                            select = select + '<option value="' + institutions[i].ID + '">' + institutions[i].name + '</option>';
                        }
                        select = select + '</select>';
                        if (type !== 'asp') {
                            shtml = shtml + select + '</td>';
                            $('#row_' + type + '_institution').html('');
                            $('#row_' + type + '_institution').append(shtml);
                            $('#row_' + type + '_realm').html('');
                            $('#row_' + type + '_institution').css('visibility', 'visible');
                        } else {
                            $('#inst_' + type + '_area').html(select);
                            $('#' + type + '_desc').show();
                        }    
                        reset_footer();
                    } else {
                        if (data.status === 0) {
                            inProgress(0);
                            var msg = <?php echo '"' . _("The database does not contain the information needed to help you in realm selection for this country. You have to provide the realm you are interested in.") . '"'; ?>;
                            alert(msg);
                            $('#select_idp_country').show();
                            $('#select_idp_area').hide();
                        }
                    }
                },
                error:function() {
                    inProgress(0);
                    var msg = <?php echo '"' . _("Can not search in database. You have to provide the realm you are interested in.") . '"'; ?>;
                    alert(msg);
                    $('#select_idp_country').show();
                    $('#select_idp_area').hide();
                }
            }); 
        } else {
            $('#' + type + '_inst').remove();
            $('#row_' + type + '_institution').css('visibility', 'collapse');
            $('#start_test_area').hide();
            $('#row_idp_realm').html("");
        }
        return false;
    });
    $(document).on('change', '#select_sp_problem' , function() {
        if ($('#select_sp_problem').val().substr(0,6) == 'abuse-') {
            $('#realm_in_db_admin').text(<?php echo '"' . _("Check if this realm is registered") . '"'; ?>);
        } else {
            $('#realm_in_db_admin').text(<?php echo '"' . _("Check this realm") . '"'; ?>);
        }
        $('#realm_in_db_admin').hide();
        $('#admin_realm').val('');
        $('#tests_info_area').html('');
        clear_sp_question();
    });
    $(document).on('change', '#sp_country, #asp_country' , function() {
        var comment = <?php echo '"' . _("Fetching institutions list") . '..."'; ?>;  
        var id = $(this).attr('id');
        var k = id.indexOf('_');
        var type = id.substr(0,k);
        co=$('#'+type+'_country').val();
        if (co !== "") {
            inProgress(1, comment);
            $.ajax({
                url: "findRealm.php",
                data: {type: 'hotspot', co: co, lang: lang},
                dataType: "json",
                success:function(data) {
                    if (data.status === 1) {
                        inProgress(0);
                        var hotspots = data.hotspots;
                        var shtml = '';
                        var select = '';
                        if (type !== 'asp') {
                            shtml = <?php echo '"<td>' . _("Select institution:") . '</td><td>"'; ?>;
                        }
                        select = '<select id="' + type + '_inst" name="' + type + '_inst" style="margin-left:0px; width:400px;"><option value=""></option>';
                        for (var i in hotspots) {
                            select = select + '<option value="' + hotspots[i].ID + '">' + hotspots[i].name + '</option>';
                        }
                        select = select + '</select>';
                        if (type !== 'asp') {
                            shtml = shtml + select + '</td>';
                            $('#row_' + type + '_institution').html('');
                            $('#row_' + type + '_institution').append(shtml);
                            $('#row_' + type + '_realm').html('');
                            $('#row_' + type + '_institution').css('visibility', 'visible');
                        } else {
                            $('#inst_' + type + '_area').html(select);
                            $('#' + type + '_desc').show();
                        }    
                        reset_footer();
                    } else {
                        if (data.status === 0) {
                            inProgress(0);
                            var select = '<select id="' + type + '_inst" name="' + type + '_inst" style="margin-left:0px; width:400px;"><option value="">';
                            var shtml = '<td></td><td>';
                            select = select + <?php echo '"' . _("Other location") . '"'; ?> + '</option></select></td>';
                            if (type !== 'asp') {
                                $('#row_' + type + '_institution').html('');
                                $('#row_' + type + '_institution').append(shtml + select);
                                $('#row_' + type + '_realm').html('');
                                $('#row_' + type + '_institution').css('visibility', 'visible');
                            } else {
                                $('#inst_' + type + '_area').html(select);
                                $('#' + type + '_desc').show();
                            }
                            reset_footer();
                        }
                    }
                },
                error:function() {
                    inProgress(0);
                    reset_footer();
                }
            }); 
        } else {
            $('#' + type + '_inst').remove();
            $('#row_' + type + '_institution').css('visibility', 'collapse');
            $('#start_test_area').hide();
            $('#row_idp_realm').html("");
        }
        return false;
    });
    $(document).on('change', '#idp_inst' , function() {
        inst=$("#idp_inst").val();
        if (inst === '') {
            $('#row_idp_realm').html("");
            $('#start_test_area').hide();
            return false;
        }
        var comment = <?php echo '"' . _("Fetching realms list") . '..."'; ?>;
        inProgress(1, comment);
        $.ajax({
            url: "findRealm.php",
            data: {type: 'realm', ou: inst, lang: lang},
            dataType: "json",
            success:function(data) {
                inProgress(0);
                if (data.status === 1) {
                    var realms = data.realms;
                    var realmselect = '';
                    if (realms.length > 1) {
                        realmselect = <?php echo '"<td>' . _("Check realm(s):") . '</td>"'; ?>;
                        realmselect = realmselect + '<td>' + "<span style='margin-left: 10px'>";
                        for (var i in realms) {
                            realmselect = realmselect + '<input type="radio" name="realm" ';
                            realmselect = realmselect + 'value="' + realms[i] + '"';
                            if (i === "0") {
                                realmselect = realmselect + ' checked';
                            }
                            realmselect = realmselect + '><label>' + realms[i] + '</label>';
                        }
                        realmselect = realmselect + '</span></td>';
                    } else {
                        realmselect = <?php echo '"<td>' . _("Realm:") . '</td>"'; ?>;
                        realmselect = realmselect + '<td>' + "<span style='margin-left: 10px'>";
                        realmselect = realmselect + realms[0] + '</span>';
                        realmselect = realmselect + '<input type="hidden" name="realm" value="' + realms[0] + '">';
                        realmselect = realmselect + '</span></td>';
                    }
                    $('#row_idp_realm').html("");
                    $('#row_idp_realm').append(realmselect);
                    $('#start_test_area').show();
                    $("#user_realm").val("");
                    $("#realm_info+ok").hide();
                    reset_footer();
                }
            },
            error:function() {
                inProgress(0);
                alert('error');
            }
        }); 
        return false; 
    });
    $(document).on('click', '#realm_in_db, #realm_in_db_admin' , function() {
        var id = $(this).attr('id');
        var addtest = 0;
        var token = '';
        var user = '';
        var realm = '';
        if (id === 'realm_in_db') {
            $('#select_idp_area').hide();
            $('#select_idp_area').html('');
            $('#select_idp_country').show();
            realm = trimRealm($("#user_realm").val());
        } else {
            /* realm_in_db_admin click */
            realmfield = trimRealm($("#admin_realm").val());
            at = realmfield.indexOf("@");
            if (at >=0) {
                user = realmfield.substring(0, at);
                realm = realmfield.substring(at+1);
            } else {
                user = '';
                realm = realmfield;
            }
            if ($('#select_sp_problem').val() == 'technical') {
                token = generateId();
                addtest = 1;
            }
            $('#tests_info_area').html('');
            $('#idp_contact_area').html('');
            clear_sp_question();
            t = 1;
        }
        var comment = <?php echo '"' . _("Running realm tests") . '..."'; ?>;
        inProgress(1, comment);
        /*waiting(comment);*/
        $.ajax({
            url: "findRealm.php",
            data: {realm: realm, outeruser: user, lang: lang, addtest: addtest, token: token},
            dataType: "json",
            success:function(data) {
                inProgress(0);
                var realmFound = 0;
                if (data.status) {
                    realmFound = 1;
                }
                if ($('#token')) {
                    $('#token').val(token);
                }
                if (id === 'realm_in_db') {
                    $('#realm_info_ok').show();
                    $('#start_test_area').show();
                    $('#realm_info_fail').hide();
                } 
                if (id === 'realm_in_db_admin') {
                    if ($('#select_sp_problem').val() == 'technical' || realmFound == 1) {
                        $('#sp_questions > tbody  > tr').each(function() {
                            if ($(this).attr('class') == 'hidden_row' && $(this).attr('id') != 'send_query_to_idp') {
                                $(this).removeClass('hidden_row').addClass('visible_row');
                            }
                        });
      
                        $('#idp_contact_area').append('<input type="hidden" name="idp_contact" id="idp_contact" value="' + data.admins + '">');
                    }
                }
                if (addtest) {
                    runConnectionTests(data, realm, user, token, 'diag');
                }   
                if (realmFound == 0) {
                    $('#external_db_info').html('This realm is not registered with eduroam database, the report will be sent to eduroam OT group.');
                    $('#external_db_info').css('color', 'red');
                }
                
            },
            error: function (error) {
                alert('Error');
            }
        });
        return false;
    });
    $(document).on('click', '#answer_yes, #answer_no, #answer_noidea' , function(e) {
        e.preventDefault();
        var answer = 1; /* No */
        if ($(this).attr('id') === 'answer_yes') {
            answer = 2; /* Yes */
        }
        if ($(this).attr('id') === 'answer_noidea') {
            answer = 3; /* No idea */
        }
        testSociopath('', answer);
    });
    $('#realmtest').click(function(event){
        var comment = <?php echo '"<br><br>' . _("Running realm tests") . '..."'; ?>;
        inProgress(1, comment);
        $('#start_test_area').hide();
        if ($('#select_sp_area').is(':hidden')) {
            $('#position_info').hide();
        }
        if ($('#select_idp_area').is(':hidden')) {
            $('#realm_by_select').hide();
        }
        var realm = '';
        if ($('#user_realm').val()) {
            realm = trimRealm($('#user_realm').val());
        }
        if ($('#idp_inst').val()) {
            if ($('input[name="realm"]').attr('type') === 'hidden') {
                realm = $('input[name="realm"]').val();
            } else {
                $('input[name="realm"]').each(function() {
                    if ($(this).is(':checked')) {
                        realm = $(this).val();
                    } 
                });
            }
        }
        var nro = 0;
        if ($('#sp_country').val()) {
            nro = $('#sp_country').val();
        }
        var visited = 0;
        if ($('#sp_inst').val()) {
            visited = $('#sp_inst').val();
        }
        reset_footer();
        if (realm !== '') {
            $.ajax({
                url: "magicTelepath.php",
                data: {realm: realm, lang: lang, nro: nro, visited: visited},
                dataType: "json",
                success:function(data) {
                    inProgress(0);
                    if (data.status === 1) {
                        var realm =  data.realm;
                        $('#before_stage_1').hide();
                        $('#realm_name').text(realm);
                        $('#after_stage_1').show();
                        reset_footer();
                        testSociopath(realm, 0);
                    } else {
                        var title = <?php echo '"' . _("Diagnostics results for selected realms") . '"'; ?>;
                        result = '<div class="padding"><h3>' + <?php echo '"' . _("An unknown problem occured") . '"'; ?>;
                        result = result + '</h3>'
                        if (r.length == 1) {
                            result = result + <?php echo '"' . _("This test includes checking of the following realm") . '"'; ?>;
                        } else {    
                            result = result + <?php echo '"' . _("This test includes checking of the following realms") . '"'; ?>;
                        }
                        result = result + ': '
                        for (var i=0; i < r.length; i++) {
                            if (i > 0) {
                                result = result + ', ';
                            }
                            result = result + r[i];
                        }
                        result = result + '.<br>';
                        result = result + <?php echo '"' . _("You should report this to") . '"'; ?> + ' <a href="mailto:admin@eduroam.pl">admin@eduroam.pl</a>';
                        result = result + '</div>';
                        $('#after_stage_1').hide();
                        $('#before_stage_1').show();
                        $('#realm_by_select').show();
                        $('#position_info').show();
                        reset_footer();
                        showInfo(result, title);
                    }  
                },
                error: function (error) {
                    inProgress(0);
                    if ($('#select_sp_area').is(':hidden')) {
                        $('#position_info').show();
                    }
                    if ($('#select_idp_area').is(':hidden')) {
                        $('#realm_by_select').show();
                    }
                    $('#user_realm').val("");
                    reset_footer();
                    alert('magicTelepath error');
                }
            });
        }
    });
    $(document).on('click', 'ul#ul-menu-list.tab-links a', function(e)  {
        e.preventDefault();
        /*var activeEl = $('.tab-links > li.active > a > active');*/
        var activeTab_a = $('.tab-links > li.active > a').attr('href');
        $('.tab-links > li.active').removeClass('active');
        var currentTab = $(this).attr('href');
        $(activeTab_a).removeClass('active');
        $(currentTab).addClass('active');
        $(this).parent().addClass('active');
    });
    $(document).on('click', '#submit_idp_query, #submit_sp_query' , function() {
        var type;
        var o = new Object();
        if ($(this).attr('id') === 'submit_idp_query') {
            o['realm'] = $('#admin_realm').val();
            o['email'] = $('#email').val();
            o['mac'] = $('#mac').val();
            o['reason'] = $('#select_sp_problem').val();
            o['timestamp'] = $('#timestamp').val();
            o['freetext'] = $('#freetext').val();
            o['idpcontact'] = $('#idp_contact').val();
            o['token'] = $('#token').val();
            o['tests_result'] = $('#tests_result').val();
            type = 'idp_send';
        } else {
            o['opname'] = $('#opname').val();
            o['outerid'] = $('#outer_id').val();
            o['email'] = $('#email').val();
            o['mac'] = $('#mac').val();
            o['reason'] = $('#select_idp_problem').val();
            o['timestamp'] = $('#timestamp').val();
            o['freetext'] = $('#freetext').val();
            o['cdetails'] = $('#c_details').val();
            type = 'sp_send';
        }
        $.ajax({
            url: "adminQuery.php",
            data: {type: type, data: JSON.stringify(o)},
            dataType: "json",
            lang: lang,
            success:function(data) {
                if (data.status === 1) {
                    var result = '';
                    var title = <?php echo '"' . _("eduroam admin report submission") . '"'; ?>;
                    result = '<div class="padding">';
                    if (type == 'idp_send') {
                        result = result + '<h3>'+ <?php echo '"' . _("SP contacting IdP due to technical problems or abuse") . '"'; ?> + '</h3>';
                        result = result + '<table>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Reason") . '"'; ?> + '</td><td>' + data.reason + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Event's timestamp") . '"'; ?> + '</td><td>' + data.timestamp + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Calling-Station-Id") . '"'; ?> + '</td><td>' + data.mac + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Additional description") . '"'; ?> +'</td><td>' + data.freetext + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("An email to contact the reporter") . '"'; ?> + '</td><td>' + data.email + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Test result") . '" '; ?> +'</td><td>';
                        if (data.tests_result == 1) {
                            result = result + <?php echo '"' . _("failure") . '"'; ?>;
                        } else {
                            result = result + <?php echo '"' . _("success") . '"'; ?>;
                        }
                        result = result + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Test URL") . '" '; ?> +'</td><td><a href="' + data.testurl + '">' + data.testurl + '</a></td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Email to IdP administrators ") . '"'; ?> + ' ';
                        if (data.emailsent == 1) {
                            result = result + <?php echo '"' . _("send successfully") . '"'; ?>;
                        } else {
                            result = result + <?php echo '"' . _("not sent - failure while sending") . '"'; ?>;
                        }
                        result = result + '</td><td></td></tr>';
                    }
                    if (type == 'sp_send') {
                        result = result + '<h3>'+ <?php echo '"' . _("IdP contacting SP due to technical problems or abuse") . '"'; ?> + '</h3>';
                        result = result + '<table>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Reason") . '"'; ?> + '</td><td>' + data.reason + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("SP's Operator-Name") . '"'; ?> + '</td><td>' + data.opname + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("User's outer ID") . '"'; ?> + '</td><td>' + data.outerid + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("IdP email") . '"'; ?> + '</td><td>' + data.email + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Event's timestamp") . '"'; ?> + '</td><td>' + data.timestamp + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Calling-Station-Id") . '"'; ?> + '</td><td>' + data.mac + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("Additional description") . '"'; ?> +'</td><td>' + data.freetext + '</td></tr>';
                        result = result + '<tr><td>' + <?php echo '"' . _("How to contact the user") . '"'; ?> +'</td><td>' + data.cdetails + '</td></tr>';
                    }
                    result = result + '</div>';
                    showInfo(result, title);
                }
            },
            error: function (error) {
                alert('adminQuery error');
            }
        });
        return false;
    });
    $(document).on('keyup change', '#admin_realm' , function() {
            if ($('#admin_realm').val().length == 0 || (!isEmail($(this).val(), true) && !isDomain($(this).val()))) {
                $('#admin_realm').addClass('error_input');
                $('#realm_in_db_admin').hide();
                $('#tests_info_area').html('');
                $('#tests_info_area').css('color', 'black');
                $('#external_db_info').html('');
                $('#external_db_info').css('color', 'black');
                $('#sp_questions > tbody  > tr').each(function() {
                    if ($(this).attr('class') == 'visible_row') {
                        $(this).removeClass('visible_row').addClass('hidden_row');
                    }
                });
                $('#admin_realm').attr('title', <?php echo '"' . _("Wrong format of realm") . '"'; ?>);
            } else {
                $('#admin_realm').removeClass('error_input');
                $('#admin_realm').attr('title', '');
                $('#realm_in_db_admin').show();
            }
    });
    $(document).on('keyup change', '#email' , function() {
        if ($(this).val().length == 0 || !isEmail($(this).val())) {
            $('#email').addClass('error_input');
            $('#email').attr('title', <?php echo '"' . _("Wrong format of email") . '"'; ?>);
        } else {
            $('#email').removeClass('error_input');
            $('#email').attr('title', '');
            activate_send();
        }
    });
    $(document).on('blur', '#timestamp, #mac, #opname, #outer_id' , function() {
        $(this).val($.trim($(this).val()));
        
        if ($('#mac').val().length > 0) {
            if ($('#mac').val().length != 17) {
                $('#mac').addClass('error_input');
                $('#mac').attr('title', <?php echo '"' . _("MAC address is incomplete") . '"'; ?>);
            } else {
                $('#mac').removeClass('error_input'); 
                $('#mac').attr('title', '');
            }
        } 
        if ($(this).attr('id') == 'email' &&  $(this).val().length > 0) {
            if (!isEmail($(this).val())) {
                $('#email').addClass('error_input');
                $('#email').attr('title', <?php echo '"' . _("Wrong format of email") . '"'; ?>);
            } else {
                $('#email').removeClass('error_input');
                $('#email').attr('title', '');
            }
        }
        if ($(this).attr('id') == 'outer_id' &&  $(this).val().length > 0) {
            if (!isEmail($(this).val(), true)) {
                $('#outer_id').addClass('error_input');
                $('#outer_id').attr('title', <?php echo '"' . _("Wrong format of outer ID") . '"'; ?>);
            } else {
                $('#outer_id').removeClass('error_input');
                $('#outer_id').attr('title', '');
            }
        }
        if ($(this).attr('id') == 'opname' && $('#opname').val().length > 0) {
            if (!isOperatorName($(this).val())) {
                $('#opname').addClass('error_input');
                $('#opname').attr('title', <?php echo '"' . _("Wrong string given as OperatorName") . '"'; ?>);
                $('#spmanually').show();
            } else {
                $('#opname').removeClass('error_input');
                $('#opname').attr('title', '');
                $('#spmanually').hide();
            }
        }
        activate_send();
    });
    $('input[name="problem_type"]').click(function() {  
        var t = $('input[name=problem_type]:checked').val();
        if (t <= 1) {
            $('#sp_abuse').html('');
            /* show SP problem block */
            show_sp_block();
        } else {
            /* show IdP problem block */
            $('#sp_abuse').html('');
            if ($('#idp_problem').html() === '') {
                $.get("adminQuery.php?type=idp&lang="+lang, function(data, status) {
                    $('#idp_problem').html(data);
                    $('#sp_abuse').hide();
                    $('#idp_problem').show();
                    reset_footer();
                });
            }
            
        }
    });
    $(document).on('change', '#asp_inst' , function() {
        if ($('#asp_inst').val()) {
            $('#by_opname').hide();
            $('#opname').val('');
            $('#asp_desc').val('');
            $('#asp_desc').hide();
        } else {
            $('#by_opname').show();
            $('#asp_desc').show();
        }
    });
    $(document).on('keypress', '#email', function(e)  {
        if (e.keyCode == 13) {
            if ($('#timestamp').val().length > 0  && $('#mac').val().length == 17 && $('#email').val().length > 0 && isEmail($('#email').val())) {
                $('#send_query_to_idp').removeClass('hidden_row').addClass('visible_row');
            } else {
                $('#send_query_to_idp').removeClass('visible_row').addClass('hidden_row');
            }
            return false;
        }
    });
    $(document).on('keypress', '#opname', function(e)  {
        if (e.keyCode == 13 || e.keyCode == 9) {
            if ($('#opname').val() !== '') {
                $('#spmanually').hide();
            } else {
                $('#spmanually').show();
            }
        }
    });
    $('#answer_yes, #answer_no').click(function(e) {
        e.preventDefault();
    });
    
</script>

</body>
