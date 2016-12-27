<?php
include(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . "/config/_config.php");
$colour1 = CONFIG['APPEARANCE']['colour1'];
$colour2 = CONFIG['APPEARANCE']['colour2'];
header('Content-type: text/css; charset=utf-8');
?>

body {
   font-size:25px;
   font-size:5vw;

font-family:Verdana, Arial, Helvetica, sans-serif;
}

h1 {
 font-size:35px;
 font-size:7vw;
}
ul li {padding-bottom:4ex}
select {
   font-size:25px;
   font-size:5vw;
   width: 100%;
   background: <?php echo $colour2;?>;
   color: #ffffff;
}

div.user_info {background:yellow; padding:8px}

div.footer {
    padding-top: 20px;
    text-align:left;
}

div.download {
   font-weight: bold;
   color: red;
   background:#aaa;
  padding: 20px;
}

#tou {
   background:#eee;
  padding: 20px;
}

button, #devices {
   font-size:25px;
   font-size:5vw;
   width: 100%;
   background: <?php echo $colour2;?>;
   color: #ffffff;
   border-radius:10px ; 
   border-radius:2vw ; 
   cursor: pointer;
   border: 0; 
    padding: 10px;
    padding: 2vw;
}

#user_choice {
  font-size: 60%;
  padding: 5px;
  padding: 1vw;
  background: #eee;
}

#unsupported_os {
  color: red;
}

#motd {
  color: red;
  background: yellow;
  font-size:15px;
  font-size:3vw;
  padding: 5px;
  padding: 1vw;
}
