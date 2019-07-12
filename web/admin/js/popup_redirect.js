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

/****************************************************************
 *                                                              *
 *                      Notification Handling                   *
 *                                                              *
 ****************************************************************/


/* Get Height of the Browser Window */

function getWindowHeight() {
    var windowHeight = 0;
    if (typeof (window.innerHeight) === 'number') {
        windowHeight = window.innerHeight;
    } else {
        if (document.documentElement && document.documentElement.clientHeight) {
            windowHeight = document.documentElement.clientHeight;
        } else {
            if (document.body && document.body.clientHeight) {
                windowHeight = document.body.clientHeight;
            }
        }
    }
    return windowHeight;
}


/* Center an element in the browser window */

function centerElement(el) {
    if (document.getElementById) {
        var windowHeight = getWindowHeight();
        if (windowHeight > 0) {
            var contentHeight = el.offsetHeight;
            if (windowHeight - contentHeight > 0) {
                el.parentNode.style.top = ((windowHeight / 2) - (contentHeight / 2)) + 'px';
            }
        }
    }
}


/* Display errors/warnings/infos in an overlay box, */

function createMsgbox(type, onclick) {
    var body = document.getElementsByTagName("body")[0];
    if (document.getElementById("overlay") === null) {
        var overlay = document.createElement("div");
        overlay.setAttribute("id", "overlay");
        body.appendChild(overlay);
    } else {
        body.removeChild(document.getElementById("msgbox"));
    }
    var msgbox = document.createElement("div");
    if (type === "qr") {
        msgbox.setAttribute("id", "qrbox")
    } else {
        msgbox.setAttribute("id", "msgbox");
    }
    var div = document.createElement("div");
    var msg = document.createElement("div");
    if (type === "err") {
        msg.setAttribute("id", "errorbox");
    } else if (type === "warn") {
        msg.setAttribute("id", "warnbox");
    } else if (type === "info") {
        msg.setAttribute("class", "graybox");
    } else if (type === "qr") {
        msg.setAttribute("class", "qrbox");
    }
    var img = document.createElement("img");
    img.setAttribute("src", "../resources/images/icons/button_cancel.png");
    img.setAttribute("alt", "cancel");
    if (onclick) {
        if (type === "qr") {
            img.setAttribute("onclick", "removeQRbox(); " + onclick);
        } else {
            img.setAttribute("onclick", "removeMsgbox(); " + onclick);
        }
    } else {
        if (type === "qr") {
            img.setAttribute("onclick", "removeQRbox()");
        } else {
            img.setAttribute("onclick", "removeMsgbox()");
        }
    }
    msg.appendChild(img);
    div.appendChild(msg);
    msgbox.appendChild(div);
    body.appendChild(msgbox);
    return msg;
}


function removeMsgbox() {
    var body = document.getElementsByTagName("body")[0];
    body.removeChild(document.getElementById("overlay"));
    body.removeChild(document.getElementById("msgbox"));
}

function removeQRbox() {
    var body = document.getElementsByTagName("body")[0];
    body.removeChild(document.getElementById("overlay"));
    body.removeChild(document.getElementById("qrbox"));
}

function addEvent(elem, type, eventHandle) {
    if (elem === null || elem === undefined) {
        return;
    }
    if (elem.addEventListener) {
        elem.addEventListener(type, eventHandle, false);
    } else if (elem.attachEvent) {
        elem.attachEvent("on" + type, eventHandle);
    }
}


function overlay_resize() {
    var el = document.getElementById("msgbox");
    if (!el || !el.firstChild || !el.firstChild.firstChild) {
        return;
    }
    centerElement(el.firstChild.firstChild);
}

addEvent(window, "resize", overlay_resize);

function popupRedirectWindow(form) {
    postXML(createWindow, form);
}

function popupStatsWindow(form) {
    postXML(createWindow, form);
}

function popupQRWindow(form) {
    postXML(createQRWindow, form);
}

function createWindow() {
    if (this.readyState === 4 && this.status === 200) {
        var infoBox;
        infoBox = createMsgbox("info");
        infoBox.innerHTML += this.responseText;
        centerElement(infoBox);
    }
}

function createQRWindow() {
    if (this.readyState === 4 && this.status === 200) {
        var qrBox;
        qrBox = createMsgbox("qr");
        qrBox.innerHTML += this.responseText;
        centerElement(qrBox);
    }
}