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

const AREAS = ["web_admin", "web_user", "devices", "core", "diagnostics"];
foreach (\config\Master::LANGUAGES as $lang => $details) {
    if ($lang == "en") {
        continue;
    }
    $langCode = substr($details['locale'], 0, 5);
    echo "Generating locale for ".$details['locale']."\n";
    exec("sudo locale-gen ".$details['locale']);
    foreach (AREAS as $oneArea) {
        exec("msgfmt ../translation/$langCode/$oneArea.po -o ../translation/$langCode/LC_MESSAGES/$oneArea.mo");
    }
}
