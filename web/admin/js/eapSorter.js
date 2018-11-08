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

$(function () {
    $("#sortable1, #sortable2").sortable({
        connectWith: "ol.eapmethods",
        tolerance: 'pointer',
        out: function (event, ui) {
            ui.item.toggleClass("eap1");
        },
        stop: function (event, ui) {
            $(".eapm").removeAttr('value');
            $(".eapmv").removeAttr('value');
            $("#sortable1").children().each(function (index) {
                var v = $(this).html();
                $("#EAP-" + v).val(v);
                $("#EAP-" + v + "-priority").val(index + 1);
            });
        }
    }).disableSelection();
});
