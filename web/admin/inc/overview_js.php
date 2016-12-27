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
var pressedButton;
var sendButton;
var cancelButton;
var adminMail;
var adminLabel;
var parkContainer;
var idp_id;

function invite_admin() {
pressedButton.removeClass('pressed');
idp_id = pressedButton.parent().parent().prev().children(':first').val();
pressedButton.hide();
adminMail.val("");
pressedButton.next().empty();
pressedButton.next().append(adminLabel);
pressedButton.next().append(adminMail);
pressedButton.next().append(sendButton);
pressedButton.next().append(cancelButton);
pressedButton.next().show();
}


$(document).ready(function(){
// create send button
sendButton = $("<button>");
    sendButton.text("Send");
    sendButton.click(function(event) {
    var msg;
    event.preventDefault();
    if($("#admin_mail").val() == "")
    alert('<?php echo _("No email address provided") ?>');
    else {
    var em = $("#admin_mail").val();
    parkContainer.append(adminLabel);
    parkContainer.append(adminMail);
    parkContainer.append(sendButton);
    parkContainer.append(cancelButton);
    pressedButton.show();
    $.post("inc/sendinvite.inc.php", 
    {inst: idp_id,  mailaddr: em},
    function(data) {
    j = $.parseJSON(data);
    if(j.status == 1)
    msg = '<?php echo _("Invitation sent to:") ?> '+em;
    else
    msg = '<?php echo _("The invitation email could not be sent!") ?>';
    pressedButton.next().append('<span style="padding-left:1em">'+msg+'</span>');
    }
    );
    }
    });
    // create parking container
    parkContainer = $("<div>");
        // create cancel button
        cancelButton =$("<button>");
            cancelButton.text("Cancel");
            cancelButton.click(function(event) {
            event.preventDefault();
            adminMail.val("");
            $(this).parent().prev().show();
            $(this).parent().hide("");
            parkContainer.append(adminLabel);
            parkContainer.append(adminMail);
            parkContainer.append(sendButton);
            parkContainer.append(cancelButton);
            });
            // create mail input field
            adminMail = $('<input type="text">');
            adminMail.attr("id","admin_mail");
            adminMail.attr("size","30");
            // create label text
            adminLabel = $('<span>');
                adminLabel.text('<?php echo _("Mail address to invite:") ?>');
                $(".start_invite").click(function(event) {
                event.preventDefault();
                $(this).addClass('pressed');
                pressedButton = $(this);
                setTimeout("invite_admin()", 200);
                });





                $("#new_idp").click(function(event) {
                event.preventDefault();
                if($("#mailaddr").val() == "")
                alert('<?php echo _("No email address provided") ?>');
                else {
                var em = $("#mailaddr").val();
                var c = $("#country option:selected").val();
                var idp_name = $("#idp_name").val();

                $.post("inc/sendinvite.inc.php", 
                {name: idp_name,  mailaddr: em, country: c },
                function(data) {
                j = $.parseJSON(data);
                if(j.status == 1)
                msg = '<?php echo _("Invitation sent to:") ?> '+em;
                else
                msg = '<?php echo _("The invitation email could not be sent!") ?>';
                alert(msg);
                }
                );
                }
                });



                });
