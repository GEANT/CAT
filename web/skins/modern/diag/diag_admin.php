<?php
$sp_problem = array(
    'technical' =>  _("I suspect a Technical Problem with the IdP"),
    'abuse-copyright' => _("A user from this IdP has allegedly infringed copyrights"),
    'abuse-network' => _("A user from this IdP has conducted malicious network operations (spam, DDoS, ...)")
);
echo '<h3>' . _("Which problem are you reporting?") . '</h3>';
echo '<input type="radio" name="problem_type" value="1">';
echo _("SP contacting IdP due to technical problems or abuse") . '<br>';
echo '<input type="radio" name="problem_type" value="2">';
echo _("IdP contacting SP due to technical problems");
?>
<div id='idp_contact_area'>
</div>
<style>
    .error_row td {
        color: red;
    }
    .hidden_row {
        visibility:collapse;
    }
    .visible_row {
        visibility:visible;
    }
    .error_input {
        border:1px solid red;
    }
</style>

<script>
    function formatMAC(e) {
        var r = /([a-f0-9]{2})([a-f0-9]{2})/i,
        str = e.target.value.replace(/[^a-f0-9]/ig, "");
        while (r.test(str)) {
            str = str.replace(r, '$1' + ':' + '$2');
        }
        e.target.value = str.slice(0, 17);
    };
    function isEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    };
    var mac = $('#mac');
    mac.on("keyup", formatMAC);
    var now = new Date();
    var datefrom = new Date();
    datefrom.setMonth(datefrom.getMonth() - 3);
    console.log(datefrom);
    console.log(now);
    $('#timestamp').datetimepicker({
        timeFormat: 'HH:mm z',
        controlType: 'select',
        minDateTime: datefrom,
        maxDateTime: now
    }); 
    $(document).on('click', '#realm_in_db_admin' , function() {
        var id = $(this).attr('id');
       
        realm = $("#admin_realm").val();
        $('#idp_contact_area').html('');
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
        var comment = <?php echo '"' . _("Running realm tests") . '..."';?>;
        inProgress(1, comment);
        $.ajax({
            url: "findRealm.php",
            data: {realm: realm, lang: lang},
            dataType: "json",
            success:function(data) {
                inProgress(0);
                var realmFound = 0;
                if (data.status) {
                    var realms = data.realmlist.split(',');
                    for (var i = 0; i < realms.length; i++) {
                        if (realms[i] === realm) {
                            realmFound = 1;
                            break;
                        }
                    };
                }
                if (realmFound) { 
                    $('#sp_questions > tbody  > tr').each(function() {
                        if ($(this).attr('class') == 'hidden_row' && $(this).attr('id') != 'send_query_to_idp') {
                            $(this).removeClass('hidden_row').addClass('visible_row');
                        }
                    });
                    $('#idp_contact_area').append('<input type="hidden" name="idp_contact" id="idp_contact" value="'+data.admins+'">');
                } else {
                    $('#sp_questions > tbody  > tr').each(function() {
                            if ($(this).attr('class') == 'visible_row') {
                                $(this).removeClass('visible_row').addClass('hidden_row');
                            }
                    });
                    $('#sp_questions > tbody').append('<tr class="error_row"><td>' + "Realm is not registered with the eduroam database:" +
                        '</td><td>' + realm + '</td></tr>');
                    $('#admin_realm').val('');
                }
            },
            error: function (error) {
                alert('ERROR!');
            }
        });
        return false;
    });
    $(document).on('click', '#submit_idp_query' , function() {
        realm = $('#admin_realm').val();
        email = $('#email').val();
        mac = $('#mac').val();
        timestamp = $('#timestamp').val();
        freetext = $('#freetext').val();
        idpcontact = $('#idp_contact').val();
        $.ajax({
            url: "sendQuery.php",
            data: {realm: realm, mac: mac, email: email, timestamp: timestamp, freetext: freetext, idpcontact: idpcontact,lang: lang},
            dataType: "json",
            success:function(data) {
                if (data.status === 1) {
                    var result = '';
                    var title = <?php echo '"' . _("eduroam admin report submission") . '"';?>;
                    result = '<div class="padding">';
                    result = result + '<h3>'+ <?php echo '"' . _("SP contacting IdP due to technical problems or abuse") . '"'; ?> + '</h3>';
                    result = result + '<table>';
                    result = result + '<tr><td>' + <?php echo '"' . _("SP email") . '"';?> +'</td><td>' + data.spcontact + '</td></tr>';
                    result = result + '<tr><td>' + <?php echo '"' . _("IdP email(s)") . '"';?> +'</td><td>' + data.idpcontact + '</td></tr>';
                    result = result + '<tr><td>' + <?php echo '"'._("Event's timestamp").'"';?> +'</td><td>' + data.timestamp + '</td></tr>';
                    result = result + '<tr><td>' + <?php echo '"'._("Suspected MAC address").'"';?> + '</td><td>' + data.mac + '</td></tr>';
                    result = result + '<tr><td>' + <?php echo '"'._("Additional description").'"';?> +'</td><td>' + data.description + '</td></tr>';
                    result = result + '</div>';
                    showInfo(result, title);
                }
            },
            error: function (error) {
                alert('ERROR!');
            }
        });
        return false;
    });
    $(document).on('blur', '#timestamp, #mac, #email' , function() {
         $(this).val($.trim($(this).val()));
         if ($('#mac').val().length > 0) {
            if ($('#mac').val().length != 17) {
                $('#mac').addClass('error_input');
                $('#mac').attr('title', <?php echo '"' . _("MAC address is incomplete") . '"';?>);
            } else {
                $('#mac').removeClass('error_input'); 
                $('#mac').attr('title', '');
            }
         } 
         if ($(this).attr('id') == 'email' &&  $(this).val().length > 0) {
            if (!isEmail($(this).val())) {
                $('#email').addClass('error_input');
                $('#email').attr('title', <?php echo '"' . _("Wrong format of email") . '"';?>);
            } else {
                $('#email').removeClass('error_input');
                $('#email').attr('title', '');
            }
         }
         if ($('#timestamp').val().length > 0  && $('#mac').val().length == 17 && $('#email').val().length > 0 && isEmail($('#email').val())) {
             $('#send_query_to_idp').removeClass('hidden_row').addClass('visible_row');
         } else {
             $('#send_query_to_idp').removeClass('visible_row').addClass('hidden_row');
         }
    });
    $('input[name="problem_type"]').click(function() {  
        var t = $('input[name=problem_type]:checked').val();
        if (t == 1) {
            $('#sp_abuse').show();
            $('#idp_problem').hide();
        } else {
            $('#sp_abuse').hide();
            $('#idp_problem').show();
        }
    });
    $('#email').bind('keypress', function(e)  {
        if(e.keyCode == 13) {
            if ($('#timestamp').val().length > 0  && $('#mac').val().length == 17 && $('#email').val().length > 0 && isEmail($('#email').val())) {
                $('#send_query_to_idp').removeClass('hidden_row').addClass('visible_row');
            } else {
                $('#send_query_to_idp').removeClass('visible_row').addClass('hidden_row');
            }
            return false;
        }
    }); 
    $('#answer_yes, #answer_no').click(function(e) {
        e.preventDefault();
        alert('answer');
    });
</script>

<div id='sp_abuse' style='display: none;'>
    <div id='sp_abuse_problem' style='display:none;'>
    <select name="select_problem">
        <?php 
        foreach ($sp_problem as $pname => $pdesc) {
            echo "<option value='$pname'>$pdesc</option>\n";
        }
        ?>
    </select>
    </div>
    <table id='sp_questions'>
        <tr>
            <td><?php echo _("What is the realm of the IdP in question?");?></td>
            <td>
                <input type='text' name='admin_realm' id='admin_realm' value=''>
                <button id='realm_in_db_admin' accesskey="C" type='button'>
                    <?php echo _("Check if this value is registered"); ?>
                </button>
            </td>
        </tr>
        <tr class='hidden_row'>
            <td><?php echo _("What is the authentication timestamp of the user session in question?"); ?></td>
            <td><input type='text' id='timestamp' name='timestamp'>
            <div id="datepicker"></div>
            </td>
        </tr>
        <tr class='hidden_row'>
            <td><?php echo _("What is the MAC address of the user session in question?"); ?></td>
            <td><input type='text' id='mac' name='mac'></td>
        </tr>
        <tr class='hidden_row'>
            <td><?php echo _("Additional comments"); ?></td>
            <td><textarea id='freetext' name='freetext' cols='60' rows='5'></textarea></td>
        </tr>
        <tr class='hidden_row'>
            <td><?php echo _("Please specify an email address on which the IdP can contact you"); ?></td>
            <td><input type='text' id='email' name='email'></td>
        </tr>
        <tr class='hidden_row' id='send_query_to_idp'>
            <td><?php echo _("Now you can send your query"); ?></td>
            <td><button type='submit' id='submit_idp_query' name='go'><?php echo _("Send"); ?></button></td>
        </tr>
        </table>
</div>
<div id='idp_problem' style='display: none;'>    
</div>

