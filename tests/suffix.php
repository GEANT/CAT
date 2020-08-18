<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
const TEST = [
    ["host.somewhere.com", "gost.somewhere.com"],
    ["my.server.name"],
    ["foo.bar.de", "baz.bar.ge"],
    ["server1.example.com", "server2.example.com", "serverN.example.com"],
];
foreach (TEST as $run => $names) {
    $longestSuffix = "";
    $numStrings = count($names);
    // always take the candidate character from the first array element, and
    // verify whether the other elements have that character in the same 
    // position, too
    while (TRUE) {
        if ($longestSuffix == $names[0]) {
            break;
        }
        $candidate = substr($names[0], -(strlen($longestSuffix) + 1), 1);
        for ($iterator = 1; $iterator < $numStrings; $iterator++) {
            if (substr($names[$iterator], -(strlen($longestSuffix) + 1), 1) != $candidate) {
                break 2;
            }
        }
        $longestSuffix = $candidate . $longestSuffix;
    }
    print_r($names);
    echo "RESULT = $longestSuffix.\n";
}