<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
require_once dirname(dirname(__FILE__)) . "/config/_config.php";

/**
 * The sctipt will be called from crontab
 * 
 * list all profiles
 * foreach profile create the profile object
 * run profile->openroamingRedinessTest 
 * this will also update the profile table
 * The output from the tests is irrelevant
 * 
 */

$dbLink = \core\DBConnection::handle("INST");
$allOpenRoamingProfiles = $dbLink->exec("SELECT profile_id FROM profile_option WHERE option_name='media:openroaming'");
if (!$allOpenRoamingProfiles) {
    exit;
}


while ( $row = mysqli_fetch_object($allOpenRoamingProfiles)) {
    $profileId = $row->profile_id;
    print "$profileId\n";
    $profile = \core\ProfileFactory::instantiate($profileId);
    $res = $profile->openroamingRedinessTest();
    print_r($res);
}
