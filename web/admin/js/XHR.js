/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
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
