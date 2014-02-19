<?php

/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * **********************************************************************************/
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");

function defaultPagePrelude($pagetitle) {
    require_once("auth.inc.php");
    $Cat = new CAT();
    $Cat->set_locale("web_admin");
    $ourlocale = $Cat->lang_index;
    header("Content-Type:text/html;charset=utf-8");
    echo"<!DOCTYPE html>
<html lang='$ourlocale'>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
        <link rel='stylesheet' type='text/css' href='../resources/css/cat.css' />
    <title>$pagetitle</title>
";
    return $Cat;
}

function productheader() {
    echo "<div class='maincontent'>";
// echo "You are here: ".$_SERVER['REQUEST_URI'];
    $place = parse_url($_SERVER['REQUEST_URI']);

    if (isset($place['query'])) {
        preg_match('/(.*)&lang=.*/', '&' . $place['query'], $result);

        if (array_key_exists(1, $result))
            $short = substr($result[1], 1);
        else
            $short = $place['query'];
    } else {
        $short = "";
    }

    echo "<div class='header'><h1>" . sprintf(_("%s<br />IdP Configuration Interface"), Config::$APPEARANCE['productname_long']) . "</h1>
<hr />
<p class='preprodwarning'>" . Config::$APPEARANCE['MOTD'] . "</p>
<div class='consortium_logo'>
    <img id='test_locate' src='../resources/images/consortium_logo.png' alt='logo'>
</div> <!-- consortium_logo -->
<p>" . _("View this page in") . " ";

    foreach (Config::$LANGUAGES as $lang => $value) {
        $new_url = $place['path'] . ( $short == "" ? "?" : "?" . $short . "&" ) . "lang=$lang";
        $enc_url = htmlspecialchars($new_url);
        echo "
        <a href='$enc_url'>" . $value['display'] . "</a> ";
    }
    echo "</p>
<div class='sidebar'>
    <p><strong>" . _("You are:") . "</strong> " . (isset($_SESSION['name']) ? $_SESSION['name'] : _("Unnamed User")) . "<br/>
        <br/>
        <a href='overview_user.php'>" . _("Go to your Profile page") . "</a> <a href='../'>" . _("Start page") . "</a>
</p>
</div> <!-- sidebar -->
</div> <!-- header -->
<div class='pagecontent'><div class='trick'>";
}

;
