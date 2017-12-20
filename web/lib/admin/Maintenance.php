<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\admin;

/**
 * This class factors out some functions which are done both interactively in
 * the superadmin area and by scripts in utils/ to avoid code duplication across
 * the two.
 */
class Maintenance {

    /**
     * delete our various cache and temp dirs if they are not needed any more
     * @return int the number of deleted temporary directories
     */
    public static function deleteObsoleteTempDirs() {
        $downloadsDirs = [
            'site_installers' => dirname(dirname(dirname(dirname(__FILE__)))) . "/var/installer_cache",
            'silverbullet' => dirname(dirname(dirname(dirname(__FILE__)))) . "/var/silverbullet"
        ];
        $tm = time();
        $i = 0;
        $Cache = [];
        $dbHandle = \core\DBConnection::handle("FRONTEND");
        $result = $dbHandle->exec("SELECT download_path FROM downloads WHERE download_path IS NOT NULL");
        // SELECT -> mysqli_result, not a boolean
        while ($r = mysqli_fetch_row(/** @scrutinizer ignore-type */  $result)) {
            $e = explode('/', $r[0]);
            $Cache[$e[count($e) - 2]] = 1;
        }
        foreach ($downloadsDirs as $downloads) {
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
                    $i = $i + 1;
                    print "$entry\n";
                }
                closedir($handle);
            }
        }
        return $i;
    }
}
