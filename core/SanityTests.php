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
 * 
 * 
 * This is the definition of the CAT class implementing various configuration
 * tests. 
 * Each test is implemented as a priviate method which needs to be named "test_name_test".
 * The test returns the results by calling the testReturn method, this passing the return
 * code and the explanatory message. Multiple calls to testReturn are allowed.
 *
 * An individual test can be run by the "test" method which takes the test name as an argument
 * multiple tests should be run by the run_all_tests method which takes an array as an argument
 * see method descriptions for more information.
 * 
 * The results of the tests are passed within the $test_result array
 *
 * Some configuration of this class is required, see further down.
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Utilities
 */

namespace core;

use GeoIp2\Database\Reader;
use \Exception;

class SanityTests extends CAT
{
    /* in this section set current CAT requirements */

    /**
     * the minumum required php version 
     * 
     * @var string
     */
    private $needversionPHP = '7.2.0';

    /**
     * the minimum required simpleSAMLphp version
     * 
     * @var array
     */
    private $needversionSSP = ['major' => 1, 'minor' => 15];

    /**
     * all required NSIS modules
     * 
     * @var array<string>
     */
    private $NSISModules = [
        "nsArray.nsh",
        "FileFunc.nsh",
        "LogicLib.nsh",
        "WordFunc.nsh",
        "FileFunc.nsh",
        "x64.nsh",
    ];

    /**
     * set $profile_option_ct to the number of rows returned by 
     * "SELECT * FROM profile_option_dict" 
     * to compare actual vs. expected database structure
     * 
     * @var integer
     */
    private $profileOptionCount;

    /**
     * set $view_admin_ct to the number of rows returned by "desc view_admin" 
     *
     * @var integer
     */
    private $viewAdminCount = 8;

    /* end of config */

    /**
     * array holding the output of all tests that were executed
     * 
     * @var array
     */
    public $out;

    /**
     * temporary storage for the name of the test as it is being run
     * 
     * @var string
     */
    public $name;
    
    /**
     * variable used to signal that no more tests are to be performed
     * 
     * @var boolean
     */
    public $fatalError = false;

    /**
     * initialise the tests. Includes counting the number of expected rows in the profile_option_dict table.
     */
    public function __construct()
    {
        parent::__construct();
        $this->test_result = [];
        $this->test_result['global'] = 0;
        // parse the schema file to find out the number of expected rows...
        $schema = file(dirname(dirname(__FILE__)) . "/schema/schema.sql");
        $this->profileOptionCount = 0;
        $passedTheWindmill = FALSE;
        foreach ($schema as $schemaLine) {
            if (preg_match("/^INSERT INTO \`profile_option_dict\` VALUES/", $schemaLine)) {
                $passedTheWindmill = TRUE;
                continue;
            }
            if ($passedTheWindmill) {
                if (substr($schemaLine, 0, 1) == '(') { // a relevant line in schema
                    $this->profileOptionCount = $this->profileOptionCount + 1;
                } else { // anything else, quit parsing
                    break;
                }
            }
        }
    }

    /**
     * The single test wrapper
     * @param string $test the test name
     * @return void
     */
    public function runTest($test)
    {
        $this->out[$test] = [];
        $this->name = $test;
        $m_name = 'test' . $test;
        $this->test_result[$test] = 0;
        if (!method_exists($this, $m_name)) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Configuration error, no test configured for <strong>$test</strong>.");
            return;
        }
        $this->$m_name();
    }

    /**
     * The multiple tests wrapper
     * @param array $Tests the tests array is a simple string array, where each 
     *                     entry is a test name. The test names can also be 
     *                     given in the format "test=>subtest", which defines a
     *                     conditional execution of the "subtest" if the "test"
     *                     was run earlier and returned a success.
     * @return void
     */
    public function runTests($Tests)
    {
        foreach ($Tests as $testName) {
            $matchArray = [];
            if (preg_match('/(.+)=>(.+)/', $testName, $matchArray)) {
                $tst = $matchArray[1];
                $subtst = $matchArray[2];
                if ($this->test_result[$tst] < \core\common\Entity::L_ERROR) {
                    $this->runTest($subtst);
                }
            } else {
                $this->runTest($testName);
            }
            if ($this->fatalError) {
                return;
            }
        }
    }

    /**
     * enumerates the tests which are defined
     * 
     * @return array
     */
    public function getTestNames()
    {
        $T = get_class_methods($this);
        $out = [];
        foreach ($T as $t) {
            if (preg_match('/^test(.*)$/', $t, $m)) {
                $out[] = $m[1];
            }
        }
        return $out;
    }

    /**
     * This array is used to return the test results.
     * As the 'global' entry it returns the maximum return value
     * from all tests.
     * Individual tests results are teturned as separate entires
     * indexed by test names; each value is an array passing "level" and "message"
     * from each of the tests.
     * $test_result is set by the testReturn method
     *
     * @var array $test_result
     */
    public $test_result;

    /**
     * stores the result of a given test in standardised format
     * 
     * @param int    $level   severity level of the result
     * @param string $message verbal description of the result
     * @return void
     */
    private function storeTestResult($level, $message)
    {
        $this->out[$this->name][] = ['level' => $level, 'message' => $message];
        $this->test_result[$this->name] = max($this->test_result[$this->name], $level);
        $this->test_result['global'] = max($this->test_result['global'], $level);
    }

    /**
     * finds out if a path name is configured as an absolute path or only implicit (e.g. is in $PATH)
     * @param string $pathToCheck the path to check
     * @return array
     */
    private function getExecPath($pathToCheck)
    {
        $the_path = "";
        $exec_is = "UNDEFINED";

        foreach ([\config\Master::PATHS, \config\ConfAssistant::PATHS, \config\Diagnostics::PATHS] as $config) {
            if (!empty($config[$pathToCheck])) {
                $the_path = $config[$pathToCheck];
                if (substr($the_path, 0, 1) == "/") {
                    $exec_is = "EXPLICIT";
                } else {
                    $exec_is = "IMPLICIT";
                }
                return(['exec' => $the_path, 'exec_is' => $exec_is]);
            }
        }
        return(['exec' => $the_path, 'exec_is' => $exec_is]);
    }

    /**
     *  Test for php version
     * 
     * @return void
     */
    private function testPhp()
    {
        if (version_compare(phpversion(), $this->needversionPHP, '>=')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "<strong>PHP</strong> is sufficiently recent. You are running " . phpversion() . ".");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>PHP</strong> is too old. We need at least $this->needversionPHP, but you only have " . phpversion() . ".");
        }
    }
    
    /**
     * Check if configuration constants from the template are set
     * in the correcponding config file
     * 
     * @param string $config file basename
     * @return array $failResults
     */
    private function runConstantsTest($config)
    {
        $templateConfig = file_get_contents(ROOT . "/config/$config-template.php");
        $newTemplateConfig = preg_replace("/class *$config/", "class $config" . "_template", $templateConfig);
        file_put_contents(ROOT . "/var/tmp/$config-template.php", $newTemplateConfig);
        include(ROOT . "/var/tmp/$config-template.php");
        unlink(ROOT . "/var/tmp/$config-template.php");
        $rft = new \ReflectionClass("\config\\$config" . "_template");
        $templateConstants = $rft->getConstants();
        $failResults = [];
        foreach ($templateConstants as $constant => $value) {
            try {
                $m = constant("\config\\$config::$constant");
            } catch (Exception $e) {
                $failResults[] = "\config\\$config::$constant";
            }
        }
        return $failResults;
    }

    /**
     * Check if all required constants are set
     */
    private function testConfigConstants() {
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, $severity, $severity, $file, $line);
        });
        
        $failCount = 0;
        
        foreach (["Master", "ConfAssistant", "Diagnostics"] as $conf) {
            $failResults = $this->runConstantsTest($conf);
            $failCount = $failCount + count($failResults);
            if (count($failResults) > 0) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, 
                    "<strong>The following constants are not set:</strong>" . implode(', ', $failResults));
            }
        }
        
        restore_error_handler();
        if ($failCount == 0) {
            $this->storeTestResult(\core\common\Entity::L_OK, "<strong>All config constants set</strong>");
        } else {
            $this->fatalError = true;
        }
    }
    /**
     * set for cat_base_url setting
     * 
     * @return void
     */
    private function testCatBaseUrl()
    {
        $rootUrl = substr(\config\Master::PATHS['cat_base_url'], -1) === '/' ? substr(\config\Master::PATHS['cat_base_url'], 0, -1) : \config\Master::PATHS['cat_base_url'];
        preg_match('/(^.*)\/admin\/112365365321.php/', $_SERVER['SCRIPT_NAME'], $m);
        if ($rootUrl === $m[1]) {
            $this->storeTestResult(\core\common\Entity::L_OK, "<strong>cat_base_url</strong> set correctly");
        } else {
            $rootFromScript = $m[1] === '' ? '/' : $m[1];
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>cat_base_url</strong> is set to <strong>" . \config\Master::PATHS['cat_base_url'] . "</strong> and should be <strong>$rootFromScript</strong>");
        }
    }

    /**
     * check whether the configured RADIUS hosts actually exist
     * 
     * @return void
     */
    private function testRADIUSProbes()
    {
        $probeReturns = [];
        foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $oneProbe) {
            $statusServer = new diag\RFC5997Tests($oneProbe['ip'], 1812, $oneProbe['secret']);
            if ($statusServer->statusServerCheck() !== diag\AbstractTest::RETVAL_OK) {
                $probeReturns[] = $oneProbe['display_name'];
            }
        }
        if (count($probeReturns) == 0) {
            $this->storeTestResult(common\Entity::L_OK, "All configured RADIUS/UDP probes are reachable.");
        } else {
            $this->storeTestResult(common\Entity::L_ERROR, "The following RADIUS probes are NOT reachable: " . implode(', ', $probeReturns));
        }
    }

    /**
     * test for simpleSAMLphp
     * 
     * @return void
     */
    private function testSsp()
    {
        if (!is_file(\config\Master::AUTHENTICATION['ssp-path-to-autoloader'])) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>simpleSAMLphp</strong> not found!");
        } else {
            include_once \config\Master::AUTHENTICATION['ssp-path-to-autoloader'];
            $SSPconfig = \SimpleSAML\Configuration::getInstance();
            $sspVersion = explode('.', $SSPconfig->getVersion());
            if ((int) $sspVersion[0] >= $this->needversionSSP['major'] && (int) $sspVersion[1] >= $this->needversionSSP['minor']) {
                $this->storeTestResult(\core\common\Entity::L_OK, "<strong>simpleSAMLphp</strong> is sufficently recent. You are running " . implode('.', $sspVersion));
            } else {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>simpleSAMLphp</strong> is too old. We need at least " . implode('.', $this->needversionSSP));
            }
        }
    }

    /**
     * test for security setting
     * 
     * @return void
     */
    private function testSecurity()
    {
        if (in_array("I do not care about security!", \config\Master::SUPERADMINS)) {
            $this->storeTestResult(\core\common\Entity::L_WARN, "You do not care about security. This page should be made accessible to the CAT admin only! See config/Master.php: 'SUPERADMINS'!");
        }
    }

    /**
     * test if zip is available
     * 
     * @return void
     */
    private function testZip()
    {
        $A = $this->getExecPath('zip');
        if ($A['exec'] != "") {
            $fullOutput = [];
            $t = exec($A['exec'] . ' --version', $fullOutput);
            if ($A['exec_is'] == "EXPLICIT") {
                $this->storeTestResult(\core\common\Entity::L_OK, "<strong>".$fullOutput[1]."</strong> was found and is configured explicitly in your config.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_WARN, "<strong>".$fullOutput[1]."</strong> was found, but is not configured with an absolute path in your config.");
            }
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>zip</strong> was not found on your system!");
        }
    }

    /**
     * test if eapol_test is available and recent enough
     * 
     * @return void
     */
    private function testEapoltest()
    {
        exec(\config\Diagnostics::PATHS['eapol_test'], $out, $retval);
        if ($retval == 255) {
            $o = preg_grep('/-o<server cert/', $out);
            if (count($o) > 0) {
                $this->storeTestResult(\core\common\Entity::L_OK, "<strong>eapol_test</strong> script found.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>eapol_test</strong> found, but is too old!");
            }
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>eapol_test</strong> not found!");
        }
    }

    /**
     * test if logdir exists and is writable
     * 
     * @return void
     */
    private function testLogdir()
    {
        if (fopen(\config\Master::PATHS['logdir'] . "/debug.log", "a") == FALSE) {
            $this->storeTestResult(\core\common\Entity::L_WARN, "Log files in <strong>" . \config\Master::PATHS['logdir'] . "</strong> are not writable!");
        } else {
            $this->storeTestResult(\core\common\Entity::L_OK, "Log directory is writable.");
        }
    }

    /**
     * test for required PHP modules
     * 
     * @return void
     */
    private function testPhpModules()
    {
        if (function_exists('idn_to_ascii')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP can handle internationalisation.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP can <strong>NOT</strong> handle internationalisation (idn_to_ascii() from php7.0-intl).");
        }

        if (function_exists('gettext')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>GNU Gettext</strong> is installed.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GNU Gettext</strong> not found!");
        }

        if (function_exists('openssl_sign')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>OpenSSL</strong> is installed.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>OpenSSL</strong> not found!");
        }

        if (class_exists('\\Gmagick')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>Gmagic</strong> is installed.");
        } elseif (class_exists('\\Imagick')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>Imagick</strong> is installed.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>Gmagic</strong> nor <strong>Imagic</stromg> not found!");
        }
        if (function_exists('ImageCreate')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>GD</strong> is installed.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GD</strong> not found!</a>.");
        }

        if (function_exists('mysqli_connect')) {
            $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>MySQL</strong> is installed.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>MySQL</strong> not found!");
        }
    }

    /**
     * test if GeoIP is installed correctly
     * 
     * @return void
     */
    private function testGeoip()
    {
        $host_4 = '145.0.2.50';
        $host_6 = '2001:610:188:444::50';
        switch (\config\Master::GEOIP['version']) {
            case 0:
                $this->storeTestResult(\core\common\Entity::L_REMARK, "As set in the config, no geolocation service will be used");
                break;
            case 1:
                if (!function_exists('geoip_record_by_name')) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) not found! Get it from your distribution or <a href='http://pecl.php.net/package/geoip'>here</a> or better install GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                    return;
                }
                $record = geoip_record_by_name($host_4);
                if ($record === FALSE) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                if ($record['city'] != 'Utrecht') {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                $this->storeTestResult(\core\common\Entity::L_REMARK, "PHP extension <strong>GeoIP</strong> (legacy) is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly. We stronly advise to replace the legacy GeoIP with GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                break;
            case 2:
                if (!is_file(\config\Master::GEOIP['geoip2-path-to-autoloader'])) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> not found! Get it from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                    return;
                }
                if (!is_file(\config\Master::GEOIP['geoip2-path-to-db'])) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>GeoIP2 database</strong> not found! See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                include_once \config\Master::GEOIP['geoip2-path-to-autoloader'];
                $reader = new Reader(\config\Master::GEOIP['geoip2-path-to-db']);
                try {
                    $record = $reader->city($host_4);
                } catch (Exception $e) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it to update the GeoIP database regularly.");
                    return;
                }
                if ($record->city->name != 'Utrecht') {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it to update the GeoIP database regularly.");
                    return;
                }
                try {
                    $record = $reader->city($host_6);
                } catch (Exception $e) {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                if ($record->city->name != 'Utrecht') {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                $this->storeTestResult(\core\common\Entity::L_OK, "PHP extension <strong>GeoIP2</strong> is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                break;
            default:
                $this->storeTestResult(\core\common\Entity::L_ERROR, 'Check \config\Master::GEOIP[\'version\'], it must be set to either 1 or 2');
                break;
        }
    }

    /**
     * test if openssl is available
     * 
     * @return void
     */
    private function testOpenssl()
    {
        $A = $this->getExecPath('openssl');
        if ($A['exec'] != "") {
            $t = exec($A['exec'] . ' version');
            if ($A['exec_is'] == "EXPLICIT") {
                $this->storeTestResult(\core\common\Entity::L_OK, "<strong>$t</strong> was found and is configured explicitly in your config.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_WARN, "<strong>$t</strong> was found, but is not configured with an absolute path in your config.");
            }
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>openssl</strong> was not found on your system!");
        }
    }

    /**
     * test if makensis is available
     * 
     * @return void
     */
    private function testMakensis()
    {
        if (!is_numeric(\config\ConfAssistant::NSIS_VERSION)) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "NSIS_VERSION needs to be numeric!");
            return;
        }
        if (\config\ConfAssistant::NSIS_VERSION < 2) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "NSIS_VERSION needs to be at least 2!");
            return;
        }
        $A = $this->getExecPath('makensis');
        if ($A['exec'] != "") {
            $t = exec($A['exec'] . ' -VERSION');
            if ($A['exec_is'] == "EXPLICIT") {
                $this->storeTestResult(\core\common\Entity::L_OK, "<strong>makensis $t</strong> was found and is configured explicitly in your config.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_WARN, "<strong>makensis $t</strong> was found, but is not configured with an absolute path in your config.");
            }
            $outputArray = [];
            exec($A['exec'] . ' -HELP', $outputArray);
            $t1 = count(preg_grep('/INPUTCHARSET/', $outputArray));
            if ($t1 == 1 && \config\ConfAssistant::NSIS_VERSION == 2) {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "Declared NSIS_VERSION does not seem to match the file pointed to by PATHS['makensis']!");
            }
            if ($t1 == 0 && \config\ConfAssistant::NSIS_VERSION >= 3) {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "Declared NSIS_VERSION does not seem to match the file pointed to by PATHS['makensis']!");
            }
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "<strong>makensis</strong> was not found on your system!");
        }
    }

    /**
     * test if all required NSIS modules are available
     * 
     * @return void
     */
    private function testNSISmodules()
    {
        $tmp_dir = \core\common\Entity::createTemporaryDirectory('installer', 0)['dir'];
        if (!chdir($tmp_dir)) {
            $this->loggerInstance->debug(2, "Cannot chdir to $tmp_dir\n");
            $this->storeTestResult(\core\common\Entity::L_ERROR, "NSIS modules test - problem with temporary directory permissions, cannot continue");
            return;
        }
        $exe = 'tt.exe';
        $NSIS_Module_status = [];
        foreach ($this->NSISModules as $module) {
            unset($out);
            exec(\config\ConfAssistant::PATHS['makensis'] . " -V1 '-X!include $module' '-XOutFile $exe' '-XSection X' '-XSectionEnd'", $out, $retval);
            if ($retval > 0) {
                $NSIS_Module_status[$module] = 0;
            } else {
                $NSIS_Module_status[$module] = 1;
            }
        }
        if (is_file($exe)) {
            unlink($exe);
        }
        foreach ($NSIS_Module_status as $module => $status) {
            if ($status == 1) {
                $this->storeTestResult(\core\common\Entity::L_OK, "NSIS module <strong>$module</strong> was found.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "NSIS module <strong>$module</strong> was not found or is not working correctly.");
            }
        }
    }

    /**
     * test access to dowloads directories
     * 
     * @return void
     */
    private function testDirectories()
    {
        $Dir1 = \core\common\Entity::createTemporaryDirectory('installer', 0);
        $dir1 = $Dir1['dir'];
        $base1 = $Dir1['base'];
        if ($dir1) {
            $this->storeTestResult(\core\common\Entity::L_OK, "Installer cache directory is writable.");
            \core\common\Entity::rrmdir($dir1);
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Installer cache directory $base1 does not exist or is not writable!");
            $this->fatalError = true;
        }
        $Dir2 = \core\common\Entity::createTemporaryDirectory('test', 0);
        $dir2 = $Dir2['dir'];
        $base2 = $Dir2['base'];
        if ($dir2) {
            $this->storeTestResult(\core\common\Entity::L_OK, "Test directory is writable.");
            \core\common\Entity::rrmdir($dir2);
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Test directory $base2 does not exist or is not writable!");
            $this->fatalError = true;
        }
        $Dir3 = \core\common\Entity::createTemporaryDirectory('logo', 0);
        $dir3 = $Dir3['dir'];
        $base3 = $Dir3['base'];
        if ($dir3) {
            $this->storeTestResult(\core\common\Entity::L_OK, "Logos cache directory is writable.");
            \core\common\Entity::rrmdir($dir3);
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Logos cache directory $base3 does not exist or is not writable!");
        }
    }

    /**
     * test if all required locales are enabled
     * 
     * @return void
     */
    private function testLocales()
    {
        $locales = shell_exec("locale -a");
        $allthere = "";
        foreach (\config\Master::LANGUAGES as $onelanguage) {
            if (preg_match("/" . $onelanguage['locale'] . "/", $locales) == 0) {
                $allthere .= $onelanguage['locale'] . " ";
            }
        }
        if ($allthere == "") {
            $this->storeTestResult(\core\common\Entity::L_OK, "All of your configured locales are available on your system.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_WARN, "Some of your configured locales (<strong>$allthere</strong>) are not installed and will not be displayed correctly!");
        }
    }

    const DEFAULTS = [
        ["SETTING" => \config\Master::APPEARANCE['from-mail'],
            "DEFVALUE" => "cat-invite@your-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/from-mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['support-contact']['url'],
            "DEFVALUE" => "cat-support@our-cat-installation.example?body=Only%20English%20language%20please!",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/url ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['support-contact']['display'],
            "DEFVALUE" => "cat-support@our-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/display ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['support-contact']['developer-mail'],
            "DEFVALUE" => "cat-develop@our-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['abuse-mail'],
            "DEFVALUE" => "my-abuse-contact@your-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/abuse-mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['MOTD'],
            "DEFVALUE" => "Release Candidate. All bugs to be shot on sight!",
            "COMPLAINTSTRING" => "APPEARANCE/MOTD ",
            "REQUIRED" => FALSE,],
        ["SETTING" => \config\Master::APPEARANCE['webcert_CRLDP'],
            "DEFVALUE" => ['list', 'of', 'CRL', 'pointers'],
            "COMPLAINTSTRING" => "APPEARANCE/webcert_CRLDP ",
            "REQUIRED" => TRUE,],
        ["SETTING" => \config\Master::APPEARANCE['webcert_OCSP'],
            "DEFVALUE" => ['list', 'of', 'OCSP', 'pointers'],
            "COMPLAINTSTRING" => "APPEARANCE/webcert_OCSP ",
            "REQUIRED" => TRUE,],
        ["SETTING" => \config\Master::DB['INST']['host'],
            "DEFVALUE" => "db.host.example",
            "COMPLAINTSTRING" => "DB/INST ",
            "REQUIRED" => TRUE,],
        ["SETTING" => \config\Master::DB['INST']['host'],
            "DEFVALUE" => "db.host.example",
            "COMPLAINTSTRING" => "DB/USER ",
            "REQUIRED" => TRUE,],
        ["SETTING" => \config\Master::DB['EXTERNAL']['host'],
            "DEFVALUE" => "customerdb.otherhost.example",
            "COMPLAINTSTRING" => "DB/EXTERNAL ",
            "REQUIRED" => FALSE,],
    ];

    /**
     * test if defaults in the config have been replaced with some real values
     * 
     * @return void
     */
    private function testDefaults()
    {
        $defaultvalues = "";
        $missingvalues = "";
        // all the checks for equality with a shipped default value
        foreach (SanityTests::DEFAULTS as $oneCheckItem) {
            if ($oneCheckItem['REQUIRED'] && !$oneCheckItem['SETTING']) {
                $missingvalues .= $oneCheckItem["COMPLAINTSTRING"];
            } elseif ($oneCheckItem['SETTING'] == $oneCheckItem["DEFVALUE"]) {
                $defaultvalues .= $oneCheckItem["COMPLAINTSTRING"];
            }
        }
        // additional checks for defaults, which are not simple equality checks
        if (isset(\config\Diagnostics::RADIUSTESTS['UDP-hosts'][0]) && \config\Diagnostics::RADIUSTESTS['UDP-hosts'][0]['ip'] == "192.0.2.1") {
            $defaultvalues .= "RADIUSTESTS/UDP-hosts ";
        }


        if (isset(\config\Diagnostics::RADIUSTESTS['TLS-clientcerts'])) {
            foreach (\config\Diagnostics::RADIUSTESTS['TLS-clientcerts'] as $cadata) {
                foreach ($cadata['certificates'] as $cert_files) {
                    if (file_get_contents(ROOT . "/config/cli-certs/" . $cert_files['public']) === FALSE) {
                        $defaultvalues .= "CERTIFICATE/" . $cert_files['public'] . " ";
                    }
                    if (file_get_contents(ROOT . "/config/cli-certs/" . $cert_files['private']) === FALSE) {
                        $defaultvalues .= "CERTIFICATE/" . $cert_files['private'] . " ";
                    }
                }
            }
        }

        if ($defaultvalues != "") {
            $this->storeTestResult(\core\common\Entity::L_WARN, "Your configuration in config/config.php contains unchanged default values or links to inexistent files: <strong>$defaultvalues</strong>!");
        } else {
            $this->storeTestResult(\core\common\Entity::L_OK, "Your configuration does not contain any unchanged defaults, which is a good sign.");
        }
    }

    /**
     * test access to databases
     * 
     * @return void
     */
    private function testDatabases()
    {
        $databaseName1 = 'INST';
        try {
            $db1 = DBConnection::handle($databaseName1);
            $res1 = $db1->exec('SELECT * FROM profile_option_dict');
            if ($res1->num_rows == $this->profileOptionCount) {
                $this->storeTestResult(\core\common\Entity::L_OK, "The $databaseName1 database appears to be OK.");
            } else {
                $this->storeTestResult(\core\common\Entity::L_ERROR, "The $databaseName1 database is reacheable but probably not updated to this version of CAT.");
            }
        } catch (Exception $e) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Connection to the  $databaseName1 database failed");
        }

        $databaseName2 = 'USER';
        try {
            $db2 = DBConnection::handle($databaseName2);
            if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                $res2 = $db2->exec('desc view_admin');
                if ($res2->num_rows == $this->viewAdminCount) {
                    $this->storeTestResult(\core\common\Entity::L_OK, "The $databaseName2 database appears to be OK.");
                } else {
                    $this->storeTestResult(\core\common\Entity::L_ERROR, "The $databaseName2 is reacheable but there is something wrong with the schema");
                }
            } else {
                $this->storeTestResult(\core\common\Entity::L_OK, "The $databaseName2 database appears to be OK.");
            }
        } catch (Exception $e) {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Connection to the  $databaseName2 database failed");
        }

        $databaseName3 = 'EXTERNAL';
        if (!empty(\config\Master::DB[$databaseName3])) {
            try {
                $db3 = DBConnection::handle($databaseName3);
                if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                    $res3 = $db3->exec('desc view_admin');
                    if ($res3->num_rows == $this->viewAdminCount) {
                        $this->storeTestResult(\core\common\Entity::L_OK, "The $databaseName3 database appears to be OK.");
                    } else {
                        $this->storeTestResult(\core\common\Entity::L_ERROR, "The $databaseName3 is reacheable but there is something wrong with the schema");
                    }
                } else {
                    $this->storeTestResult(\core\common\Entity::L_OK, "The $databaseName3 database appears to be OK.");
                }
            } catch (Exception $e) {

                $this->storeTestResult(\core\common\Entity::L_ERROR, "Connection to the  $databaseName3 database failed");
            }
        }
    }

    /**
     * test devices.php for the no_cache option
     * 
     * @return void
     */
    private function testDeviceCache()
    {
        if ((!empty(\devices\Devices::$Options['no_cache'])) && \devices\Devices::$Options['no_cache']) {
            $global_no_cache = 1;
        } else {
            $global_no_cache = 0;
        }

        if ($global_no_cache == 1) {
            $this->storeTestResult(\core\common\Entity::L_WARN, "Devices no_cache global option is set, this is not a good idea in a production setting\n");
        }
        $Devs = \devices\Devices::listDevices();
        $no_cache_dev = '';
        $no_cache_dev_count = 0;
        if ($global_no_cache) {
            foreach ($Devs as $dev => $D) {
                if (empty($D['options']['no_cache']) || $D['options']['no_cache'] != 0) {
                    $no_cache_dev .= $dev . " ";
                    $no_cache_dev_count++;
                }
            }
        } else {
            foreach ($Devs as $dev => $D) {
                if (!empty($D['options']['no_cache']) && $D['options']['no_cache'] != 0) {
                    $no_cache_dev .= $dev . " ";
                    $no_cache_dev_count++;
                }
            }
        }


        if ($no_cache_dev_count > 1) {
            $this->storeTestResult(\core\common\Entity::L_WARN, "The following devices will not be cached: $no_cache_dev");
        }
        if ($no_cache_dev_count == 1) {
            $this->storeTestResult(\core\common\Entity::L_WARN, "The following device will not be cached: $no_cache_dev");
        }
    }

    /**
     * test if mailer works
     * 
     * @return void
     */
    private function testMailer()
    {
        if (empty(\config\Master::APPEARANCE['abuse-mail']) || \config\Master::APPEARANCE['abuse-mail'] == "my-abuse-contact@your-cat-installation.example") {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "Your abuse-mail has not been set, cannot continue with mailer tests.");
            return;
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Host = \config\Master::MAILSETTINGS['host'];
        $mail->Username = \config\Master::MAILSETTINGS['user'];
        $mail->Password = \config\Master::MAILSETTINGS['pass'];
        $mail->SMTPOptions = \config\Master::MAILSETTINGS['options'];
        $mail->WordWrap = 72;
        $mail->isHTML(FALSE);
        $mail->CharSet = 'UTF-8';
        $mail->From = \config\Master::APPEARANCE['from-mail'];
        $mail->FromName = \config\Master::APPEARANCE['productname'] . " Invitation System";
        $mail->addAddress(\config\Master::APPEARANCE['abuse-mail']);
        $mail->Subject = "testing CAT configuration mail";
        $mail->Body = "Testing CAT mailing\n";
        $sent = $mail->send();
        if ($sent) {
            $this->storeTestResult(\core\common\Entity::L_OK, "mailer settings appear to be working, check " . \config\Master::APPEARANCE['abuse-mail'] . " mailbox if the message was receiced.");
        } else {
            $this->storeTestResult(\core\common\Entity::L_ERROR, "mailer settings failed, check the Config::MAILSETTINGS");
        }
    }

    /**
     * TODO test if RADIUS connections work
     * 
     * @return void
     */
    private function testUDPhosts()
    {
//        if(empty)
    }
}
