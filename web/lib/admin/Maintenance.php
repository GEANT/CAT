<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
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
