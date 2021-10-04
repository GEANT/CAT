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
abstract class Entity
{

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
     * keep internal track of the gettext catalogue that was used outside the
     * class call
     * 
     * @var array
     */
    protected static $gettextCatalogue;

    /**
     * the custom displayable variant of the term 'federation'
     * @var string
     */
    public static $nomenclature_fed;

    /**
     * the custom displayable variant of the term 'institution'
     * @var string
     */
    public static $nomenclature_idp;

    /**
     * the custom displayable variant of the term "hotspot"
     * @var string
     */
    public static $nomenclature_hotspot;

    /**
     * the custom displayable variant of the term "participating organisation"
     * @var string
     */
    public static $nomenclature_participant;

    /**
     * initialise the entity. 
     * 
     * Logs the start of lifetime of the entity to the debug log on levels 3 and higher.
     * 
     * @throws Exception
     */
    public function __construct()
    {
        $this->loggerInstance = new Logging();
        $this->loggerInstance->debug(3, "--- BEGIN constructing class " . get_class($this) . " .\n");
        $this->languageInstance = new Language();
        Entity::intoThePotatoes("core");
        // some config elements are displayable. We need some dummies to 
        // translate the common values for them. If a deployment chooses a 
        // different wording, no translation, sorry

        $dummy_NRO = _("National Roaming Operator");
        $dummy_idp1 = _("identity provider");
        $dummy_idp2 = _("organisation");
        $dummy_idp3 = _("Identity Provider");
        $dummy_hotspot1 = _("Wi-Fi Hotspot");
        $dummy_hotspot2 = _("Hotspot");
        $dummy_hotspot3 = _("Service Provider");
        $dummy_organisation1 = _("participant");
        $dummy_organisation2 = _("organisation");
        $dummy_organisation2a = _("organization");
        $dummy_organisation3 = _("entity");
        // and do something useless with the strings so that there's no "unused" complaint
        if (strlen($dummy_NRO . $dummy_idp1 . $dummy_idp2 . $dummy_idp3 . $dummy_hotspot1 . $dummy_hotspot2 . $dummy_hotspot3 . $dummy_organisation1 . $dummy_organisation2 . $dummy_organisation2a . $dummy_organisation3) < 0) {
            throw new Exception("Strings are usually not shorter than 0 characters. We've encountered a string blackhole.");
        }
        $xyzVariableFed = \config\ConfAssistant::CONSORTIUM['nomenclature_federation'] . "";
        $xyzVariableIdP = \config\ConfAssistant::CONSORTIUM['nomenclature_idp'] . "";
        $xyzVariableHotspot = \config\ConfAssistant::CONSORTIUM['nomenclature_hotspot'] . "";
        $xyzVariableParticipant = \config\ConfAssistant::CONSORTIUM['nomenclature_participant'] . "";
        Entity::$nomenclature_fed = _($xyzVariableFed);
        Entity::$nomenclature_idp = _($xyzVariableIdP);
        Entity::$nomenclature_hotspot = _($xyzVariableHotspot);
        Entity::$nomenclature_participant = _($xyzVariableParticipant);

        Entity::outOfThePotatoes();
    }

    /**
     * destroys the entity.
     * 
     * Logs the end of lifetime of the entity to the debug log on level 5.
     */
    public function __destruct()
    {
        (new Logging())->debug(5, "--- KILL Destructing class " . get_class($this) . " .\n");
    }

    /**
     * This is a helper fuction to retrieve a value from two-dimensional arrays
     * The function tests if the value for the first indes is defined and then
     * the same with the second and finally returns the value
     * if something on the way is not defined, NULL is returned
     * 
     * @param array      $attributeArray the array to search in
     * @param string|int $index1         first-level index to check
     * @param string|int $index2         second-level index to check
     * @return mixed
     */
    public static function getAttributeValue($attributeArray, $index1, $index2)
    {
        if (isset($attributeArray[$index1]) && isset($attributeArray[$index1][$index2])) {
            return($attributeArray[$index1][$index2]);
        } else {
            return(NULL);
        }
    }

    /**
     * create a temporary directory and return the location
     * @param string  $purpose     one of 'installer', 'logo', 'test' defined the purpose of the directory
     * @param boolean $failIsFatal decides if a creation failure should cause an error; defaults to true
     * @return array the tuple of: base path, absolute path for directory, directory name
     * @throws Exception
     */
    public static function createTemporaryDirectory($purpose = 'installer', $failIsFatal = 1)
    {
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
     * @return void
     */
    public static function rrmdir($dir)
    {
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
     * @param string $prefix              an extra prefix to set before the UUID
     * @param mixed  $deterministicSource don't generate a random UUID, base it deterministically on the provided input
     * @return string UUID (possibly prefixed)
     */
    public static function uuid($prefix = '', $deterministicSource = NULL)
    {
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
     * @param int    $length   the length of the string to produce
     * @param string $keyspace the pool of characters to use for producing the string
     * @return string
     * @throws Exception
     */
    public static function randomString(
            $length, $keyspace = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    )
    {
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

    /**
     * Finds out which gettext catalogue has the translations for the caller
     * 
     * @param boolean $showTrace print a full backtrace of how we got here, only for debugging auto-detect problems
     * @return string the catalogue
     */
    private static function determineOwnCatalogue($showTrace = FALSE)
    {
        $trace = debug_backtrace();
        $caller = [];
        // find the first caller in the stack trace which is NOT "Entity" itself
        // this means walking back from the end of the trace to the penultimate
        // index before something with "Entity" comes in
        for ($i = count($trace); $i--; $i > 0) {
            if (isset($trace[$i - 1]['class']) && preg_match('/Entity/', $trace[$i - 1]['class'])) {
                if ($showTrace) {
                    echo "FOUND caller: " . /** @scrutinizer ignore-type */ print_r($trace[$i], true) . " - class is " . $trace[$i]['class'];
                }
                $caller = $trace[$i];
                break;
            }
        }
        // if called from a class, guess based on the class name; 
        // otherwise, on the filename relative to ROOT
        $myName = $caller['class'] ?? substr($caller['file'], strlen(ROOT));
        if ($showTrace === TRUE) {
            echo "<pre>" . /** @scrutinizer ignore-type */ print_r($trace, true) . "</pre>";
            echo "CLASS = " . $myName . "<br/>";
        }
        if (preg_match("/diag/", $myName) == 1) {
            $ret = "diagnostics";
        } elseif (preg_match("/core/", $myName) == 1) {
            $ret = "core";
        } elseif (preg_match("/common/", $myName) == 1) {
            $ret = "core";
        } elseif (preg_match("/devices/", $myName) == 1) {
            $ret = "devices";
        } elseif (preg_match("/admin/", $myName) == 1) {
            $ret = "web_admin";
        } else {
            $ret = "web_user";
        }
        return $ret;
    }

    /**
     * sets the language catalogue to one matching the gettext segmentation of
     * source files. Also memorises the previous catalogue so that it can be
     * restored later on.
     * 
     * @param string  $catalogue the catalogue to select, overrides detection
     * @param boolean $trace     if we need to debug the automatic detection heuristics, turn this on: it prints a debug of the stack trace
     * @return void
     */
    public static function intoThePotatoes($catalogue = NULL, $trace = FALSE)
    {
        // array_push, without the function call overhead
        Entity::$gettextCatalogue[] = textdomain(NULL);
        if ($catalogue === NULL) {
            $theCatalogue = Entity::determineOwnCatalogue($trace);
            textdomain($theCatalogue);
            bindtextdomain($theCatalogue, ROOT . "/translation/");
        } else {
            textdomain($catalogue);
            bindtextdomain($catalogue, ROOT . "/translation/");
        }
    }

    /**
     * restores the previous language catalogue.
     * 
     * @return void
     * @throws Exception
     */
    public static function outOfThePotatoes()
    {
        $restoreCatalogue = array_pop(Entity::$gettextCatalogue);
        if ($restoreCatalogue === NULL) {
            throw new Exception("Unable to restore previous catalogue - outOfThePotatoes called too often?!");
        }
        textdomain($restoreCatalogue);
    }

    /**
     * for debugging only
     * 
     * @return array the stack of language contexts
     */
    public static function potatoStack()
    {
        $debugArray = Entity::$gettextCatalogue;
        array_push($debugArray, Entity::determineOwnCatalogue());
        return $debugArray;
    }
}