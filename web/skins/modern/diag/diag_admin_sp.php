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
        <td><?php echo _("What is the realm of the IdP in question?"); ?></td>
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

<script>
    var mac = $('#mac');
    mac.on("keyup", formatMAC);
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

