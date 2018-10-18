<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This file contains Federation, IdP and Profile classes.
 * These should be split into separate files later.
 *
 * @package Developer
 */
/**
 * 
 */

namespace core\common;

use Exception;

/**
 * This class represents an Entity in its widest sense. Every entity can log
 * and query/change the language settings where needed.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
abstract class Entity {

    const L_OK = 0;
    const L_REMARK = 4;
    const L_WARN = 32;
    const L_ERROR = 256;

    /**
     * We occasionally log stuff (debug/audit). Have an initialised Logging
     * instance nearby is sure helpful.
     * 
     * @var Logging
     */
    protected $loggerInstance;

    /**
     * access to language settings to be able to switch textDomain
     * 
     * @var Language
     */
    public $languageInstance;

    /**
     * initialise the entity.
     * 
     * Logs the start of lifetime of the entity to the debug log on levels 3 and higher.
     */
    public function __construct() {
        $this->loggerInstance = new Logging();
        $this->loggerInstance->debug(3, "--- BEGIN constructing class " . get_class($this) . " .\n");
        $this->languageInstance = new Language();
    }

    /**
     * destroys the entity.
     * 
     * Logs the end of lifetime of the entity to the debug log on level 5.
     */
    public function __destruct() {
        (new Logging())->debug(5, "--- KILL Destructing class " . get_class($this) . " .\n");
    }

    /**
     * This is a helper fuction to retrieve a value from two-dimensional arrays
     * The function tests if the value for the first indes is defined and then
     * the same with the second and finally returns the value
     * if something on the way is not defined, NULL is returned
     * 
     * @param array $attributeArray 
     * @param string|int $index1 
     * @param string|int $index2
     * @return mixed
     */
    public static function getAttributeValue($attributeArray, $index1, $index2) {
        if (isset($attributeArray[$index1]) && isset($attributeArray[$index1][$index2])) {
            return($attributeArray[$index1][$index2]);
        } else {
            return(NULL);
        }
    }

    /**
     * create a temporary directory and return the location
     * @param string $purpose one of 'installer', 'logo', 'test' defined the purpose of the directory
     * @param bool $failIsFatal decides if a creation failure should cause an error; defaults to true
     * @return array the tuple of: base path, absolute path for directory, directory name
     */
    public static function createTemporaryDirectory($purpose = 'installer', $failIsFatal = 1) {
        $loggerInstance = new Logging();
        $name = md5(time() . rand());
        $path = ROOT;
        switch ($purpose) {
            case 'silverbullet':
                $path .= '/var/silverbullet';
                break;
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

    /**
     * this direcory delete function has been copied from PHP documentation
     * 
     * @param string $dir name of the directory to delete
     */
    public static function rrmdir($dir) {
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                Entity::rrmdir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    /**
     * generates a UUID, for the devices which identify file contents by UUID
     *
     * @param string $prefix an extra prefix to set before the UUID
     * @return string UUID (possibly prefixed)
     */
    public static function uuid($prefix = '', $deterministicSource = NULL) {
        if ($deterministicSource === NULL) {
            $chars = md5(uniqid(mt_rand(), true));
        } else {
            $chars = md5($deterministicSource);
        }
        // these substr() are guaranteed to yield actual string data, as the
        // base string is an MD5 hash - has sufficient length
        $uuid = /** @scrutinizer ignore-type */ substr($chars, 0, 8) . '-';
        $uuid .= /** @scrutinizer ignore-type */ substr($chars, 8, 4) . '-';
        $uuid .= /** @scrutinizer ignore-type */ substr($chars, 12, 4) . '-';
        $uuid .= /** @scrutinizer ignore-type */ substr($chars, 16, 4) . '-';
        $uuid .= /** @scrutinizer ignore-type */ substr($chars, 20, 12);
        return $prefix . $uuid;
    }
    
        /**
     * produces a random string
     * @param int $length the length of the string to produce
     * @param string $keyspace the pool of characters to use for producing the string
     * @return string
     * @throws Exception
     */
    public static function randomString(
    $length, $keyspace = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ) {
        $str = '';
        $max = strlen($keyspace) - 1;
        if ($max < 1) {
            throw new Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

}
