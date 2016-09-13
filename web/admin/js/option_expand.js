/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/* General function for doing HTTP XML GET requests. */

function getXML(URL, attribute_class ) {
    var client = new XMLHttpRequest();
    client.attribute_class = attribute_class;
    client.onreadystatechange = addOption;
    client.open( "GET", URL+"&etype=XML" );
    client.send();
}

function postXML( funct, form ) {
    var client = new XMLHttpRequest();
    client.onreadystatechange = funct;
    client.open( "POST", form.action );
    client.setRequestHeader( "Content-Type", "application/x-www-form-urlencoded" );
    var form_values = "";
    for (var i = 0; i<form.elements.length; i++) {
        form_values = form_values + (form_values === "" ? "" : "&") + encodeURIComponent(form.elements[i].name) + "=" + encodeURIComponent(form.elements[i].value);
    }
    client.send( form_values );
}

function addOption(attribute_class) {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_"+this.attribute_class+"_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function processCredentials() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("disposable_credential_container");
        field.innerHTML = this.responseText;
    }
}

function addDefaultSupportOptions() {
    getXML("inc/option_xhr.inc.php?class=support", "support")
}

function addDefaultGeneralOptions() {
    getXML("inc/option_xhr.inc.php?class=general", "general");
}

function addDefaultUserOptions() {
    getXML("inc/option_xhr.inc.php?class=user", "user");
}

function addDefaultProfileOptions() {
    getXML("inc/option_xhr.inc.php?class=profile", "profile");
}

function addDefaultEapServerOptions() {
    getXML("inc/option_xhr.inc.php?class=eap", "eap");
}

function addDefaultMediaOptions() {
    getXML("inc/option_xhr.inc.php?class=media", "media");
}

function addDefaultFedOptions() {
    getXML("inc/option_xhr.inc.php?class=fed", "fed");
}

function addDeviceOptions() {
    getXML("inc/option_xhr.inc.php?class=device-specific", "device-specific");
}

function addEapSpecificOptions() {
    getXML("inc/option_xhr.inc.php?class=eap-specific", "eap-specific");
}

function doCredentialCheck(form) {
    postXML(processCredentials, form);
}

function deleteOption(e,identifier) {
    var field = document.getElementById(identifier);
           if(e) {
             marks[e - 1].setOptions({visible: false});
        }
        field.parentNode.removeChild(field);
}
