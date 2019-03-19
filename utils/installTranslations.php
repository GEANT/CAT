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
const AREAS = ["web_admin", "web_user", "devices", "core", "diagnostics"];
foreach (CONFIG['LANGUAGES'] as $lang => $details) {
    echo "Generating locale for ".$details['locale'];
    exec("sudo locale-gen ".$details['locale']);
    foreach (AREAS as $oneArea) {
        exec("msgfmt ../$lang/$oneArea.po -o ../$lang/LC_MESSAGES/$oneArea.mo");
    }
}
