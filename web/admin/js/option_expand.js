/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/* General function for doing HTTP XML GET requests. */

function getXML( funct, URL ) {
    var client = new XMLHttpRequest();
    client.onreadystatechange = funct;
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

function addSupportOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_support_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addGeneralOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_general_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addUserOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_user_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addProfileOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_profile_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addEapServerOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_eap_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addMediaOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_media_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addFedOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_fed_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addDeviceOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_device-specific_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function addEapSpecificOption() {
    if( this.readyState === 4 && this.status === 200 ) {
        var field = document.getElementById("expandable_eap-specific_options");
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
    getXML(addSupportOption,"inc/option_xhr.inc.php?class=support")
}

function addDefaultGeneralOptions() {
    getXML(addGeneralOption,"inc/option_xhr.inc.php?class=general");
}

function addDefaultUserOptions() {
    getXML(addUserOption,"inc/option_xhr.inc.php?class=user");
}

function addDefaultProfileOptions() {
    getXML(addProfileOption,"inc/option_xhr.inc.php?class=profile");
}

function addDefaultEapServerOptions() {
    getXML(addEapServerOption,"inc/option_xhr.inc.php?class=eap");
}

function addDefaultMediaOptions() {
    getXML(addMediaOption,"inc/option_xhr.inc.php?class=media");
}

function addDefaultFedOptions() {
    getXML(addFedOption,"inc/option_xhr.inc.php?class=fed");
}

function addDeviceOptions() {
    getXML(addDeviceOption,"inc/option_xhr.inc.php?class=device-specific");
}

function addEapSpecificOptions() {
    getXML(addEapSpecificOption,"inc/option_xhr.inc.php?class=eap-specific");
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
