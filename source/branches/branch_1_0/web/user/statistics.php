<?php

/*
 * ******************************************************************************
 * *  Copyright 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */

require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once 'IdP.php';
require_once 'Profile.php';
require_once '../admin/inc/input_validation.inc.php';

// profile variable needs to be set...

if (isset($_GET['profile'])) {
    $profile = valid_Profile($_GET['profile']);
    $stats = $profile->getUserDownloadStats();
    $timestamp = date("Y-m-d") . "T" . date("H:i:s");
    $total = 0;
    echo "<?xml>\n";
    echo "<statistics>\n";
    echo "  <profile id='".$profile->identifier."' ts='$timestamp'>\n";
    foreach ($stats as $name => $number) {
        echo "    <device name='$name'>\n";
        echo "      <downloads group='user'>$number</downloads>\n";
        echo "    </device>\n";
        $total = $total + $number;
    }
    echo "    <total>\n";
    echo "      <downloads group='user'>$total</downloads>\n";
    echo "    </total>\n";
    echo "  </profile>\n";
    echo "</statistics>\n";
} else
    exit(1);

?>
