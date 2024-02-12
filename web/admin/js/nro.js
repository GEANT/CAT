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

/* various jquery scripts for the NRO admin page */

$(document).ready(function() {
    // realm diagnostics
    $("#realmcheck").on('click', function() {
        event.preventDefault();
        document.location.href = '../diag/diag.php?admin=1&sp=1&realm=';
    });

    // this gets the maximum width of the Organisation column and then sets this to all
    // thanks to this the width does not change as we filter out some, possibly wide names
    var instTdWidth = 0;
    $("td.inst_td").each(function() {
        instTdWidth = Math.max(instTdWidth, $(this).width());
    });
    $("td.inst_td").width(instTdWidth);
    
    // show/hide download statistics part of the window
    $("button.stat-button").on('click', function() {
        var stat_downloads = $(this).siblings("table").find(".stat-downloads");
        if (stat_downloads.is(":visible")) {
            stat_downloads.hide();
            $(this).css('position', 'absolute');
            $(this).text(show_downloads);                
        } else {
            stat_downloads.show();
            $(this).css('position', 'static');
            $(this).text(hide_downloads);
        }
    });

    // handler for the text filter (must take into account possible filtering 
    // on linked status
    $('[id^="qsearch_"]').keyup(function() {
        var input = $(this).val().toLowerCase();
        var this_row = $(this).parent().parent();
        var this_table = this_row.parent();
        var this_ck = this_row.find('input[id^="unlinked_ck_"]');
        var tr;
        if (input === '') {
            if (this_ck.is(':checked')) {
                console.log("checked");
                this_table.children("tr.notlinked").show();
            } else {
                console.log("unchecked");
                this_table.children("tr.idp_tr").show();
            }
        } else {
            if (this_ck.is(':checked')) {
                this_table.children("tr.idp_tr").hide();
                this_table.find("span.inst_name:contains('"+input+"')").each(function() {
                    tr = $(this).parent().parent();
                    if (tr.hasClass("notlinked")) {
                       tr.show();
                   }
               });
            } else {
                this_table.children("tr.idp_tr").hide();
                this_table.find("span.inst_name:contains('"+input+"')").parent().parent().show();
            }

        }
    });

    // the linked filter checkbox handler
    $('[id^="unlinked_ck_"]').on('click', function() {
        var this_table = $(this).parent().parent().parent();
        if ($(this).is(':checked')) {
            this_table.children("tr.linked").hide();
        } else {
            this_table.children("tr.linked").show();
        }
    });
});


    