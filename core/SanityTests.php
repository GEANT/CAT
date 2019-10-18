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

require_once dirname(dirname(__FILE__)) . "/config/_config.php";
require_once dirname(dirname(__FILE__)) . "/core/PHPMailer/src/PHPMailer.php";
require_once dirname(dirname(__FILE__)) . "/core/PHPMailer/src/SMTP.php";

class SanityTests extends CAT
{
    /* in this section set current CAT requirements */

    /* $php_needversion sets the minumum required php version */

    // because of bug:
    // Fixed bug #74005 (mail.add_x_header causes RFC-breaking lone line feed).
    private $php_needversion = '7.2.0';
    private $ssp_needversion = ['major' => 1, 'minor' => 15];


    /* List all required NSIS modules below */
    private $NSIS_Modules = [
        "nsArray.nsh",
        "FileFunc.nsh",
        "LogicLib.nsh",
        "WordFunc.nsh",
        "FileFunc.nsh",
        "x64.nsh",
    ];

    /* set $profile_option_ct to the number of rows returned by "SELECT * FROM profile_option_dict" */
    private $profile_option_ct;
    /* set $view_admin_ct to the number of rows returned by "desc view_admin" */
    private $view_admin_ct = 8;

    /* end of config */
    public $out;
    public $name;

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
        $this->profile_option_ct = 0;
        $passedTheWindmill = FALSE;
        foreach ($schema as $schemaLine) {
            if (preg_match("/^INSERT INTO \`profile_option_dict\` VALUES/", $schemaLine)) {
                $passedTheWindmill = TRUE;
                continue;
            }
            if ($passedTheWindmill) {
                if (substr($schemaLine, 0, 1) == '(') { // a relevant line in schema
                    $this->profile_option_ct = $this->profile_option_ct + 1;
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
    public function test($test)
    {
        $this->out[$test] = [];
        $this->name = $test;
        $m_name = $test . '_test';
        $this->test_result[$test] = 0;
        if (!method_exists($this, $m_name)) {
            $this->testReturn(\core\common\Entity::L_ERROR, "Configuration error, no test configured for <strong>$test</strong>.");
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
    public function run_tests($Tests)
    {
        foreach ($Tests as $testName) {
            $matchArray = [];
            if (preg_match('/(.+)=>(.+)/', $testName, $matchArray)) {
                $tst = $matchArray[1];
                $subtst = $matchArray[2];
                if ($this->test_result[$tst] < \core\common\Entity::L_ERROR) {
                    $this->test($subtst);
                }
            } else {
                $this->test($testName);
            }
        }
    }

    /**
     * enumerates the tests which are defined
     * 
     * @return array
     */
    public function get_test_names()
    {
        $T = get_class_methods($this);
        $out = [];
        foreach ($T as $t) {
            if (preg_match('/^(.*)_test$/', $t, $m)) {
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
    private function testReturn($level, $message)
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
        foreach ([CONFIG, CONFIG_CONFASSISTANT, CONFIG_DIAGNOSTICS] as $config) {
            if (!empty($config['PATHS'][$pathToCheck])) {
                $the_path = $config['PATHS'][$pathToCheck];
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
    private function php_test()
    {
        if (version_compare(phpversion(), $this->php_needversion, '>=')) {
            $this->testReturn(\core\common\Entity::L_OK, "<strong>PHP</strong> is sufficiently recent. You are running " . phpversion() . ".");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>PHP</strong> is too old. We need at least $this->php_needversion, but you only have " . phpversion() . ".");
        }
    }

    /**
     * set for cat_base_url setting
     * 
     * @return void
     */
    private function cat_base_url_test()
    {
        $rootUrl = substr(CONFIG['PATHS']['cat_base_url'], -1) === '/' ? substr(CONFIG['PATHS']['cat_base_url'], 0, -1) : CONFIG['PATHS']['cat_base_url'];
        preg_match('/(^.*)\/admin\/112365365321.php/', $_SERVER['SCRIPT_NAME'], $m);
        if ($rootUrl === $m[1]) {
            $this->testReturn(\core\common\Entity::L_OK, "<strong>cat_base_url</strong> set correctly");
        } else {
            $rootFromScript = $m[1] === '' ? '/' : $m[1];
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>cat_base_url</strong> is set to <strong>" . CONFIG['PATHS']['cat_base_url'] . "</strong> and should be <strong>$rootFromScript</strong>");
        }
    }

    /**
     * test for simpleSAMLphp
     * 
     * @return void
     */
    private function ssp_test()
    {
        if (!is_file(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader'])) {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>simpleSAMLphp</strong> not found!");
        } else {
            include_once CONFIG['AUTHENTICATION']['ssp-path-to-autoloader'];
            $SSPconfig = \SimpleSAML_Configuration::getInstance();
            $sspVersion = explode('.', $SSPconfig->getVersion());
            if ((int) $sspVersion[0] >= $this->ssp_needversion['major'] && (int) $sspVersion[1] >= $this->ssp_needversion['minor']) {
                $this->testReturn(\core\common\Entity::L_OK, "<strong>simpleSAMLphp</strong> is sufficently recent. You are running " . implode('.', $sspVersion));
            } else {
                $this->testReturn(\core\common\Entity::L_ERROR, "<strong>simpleSAMLphp</strong> is too old. We need at least " . implode('.', $this->ssp_needversion));
            }
        }
    }

    /**
     * test for security setting
     * 
     * @return void
     */
    private function security_test()
    {
        if (in_array("I do not care about security!", CONFIG['SUPERADMINS'])) {
            $this->testReturn(\core\common\Entity::L_WARN, "You do not care about security. This page should be made accessible to the CAT admin only! See config-master.php: 'SUPERADMINS'!");
        }
    }

    /**
     * test if zip is available
     * 
     * @return void
     */
    private function zip_test()
    {
        $output = [];
        $retval = -100;
        exec("zip", $output, $retval);
        if (count($output) > 0 && $retval == 0) {
            $this->testReturn(\core\common\Entity::L_OK, "<strong>zip</strong> binary found.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>zip</strong> not found in your \$PATH!");
        }
    }

    /**
     * test if eapol_test is available and recent enough
     * 
     * @return void
     */
    private function eapol_test_test()
    {
        exec(CONFIG_DIAGNOSTICS['PATHS']['eapol_test'], $out, $retval);
        if ($retval == 255) {
            $o = preg_grep('/-o<server cert/', $out);
            if (count($o) > 0) {
                $this->testReturn(\core\common\Entity::L_OK, "<strong>eapol_test</strong> script found.");
            } else {
                $this->testReturn(\core\common\Entity::L_ERROR, "<strong>eapol_test</strong> found, but is too old!");
            }
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>eapol_test</strong> not found!");
        }
    }

    /**
     * test if logdir exists and is writable
     * 
     * @return void
     */
    private function logdir_test()
    {
        if (fopen(CONFIG['PATHS']['logdir'] . "/debug.log", "a") == FALSE) {
            $this->testReturn(\core\common\Entity::L_WARN, "Log files in <strong>" . CONFIG['PATHS']['logdir'] . "</strong> are not writable!");
        } else {
            $this->testReturn(\core\common\Entity::L_OK, "Log directory is writable.");
        }
    }

    /**
     * test for required PHP modules
     * 
     * @return void
     */
    private function phpModules_test()
    {
        if (function_exists('idn_to_ascii')) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP can handle internationalisation.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP can <strong>NOT</strong> handle internationalisation (idn_to_ascii() from php7.0-intl).");
        }

        if (function_exists('gettext')) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>GNU Gettext</strong> is installed.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GNU Gettext</strong> not found!");
        }

        if (function_exists('openssl_sign')) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>OpenSSL</strong> is installed.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>OpenSSL</strong> not found!");
        }

        // on CentOS and RHEL 8, look for Gmagick, else Imagick
        if (strpos(php_uname("r"), "el8") !== FALSE) {
            $classname = 'Gmagick';
        } else {
            $classname = 'Imagick';
        }
        if (class_exists('\\' . $classname)) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>$classname</strong> is installed.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>$classname</strong> not found! Get it from your distribution or <a href='http://pecl.php.net/get/" . strtolower($classname) . "'>here</a>.");
        }

        if (function_exists('ImageCreate')) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>GD</strong> is installed.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GD</strong> not found!</a>.");
        }

        if (function_exists('mysqli_connect')) {
            $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>MySQL</strong> is installed.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>MySQL</strong> not found!");
        }
    }

    /**
     * test if GeoIP is installed correctly
     * 
     * @return void
     */
    private function geoip_test()
    {
        $host_4 = '145.0.2.50';
        $host_6 = '2001:610:188:444::50';
        switch (CONFIG['GEOIP']['version']) {
            case 0:
                $this->testReturn(\core\common\Entity::L_REMARK, "As set in the config, no geolocation service will be used");
                break;
            case 1:
                if (!function_exists('geoip_record_by_name')) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) not found! Get it from your distribution or <a href='http://pecl.php.net/package/geoip'>here</a> or better install GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                    return;
                }
                $record = geoip_record_by_name($host_4);
                if ($record === FALSE) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                if ($record['city'] != 'Utrecht') {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                $this->testReturn(\core\common\Entity::L_REMARK, "PHP extension <strong>GeoIP</strong> (legacy) is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly. We stronly advise to replace the legacy GeoIP with GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                break;
            case 2:
                if (!is_file(CONFIG['GEOIP']['geoip2-path-to-autoloader'])) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> not found! Get it from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                    return;
                }
                if (!is_file(CONFIG['GEOIP']['geoip2-path-to-db'])) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "<strong>GeoIP2 database</strong> not found! See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                include_once CONFIG['GEOIP']['geoip2-path-to-autoloader'];
                $reader = new Reader(CONFIG['GEOIP']['geoip2-path-to-db']);
                try {
                    $record = $reader->city($host_4);
                } catch (Exception $e) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                if ($record->city->name != 'Utrecht') {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                try {
                    $record = $reader->city($host_6);
                } catch (Exception $e) {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                if ($record->city->name != 'Utrecht') {
                    $this->testReturn(\core\common\Entity::L_ERROR, "PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                    return;
                }
                $this->testReturn(\core\common\Entity::L_OK, "PHP extension <strong>GeoIP2</strong> is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                break;
            default:
                $this->testReturn(\core\common\Entity::L_ERROR, 'Check CONFIG[\'GEOIP\'][\'version\'], it must be set to either 1 or 2');
                break;
        }
    }

    /**
     * test if openssl is available
     * 
     * @return void
     */
    private function openssl_test()
    {
        $A = $this->getExecPath('openssl');
        if ($A['exec'] == "") {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>openssl</strong> was not found on your system!");
            return;
        }
        $output = [];
        $retval = -100;
        $t = exec($A['exec'] . ' version', $output, $retval);
        if ($retval != 0 || count($output) != 1) {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>openssl</strong> was not found on your system despite being configured!");
            return;
        }
        if ($A['exec_is'] == "EXPLICIT") {
            $this->testReturn(\core\common\Entity::L_OK, "<strong>" . $output[0] . "</strong> was found and is configured explicitly in your config.");
        } else {
            $this->testReturn(\core\common\Entity::L_WARN, "<strong>" . $output[0] . "</strong> was found, but is not configured with an absolute path in your config.");
        }
    }

    /**
     * test if makensis is available
     * 
     * @return void
     */
    private function makensis_test()
    {
        if (!is_numeric(CONFIG_CONFASSISTANT['NSIS_VERSION'])) {
            $this->testReturn(\core\common\Entity::L_ERROR, "NSIS_VERSION needs to be numeric!");
            return;
        }
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] < 2) {
            $this->testReturn(\core\common\Entity::L_ERROR, "NSIS_VERSION needs to be at least 2!");
            return;
        }
        $A = $this->getExecPath('makensis');
        if ($A['exec'] == "") {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>makensis</strong> was not found on your system!");
            return;
        }
        $output = [];
        $retval = -100;
        $t = exec($A['exec'] . ' -VERSION', $output, $retval);
        if ($retval != 0 || count($output) != 1) {
            $this->testReturn(\core\common\Entity::L_ERROR, "<strong>makensis</strong> was not found on your system despite being configured!");
            return;
        }
        if ($A['exec_is'] == "EXPLICIT") {
            $this->testReturn(\core\common\Entity::L_OK, "<strong>makensis $t</strong> was found and is configured explicitly in your config.");
        } else {
            $this->testReturn(\core\common\Entity::L_WARN, "<strong>makensis $t</strong> was found, but is not configured with an absolute path in your config.");
        }
        $outputArray = [];
        exec($A['exec'] . ' -HELP', $outputArray);
        $t1 = count(preg_grep('/INPUTCHARSET/', $outputArray));
        if ($t1 == 1 && CONFIG_CONFASSISTANT['NSIS_VERSION'] == 2) {
            $this->testReturn(\core\common\Entity::L_ERROR, "Declared NSIS_VERSION does not seem to match the file pointed to by PATHS['makensis']!");
        }
        if ($t1 == 0 && CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $this->testReturn(\core\common\Entity::L_ERROR, "Declared NSIS_VERSION does not seem to match the file pointed to by PATHS['makensis']!");
        }
    }

    /**
     * test if all required NSIS modules are available
     * 
     * @return void
     */
    private function NSISmodules_test()
    {
        $tmp_dir = $this->createTemporaryDirectory('installer', 0)['dir'];
        if (!chdir($tmp_dir)) {
            $this->loggerInstance->debug(2, "Cannot chdir to $tmp_dir\n");
            $this->testReturn(\core\common\Entity::L_ERROR, "NSIS modules test - problem with temporary directory permissions, cannot continue");
            return;
        }
        $exe = 'tt.exe';
        $NSIS_Module_status = [];
        foreach ($this->NSIS_Modules as $module) {
            unset($out);
            exec(CONFIG_CONFASSISTANT['PATHS']['makensis'] . " -V1 '-X!include $module' '-XOutFile $exe' '-XSection X' '-XSectionEnd'", $out, $retval);
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
                $this->testReturn(\core\common\Entity::L_OK, "NSIS module <strong>$module</strong> was found.");
            } else {
                $this->testReturn(\core\common\Entity::L_ERROR, "NSIS module <strong>$module</strong> was not found or is not working correctly.");
            }
        }
    }

    /**
     * test access to dowloads directories
     * 
     * @return void
     */
    private function directories_test()
    {
        $Dir1 = $this->createTemporaryDirectory('installer', 0);
        $dir1 = $Dir1['dir'];
        $base1 = $Dir1['base'];
        if ($dir1) {
            $this->testReturn(\core\common\Entity::L_OK, "Installer cache directory is writable.");
            \core\common\Entity::rrmdir($dir1);
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "Installer cache directory $base1 does not exist or is not writable!");
        }
        $Dir2 = $this->createTemporaryDirectory('test', 0);
        $dir2 = $Dir2['dir'];
        $base2 = $Dir2['base'];
        if ($dir2) {
            $this->testReturn(\core\common\Entity::L_OK, "Test directory is writable.");
            \core\common\Entity::rrmdir($dir2);
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "Test directory $base2 does not exist or is not writable!");
        }
        $Dir3 = $this->createTemporaryDirectory('logo', 0);
        $dir3 = $Dir3['dir'];
        $base3 = $Dir3['base'];
        if ($dir3) {
            $this->testReturn(\core\common\Entity::L_OK, "Logos cache directory is writable.");
            \core\common\Entity::rrmdir($dir3);
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "Logos cache directory $base3 does not exist or is not writable!");
        }
    }

    /**
     * test if all required locales are enabled
     * 
     * @return void
     */
    private function locales_test()
    {
        $locales = shell_exec("locale -a");
        $allthere = "";
        foreach (CONFIG['LANGUAGES'] as $onelanguage) {
            if (preg_match("/" . $onelanguage['locale'] . "/", $locales) == 0) {
                $allthere .= $onelanguage['locale'] . " ";
            }
        }
        if ($allthere == "") {
            $this->testReturn(\core\common\Entity::L_OK, "All of your configured locales are available on your system.");
        } else {
            $this->testReturn(\core\common\Entity::L_WARN, "Some of your configured locales (<strong>$allthere</strong>) are not installed and will not be displayed correctly!");
        }
    }

    const DEFAULTS = [
        ["SETTING" => CONFIG['APPEARANCE']['from-mail'],
            "DEFVALUE" => "cat-invite@your-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/from-mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['support-contact']['url'],
            "DEFVALUE" => "cat-support@our-cat-installation.example?body=Only%20English%20language%20please!",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/url ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['support-contact']['display'],
            "DEFVALUE" => "cat-support@our-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/display ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['support-contact']['developer-mail'],
            "DEFVALUE" => "cat-develop@our-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/support-contact/mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['abuse-mail'],
            "DEFVALUE" => "my-abuse-contact@your-cat-installation.example",
            "COMPLAINTSTRING" => "APPEARANCE/abuse-mail ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['MOTD'],
            "DEFVALUE" => "Release Candidate. All bugs to be shot on sight!",
            "COMPLAINTSTRING" => "APPEARANCE/MOTD ",
            "REQUIRED" => FALSE,],
        ["SETTING" => CONFIG['APPEARANCE']['webcert_CRLDP'],
            "DEFVALUE" => ['list', 'of', 'CRL', 'pointers'],
            "COMPLAINTSTRING" => "APPEARANCE/webcert_CRLDP ",
            "REQUIRED" => TRUE,],
        ["SETTING" => CONFIG['APPEARANCE']['webcert_OCSP'],
            "DEFVALUE" => ['list', 'of', 'OCSP', 'pointers'],
            "COMPLAINTSTRING" => "APPEARANCE/webcert_OCSP ",
            "REQUIRED" => TRUE,],
        ["SETTING" => CONFIG['DB']['INST']['host'],
            "DEFVALUE" => "db.host.example",
            "COMPLAINTSTRING" => "DB/INST ",
            "REQUIRED" => TRUE,],
        ["SETTING" => CONFIG['DB']['INST']['host'],
            "DEFVALUE" => "db.host.example",
            "COMPLAINTSTRING" => "DB/USER ",
            "REQUIRED" => TRUE,],
        ["SETTING" => CONFIG['DB']['EXTERNAL']['host'],
            "DEFVALUE" => "customerdb.otherhost.example",
            "COMPLAINTSTRING" => "DB/EXTERNAL ",
            "REQUIRED" => FALSE,],
    ];

    /**
     * test if defaults in the config have been replaced with some real values
     * 
     * @return void
     */
    private function defaults_test()
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
        if (isset(CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][0]) && CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'][0]['ip'] == "192.0.2.1") {
            $defaultvalues .= "RADIUSTESTS/UDP-hosts ";
        }

        foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-clientcerts'] as $cadata) {
            foreach ($cadata['certificates'] as $cert_files) {
                if (file_get_contents(ROOT . "/config/cli-certs/" . $cert_files['public']) === FALSE) {
                    $defaultvalues .= "CERTIFICATE/" . $cert_files['public'] . " ";
                }
                if (file_get_contents(ROOT . "/config/cli-certs/" . $cert_files['private']) === FALSE) {
                    $defaultvalues .= "CERTIFICATE/" . $cert_files['private'] . " ";
                }
            }
        }

        if ($defaultvalues != "") {
            $this->testReturn(\core\common\Entity::L_WARN, "Your configuration in config/config.php contains unchanged default values or links to inexistent files: <strong>$defaultvalues</strong>!");
        } else {
            $this->testReturn(\core\common\Entity::L_OK, "Your configuration does not contain any unchanged defaults, which is a good sign.");
        }
    }

    /**
     * test access to databases
     * 
     * @return void
     */
    private function databases_test()
    {
        $databaseName1 = 'INST';
        try {
            $db1 = DBConnection::handle($databaseName1);
            $res1 = $db1->exec('SELECT * FROM profile_option_dict');
            if ($res1->num_rows == $this->profile_option_ct) {
                $this->testReturn(\core\common\Entity::L_OK, "The $databaseName1 database appears to be OK.");
            } else {
                $this->testReturn(\core\common\Entity::L_ERROR, "The $databaseName1 database is reacheable but probably not updated to this version of CAT.");
            }
        } catch (Exception $e) {
            $this->testReturn(\core\common\Entity::L_ERROR, "Connection to the  $databaseName1 database failed");
        }

        $databaseName2 = 'USER';
        try {
            $db2 = DBConnection::handle($databaseName2);
            if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                $res2 = $db2->exec('desc view_admin');
                if ($res2->num_rows == $this->view_admin_ct) {
                    $this->testReturn(\core\common\Entity::L_OK, "The $databaseName2 database appears to be OK.");
                } else {
                    $this->testReturn(\core\common\Entity::L_ERROR, "The $databaseName2 is reacheable but there is something wrong with the schema");
                }
            } else {
                $this->testReturn(\core\common\Entity::L_OK, "The $databaseName2 database appears to be OK.");
            }
        } catch (Exception $e) {
            $this->testReturn(\core\common\Entity::L_ERROR, "Connection to the  $databaseName2 database failed");
        }

        $databaseName3 = 'EXTERNAL';
        if (!empty(CONFIG['DB'][$databaseName3])) {
            try {
                $db3 = DBConnection::handle($databaseName3);
                if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                    $res3 = $db3->exec('desc view_admin');
                    if ($res3->num_rows == $this->view_admin_ct) {
                        $this->testReturn(\core\common\Entity::L_OK, "The $databaseName3 database appears to be OK.");
                    } else {
                        $this->testReturn(\core\common\Entity::L_ERROR, "The $databaseName3 is reacheable but there is something wrong with the schema");
                    }
                } else {
                    $this->testReturn(\core\common\Entity::L_OK, "The $databaseName3 database appears to be OK.");
                }
            } catch (Exception $e) {

                $this->testReturn(\core\common\Entity::L_ERROR, "Connection to the  $databaseName3 database failed");
            }
        }
    }

    /**
     * test devices.php for the no_cache option
     * 
     * @return void
     */
    private function device_cache_test()
    {
        if ((!empty(\devices\Devices::$Options['no_cache'])) && \devices\Devices::$Options['no_cache']) {
            $global_no_cache = 1;
        } else {
            $global_no_cache = 0;
        }

        if ($global_no_cache == 1) {
            $this->testReturn(\core\common\Entity::L_WARN, "Devices no_cache global option is set, this is not a good idea in a production setting\n");
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
            $this->testReturn(\core\common\Entity::L_WARN, "The following devices will not be cached: $no_cache_dev");
        }
        if ($no_cache_dev_count == 1) {
            $this->testReturn(\core\common\Entity::L_WARN, "The following device will not be cached: $no_cache_dev");
        }
    }

    /**
     * test if mailer works
     * 
     * @return void
     */
    private function mailer_test()
    {
        if (empty(CONFIG['APPEARANCE']['abuse-mail']) || CONFIG['APPEARANCE']['abuse-mail'] == "my-abuse-contact@your-cat-installation.example") {
            $this->testReturn(\core\common\Entity::L_ERROR, "Your abuse-mail has not been set, cannot continue with mailer tests.");
            return;
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Host = CONFIG['MAILSETTINGS']['host'];
        $mail->Username = CONFIG['MAILSETTINGS']['user'];
        $mail->Password = CONFIG['MAILSETTINGS']['pass'];
        $mail->SMTPOptions = CONFIG['MAILSETTINGS']['options'];
        $mail->WordWrap = 72;
        $mail->isHTML(FALSE);
        $mail->CharSet = 'UTF-8';
        $mail->From = CONFIG['APPEARANCE']['from-mail'];
        $mail->FromName = CONFIG['APPEARANCE']['productname'] . " Invitation System";
        $mail->addAddress(CONFIG['APPEARANCE']['abuse-mail']);
        $mail->Subject = "testing CAT configuration mail";
        $mail->Body = "Testing CAT mailing\n";
        $sent = $mail->send();
        if ($sent) {
            $this->testReturn(\core\common\Entity::L_OK, "mailer settings appear to be working, check " . CONFIG['APPEARANCE']['abuse-mail'] . " mailbox if the message was receiced.");
        } else {
            $this->testReturn(\core\common\Entity::L_ERROR, "mailer settings failed, check the Config::MAILSETTINGS");
        }
    }

    /**
     * TODO test if RADIUS connections work
     * 
     * @return void
     */
    private function UDPhosts_test()
    {
//        if(empty)
    }

}
