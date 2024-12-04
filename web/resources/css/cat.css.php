<?php
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

require dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";
$colour1 = \config\Master::APPEARANCE['colour1'];
$colour2 = \config\Master::APPEARANCE['colour2'];
// we need to know if we are serving a RTL language so we can flip some heading
// items
$langInstance = new core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";
header('Content-type: text/css; charset=utf-8');
?>
html {
    height: 100%;
}

body {
    background: <?php echo $colour1;?>;
    color: #000000;
    font-family:Verdana, Arial, Helvetica, sans-serif;
    font-size:11px;
    height: 100%;
    margin: 0px;
    padding: 0px;
    padding-<?php echo $start;?>: 0px;
    min-width: 700px;
    font-size: 11px;
    font-weight: normal;

}

caption {
    display: none;
}

th.wai-invisible {
    display: none;
}

button {
    background: <?php echo $colour2;?>; 
    color: #FFFFFF; 
    min-height: 23px;
    border-<?php echo $start;?>-style: outset; 
    border-<?php echo $start;?>-width: 1px; 
    border-<?php echo $start;?>-color: #8bbacb;
    border-top-style: outset; 
    border-top-width: 1px; 
    border-top-color: #8bbacb;
    border-<?php echo $end;?>-style: outset; 
    border-<?php echo $end;?>-width: 2px; 
    border-<?php echo $end;?>-color: #043d52;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #043d52;
}

button.pressed {
    background:#095e80;
    border-style:inset;
    position: relative;
    <?php echo $start;?>: 3px;
}

button.pressedDisabled {
    background:#999;
    border-style:inset;
    position: relative;
    <?php echo $start;?>: 3px;
}

button.delete {
    background: maroon;
}

.problemdescription {
    padding-<?php echo $start;?>:40px;
    padding-top: 10px;
    padding-bottom: 10px;
    background-color: lightyellow;
}

.problemsolution {
    padding-<?php echo $start;?>:40px;
    padding-top: 10px;
    padding-bottom: 10px;
    background-color: lightgreen;
}

.use_borders button.alertButton {
    color: maroon; 
    background: #bbb; 
    border-<?php echo $start;?>-style: outset; 
    border-<?php echo $start;?>-width: 1px; 
    border-<?php echo $start;?>-color: #eee;
    border-top-style: outset; 
    border-top-width: 1px; 
    border-top-color: #eee;
    border-<?php echo $end;?>-style: outset; 
    border-<?php echo $end;?>-width: 2px; 
    border-<?php echo $end;?>-color: #444;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #444;
}


button[disabled] {
    background: #bababa;
    color: #6a6a6a;
    border-<?php echo $start;?>-style: inset;
    border-<?php echo $start;?>-width: 1px;
    border-<?php echo $start;?>-color: #dadada;
    border-top-style: inset;
    border-top-width: 1px;
    border-top-color: #dadada;
    border-<?php echo $end;?>-style: outset;
    border-<?php echo $end;?>-width: 2px;
    border-<?php echo $end;?>-color: #dadada;
    border-bottom-style: outset;
    border-bottom-width: 2px;
    border-bottom-color: #dadada;
    cursor: default;
}

button.download {
    font-size: 20px;
    min-height: 27px;
}

button.redirect {
    font-size: 10px;
}

div.infobox {
    background: #f0f0f0;
    display: inline-block;
    position: relative;
    border: 1px solid #dddddd;
    margin: 5px;
    padding: 5px;
    max-width: 49%;
    min-width: 20em;
    min-height: 150px;
    vertical-align: top;
}

div.profilemodulebuttons {
    position: inherit;
    bottom: 5px;
    <?php echo $end;?>: 5px;
    text-align: <?php echo $end;?>;
}

div.profilebox {
    background: #f0f0f0;
    display: block;
    position: relative;
    border: 1px solid #dddddd;
    margin: 5px;
    margin-bottom: 15px;
    padding: 5px;
    max-width: 49%;
    min-width: 40em;
    min-height: 150px;
    vertical-align: top;
}

div.consortium_logo {
    display: block;
    position: absolute;
    top:0;
    <?php echo $end;?>:0;
    padding-<?php echo $end;?>:20px;
    padding-top:7px;
}

div.sidebar {
    display: inline;
    float: <?php echo $end;?>;
    padding-<?php echo $end;?>: 20px;
}
div.sidebar a {
    color: white;
}

div.header {
    height: 54px;
    background: #FFFFFF;
    padding-<?php echo $start;?>:30px;
    padding-bottom: 10px;
    color: <?php echo $colour2?>;
}

div.pagecontent {
    position: absolute;
    top: 54px;
    bottom: 50px;
    padding-top: 10px;
    padding-<?php echo $start;?>: 0px;
    padding-<?php echo $end;?>: 0px;
    width:100%;
}

div.pagecontent div.trick {
    height: 100%;
    overflow: auto;
}

#secondrow {
    background:#1d4a74;
    color: #FFFFFF;
    min-height:100px;
    overflow: auto;
    padding-<?php echo $start;?>:20px
}

#thirdrow {
    padding-<?php echo $start;?>: 10px;
    padding-<?php echo $end;?>: 10px;
}

#footer {
    width: 100%;
    <?php echo $start;?>: 0;
    <?php echo $end;?>: 0;
    bottom: 0;
    position: absolute;
    background: white;
    border-top: 1px solid #000;
    direction: ltr;
}


#footer table {
    width: 100%;
    direction: ltr;
}


#footer table td {
    padding-left:10px; 
    padding-right:10px; 
    vertical-align:top;
    direction: ltr;
}

div.maincontent {
    height: 100% !important;
    position: relative;
    min-width: 1000px;
    margin-<?php echo $start;?>: auto;
    margin-<?php echo $end;?>: auto;
}

div.device_info {
    background: #f0f0f0;
    border: 1px solid #dddddd;
    margin: 5px;
    padding: 5px;
    padding-bottom: 22px;
    vertical-align: top;
    width: 350px;
    font-weight: normal;
    font-style: normal;
    display: none;
}

div#overlay {
    position: fixed;
    top: 0;
    bottom: 0;
    <?php echo $start;?>: 0;
    <?php echo $end;?>: 0;
    background-color: #000000;
    opacity: 0.5;
    z-index: 90;
}

div.graybox {
    background-color: #fbfcfc;
    border: 1px solid #e6e6e6;
    clear: both;
    display: block;
    padding: 15px;
    text-align: start;
    width: 850px;
    margin: 0px auto 10px;
}

div.qrbox {
    position: absolute;
    background-color: #fbfcfc;
    border: 1px solid #e6e6e6;
    clear: both;
    display: block;
    padding: 15px;
    text-align: start;
    width: 850px;
    <?php echo $start;?>: 100px;
    top: 50px;
    z-index: 100;
}

div#msgbox {
    position: absolute;
    top: 0;
    bottom: 0;
    <?php echo $start;?>: 0;
    <?php echo $end;?>: 0;
    z-index: 100;
}

div#msgbox div {
    position: fixed;
    <?php echo $start;?>: 0;
    <?php echo $end;?>: 0;
}

div#msgbox div div.graybox {
    width: 850px;
    min-height: 50px;
    max-height: 80%;
    overflow: auto;
    margin: 0px auto;
}

div.graybox img {
    display: block;
    cursor: pointer;
    float: <?php echo $end;?>;
    margin: 0px 0px 10px 10px;
}

img.icon {
    float: <?php echo $start;?>;
    margin-<?php echo $end;?>: 5px;
    margin-top: 3px;
}

fieldset.option_container {
    display: inline-block;
    position: relative;
    margin: 5px;
    padding: 5px;
    min-width: 500px;
    min-height: 150px;
    max-width: 90%;
    vertical-align: top;
}

fieldset.option_container_map {
    position: relative;
    margin: 5px;
    padding: 5px;
    min-width: 500px;
    min-height: 150px;
    max-width: 90%;
    vertical-align: top;
}

div.googlemap {
    min-width: 300px;
    max-width: 500px;
    min-height: 300px;
}

div.locationmap {
    width: 90%;
    min-width: 300px;
    min-height: 200px;
}

div.infobox div.locationmap {
    width: 100%;
}

#location-prompt {
    display: none;
    font-weight: bold;
    color: #1d4a74;
}

div.sub_h {
    padding-top: 15px;
}

div.acceptable {
    color: green;
    display: inline;
}

.notacceptable {
    color: red;
    display: inline;
}

div.ca-summary {
    border: 1px dotted;
    background-color: #ccccff;
    border-<?php echo $start;?>: 10px solid;
    border-<?php echo $start;?>-color: green;
    padding: 2px;
    padding-<?php echo $start;?>: 8px;
}

span.edu_cat {
    font-weight: bold;
    color: <?php echo $colour2;?>;
}

span.tooltip {
    font-size: 9px;
    text-decoration: underline;
}
div.infobox td {
    vertical-align: top;
}

table.compatmatrix {
    border-spacing: 2px;
}

table.compatmatrix td {
    vertical-align: top;
}

table.compatmatrix th {
    text-align: start;
}

table.compatmatrix td.compat_incomplete {
    background-color: gray;
    text-align: center;
}

table.compatmatrix td.compat_default {
    background-color: #3fb75e;
    text-align: start;
    white-space:nowrap;
}

table.compatmatrix td.compat_secondary {
    background-color: #00a8ff;
    text-align: center;
}

table.compatmatrix td.compat_unsupported {
    background-color: #f15151;
    text-align: center;
}

table.compatmatrix td.compat_redirected {
    background-color: khaki;
    text-align: start;
    white-space:nowrap;
}

table.authrecord tr.auth-success {
    color: green;
}

table.authrecord tr.auth-fail {
    color: maroon;
}

table.authrecord td {
    padding-<?php echo $end;?>: 10px;
}

p.MOTD {
    color: white;
    background-color: red;
    min-width:200px;
    max-width:400px;
    min-height: 30px;
    text-align: center;
    font-size: 14px;
}
.sub_h {
    font-weight: bold;
    font-style: italic;
}

.use_borders button {
    border-<?php echo $start;?>-style: outset; 
    border-<?php echo $start;?>-width: 1px; 
    border-<?php echo $start;?>-color: #8bbacb;
    border-top-style: outset; 
    border-top-width: 1px; 
    border-top-color: #8bbacb;
    border-<?php echo $end;?>-style: outset; 
    border-<?php echo $end;?>-width: 2px; 
    border-<?php echo $end;?>-color: #043d52;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #043d52;
}


.no_borders * {
    border-style: none;
    border-radius: 0px;
}

.no_borders .device_list td {
    padding-top: 2px;
    margin-top: 2px;
}


.no_borders #heading {
    border-top-style:solid; 
    border-bottom-style:solid;
    border-top-width:5px; 
    border-bottom-width:5px; 
    border-color: <?php echo $colour1;?>; 
    padding-<?php echo $start;?>:30px;
}

.no_borders button.disabledDevice {
    border-style: none;
    border-radius: 0px;
    color: #444; background: #aaaaaa;
}

.old_ie button.disabledDevice {
    border-style: none;
    color: #444; 
    background: #aaaaaa;
}

input {
    margin-<?php echo $end;?>: 5px;

}

select {
    vertical-align: middle;
    margin-<?php echo $start;?>: 10px;
}

td.notapplicable {
    background-color: gray;
}

.device_list .vendor {
    background: #aaa; 
    padding: 4px;
    border-style: solid; 
    border-width: 0px; 
    border-color: #fff;
    border-bottom-width:1px;
    border-top-width:1px;
    width: 50px;
    text-align: center;
    vertical-align: middle;
}

td.vendor {
    width: 50px;
    text-align: center;
    vertical-align: middle;
}

td.vendor img {
    height: 40px;
}


.signin_large {
    vertical-align: top;
    padding-<?php echo $start;?>:20px;
    padding-<?php echo $end;?>:20px;
    color: #bfd5dc;
    font-size: 20px;
}

#motd {
   color: maroon;
   padding:0px;
   padding-top:2px;
   height: 10px;
}

#close_button {
    background: <?php echo $colour2;?>; 
    color: #FFFFFF; 
    height: 23px;
    border-<?php echo $start;?>-style: inset; 
    border-<?php echo $start;?>-width: 1px; 
    border-<?php echo $start;?>-color: #8bbacb;
    border-top-style: inset; 
    border-top-width: 1px; 
    border-top-color: #8bbacb;
    border-<?php echo $end;?>-style: outset; 
    border-<?php echo $end;?>-width: 2px; 
    border-<?php echo $end;?>-color: #043d52;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #043d52;
    padding-<?php echo $start;?>: 5px;
    padding-<?php echo $end;?>: 5px;
    padding-top: 1px;
    padding-bottom: 1px;
    position: relative;
    <?php echo $start;?>: 640px;
    cursor:pointer;
}

#loading_ico {
    display: none;
    position: absolute;
    <?php echo $start;?>: 200px;
    top: 220px;
    z-index: 200;
    text-align: center;
}

#info_overlay {
    display: none;
    background: #f0f0f0;
    padding: 20px;
    padding-bottom: 10px;
    z-index: 100;
    position: absolute;
    width: 700px;
    <?php echo $start;?>: 200px;
    text-align: justify;
    top: 200px;
    box-shadow: 5px 5px 5px #666666;
    border: 1px solid #dddddd;
}

#user_info {
    padding-<?php echo $start;?>: 30px; 
    font-size: 11px;  
    font-weight: normal; 
}

#user_welcome {
    background: #ffffff;
    padding-<?php echo $start;?>: 30px; 
    padding-top: 20px; 
    padding-<?php echo $end;?>: 180px;
    font-size: 12px;  
    font-weight: normal; 
}

#devices {
    z-index:90;
    padding-<?php echo $start;?>: 30px;
    font-size: 11px;  
    font-weight: normal;
    position: relative;
}

#profile_list {
    width: 30em; 
    padding-<?php echo $start;?>: 10px; 
    padding-<?php echo $end;?>: 0px; 
    background: <?php echo $colour2;?>; 
    color: white; 
    box-shadow: 10px 10px 5px #888888;
}

#profile_redirect {
    padding-<?php echo $start;?>: 30px;
    padding-top: 20px;
    font-size: 11px;
    font-weight: normal;
    max-width:500px;
    width: 80%;
    display: none;
}

#profiles {
    padding-<?php echo $start;?>: 30px; 
    font-size: 11px; 
    padding-bottom: 10px 
}

#signin {
    padding-<?php echo $start;?>: 30px;
    padding-top: 10px;
}


.device_list {
    list-style-type: none;
}

.device_list button {
    width: 270px; 
    font-size: 11px;  
    font-weight: normal; 
    font-style: normal;
}

.device_list td {
    vertical-align:middle;
}

#institution_name {
    font-size: 14px; 
    padding-top: 4px; 
    padding-bottom: 12px; 
    padding-<?php echo $start;?>: 30px; 
    background: <?php echo $colour1;?>; 
    text-align: start; 
    text-shadow: 10px 10px 5px #888888;
}

#heading {
    border-top-style:solid;
    border-bottom-style:solid;
    border-top-width:5px;
    border-bottom-width:5px;
    border-color: <?php echo $colour1;?>;
    padding-<?php echo $start;?>:30px;
    color: <?php echo $colour2;?>;
}

#heading h1 { 
    font-size: 18px; 
    font-weight: bold;
}

#heading h2 { 
    font-size: 16px; 
    font-weight: bold;
}

#welcome {
    padding: 20px;
    padding-<?php echo $start;?>: 30px;
    text-align: justify;
    border-bottom-style:solid;
    border-bottom-width:5px;
    border-color: <?php echo $colour1;?>;
    font-size: 11px;
    font-weight: normal;
}


#main_menu_info { 
                    position: relative;
                    top: 15px;
                    <?php echo $start;?>: 0px;
                    padding:10px; padding-<?php echo $start;?>:20px; padding-<?php echo $end;?>:20px;
                    background: #f0f0f0;
                    border: 1px solid #dddddd;
                    margin-<?php echo $start;?>: 25px;
                    padding-<?php echo $start;?>: 25px;
                    margin-<?php echo $end;?>: 25px;
                    padding-<?php echo $end;?>: 25px;
                    padding-bottom: 10px;
                    vertical-align: top;
                    box-shadow: 5px 5px 5px #666666;
                    text-align: justify
}

#main_menu_content h1 {
    font-size: 16px; 
}

#main_menu_content dt {
    font-size: 12px; 
    font-weight: bold;
    padding-bottom: 3px;
}
#main_menu_content dd {
    padding-bottom: 10px;
}

#main_menu_content dd p {
    margin-top: 5px;
}

#main_body {
    background: #ffffff;
}


#faq {
    padding: 20px;
    color: <?php echo $colour2;?>;
    background: #ffffff;
}

#lang_select {
    width:100%;
}

#lang_select td {
    font-size: 11px;
    font-weight: normal;
    height:3em;
    vertical-align: bottom;
}

#idp_logo {
   display:none;
   position:absolute;
   <?php echo $end;?>:30px;
   max-height:150px;
   max-width:150px;
   padding-top:10px;
}

#profile_desc {
   display:none;
    background: #f0f0f0;
    position: relative;
    border: 1px solid #dddddd;
    margin: 5px;
    padding: 5px;
    max-width: 49%;
    min-width: 30em;
    min-height: 25px;
    vertical-align: top;
    box-shadow: 5px 5px 5px #666666;
}

#slides img {
    position: absolute;
    top: 145px;
    <?php echo $end;?>: 60px;
}

#slides span {
    position: absolute;
    <?php echo $start;?>: 180px;
    z-index: 20;
}

#line1 {
    top:145px;
    color: <?php echo $colour2;?>;
    font-size:20px;
}

#line2 {
    top:180px;
    font-size:40px;
    color: #fff;
}

#line3 {
    top:245px;
    <?php echo $start;?>: 200px;
    color: maroon;
    font-size:25px;
}

#line4 {
    top:305px;
    width: 400px;
    color: #fff;
    font-size:16px;

}

#line5 {
    top:345px;
    width: 400px;
    color: #fff;
    font-size:16px;
}

.img.img_roll {
    z-index: 90;
}

#front_page {
    width: 100%;
    height:100%;
    border-spacing:0; 
    border-collapse:collapse;
    padding-<?php echo $start;?>:200px;
    padding-top:10px;
}

#front_page_leftmenu {
    border-<?php echo $end;?>:solid; 
    border-color: <?php echo $colour1;?>; 
    border-width:5px; 
    min-height:400px; 
    padding-<?php echo $start;?>: 10px; 
    vertical-align:top; 
    width:110px; 
    padding-top:30px;
}

#front_page_advertarea {
    vertical-align: top;
    height:280px;
    background: #fff;
    padding-<?php echo $start;?>: 20px;
    padding-<?php echo $end;?>: 20px;
}

#user_button_td {
    text-align:center;
    vertical-align:top;
    vertical-align:top;
    padding-bottom: 20px;
}

a:link {
    color:<?php echo $colour2;?>;
}

a:visited {
    color:<?php echo $colour2;?>;
}

a:hover {
    color:#043d52;
}

a:active {
    color:<?php echo $colour2;?>;
}

.comment {
    width: 400px;
    margin: 10px;
}

a.morelink {
    text-decoration:none;
    outline: none;
    font-weight: bold;
    font-style: italic;
}

a.moreall {
    text-decoration:none;
    outline: none;
}

.morecontent span {
    display: none;
}

table.user_overview  {
    width: 90%;
}

table.user_overview th {
    text-align: start;  
    background: #f0f0f0;
    padding-<?php echo $start;?>: 4px;
    padding-<?php echo $end;?>: 4px;
}

table.user_overview td {
    border-top-style: none;
    padding-<?php echo $start;?>: 4px;
    padding-<?php echo $end;?>: 4px;
    vertical-align: middle;
    height: 28px;
}

table.user_overview img {
    vertical-align: middle;
}

table.user_overview td:first-child {
    border-bottom-style: dotted;
    border-bottom-width: 1px;
}

.download_button_text {
    width: 380px;
    position:absolute;
    <?php echo $end;?>: 5px;
    padding-top:0px;
}

#download_info {
   background: #f0f0f0;
   padding-<?php echo $start;?>: 20px;
   padding-<?php echo $end;?>: 20px;
   padding-top:3px;
   padding-bottom:3px;
}

td.icon_td {
   vertical-align: top;
   width:30px;
   min-height: 80px;
}

td.icon_td img {
   width: 24px;
}

input.missing_input {
   background: #ffccff;
}

.server_cert,.server_cert_list {
   display: none;
}

.server_cert_list {
    background: #eee;
    padding: 5px;
}


.server_cert_list {
    background: #eee;
    padding: 0px;
}

.server_cert dl dt {
   font-weight: bold;
}

.server_cert dl dd {
    font-style: italic;
    font-family: Arial;
}

.downloads tr td {
    text-align: <?php echo $end;?>;
    padding-left: 5px;
    padding-right: 5px;
    border-bottom-style: solid;
    border-bottom-width: 1px;
}

.downloads tr td:first-child {
    text-align: <?php echo $start;?>;
}