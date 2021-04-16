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
require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("diagnostics");
$loggerInstance = new \core\common\Logging();
$loggerInstance->debug(4, $_REQUEST);
$o = new stdClass();
if (isset($_REQUEST['data'])) {    
    $o = json_decode($_REQUEST['data']);
}
$sp_problem = array(
    'technical' => _("I suspect a Technical Problem with the IdP"),
    'abuse-copyright' => _("A user from this IdP has allegedly infringed copyrights"),
    'abuse-network' => _("A user from this IdP has conducted malicious network operations (spam, DDoS, ...)")
);
$idp_problem = array(
    'technical' => _("User claims connectivity problems but has been authenticated successfully"),
    'abuse-copyright' => _("User claims that mandatory open port is not open")
);
$queryType = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
$realmFromURL = filter_input(INPUT_GET, 'realm', FILTER_SANITIZE_STRING);
if (!$realmFromURL) {
    $realmFromURL = '';
}
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
<input type='hidden' name='token' id='token' value=''>
<input type='hidden' name='tests_result' id='tests_result' value=''>
<table id='sp_questions'>
    <tr id='sp_problem_selector'>
        <td>" . _("Select your problem") . "</td>
        <td>$select</td>
    </tr>
    <tr>
        <td>" . _("What is the realm of the IdP in question?") . "</td>
        <td>
                <input type='text' name='admin_realm' id='admin_realm' value='$realmFromURL'>
                <button class='diag_button' id='realm_in_db_admin' style='display: none;' accesskey='R' type='button'>" .
                _("Check this realm") .
                "</button>
                <div id='tests_info_area'></div>
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
    <tr>
        <td id='external_db_info'></td>
        <td></td>
    </tr>
    <tr class='hidden_row' id='send_query_to_idp'>
        <td>" . _("Now you can send your query") . "</td>
        <td><button type='submit' class='diag_button' id='submit_idp_query' name='go'>" . _("Send") . "</button></td>
    </tr>
 </table>";
    $res = $res . $javascript;
}
if ($queryType == 'idp') {
    $select = "<div id='idp_reported_problem' style='display:;'>
<select style='margin-left:0px;' id='select_idp_problem'>";
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
    <tr>
        <td>" . _("Identify the SP by one of following means") . "</td>
        <td></td>
    </tr>
    <tr id='by_opname'>
        <td>" . _("SP Operator-Name attribute") . "</td>
        <td><input type='text' id='opname' name='opname' value=''></td>
    </tr>
    <tr id='spmanually'>
        <td>" . _("Select the SP manually:") . "</td>
        <td>
            <div id='select_asp_country'><a href='' id='asp_countries_list'>
            <span id='opnameselect'>" . _("click to select country and organisation") . "</a></span>
            </div>
            <div id='select_asp_area'></div>
        </td>
    </tr>
    <tr id='asp_desc' style='display: none;'>
        <td>" . _("or") . ' ' . _("at least describe the SP location") . "</td>
        <td><input type='text' id='asp_location' name='asp_location' value=''></td>
    </tr>
    <tr>
        <td>" . _("What is the outer ID of the user session in question?") . "</td>
        <td><input type='text' id='outer_id' name='outer_id' value=''></td>
    </tr>
    <tr>
        <td>" . _("What is the authentication timestamp of the user session in question?") . "</td>
        <td>
            <input type='text' id='timestamp' name='timestamp'>
            <div id='datepicker'></div>
        </td>
    </tr>
    <tr>
        <td>" . _("What is the MAC address of the user session in question?") . "</td>
        <td><input type='text' id='mac' name='mac'></td>
    </tr>
    <tr>
        <td>" . _("Additional comments about the problem") . "</td>
        <td><textarea id='freetext' name='freetext' cols='60' rows='5'></textarea></td>
    </tr>
    <tr>
        <td>" . _("Do you have any contact details by which the user wishes to be contacted by the SP?") . "</td>
        <td><textarea id='c_details' name='c_details' cols='60' rows='5'></textarea></td>
    </tr>
    <tr>
        <td>" . _("Please specify an email address on which the SP can contact you") . "</td>
        <td><input type='text' id='email' name='email'></td>
    </tr>
    <tr class='hidden_row' id='send_query_to_sp'>
        <td>" . _("Now you can send your query") . "</td>
        <td><button type='submit' class='diag_button' id='submit_sp_query' name='go'>" . _("Send") . "</button></td>
    </tr>
</table>";
    $res = $res . $javascript;
}
if ($queryType == 'idp_send' || $queryType == 'sp_send') {
    include_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
    $cat = new \core\CAT();
    $returnArray = array();
    if (count((array) $o) > 0) {
        foreach ($o as $key => $value) {
            $value = trim($value);
            switch ($key) {
                case 'realm':
                    $pos = strpos($value, '@');
                    if ($pos !== FALSE ) {
                        $value = substr($value, $pos+1);
                    }
                case 'email':
                    $returnArray[$key] = filter_var($value, FILTER_VALIDATE_EMAIL);
                    break;
                case 'mac':
                case 'freetext':
                case 'timestamp':
                case 'opname':
                case 'outerid':
                case 'cdetails':
                case 'token':
                    // all of the above have to be printable strings, so sanitise them all in one go
                    $returnArray[$key] = filter_var($value, FILTER_SANITIZE_STRING);
                    break;
                case 'tests_result':     
                    $returnArray[$key] = filter_var($value, FILTER_VALIDATE_INT);
                    break;
                case 'idpcontact':
                    if ($value == '') {
                        $returnArray[$key] = 'mgw@umk.pl';
                    } else {
                        $returnArray[$key] = filter_var(base64_decode($value), FILTER_VALIDATE_EMAIL);
                    }
                    break;
                case 'reason':
                    if ($queryType == 'idp_send') {
                        $returnArray[$key] = $sp_problem[$value];
                    } else {
                        $returnArray[$key] = $idp_problem[$value];
                    }
                    break;
                default:
                    break;
            }
        }
    }
    if ($queryType == 'idp_send') {
        $mail = \core\common\OutsideComm::mailHandle();
        $emails = ['mgw@umk.pl'];
        //$emails = explode(',', $returnArray['idpcontact']);
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Notification System";
        foreach ($emails as $email) {
            $mail->addAddress($email);
        }
        $link = '';
        if (isset($_SERVER['HTTPS'])) {
            $link = 'https://';
        } else {
            $link = 'http://';
        }
        $link .= $_SERVER['SERVER_NAME'] . \core\CAT::getRootUrlPath() . '/diag/show_realmcheck.php?token=' . $returnArray['token'];
        $returnArray['testurl'] = $link;
        $mail->Subject = _('Suspected a technical problem with the IdP');
        $txt = _("We suspect a technical problem with the IdP handling the realm") . ' ' . 
                $returnArray['realm'] . ".\n";
        $txt .= _("The CAT diagnostic test was run for this realm during reporting.\n");
        $txt .= _("The overall result was ");
        if ($returnArray['tests_result'] == 0) {
            $txt .= _("success");
        } else {
            $txt .= _("failure");
        }
        $txt .= ".\n" . _("To see details go to ");
        $txt .= "$link\n\n";
        $txt .= _("The reported problem details are as follows") . "\n";
        $txt .= _("timestamp") . ": " . $returnArray['timestamp'] . "\n";
        $txt .= _("client MAC address") . ": " . $returnArray['mac'] . "\n";
        if ($returnArray['freetext']) {
            $txt .= _("additional comments") . ': ' . $returnArray['freetext'] . "\n";
        }
        $txt .= "\n" . _("You can contact the incident reporter at") . ' ' . $returnArray['email'];
        
        $mail->Body = $txt;
        $sent = $mail->send();
        if ($sent === FALSE) {
            $returnArray['emailsent'] = 0;
            $loggerInstance->debug(1, 'Mailing  failed');
        } else {
            $returnArray['emailsent'] = 1;
        }
    }
    $returnArray['status'] = 1;
    $res = json_encode($returnArray);
}
echo $res;
