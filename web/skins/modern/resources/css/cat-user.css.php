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
header("Content-Type:text/css");
require dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . "/config/_config.php";
$langInstance = new core\common\Language();
$start = $langInstance->rtl ? "right" : "left";
$end = $langInstance->rtl ? "left" : "right";
$direction = $langInstance->rtl ? "rtl" : "ltr";
header('Content-type: text/css; charset=utf-8');
?>

:root {
    --color1: #FFFFFF;
    --color2: #575757;
    --color3: #1d4a74;
}

html {
    height: 100%;
}

body {
    background: #FFFFFF; 
    color: #000000;
    font-family:"Open Sans", Helvetica, sans-serif;
    font-size:12px;
    font-weight: normal;
    height: 100%;
    min-height: 100%;
    margin: 0px;
    padding: 0px;
    padding-<?php echo $start ?>: 0px;
    position: relative;
}
button[disabled] {
    background: #bababa;
    color: #6a6a6a;
    border-<?php echo $start ?>-style: inset;
    border-<?php echo $start ?>-width: 1px;
    border-<?php echo $start ?>-color: #dadada;
    border-top-style: inset;
    border-top-width: 1px;
    border-top-color: #dadada;
    border-<?php echo $end ?>-style: outset;
    border-<?php echo $end ?>-width: 2px;
    border-<?php echo $end ?>-color: #dadada;
    border-bottom-style: outset;
    border-bottom-width: 2px;
    border-bottom-color: #dadada;
    cursor: default;
}

.use_borders button {
border-<?php echo $start ?>-style: none; 
border-<?php echo $start ?>-width: 1px; 
border-<?php echo $start ?>-color: #8bbacb;
    border-top-style: outset; 
    border-top-width: 1px; 
    border-top-color: #8bbacb;
    border-<?php echo $end ?>-style: outset; 
    border-<?php echo $end ?>-width: 2px; 
    border-<?php echo $end ?>-color: #043d52;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #043d52;
}

input {
margin-<?php echo $end ?>: 5px;
}

input[button] {
    background: #FFFFFF;
}

select {
    vertical-align: middle;
    margin-<?php echo $start ?>: 10px;
}

a:link {
    color: #575757;
}

a:visited {
    color: #575757;
}

a:hover {
    color:#043d52;
}

a:active {
    color: #575757;
}

#wrap {
    min-height: 100%;
    max-width: 1200px;
    background: white;
    margin: auto;
  /*  height: 100%;
  padding-bottom: 80px;*/
}

#cat_form {
    height: 100%;
}

#heading {
   position: relative;
}

#main_page {
   position: relative;
   height: 100%;
   width: 100%;
}

#front_page {
    position: relative;
    width: 100%;
    height:auto;
}

button {
    background: #575757; 
    color: #FFFFFF; 
    height: 23px;
}

button:hover {
     background: #bcd5e4;
     color:#000;
}

button.pressed {
    background:#095e80;
}

#device_list td {
    vertical-align: top;
        background: #aaa; 

}


#device_list button.pressedDisabled {
    background:#f00;
}

button.delete {
    background: maroon;
}

#devices {
    z-index:80;
    padding-<?php echo $start ?>: 30px;
    font-size: 11px;
    font-weight: normal;
    position: relative;
    display: none;
}

#device_list button {
    min-height: 23px;
    color: white;
    background:  #1d4a74;
}

#device_list button:hover {
     background: #bcd5e4;
     color:#000;
}

#device_list button.more_info_b {
    width: 23px; 
    font-style: italic;
    font-family: "Times New Roman", Times, serif;
    font-size: 18px;
    text-align: center;
    padding-bottom: 8px;
}

#device_list button.disabledDevice {
    color: #444;
    background: #bbb;
}

#device_list td.vendor {
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

#device_list td.vendor img {
    height: 40px;
}

#device_list button.dev_or {
    background-color: #bbb;
}

#devices button {
    width: 25em; 
    font-size: 14px;  
    font-weight: normal; 
    font-style: normal;
}

#devices.no_borders td {
    padding-top: 2px;
    margin-top: 2px;
}

#devices {
    list-style-type: none;
}

#devices td {
    vertical-align:middle;
}

#roller {
    position: relative;
    padding-top: 140px;
    height:272px;
}

#slides {
    position: absolute;
<?php echo $start ?>: 0px;
    height:272px;
    background: #1d4a74;
    background-image: url("../images//image_5a.jpg");
    background-size: 100%;
    background-repeat: no-repeat;
    width: 100%;
}

#slides img {
    position: absolute;
    top: 100px;
<?php echo $end ?>: 60px;
}

#img_roll {
    position: absolute;
<?php echo $end ?>: 50px;
    top: 90px;
}

#img_roll  img {
    position: absolute;
<?php echo $end ?>: 0px;
    top: 70px;
}

.img.img_roll {
    z-index: 90;
}

#user_button {
    display: block;
    width: 50vw;
    max-width: 600px;
}

#user_button_td {
    text-align:center;
    vertical-align:top;
    padding-bottom: 20px;
    padding-top: 50px;
    position: relative;
    height: auto;
}

#signin {
padding-<?php echo $start ?>: 30px;
    padding-top: 10px;
}

#top_invite {
    clear: both;
    color: #575757;
    font-weight: bold;
    font-size: 30px;
    padding-top: 20px;
    padding-bottom: 25px;
    text-align: center;
    width: 100%;
    min-height: 50px;
    cursor: auto;
    text-decoration: none;
}

#top_invite_ad {
    font-weight: normal;
    font-size: 18px;
    padding-top: 25px;
    padding-<?php echo $start ?>: 15px;
    padding-<?php echo $end ?>: 15px;
    line-height: 1.3;
    color: black;
}

#top_invite_ad a {
    white-space: nowrap;
    font-weight: bold;
}

#welcome_top1 {
    clear: both;
    padding-top: 30px;
    color: #575757;
    font-weight: bold;
    font-size: 20px;
    text-align: center;
}


#cat_logo {
   width: 180px;
   position:absolute;
<?php echo $start ?>:0px;
   top:50px;
   text-align: center;
}

#cat_logo span {
  display:block;
  font-weight:bold;
  position: relative;
  top: 8px;
/*  left: 0px; */
  
}

#info_overlay {
    display: none;
    background: #f0f0f0;
    padding: 20px;
    padding-bottom: 10px;
    z-index: 100;
    position: absolute;
    width: 70%;
<?php echo $start ?>: 20%;
    text-align: justify;
    top: 200px;
    border-top: 4px solid #1d4a74;
    box-shadow: 0px 3px 2px 1px rgba(0,0,0,0.06)
}

#idp_logo {
   display:none;
   float:<?php echo $end ?>;
   padding-<?php echo $end ?>: 20px;
   max-height:150px;
   padding-top:10px;
}


#fed_logo {
   display:none;
   position:absolute;
<?php echo $end ?>:30px;
   max-height:150px;
   padding-bottom:10px;
   bottom: 50px;
}

#inst_extra_text {
   position: absolute;
   max-width: 30vw;
   width: auto;
   top: 0px;
<?php echo $end ?>: 20px;
   font-size: 16px;
   padding-top: 12px;
   color: yellow;
   padding-<?php echo $start ?>:0px;
   text-align: <?php echo $end ?>;
}

#message_only {
    padding-top: 20px;
    padding-<?php echo $start;?>: 30px;
    padding-<?php echo $end;?>: 30px;
    font-weight: normal;
    position: relative;
    font-size: 15px;
}

div.device_info {
    background: #f0f0f0;
    border: 1px solid #dddddd;
    margin: 5px;
    padding: 5px;
    padding-bottom: 22px;
    vertical-align: top;
    border-radius: 10px 10px 10px 10px;
    box-shadow: 5px 5px 5px #666666;
    max-width: 350px;
    font-weight: normal;
    font-style: normal;
    display: none;
}

span.redirect_link {
    background: #575757;
    color: #FFFFFF; height: 23px;
    padding-<?php echo $start ?>: 5px;
    padding-<?php echo $end ?>: 5px;
    padding-top: 1px;
    padding-bottom: 1px;
    position: relative;
    cursor:pointer;
    float: <?php echo $end ?>;
    height: 14px;
}

span.redirect_link a:link {
    color: white; 
    text-decoration: none;
}

span.redirect_link a:visited {
    color: white; 
    text-decoration: none;
}

span.redirect_link a:active {
    color: white; 
    text-decoration: none;
}

span.continue {
    background: #575757;
    color: #FFFFFF; height: 23px;
    padding-<?php echo $start ?>: 5px;
    padding-<?php echo $end ?>: 5px;
    padding-top: 1px;
    padding-bottom: 1px;
    position: relative;
    cursor:pointer;
    float: <?php echo $end ?>;
    height: 14px;
}

span.continue:hover {
     background: #bcd5e4;
     color:#000;
}

.use_borders button.alertButton {
    color: maroon; 
    background: #bbb; 
    border-<?php echo $start ?>-style: outset; 
    border-<?php echo $start ?>-width: 1px; 
    border-<?php echo $start ?>-color: #eee;
    border-top-style: outset; 
    border-top-width: 1px; 
    border-top-color: #eee;
    border-<?php echo $end ?>-style: outset; 
    border-<?php echo $end ?>-width: 2px; 
    border-<?php echo $end ?>-color: #444;
    border-bottom-style: outset; 
    border-bottom-width: 2px; 
    border-bottom-color: #444;
}

#profile_desc {
    display:none;
    background: #f0f0f0;
    position: relative;
    border: 1px solid #dddddd;
    margin: 5px;
    padding: 5px;
    max-width: 49%;
    min-width: 15em;
    min-height: 25px;
    vertical-align: top;
}

div.sub_h {
    font-weight: bold;
    font-style: italic;
    padding-top: 15px;
}

#profile_redirect {
padding-<?php echo $start ?>: 30px;
    padding-top: 20px;
    padding-bottom: 16px;
    font-size: 12px;
    font-weight: normal;
    max-width:500px;
    width: 80%;
    display: none;
}

span.edu_cat {
    font-weight: bold;
    color: #575757;
}

#welcome {
    padding: 20px;
    padding-<?php echo $start ?>: 30px;
    text-align: justify;
    border-bottom-style:solid;
    border-bottom-width:5px;
    border-color: #FFFFFF;
    font-size: 14px;
    font-weight: normal;
}

#profiles {
padding-<?php echo $start ?>: 30px; 
    font-size: 14px; 
    padding-bottom: 10px 
}

#user_info {
    max-width: 100%;
    padding-<?php echo $start ?>: 30px; 
    font-size: 14px;  
    line-height: 150%;
    font-weight: normal; 
}

#user_info table td,th {
text-align: <?php echo $start ?>;
}

span.user_info {
    overflow: hidden;
    padding-<?php echo $end ?>:10px;
    display: inline-block;
    max-width:100%;
    text-overflow: ellipsis;
    white-space: nowrap;
}

span.user_info_header {
    font-weight: bold;
}

#loading_ico {
    display: none;
    position: absolute;
<?php echo $start ?>: 200px;
    top: 220px;
    z-index: 200;
    text-align: center;
}

#motd {
   position: absolute;
   top: 0px;
   height:36px;
   line-height:36px;
/*   color: maroon; */
   color: #ffff66;
   text-align: <?php echo $end ?>;
   padding:0px;
   padding-<?php echo $end ?>: 20px;
   width: 100%;
   background: #1d4a74;
   box-sizing: border-box;
}

#footer {
    position: relative;
    min-height: 49px;
    width: 100%;
    background: white;
    margin: auto;
    padding-top:5px;
    padding-bottom:10px;
    border-top: 1px solid #000;
    direction:ltr;
}

div.footer table {
    width: 100%;
    direction:ltr;
}

div.footer table td {
    padding-<?php echo $end ?>:10px; 
    padding-<?php echo $start ?>:10px; 
    vertical-align:top;
    position: relative;
    direction:ltr;
}


#eu_text {
text-align: right;
padding-left: 60px;
display: block;
}

#eu_text a {
text-decoration:none;
vertical-align:top;
text-align: right;
}

#open_roam_cond {
    display: none;
}


.download_button_text_1 {
        font-size: 20px;
        text-align: center;
        padding: 20px;
}
button.guess_os {  
    background-color: #1d4a74;
    border: 6px solid  #e7e7e7;
    border-radius: 12px;
    padding-<?php echo $start ?>:20px;
    padding-<?php echo $end ?>:20px;
    color: #ffffff;  
    width: 40vw;
    height: 100px;
    vertical-align: middle;
    min-width: 300px;
    max-width: 450px;
    display: none;
}

button.guess_os:hover {
     background: #bcd5e4;
     color:#000;
}

#guess_os button.more_info_b {
    background-color: #1d4a74;
    border: 6px solid  #e7e7e7;
    border-radius: 12px;
    padding-<?php echo $start ?>:20px;
    padding-<?php echo $end ?>:20px;
    color: white;
    font-size: 50px;
    font-style: italic;
    font-family: "Times New Roman", Times, serif;
    width: 80px;
    height: 100px;
    text-align: center;
    vertical-align: middle;
}

#guess_os button.more_info_b:hover {
     background: #bcd5e4;
     color:#000;
}

#openroaming_tou {
    display: none;
    min-width: 300px;
    max-width: 900px;
    padding-<?php echo $start ?>: 10px;
    width: 80vw;
}

#more_i {
    display: none;
}


#download_text_1 {
    font-size: 20px;
    color: #1d4a74;
    padding-<?php echo $start ?>: 60px;
    min-height: 60px;
    margin: 0;
    background-repeat:no-repeat;
    position: relative;
    background-position: <?php echo $start ?>;
    background-size: 50px 50px;
}

#download_text_1 div {
    margin: 0;
    position: absolute;
    top: 50%;
    -ms-transform: translateY(-50%);
    transform: translateY(-50%);
}


div.button_wrapper {
    display: inline-block;
    vertical-align: top;
}

    img.applogos {
        height: 100px;
    }

@media only screen and (max-width: 720px) {
            
    #more_i {
        font-family: Arial;
        font-size: 15px;
        color: #1d4a74;
        padding-top: 2vh;
        display: block;
    }
            
    #more_i a {
        color: #1d4a74;
    }        
    #more_i a:visited {
        color: #1d4a74;
    } 
    
    #guess_os button.more_info_b {
        display: none;
    }
    
    img.applogos {
        height: 50px;
    }
}


@media all and (max-width: 389px) {
#institution_name {
    font-size: 20px;
    padding-top: 20px;
    padding-bottom: 18px;
    padding-<?php echo $end ?>: 35vw;
    padding-<?php echo $start ?>: 20px;
    background: #1d4a74;
    color: white;
    text-align: <?php echo $start ?>;
}

#top_invite {
    padding-bottom: 10px;
}


#user_button_td {
    padding-top: 10px;
}


#slides span {
    position: absolute;
<?php echo $start ?>: 20px;
    z-index: 20;
}


#user_welcome {
    background: #ffffff;
    padding-<?php echo $start ?>: 30px;
    padding-top: 20px;
    padding-<?php echo $end ?>: 20px;
    font-size: 14px;
    font-weight: normal;
    display:none;
}

.download_button_text {
    width: 150px;
    position:absolute;
    font-size:20px;
    top:15px;
<?php echo $end ?>: 10px;
    padding-top:0px;
}



#profile_list {
    width: auto;
    max-width: 90%;
    padding-<?php echo $start ?>: 10px;
    padding-<?php echo $end ?>: 0px;
    background-color: #1d4a74;
    color: white;
}

#devices {
padding-<?php echo $start ?>: auto;
padding-<?php echo $end ?>: auto;
}

#browser {
    width: 90%;
}

#img_roll {
   display: none;
}


#devices {
    list-style-type: none;
}

#devices td {
    vertical-align:middle;
}


td.vendor {
    display: none;
}

#hamburger {
   position: absolute;
<?php echo $end ?>: 0px;
   top: 30px;
   width: 80px;
   cursor: pointer;
   z-index: 140;
}
#menu_top {
    position: absolute;
<?php echo $start ?>: 0px;
    top: 20px;
    padding-<?php echo $end ?>: 2vw;
    z-index: 150;
    background: #e7e7e7;
}

#menu_top > ul {
    list-style-type: none;
    font-weight: bold;
    display: none;
}

#menu_top > ul li ul{
    font-weight: normal;
}

#idp_logo {
   max-width:20vw;
   padding-top:30px;
}

#fed_logo {
   max-width:20vw;
   padding-top:30px;
}

#inst_extra_text {
   position: absolute;
   max-width: 30vw;
   width: auto;
   top: 0px;
<?php echo $end ?>: 20px;
   font-size: 13px;
   padding-top: 14px;
   color: yellow;
   padding-<?php echo $start ?>:0px;
   text-align: <?php echo $end ?>;
}

}

@media all and (max-width: 389px) and (orientation:landscape) { 
#menu_top {
    font-size: 2.5vw;
    max-width: 60vw;
}
img.applogos {
    height: 50px;
}

}

@media all and (max-width: 389px) and (orientation:portrait) { 
    
    
button.guess_os {
    width: 90vw;
    height: 20vh;
    min-width: 200px;
    }
    
.download_button_text_1 {
    font-size: 4vh;
    padding: 2vh;
}


#openroaming_tou {
    display: none;
    min-width: 200px;
    padding-<?php echo $start ?>: 10px;
    width: 90vw;
}

#tt {
    font-size: 3vh;
    padding-bottom: 2vh;
}
    
#menu_top {
    font-size: 2.5vh;
    max-width: 60vw;
}

#welcome_top1 {
    padding-top: 140px;
}

#roller {
    display: none;
}

img.applogos {
    max-width: 45%;
    height: auto;
}

}

@media all and (max-width: 750px) and (min-width:  390px) {
#institution_name {
    font-size: 24px;
    padding-top: 20px;
    padding-bottom: 18px;
    padding-<?php echo $end ?>: 33vw;
    padding-<?php echo $start ?>: 20px;
    background: #1d4a74;
    color: white;
    text-align: <?php echo $start ?>;
}

#slides span {
    position: absolute;
<?php echo $start ?>: 20px;
    z-index: 20;
}

#line1 {
    top:20px;
    color: yellow;
    font-size:20px;
}

#line2 {
    top:55px;
    font-size:40px;
    color: #fff;
}

#line3 {
    top:120px;
<?php echo $start ?>: 200px;
    color: #ffcc99;
    font-size:25px;
    display: none;
}

#line4 {
    top:180px;
    color: #fff;
    font-size:16px;
    width: auto;
    min-width: 200px;

}

#line5 {
    top:220px;
    width: 400px;
    color: #fff;
    font-size:16px;
    width: auto;
    min-width: 200px;
}

#user_welcome {
    background: #ffffff;
    padding-<?php echo $start ?>: 30px;
    padding-top: 20px;
    padding-<?php echo $end ?>: 20px;
    font-size: 14px;
    font-weight: normal;
    display:none;
}

.download_button_text {
    width: 150px;
    position:absolute;
    font-size:20px;
    top:15px;
<?php echo $end ?>: 10px;
    padding-top:0px;
}

#profile_list {
    width: auto;
    padding-<?php echo $start ?>: 10px;
    padding-<?php echo $end ?>: 0px;
    background-color: #1d4a74;
    color: white;
}


#img_roll {
   display: none;
}


#hamburger {
   position: absolute;
<?php echo $end ?>: 0px;
   top: 30px;
   width: 80px;
   cursor: pointer;
   z-index: 140;
}
#menu_top {
    position: absolute;
<?php echo $start ?>: 0px;
    top: 20px;
    padding-<?php echo $end ?>: 2vw;
    z-index: 150;
    background: #e7e7e7;
}

#menu_top > ul {
    list-style-type: none;
    font-weight: bold;
    display: none;
}

#menu_top > ul li ul{
    font-weight: normal;
}

#idp_logo {
   max-width:20vw;
   padding-top:30px;
}

#fed_logo {
   max-width:20vw;
   padding-top:30px;
}

#inst_extra_text {
   position: absolute;
   max-width: 30vw;
   width: auto;
   top: 0px;
<?php echo $end ?>: 20px;
   font-size: 13px;
   padding-top: 14px;
   color: yellow;
   padding-<?php echo $start ?>:0px;
   text-align: <?php echo $end ?>;
}

#devices {
padding-<?php echo $start ?>: auto;
padding-<?php echo $end ?>: auto;
}
#top_invite {
    padding-bottom: 10px;
}

#user_button_td {
    padding-top: 10px;
}

img.applogos {
    height: 50px;
}

}

@media all and (max-width: 750px) and (min-width: 390px) and (orientation:landscape) { 
#menu_top {
    font-size: 2.5vw;
    max-width: 60vw;
}

}

@media all and (max-width: 750px) and (min-width: 390px) and (orientation:portrait) { 
#menu_top {
    font-size: 2.5vh;
    max-width: 60vw;
}

}

@media all and (min-width: 751px) {
body {
    background: rgb(188, 205, 216);
}

#institution_name {
    font-size: 32px;
    padding-top: 30px;
    padding-bottom: 28px;
    padding-<?php echo $end ?>: 120px;
    padding-<?php echo $start ?>: 30px;
    background: #1d4a74;
    color: white;
    text-align: <?php echo $start ?>;
}


#idp_logo {
   max-width:150px;
}

#fed_logo {
   max-width:150px;
}

#slides span {
    position: absolute;
<?php echo $start ?>: 20px;
    z-index: 20;
}

#line1 {
    top:20px;
    color: yellow;
    font-size:20px;
}

#line2 {
    top:55px;
    font-size:40px;
    color: #fff;
}

#line3 {
    top:120px;
<?php echo $start ?>: 200px;
    color: #ffcc99;
    font-size:25px;
}

#line4 {
    top:180px;
    color: #fff;
    font-size:16px;
    width: auto;
    min-width: 200px;

}

#line5 {
    top:220px;
    color: #fff;
    font-size:16px;
    width: auto;
    min-width: 200px;
}


#user_welcome {
    background: #ffffff;
    padding-<?php echo $start ?>: 30px;
    padding-top: 20px;
    padding-<?php echo $end ?>: 180px;
    font-size: 14px;
    font-weight: normal;
    display:none;
}

.download_button_text {
    width: 380px;
    position:absolute;
    font-size:20px;
    top:40px;
<?php echo $end ?>: 5px;
    padding-top:0px;
}

#profile_list {
    width: 30em;
    padding-<?php echo $start ?>: 10px;
    padding-<?php echo $end ?>: 0px;
    background-color: #1d4a74;
    color: white;
}

#slides span {
    position: absolute;
<?php echo $start ?>: 80px;
    z-index: 20;
}

#hamburger {
   display: none;
}


#menu_top {
    position: absolute;
<?php echo $end ?>: 10px;
    top: 35px;
    z-index: 150;

}

#menu_top > ul {
    list-style-type: none;
    margin: 0;
    padding: 0;
    display: inline;
}

#menu_top li {
float: <?php echo $start ?>;
   position: relative;
}

#menu_top > ul > li:hover {
    background: #1d4a74;
    color: white;
}

#menu_top> ul > li > span:hover, #menu_top > ul > li > a:hover {
    background: #1d4a74;
    color: white;
}

#menu_top > ul li a, #menu_top > ul li span {
    transition-delay: 0s;
    transition-duration: 0.3s;
    transition-property: all;
    transition-timing-function: ease-in-out;
    position: relative;
    display: block;
    font-size: 14px;
    text-align: center;
    padding: 35px 2vw 35px 2vw;
    text-decoration: none;
    min-height: 20px;
}

#menu_top a.selected-lang {
   background: lightblue;
   color: black;
}

#menu_top > ul > li > ul > li > a:hover {
    background: #333;
    color: white;
}
#menu_top > ul > li > a {
    color: #000000;
}

#menu_top > ul li:hover ul {
    display: block;
}

#menu_top > ul li ul {
    position: absolute;
    top:90px;
    display: none;
    list-style-type: none;
    margin: 0;
    padding: 0;
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
}

#menu_top > ul > li > ul > li {
   clear: both;
}

#menu_top > ul > li > ul > li:last-child a {
	border-bottom: 3px solid #FFF;
}


#menu_top > ul > li > ul > li > a {
   position:relative;
   background: #1d4a74;
   color: white;
   display: block;
   width: 200px;
   min-height: 20px;
   padding: 15px 20px 15px 20px;
   text-align: <?php echo $start ?>;
   border-top: 1px solid  rgba(255,255,255,0.3);
   font-size: 14px;
   z-index: 150;
   box-shadow: 0px 3px 2px 1px rgba(0,0,0,0.06)
}

}

#main_menu_info {
    position: absolute;
    top: 140px;
<?php echo $start ?>: 5%;
    width: 90%;
    background: #f0f0f0;
    margin-<?php echo $start ?>: 0px;
    padding-top: 10px;
    padding-<?php echo $start ?>: 0px;
    margin-<?php echo $end ?>: 0px;
    padding-<?php echo $end ?>: 0px;
    padding-bottom: 10px;
    vertical-align: top;
    box-shadow: 0px 3px 2px 1px rgba(0,0,0,0.06);
/*    border-top: 4px solid var(--color3);*/
    text-align: justify;
    font-size: 14px;
    z-index: 140;
}

#main_menu_content {
   font-size: 12px;
}

img.close_button {
   position: absolute;
<?php echo $end ?>: 5px;
   top: 7px;
   cursor: pointer;
}

#main_menu_content div.padding {
padding-<?php echo $start ?>: 20px;
padding-<?php echo $end ?>: 20px;
}
#main_menu_content h1 {
    padding: 40px;
    height: 30px;
    font-size: 24px;
    font-weight: normal;
    color: white;
    background: #1d4a74;
}

#main_menu_content div > div {
    padding: 0px 40px 0px 40px;
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

#user_page {
   position: relative;;
   width: 100%;
   top: 130px;
   padding-bottom: 130px;
   display: none;
}

#guess_os  {
   display: none;
}


button.large_button {
    background-color: #1d4a74;
    border: 6px solid  #e7e7e7;
    border-radius: 12px;
    padding-<?php echo $start ?>:20px;
    padding-<?php echo $end ?>:20px;
    height: auto;
    min-height: 100px;
    color: #ffffff;
    font-size: 20px;
    text-decoration: none;
}

button.large_button:hover {
    background-color: #bcd5e4;
    color: #575757;
    text-decoration: underline;
}

a.signin {
   color: white;
   padding-<?php echo $start ?>: 30px;
}

#logos {
    position: absolute;
    left : 0px;
}

#select_another {
   position: absolute;
   max-width: 30vw;
   width: auto;
   top: 0px;
<?php echo $end ?>: 20px;
   font-size: 16px;
   padding-top: 12px;
   color: yellow;
   padding-<?php echo $start ?>:0px;
   text-align: <?php echo $end ?>;
}

#download_info {
   background: #f0f0f0;
   padding-<?php echo $start ?>: 20px;
   padding-<?php echo $end ?>: 20px;
   padding-top:3px;
   padding-bottom:3px;
   display: block;
}

#cert_details {
    display: none;
    padding-top: 10px;
    
}

#cert_details td {
    background: #eee;
    padding: 1px;
}

#cert_details th.th1 {
    font-size: 14px;
    text-align: <?php echo $start ?>;
    padding-top: 4px;
    border-bottom: 1px black solid;
}

#cert_details td.revoke {
    background: #fff;
}

#cert_details td.revoke a {
    color: red;
    font-weight: bold;
    text-decoration: none;
}

#cert_details td.revoke a:hover {
    text-decoration: underline;
}

#sb_download_message span.emph {
    font-weight: bold;
}

#sb_download_message {
    font-size: 16px;
    padding-<?php echo $end ?>: 30px;
    padding-<?php echo $start ?>: 20px;
    padding-top:1px;
    padding-bottom: 1px;
    position: relative;
<?php echo $start ?>: -20px;
    top: 10px;
}

#sb_info {
    padding-top: 15px;
    padding-bottom: 50px;
}

#detailtext {
    cursor: pointer;
    font-weight: bold;
    font-style: italic;
}

#silverbullet {
    display: none;
    padding: 30px;
    font-size: 14px;
    font-weight: bold;
}
