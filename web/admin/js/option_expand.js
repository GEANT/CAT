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

/* General function for doing HTTP XML GET requests. */

$(function () {
    $(".newoption").on("click", getXML);
    $("button.deleteOption").on("click", deleteOption);
});

function getXML(event) {
    event.preventDefault();
    fedid = $("#fedid").val();
    filedset = $(this).parents("fieldset").eq(0);
    attribute_class = $(this).parents("fieldset").eq(0).attr("name");
    if (attribute_class === 'device-specific') {
        device = $("#optionvalue").val();
        inp = {class: attribute_class, fedid: fedid, etype: "XML", device: device};
    } else {
        inp = {class: attribute_class, fedid: fedid, etype: "XML"};
    }

    $.ajax({
        url: "inc/option_xhr.inc.php",
        method: "GET",
        dataType: "html",
        data: inp,
        statusCode: {
            200: function(data) { 
                tbody = $("#expandable_"+attribute_class+"_options tbody");
                if (tbody.length === 0) {
                    $("#expandable_"+attribute_class+"_options").append("<tbody></tbody>");
                }
                $("#expandable_"+attribute_class+"_options tbody").append(data);
                newSelect = $("#expandable_"+attribute_class+"_options tbody").children().last();
                hideOptionsAlreadySet(newSelect, attribute_class);
                $("select.MMM").on('change', function() {
                    showInputElement($('option:selected',this));
                });
                $("button.deleteOption").on("click", deleteOption);
            }
        }
    });
}

function hideOptionsAlreadySet(element, attribute_class) {
    set_options = [];
    element.find("option").show();
    element.find("option[id|='option']").show();
    element.find("option[id|='option']").prop('selected', false);
    i=0;
    $("#expandable_"+attribute_class+"_options input[id|=option]").each(function() {
        v = $(this).val();
        s = v.match(/^([^#]+)#.*0$/);
        if (s !== null) {
            set_options[s[1]] = 1;
        }
    });
   
    element.find("option[id|='option']").each(function() {
        v = $(this).val();
        s = v.match(/^([^#]+)#/);    
        if (set_options[s[1]] === 1) {
            $(this).hide();
        } else {
            if (i === 0) {
                $(this).prop('selected', true);
                i = 1;
                showInputElement($(this));
            }
        }
    });
}

function showInputElement(element) {
    id=element.attr('id');
    m = id.match(/^option-S(\d+)-/);    
    rowid=m[1];
    v=element.val();
    s = v.match(/^([^#]+)#([^#]+)#([^#]*)#/);
    optionId = s[1];
    dataType=s[2];
    ml = s[3];
    $("[id|='S"+rowid+"-input']").hide();
    $("#S"+rowid+"-input-"+dataType).show();
    if (ml === 'ML') {
        $("#S"+rowid+"-input-langselect").show();
    }
}

function deleteOption() {
    tr=$(this).parents("tr").eq(0);
    v=tr.find("input").eq(0).val();
    s=v.match(/^([^#]+)#/);
    tr.remove();
    if (s !== null) {
        $("option[id$='"+s[1]+"']").show();
    }
}

function processCredentials() {
    if (this.readyState === 4 && this.status === 200) {
        var field = document.getElementById("disposable_credential_container");
        field.innerHTML = this.responseText;
    }
}

function MapGoogleDeleteCoord(e) {
    marks[e - 1].setOptions({visible: false});
}

