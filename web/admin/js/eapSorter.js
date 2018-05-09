/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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
