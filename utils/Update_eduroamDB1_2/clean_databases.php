<?php

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");
require 'config.php';

$db1 = $databases['eduroam'];
$db2 = $databases['eduroamv2'];
$cat_db = $databases['cat'];
$newDb = $databases['eduroam_new'];

$DB_LOCAL = \config\Master::DB['EXTERNAL'];

$user = $DB_LOCAL['user'];
$password = $DB_LOCAL['pass'];
$host = $DB_LOCAL['host'];

$db_root = new mysqli($host, 'root');

$db_root->query("DROP DATABASE IF EXISTS `$db1`");
$db_root->query("DROP DATABASE IF EXISTS `$db2`");
$db_root->query("DROP DATABASE IF EXISTS `$newDb`");
$db_root->query("CREATE DATABASE `$db1`");
$db_root->query("CREATE DATABASE `$db2`");
$db_root->query("CREATE DATABASE `$newDb`");
$db_root->query("GRANT ALL PRIVILEGES ON `$db1`.* TO `$user`@`$host`");
$db_root->query("GRANT ALL PRIVILEGES ON `$db2`.* TO `$user`@`$host`");
$db_root->query("GRANT ALL PRIVILEGES ON `$newDb`.* TO `$user`@`$host`");
exec("mysql -u $user --password=$password $db1 < eduroam.schema");
exec("mysql -u $user --password=$password $db2 < eduroamv2.schema");
