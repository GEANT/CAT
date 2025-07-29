<?php

$databases = [
    'eduroam' => "eduroam1_1_tmp", // this is the local copy of eduroam (v1) database created by update_monitor_copy.php
    'eduroamv2' => "eduroam2_1_tmp", // this is the local copy of eduroam (v2) database created by update_monitor_copy.php
    'cat' => "cat_twoln_214_test2",  // this is the curent production CAT database where we need to put in new identifiers
    'eduroam_new' => "monitor_copy2_test_xxx" // this is the resulting monitor_copy database containing all data required by CAT it must exist but tables will be created by the sript
];

