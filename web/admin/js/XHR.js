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

function postXML(funct, form) {
    var client = new XMLHttpRequest();
    client.onreadystatechange = funct;
    client.open("POST", form.action);
    client.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    var form_values = "";
    var elementlength = form.elements.length;
    for (var i = 0; i < elementlength; i++) {
        form_values = form_values + (form_values === "" ? "" : "&") + encodeURIComponent(form.elements[i].name) + "=" + encodeURIComponent(form.elements[i].value);
    }
    client.send(form_values);
}
