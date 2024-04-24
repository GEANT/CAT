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

function row_filter(table) {
    var linked = table.find('[id^="unlinked_ck_"]').is(':checked');
    var broken_cert = table.find('[id^="brokencert_ck_"]').is(':checked');
    var or_warn = table.find('[id^="or_ck_"]').is(':checked');
    var profile_warn = table.find('[id^="profile_ck_"]').is(':checked');
    var input = table.find('[id^="qsearch_"]').val().toLowerCase();
    var tr_visible;
    var inp_found;
    table.children("tr.idp_tr").each(function() {
        tr_visible = true;
        if (linked && $(this).hasClass('linked')) {
            tr_visible = false;
        }
        if (tr_visible && broken_cert && $(this).hasClass('certok')) {
            tr_visible = false;
        }
        if (tr_visible && or_warn && $(this).hasClass('orok')) {
            tr_visible = false;
        }        
        if (tr_visible && profile_warn && $(this).hasClass('profileok')) {
            tr_visible = false;
        }         
        if (tr_visible && input !== '') {
            inp_found = $(this).find("span.inst_name:contains('"+input+"')").length;
            if (inp_found == 0) {
                tr_visible = false;
            }
        }
        
        if (tr_visible) {
            $(this).show();
        } else {
            $(this).hide();            
        }
    });
}

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
        var this_table = $(this).parent().parent().parent();
        row_filter(this_table);
    });

    // the linked filter checkbox handler
    $(":checkbox").on('click', function() {
        var this_table = $(this).parent().parent().parent();
        row_filter(this_table);
    });
    
    $("#fed_selection").on('change', function() {
        fed = $("#fed_selection option:selected").val();
        if (fed === "XX") {
            return;
        }
        $("#thirdrow").hide();
        document.location.href = "overview_federation.php?fed_id="+fed;
    });
    
    
    $("img.cat-icon").tooltip();

});


    