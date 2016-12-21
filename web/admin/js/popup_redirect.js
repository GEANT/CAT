/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
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
    msgbox.setAttribute("id", "msgbox");
    var div = document.createElement("div");
    var msg = document.createElement("div");
    if (type === "err") {
        msg.setAttribute("id", "errorbox");
    } else if (type === "warn") {
        msg.setAttribute("id", "warnbox");
    } else if (type === "info") {
        msg.setAttribute("class", "graybox");
    }
    var img = document.createElement("img");
    img.setAttribute("src", "../resources/images/icons/button_cancel.png");
    img.setAttribute("alt", "cancel");
    if (onclick) {
        img.setAttribute("onclick", "removeMsgbox(); " + onclick);
    } else {
        img.setAttribute("onclick", "removeMsgbox()");
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

function createWindow() {
    if (this.readyState === 4 && this.status === 200) {
        var infoBox;
        infoBox = createMsgbox("info");
        infoBox.innerHTML += this.responseText;
        centerElement(infoBox);
    }
}