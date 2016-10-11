/* 
 * ******************************************************************************
 * *  Copyright 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */

function postXML(funct, form) {
    var client = new XMLHttpRequest();
    client.onreadystatechange = funct;
    client.open("POST", form.action);
    client.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    var form_values = "";
    for (var i = 0; i < form.elements.length; i++) {
        form_values = form_values + (form_values === "" ? "" : "&") + encodeURIComponent(form.elements[i].name) + "=" + encodeURIComponent(form.elements[i].value);
    }
    client.send(form_values);
}
