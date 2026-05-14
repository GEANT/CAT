<?php

require_once dirname(dirname(__FILE__)) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");
$DB_LOCAL = \config\Master::DB['INST'];
$db = new mysqli($DB_LOCAL['host'], $DB_LOCAL['user'], $DB_LOCAL['pass']);
$db->select_db($DB_LOCAL['db']);

$federation = 'LU';
$adminApi = new \web\lib\admin\API();
$adminApi->catlink = 'https://cat-test.eduroam.org/twoln';
$adminApi->fed = new \core\Federation($federation);
$adminApi->outputFormat = 'array';

$parameters = [[
        "NAME"=>"ATTRIB-CAT-PROFILEID",
        "VALUE"=>""
    ]];

$codeMapping = [
    0 => \core\AbstractProfile::TEST_STATUS_OK,
    1 => \core\AbstractProfile::TEST_STATUS_REMARK,
    2 => \core\AbstractProfile::TEST_STATUS_WARN,
    3 => \core\AbstractProfile::TEST_STATUS_ERROR,
    5 => \core\AbstractProfile::TEST_STATUS_UNKNOWN,
];

$query = "SELECT profile.profile_id AS profile_id FROM profile JOIN institution ON profile.inst_id=institution.inst_id WHERE institution.country='$federation' AND profile.showtime=1  AND realm != '' AND realm IS NOT NULL";
//print "$query\n";
$profiles = $db->query($query);
$updates = [];

while ($row = $profiles->fetch_row()) {
    $profileId = $row[0];
    print "PROFILE=$profileId\n";
    $q = "SELECT profile_id FROM profile_option WHERE profile_id = $profileId AND option_name='device-specific:redirect' AND device_id IS NULL";
    $redirect = $db->query($q);
    if ($redirect->num_rows > 0) {
        print "redirected\n";
        continue;
    }
    $parameters[0]["VALUE"] = $profileId;
    $adminApi->scrubbedParameters = $parameters;
    $diagResult = $adminApi->actionDiagTests();
    $results = $diagResult['details']['radius_hosts_tests'];
    
    $maxResult = 0;
    foreach ($results as $result) {
        $maxResult = max($maxResult, $result['returncode']);
    }
    $updates[] = "UPDATE profile SET test_result=".$codeMapping[$maxResult]." WHERE profile_id=$profileId";
    print "Result:".$maxResult."\n";
    print "Mapped Result:".$codeMapping[$maxResult]."\n";
}

foreach ($updates as $update) {
    $db->query($update); 
}
