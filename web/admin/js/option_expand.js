/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/* General function for doing HTTP XML GET requests. */

function getXML(attribute_class) {
    var client = new XMLHttpRequest();
    client.attribute_class = attribute_class;
    client.onreadystatechange = addOption;
    client.open("GET", "inc/option_xhr.inc.php?class=" + attribute_class + "&etype=XML");
    client.send();
}

function addOption(attribute_class) {
    if (this.readyState === 4 && this.status === 200) {
        var field = document.getElementById("expandable_" + this.attribute_class + "_options");
        var div = document.createElement('tbody');
        div.innerHTML = this.responseText;
        field.appendChild(div.firstChild);
    }
}

function processCredentials() {
    if (this.readyState === 4 && this.status === 200) {
        var field = document.getElementById("disposable_credential_container");
        field.innerHTML = this.responseText;
    }
}

function doCredentialCheck(form) {
    postXML(processCredentials, form);
}

function deleteOption(e, identifier) {
    var field = document.getElementById(identifier);
    if (e) {
        marks[e - 1].setOptions({visible: false});
    }
    field.parentNode.removeChild(field);
}
