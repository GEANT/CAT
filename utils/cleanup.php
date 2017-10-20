<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

// called by cleanup.sh (which in turn should run via cron every hour)

/**
 * This script deletes obsolete directories from installer cache and siverbullet directory
 */

require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

$downloads = dirname(dirname(__FILE__)) . "/var/installer_cache";
$tm = time();

$Cache = [];
$dbHandle = \core\DBConnection::handle("FRONTEND");
$result = $dbHandle->exec("SELECT download_path FROM downloads WHERE download_path IS NOT NULL");
while ($r = mysqli_fetch_row($result)) {
    $e = explode('/', $r[0]);
    $Cache[$e[count($e) - 2]] = 1;
}

if ($handle = opendir($downloads)) {
    /* This is the correct way to loop over the directory. */
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitignore') {
            continue;
        }
        $ftime = $tm - filemtime($downloads . '/' . $entry);
        if ($ftime < 3600) {
            continue;
        }
        if (isset($Cache[$entry])) {
            continue;
        }
       \core\common\Entity::rrmdir($downloads . '/' . $entry);
        print "$entry\n";
    }
    closedir($handle);
}
$downloads = dirname(dirname(__FILE__)) . "/var/silverbullet";
if ($handle = opendir($downloads)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitignore') {
            continue;
        }
        $ftime = $tm - filemtime($downloads . '/' . $entry);
        if ($ftime < 3600) {
            continue;
        }
       \core\common\Entity::rrmdir($downloads . '/' . $entry);
        print "$entry\n";
    }
    closedir($handle);
}