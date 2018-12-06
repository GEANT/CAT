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
    pressedButton.next().append('<span style="padding-left:1em">'+msg+'<\/span>');
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
