<?php

require_once dirname(dirname(__FILE__)) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");
$DB_LOCAL = \config\Master::DB['INST'];
$db = new mysqli($DB_LOCAL['host'], $DB_LOCAL['user'], $DB_LOCAL['pass']);
$db->select_db($DB_LOCAL['db']);


//Load all feds

$cat = new \core\CAT();

$feds = array_keys($cat->printCountryList(1));
$res = $db->query("SELECT federation_id FROM federation_option WHERE option_name='fed:no-testing'");
$blockedFeds = array_column($res->fetch_all(), 0);
$db->query("DELETE FROM feds_for_testing");

foreach ($feds as $fed) {
    if (!in_array($fed, $blockedFeds)) {
        $db->query("INSERT INTO feds_for_testing VALUES ('$fed')");
    }
}
