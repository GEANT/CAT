<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
require_once "../config/config-master-template.php";
const OSLIST = ["chromeos", "w10", "apple_hi_sierra", "linux", "eap-config"];
$bashLines = "";
foreach (CONFIG['LANGUAGES'] as $lang => $details) {
    if ($lang == "en") {
        continue;
    }
    echo "Generating bash script for generation of all language variants except English, locale = $lang\n";
    foreach (OSLIST as $oneOS) {
        $filename = "/home/scrutinizer/artifacts/$lang-$oneOS";
        $bashLines .= "wget http://ci.test/user/API.php?action=downloadInstaller\&api_version=2\&lang=$lang\&device=$oneOS\&profile=3 -O $filename --no-verbose\n";
        switch ($oneOS) {
            case "chromeos":
                $bashLines .= "cat $filename | jq -r .Type | grep UnencryptedConfiguration\n";
                break;
            case "w10":
                $bashLines .= "file $filename | egrep 'executable.*Intel.*Windows.*Nullsoft'\n";
                break;
            case "apple_hi_sierra":
                $bashLines .= "openssl smime -verify -in $filename -inform der -noverify 2>&1 | egrep '(Verification successful|plist)' | wc -l | grep 4\n";
                break;
            case "linux":
                $bashLines .= "pylint -E $filename\n";
                break;
            case "eap-config":
                $bashLines .= "xmlstarlet val -s \"/home/scrutinizer/build/devices/xml/eap-metadata.xsd\" \"$filename\"\n";
                break;
        }
    }
}
file_put_contents("/home/scrutinizer/build/tests/langTestScript.sh", $bashLines);