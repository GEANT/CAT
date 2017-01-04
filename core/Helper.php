<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php

/**
 * This file contains a random assortment of useful functions and classes.
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
/**
 * necessary includes
 */
namespace core;

define("L_OK", 0);
define("L_REMARK", 4);
define("L_WARN", 32);
define("L_ERROR", 256);

/**
 * this direcory delete function has been copied from PHP documentation
 */
function rrmdir($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            rrmdir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

/**
 * create a temporary directory and return the location
 * @param $purpose one of 'installer', 'logo', 'test' defined the purpose of the directory
 * @param $failIsFatal (default true) decides if a creation failure should cause an error
 * @return array the tuple of: base path, absolute path for directory, directory name
 */
function createTemporaryDirectory($purpose = 'installer', $failIsFatal = 1) {
    $loggerInstance = new Logging();
    $name = md5(time() . rand());
    $path = ROOT;
    switch ($purpose) {
        case 'silverbullet':
            $path .= '/var/silverbullet';
            break;
        case 'logo':
        case 'installer':
            $path .= '/var/installer_cache';
            break;
        case 'logo':
            $path .= '/web/downloads/logos';
            break;
        case 'test':
            $path .= '/var/tmp';
            break;
        default:
            throw new Exception("unable to create temporary directory due to unknown purpose: $purpose\n");
    }
    $tmpDir = $path . '/' . $name;
    $loggerInstance->debug(4, "temp dir: $purpose : $tmpDir\n");
    if (!mkdir($tmpDir, 0700, true)) {
        if ($failIsFatal) {
            throw new Exception("unable to create temporary directory: $tmpDir\n");
        }
        $loggerInstance->debug(4, "Directory creation failed for $tmpDir\n");
        return ['base' => $path, 'dir' => '', $name => ''];
    }
    $loggerInstance->debug(4, "Directory created: $tmpDir\n");
    return ['base' => $path, 'dir' => $tmpDir, 'name' => $name];
}