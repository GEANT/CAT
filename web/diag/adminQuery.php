<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
    $sp_problem = array(
    'technical' =>  _("I suspect a Technical Problem with the IdP"),
    'abuse-copyright' => _("A user from this IdP has allegedly infringed copyrights"),
    'abuse-network' => _("A user from this IdP has conducted malicious network operations (spam, DDoS, ...)")
    );
    $idp_problem = array(
    'technical' =>  _("User claims connectivity problems but has been authenticated successfully"),
    'abuse-copyright' => _("User claims that mandatory open port is not open")   
    );
    $queryType = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
    $res = '';
    $javascript = "<script>
    var mac = $('#mac');
    mac.on('keyup', formatMAC);
    var now = new Date();
    var datefrom = new Date();
    datefrom.setMonth(datefrom.getMonth() - 3);
    $('#timestamp').datetimepicker({
        timeFormat: 'HH:mm z',
        controlType: 'select',
        minDateTime: datefrom,
        maxDateTime: now
    });
</script>
    ";
    if ($queryType == 'sp') {
        $select = "<div id='sp_abuse_problem'>
<select style='margin-left: 0px;' id='select_sp_problem'>";
        foreach ($sp_problem as $pname => $pdesc) {
            $select = $select . "<option value='$pname'>$pdesc</option>\n";
        }
        $select = $select . "</select></div>";
        $res = "
<table id='sp_questions'>
    <tr>
        <td>" . _("Select your problem") . "</td>
        <td>$select</td>
    </tr>
    <tr>
        <td>" . _("What is the realm of the IdP in question?") . "</td>
        <td>
                <input type='text' name='admin_realm' id='admin_realm' value=''>
                <button id='realm_in_db_admin' accesskey='R' type='button'>" .
                _("Check if this value is registered") .
                "</button>
        </td>
    </tr>
    <tr class='hidden_row'>
        <td>" . _("What is the authentication timestamp of the user session in question?") . "</td>
        <td><input type='text' id='timestamp' name='timestamp'>
            <div id='datepicker'></div>
        </td>
    </tr>
    <tr class='hidden_row'>
        <td>" . _("What is the MAC address of the user session in question?") . "</td>
        <td><input type='text' id='mac' name='mac'></td>
    </tr>
    <tr class='hidden_row'>
        <td>" . _("Additional comments") . "</td>
        <td><textarea id='freetext' name='freetext' cols='60' rows='5'></textarea></td>
    </tr>
    <tr class='hidden_row'>
        <td>" . _("Please specify an email address on which the IdP can contact you") . "</td>
        <td><input type='text' id='email' name='email'></td>
    </tr>
    <tr class='hidden_row' id='send_query_to_idp'>
        <td>" . _("Now you can send your query") . "</td>
        <td><button type='submit' id='submit_idp_query' name='go'>" . _("Send") . "</button></td>
    </tr>
 </table>";
        $res = $res . $javascript;
    }
    if ($queryType == 'idp') {
        $select = "<div id='idp_reported_problem' style='display:;'>
<select id='select_idp_problem'>";
        foreach ($idp_problem as $pname => $pdesc) {
            $select = $select . "<option value='$pname'>$pdesc</option>\n";
        }
        $select = $select . "</select></div>";
        $res = "
<table id='idp_questions'>
    <tr>
        <td>" . _("Select your problem") . "</td>
        <td>$select</td>
    </tr>
</table>";
        $res = $res . $javascript;
    }
    if ($queryType == 'idp_send') {
        require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
        $languageInstance = new \core\common\Language();
        $languageInstance->setTextDomain("web_user");
        $cat = new \core\CAT();
        $realm = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_STRING);
        $mac = filter_input(INPUT_GET, 'mac', FILTER_SANITIZE_STRING);
        $freetext = filter_input(INPUT_GET, 'freetext', FILTER_SANITIZE_STRING);
        $timestamp = filter_input(INPUT_GET, 'timestamp', FILTER_SANITIZE_STRING);
        $idpcontact = filter_input(INPUT_GET, 'idpcontact', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_GET, 'reason', FILTER_SANITIZE_STRING);
        $returnArray = array();
        $returnArray['realm'] = $realm;
        $returnArray['spcontact'] = $email;
        $returnArray['mac'] = $mac;
        $returnArray['description'] = $freetext;
        $returnArray['timestamp'] = $timestamp;
        $returnArray['idpcontact'] = base64_decode($idpcontact);
        $returnArray['reason'] = $sp_problem[$reason];
        $returnArray['status'] = 1;
        $res = json_encode($returnArray);
    }
    echo $res;
