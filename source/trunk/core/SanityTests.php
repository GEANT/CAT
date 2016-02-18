<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * 
 * 
 * This is the definition of the CAT class implementing various configuration
 * tests. 
 * Each test is implemented as a priviate method which needs to be named "test_name_test".
 * The test returns the results by calling the test_return method, this passing the return
 * code and the explanatory message. Multiple calls to test_return are allowed.
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

use GeoIp2\Database\Reader;
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("Helper.php");
require_once("CAT.php");
require_once("devices/devices.php");
require_once("core/PHPMailer/PHPMailerAutoload.php");



class SanityTest extends CAT {
/* in this section set current CAT requirements */

/* $php_needversion sets the minumum required php version */
    private $php_needversion = '5.5.14';

/* List all required NSIS modules below */
    private $NSIS_Modules = [
             "NSISArray.nsh",
             "FileFunc.nsh",
             "LogicLib.nsh",
             "WordFunc.nsh",
             "FileFunc.nsh",
             "x64.nsh",
         ];

/* set $profile_option_ct to the number of rows returned by "SELECT * FROM profile_option_dict" */
    private $profile_option_ct = 29;
/* set $view_admin_ct to the number of rows returned by "desc view_admin" */
    private $view_admin_ct = 8;

/* end of config */

    public $out;
    public $name;

    public function __construct() {
       parent::__construct();
       $this->test_result = [];
       $this->test_result['global'] = 0;
    }

    /**
     * The single test wrapper
     * @param string $test the test name
     */
    public function test($test) {
       $this->out[$test] =[];
       $this->name = $test;
       $m_name = $test.'_test';
       $this->test_result[$test] = 0;
       if(! method_exists($this,$m_name)) {
           $this->test_return($test,L_ERROR,"Configuration error, no test configured for <strong>$test</strong>.");
           return;
       }
       $this->$m_name();
    }

    /**
     * The multiple tests wrapper
     * @param array $Tests the tests array.
     *
     * The $Tests is a simple stings array, where each entry is a test name
     * the test names can also be given in the format "test=>subtest", which 
     * defines a conditional execution of the "subtest" if the "test" was run earier
     * and returned a success.
     */
    public function run_tests($Tests) {
       foreach ($Tests as $t) {
         if(preg_match('/(.+)=>(.+)/',$t,$m)) {
            $tst = $m[1];
            $subtst=$m[2];
            if($this->test_result[$tst]  < L_ERROR)
               $this->test($subtst);
         }
         else
            $this->test($t);
       }
    }

    public function get_test_names() {
       $T = get_class_methods($this);
       $out = [];
       foreach($T as $t) {
         if(preg_match('/^(.*)_test$/',$t,$m)) {
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
     * $test_result is set by the test_return method
     *
     * @var array $test_result
     */
    public $test_result;

    private function test_return($level,$message) {
        $this->out[$this->name][] = ['level'=>$level, 'message'=>$message];
        $this->test_result[$this->name] = max($this->test_result[$this->name],$level);
        $this->test_result['global'] = max($this->test_result['global'],$level);
    }

    private function get_exec_path($s) {
        $the_path = "";
        $exec_is = "UNDEFINED";
        if (!empty(Config::$PATHS[$s])) {
             preg_match('/([^ ]+) ?/',Config::$PATHS[$s],$m);
             $exe = $m[1];
             $the_path = exec("which " . Config::$PATHS[$s]);
             if ($the_path == $exe)
                 $exec_is = "EXPLICIT";
             else
                 $exec_is = "IMPLICIT";
         } 
        return(['exec'=>$the_path,'exec_is'=>$exec_is]);
    }

    /**
      *  Test for php version
      */
    private function php_test() {
         if (version_compare(phpversion(), $this->php_needversion, '>='))
            $this->test_return(L_OK,"<strong>PHP</strong> is sufficiently recent. You are running " . phpversion() . ".");
         else
            $this->test_return(L_ERROR,"<strong>PHP</strong> is too old. We need at least $this->php_needversion, but you only have ".phpversion(). ".");
    }

    /**
      * test for simpleSAMLphp
      */
    private function ssp_test() {
         if (!is_file(CONFIG::$AUTHENTICATION['ssp-path-to-autoloader']))
             $this->test_return(L_ERROR,"<strong>simpleSAMLphp</strong> not found!");
         else
             $this->test_return(L_OK,"<strong>simpleSAMLphp</strong> autoloader found.");
    }

    /**
      * test for security setting
      */
    private function security_test() {
         if (in_array("I do not care about security!", Config::$SUPERADMINS))
             $this->test_return(L_WARN,"You do not care about security. This page should be made accessible to the CAT admin only! See config.php 'Superadmins'!");
    }

    /**
      * test if zip is available
      */
    private function zip_test() {
         if (exec("which zip") != "")
             $this->test_return(L_OK,"<strong>zip</strong> binary found.");
         else
             $this->test_return(L_ERROR,"<strong>zip</strong> not found in your \$PATH!");
    }

    /**
      * test if eapol_test is availabe and reacent enough
      */
    private function eapol_test_test() {
         exec(Config::$PATHS['eapol_test'], $out, $retval);
         if($retval == 255 ) {
            $o = preg_grep('/-o<server cert/',$out);
               if(count($o) > 0)
                   $this->test_return(L_OK,"<strong>eapol_test</strong> script found.");
               else
                   $this->test_return(L_ERROR,"<strong>eapol_test</strong> found, but is too old!");
         }
         else
            $this->test_return(L_ERROR,"<strong>eapol_test</strong> not found!");
    }

    /**
      * test if logdir exists and is writable
      */
    private function logdir_test() {
         if (fopen(Config::$PATHS['logdir'] . "/debug.log", "a") == FALSE)
             $this->test_return(L_WARN,"Log files in <strong>" . Config::$PATHS['logdir'] . "</strong> are not writable!");
         else
             $this->test_return(L_OK,"Log directory is writable.");
    }

    /**
      * test for required PHP modules
      */
    private function phpModules_test() {
        if (function_exists('idn_to_ascii'))
            $this->test_return(L_OK,"PHP can handle internationalisation.");
        else
            $this->test_return(L_ERROR,"PHP can <strongNOT</strong> handle internationalisation (idn_to_ascii() from php5-intl).");

        if (function_exists('gettext'))
            $this->test_return(L_OK,"PHP extension <strong>GNU Gettext</strong> is installed.");
        else
           $this->test_return(L_ERROR,"PHP extension <strong>GNU Gettext</strong> not found!");

        if (function_exists('openssl_sign'))
            $this->test_return(L_OK,"PHP extension <strong>OpenSSL</strong> is installed.");
        else
            $this->test_return(L_ERROR,"PHP extension <strong>OpenSSL</strong> not found!");

        if (class_exists('Imagick'))
            $this->test_return(L_OK,"PHP extension <strong>Imagick</strong> is installed.");
        else
            $this->test_return(L_ERROR,"PHP extension <strong>Imagick</strong> not found! Get it from your distribution or <a href='http://pecl.php.net/package/imagick'>here</a>.");

        if (function_exists('ImageCreate'))
            $this->test_return(L_OK,"PHP extension <strong>GD</strong> is installed.");
        else
            $this->test_return(L_ERROR,"PHP extension <strong>GD</strong> not found!</a>.");

        if (function_exists('mysqli_connect'))
            $this->test_return(L_OK,"PHP extension <strong>MySQL</strong> is installed.");
        else
            $this->test_return(L_ERROR,"PHP extension <strong>MySQL</strong> not found!");
/*
        if (function_exists('geoip_record_by_name')) {
           $host = '158.75.1.10';
           $record = geoip_record_by_name($host);
           if(! $record) 
              $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
           elseif($record['city'] != 'Torun')
              $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
           else
              $this->test_return(L_OK,"PHP extension <strong>GeoIP</strong> is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
        }
        else
            $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> not found! Get it from your distribution or <a href='http://pecl.php.net/package/geoip'>here</a>.");
*/
    }

    /**
     * test if GeoIP is installed correctly
     */

    private function geoip_test() {
       $host_4 = '145.0.2.50';
       $host_6 = '2001:610:188:444::50';
       switch (Config::$GEOIP['version']) {
           case 0:
              $this->test_return(L_REMARK,"As set in the config, no geolocation service will be used");
              break;
           case 1:
              if (!function_exists('geoip_record_by_name')) {
                  $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> (legacy) not found! Get it from your distribution or <a href='http://pecl.php.net/package/geoip'>here</a> or better install GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                  return;
              }
              $record = geoip_record_by_name($host_4);
              if(! $record) {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              if($record['city'] != 'Utrecht') {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP</strong> (legacy) found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              $this->test_return(L_REMARK,"PHP extension <strong>GeoIP</strong> (legacy) is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly. We stronly advise to replace the legacy GeoIP with GeoIP2 from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
              break;
           case 2:
              if(! is_file(Config::$GEOIP['geoip2-path-to-autoloader'])) {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP2</strong> not found! Get it from <a href='https://github.com/maxmind/GeoIP2-php'>here</a>.");
                 return;
              }
              if(! is_file(Config::$GEOIP['geoip2-path-to-db'])) {
                 $this->test_return(L_ERROR,"<strong>GeoIP2 database</strong> not found! See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              require_once Config::$GEOIP['geoip2-path-to-autoloader'];
              $reader = new Reader(Config::$GEOIP['geoip2-path-to-db']);
              try {
                 $record = $reader->city($host_4);
              } catch (Exception $e) {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              if( $record->city->name != 'Utrecht') {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP2</strong> found but not working properly, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              try {
                 $record = $reader->city($host_6);
              } catch (Exception $e) {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              if( $record->city->name != 'Utrecht') {
                 $this->test_return(L_ERROR,"PHP extension <strong>GeoIP2</strong> found but not working properly with IPv6, perhaps you need to download the databases. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
                 return;
              }
              $this->test_return(L_OK,"PHP extension <strong>GeoIP2</strong> is installed and working. See utils/GeoIP-update.sh in the CAT distribution and use it tu update the GeoIP database regularly.");
              break;
           default:
              $this->test_return(L_ERROR,'Check Config::$GEOIP[\'version\'], it must be set to either 1 or 2');
              break;
       }
    }

    /**
      * test if openssl is available
      */
    private function openssl_test() {
         $A = $this->get_exec_path('openssl');    
         if($A['exec'] != "") {
             $t = exec($A['exec'] . ' version');
             if($A['exec_is'] == "EXPLICIT")
                $this->test_return(L_OK,"<strong>$t</strong> was found and is configured explicitly in your config.");
             else
                $this->test_return(L_WARN,"<strong>$t</strong> was found, but is not configured with an absolute path in your config.");
         } else
            $this->test_return(L_ERROR,"<strong>openssl</strong> was not found on your system!");
    }

    /**
      * test if makensis is available
      */
    private function makensis_test() {
         $A = $this->get_exec_path('makensis');    
         if($A['exec'] != "") {
             $t = exec($A['exec'] . ' -VERSION');
             if($A['exec_is'] == "EXPLICIT") 
                $this->test_return(L_OK,"<strong>makensis $t</strong> was found and is configured explicitly in your config.");
             else
                $this->test_return(L_WARN,"<strong>makensis $t</strong> was found, but is not configured with an absolute path in your config.");
         } else
            $this->test_return(L_ERROR,"<strong>makensis</strong> was not found on your system!");
    }

    /**
      * test if all required NSIS modules are available
      */
    private function NSISmodules_test() {
         $tmp_dir = createTemporaryDirectory('installer',0)['dir'];
         if(!chdir($tmp_dir)) {
           debug(2, "Cannot chdir to $tmp_dir\n");
           $this->test_return(L_ERROR,"NSIS modules test - problem with temporary directory permissions, cannot continue");
           return;
         }
         $exe= 'tt.exe';
         $NSIS_Module_status = [];
         foreach ($this->NSIS_Modules as $module) {
            unset($out);
            exec(Config::$PATHS['makensis']." -V1 '-X!include $module' '-XOutFile $exe' '-XSection X' '-XSectionEnd'", $out, $retval);
            if($retval > 0) 
               $NSIS_Module_status[$module] = 0;
            else
               $NSIS_Module_status[$module] = 1;
         }
         if(is_file($exe))
            unlink($exe);
         foreach ($NSIS_Module_status as $module => $status) {
            if($status == 1)
               $this->test_return(L_OK,"NSIS module <strong>$module</strong> was found.");
            else
               $this->test_return(L_ERROR,"NSIS module <strong>$module</strong> was not found or is not working correctly.");
         }
    }
    private function NSIS_GetVersion_test() {
         $tmp_dir = createTemporaryDirectory('installer',0)['dir'];
         if(!chdir($tmp_dir)) {
           debug(2, "Cannot chdir to $tmp_dir\n");
           $this->test_return(L_ERROR,"NSIS module <strong>GetVersion</strong> - problem with temporary directory permissions, cannot continue");
           return;
         }
         $exe= 'tt.exe';
         exec(Config::$PATHS['makensis']." -V1 '-XOutFile $exe' '-XSection X' '-XGetVersion::WindowsName' '-XSectionEnd'", $out, $retval);
         if($retval > 0)
            $this->test_return(L_ERROR,"NSIS module <strong>GetVersion</strong> was not found or is not working correctly.");
         else
            $this->test_return(L_OK,"NSIS module <strong>GetVersion</strong> was found.");
         if(is_file($exe))
            unlink($exe);
    }

    /**
      * test access to dowloads directories
      */
    private function directories_test() {
               $Dir = createTemporaryDirectory('installer',0);
               $dir = $Dir['dir'];
               $base = $Dir['base'];
               if($dir) {
                  $this->test_return(L_OK,"Installer cache directory is writable.");
                  rrmdir($dir);
               } else {
                  $this->test_return(L_ERROR,"Installer cache directory $base does not exist or is not writable!");
               }
               $Dir = createTemporaryDirectory('test',0);
               $dir = $Dir['dir'];
               $base = $Dir['base'];
               if($dir) {
                  $this->test_return(L_OK,"Test directory is writable.");
                  rrmdir($dir);
               } else {
                  $this->test_return(L_ERROR,"Test directory  $base does not exist or is not writable!");
               }
               $Dir = createTemporaryDirectory('logo',0);
               $dir = $Dir['dir'];
               $base = $Dir['base'];
               if($dir) {
                  $this->test_return(L_OK,"Logos cache directory is writable.");
                  rrmdir($dir);
               } else {
                  $this->test_return(L_ERROR,"Logos cache directory  $base does not exist or is not writable!");
               }
    }

    /**
      * test if all required locales are enabled
      */
    private function locales_test() {
                $locales = shell_exec("locale -a");
                $allthere = "";
                foreach (Config::$LANGUAGES as $onelanguage)
                    if (preg_match("/" . $onelanguage['locale'] . "/", $locales) == 0)
                        $allthere .= $onelanguage['locale'] . " ";

                if ($allthere == "")
                    $this->test_return(L_OK,"All of your configured locales are available on your system.");
                else
                    $this->test_return(L_WARN,"Some of your configured locales (<strong>$allthere</strong>) are not installed and will not be displayed correctly!");
    }

    private function check_config_default($type,$key,$value) {
                if (empty(Config::$type[$key]))
                    $missingvalues .="type/webcert_OCSP ";
                elseif (Config::$type['webcert_OCSP'] == $value)
                    $defaultvalues .="type/$key ";
    }


    /**
      * test if detalts in the config have been replaced with some real values
      */
    private function defaults_test() {
                $defaultvalues = "";
                $missingvalues = "";
                if (Config::$APPEARANCE['from-mail'] == "cat-invite@your-cat-installation.example")
                    $defaultvalues .="APPEARANCE/from-mail ";
                if (Config::$APPEARANCE['support-contact']['mail'] == "admin@eduroam.pl?body=Only%20English%20language%20please!")
                    $defaultvalues .="APPEARANCE/support-contact/mail ";
                if (Config::$APPEARANCE['support-contact']['display'] == "This mail please")
                    $defaultvalues .="APPEARANCE/support-contact/display ";
                if (Config::$APPEARANCE['abuse-mail'] == "my-abuse-contact@your-cat-installation.example")
                    $defaultvalues .="APPEARANCE/abuse-mail ";
                if (Config::$APPEARANCE['MOTD'] == "Release Candidate. All bugs to be shot on sight!")
                    $defaultvalues .="APPEARANCE/MOTD ";
                if (Config::$APPEARANCE['webcert_CRLDP'] == ['list', 'of', 'CRL', 'pointers'])
                    $defaultvalues .="APPEARANCE/webcert_CRLDP ";
                if (empty(Config::$APPEARANCE['webcert_OCSP']))
                    $missingvalues .="APPEARANCE/webcert_OCSP ";
                elseif (Config::$APPEARANCE['webcert_OCSP'] == ['list', 'of', 'OCSP', 'pointers'])
                    $defaultvalues .="APPEARANCE/webcert_OCSP ";
                if (isset(Config::$RADIUSTESTS['UDP-hosts'][0]) && Config::$RADIUSTESTS['UDP-hosts'][0]['ip'] == "192.0.2.1")
                    $defaultvalues .="RADIUSTESTS/UDP-hosts ";
                if (Config::$DB['INST']['host'] == "db.host.example")
                    $defaultvalues .="DB/INST ";
                if (Config::$DB['INST']['host'] == "db.host.example")
                    $defaultvalues .="DB/USER ";
                if(!empty(Config::$DB['EXTERNAL']) && Config::$DB['EXTERNAL']['host'] == "customerdb.otherhost.example")
                    $defaultvalues .="DB/EXTERNAL ";
                $files = [];
                foreach (Config::$RADIUSTESTS['TLS-clientcerts'] as $cadata) {
                    foreach ($cadata['certificates'] as $cert_files) {
                        $files[] = $cert_files['public'];
                        $files[] = $cert_files['private'];
                    }
                }

                foreach ($files as $file) {
                    $handle = fopen(CAT::$root . "/config/cli-certs/" . $file, 'r');
                    if (!$handle)
                        $defaultvalues .="CERTIFICATE/$file ";
                    else
                        fclose($handle);
                }
                if ($defaultvalues != "")
                    $this->test_return(L_WARN,"Your configuration in config/config.php contains unchanged default values or links to inexistent files: <strong>$defaultvalues</strong>!");
                else
                    $this->test_return(L_OK,"Your configuration does not contain any unchanged defaults, which is a good sign.");
    }

   /**
     * test access to databases
     */
   private function databases_test() {
        $DB = 'INST';
        $db = mysqli_connect(Config::$DB[$DB]['host'], Config::$DB[$DB]['user'], Config::$DB[$DB]['pass'], Config::$DB[$DB]['db']);
        if(! $db) {
           $this->test_return(L_ERROR,"Connection to the  $DB database failed");
        } else {
           $r = mysqli_query($db,'select * from profile_option_dict');
           if($r->num_rows == $this->profile_option_ct)
              $this->test_return(L_OK,"The $DB database appears to be OK.");
           else
              $this->test_return(L_ERROR,"The $DB is reacheable but probably not updated to this version of CAT.");
        }
        $DB = 'USER';
        $db = mysqli_connect(Config::$DB[$DB]['host'], Config::$DB[$DB]['user'], Config::$DB[$DB]['pass'], Config::$DB[$DB]['db']);
        if(! $db) {
           $this->test_return(L_ERROR,"Connection to the  $DB database failed");
        } else {
           $r = mysqli_query($db,'desc view_admin');
           if($r->num_rows == $this->view_admin_ct)
              $this->test_return(L_OK,"The $DB database appears to be OK.");
           else
              $this->test_return(L_ERROR,"The $DB is reacheable but there is something wrong with the schema");
        }
        $DB = 'EXTERNAL';
        if(! empty(Config::$DB[$DB])) {
        $db = mysqli_connect(Config::$DB[$DB]['host'], Config::$DB[$DB]['user'], Config::$DB[$DB]['pass'], Config::$DB[$DB]['db']);
        if(! $db) {
           $this->test_return(L_ERROR,"Connection to the  $DB database failed");
        } else {
           $r = mysqli_query($db,'desc view_admin');
           if($r->num_rows == $this->view_admin_ct)
              $this->test_return(L_OK,"The $DB database appears to be OK.");
           else
              $this->test_return(L_ERROR,"The $DB is reacheable but there is something wrong with the schema");
        }
        }
   }


   /**
     * test devices.php for the no_cache option
     */
   private function device_cache_test() {
       if((! empty(Devices::$Options['no_cache'])) && Devices::$Options['no_cache'])
          $global_no_cache = 1;
       else
          $global_no_cache = 0;

       if($global_no_cache == 1)
          $this->test_return(L_WARN,"Devices no_cache global option is set, this is not a good idea in a production setting\n");
       $Devs = Devices::listDevices();
       $no_cache_dev = '';
       $no_cache_dev_count = 0;
       if($global_no_cache) {
          foreach ($Devs as $dev=>$D) {
             if(empty($D['options']['no_cache']) || $D['options']['no_cache'] != 0) {
                $no_cache_dev .= $dev." ";
                $no_cache_dev_count++;
             }
          }
       } else {
          foreach ($Devs as $dev=>$D) {
             if(!empty($D['options']['no_cache']) && $D['options']['no_cache'] != 0) {
                $no_cache_dev .= $dev." ";
                $no_cache_dev_count++;
             }
          }
       }


       if($no_cache_dev_count > 1 ) 
          $this->test_return(L_WARN,"The following devices will not be cached: $no_cache_dev");
       if($no_cache_dev_count == 1 ) 
          $this->test_return(L_WARN,"The following device will not be cached: $no_cache_dev");

   }

   /**
     * test if mailer works
     */
   private function mailer_test() {
      if (empty(Config::$APPEARANCE['abuse-mail']) || Config::$APPEARANCE['abuse-mail'] == "my-abuse-contact@your-cat-installation.example") {
         $this->test_return(L_ERROR,"Your abuse-mail has not been set, cannot continue with mailer tests.");
         return;
      }
      $mail = new PHPMailer();
      $mail->isSMTP();
      $mail->Port = 587;
      $mail->SMTPAuth = true;
      $mail->SMTPSecure = 'tls';
      $mail->Host = Config::$MAILSETTINGS['host'];
      $mail->Username = Config::$MAILSETTINGS['user'];
      $mail->Password = Config::$MAILSETTINGS['pass'];
      $mail->WordWrap = 72;
      $mail->isHTML(FALSE);
      $mail->CharSet = 'UTF-8';
      $mail->From = Config::$APPEARANCE['from-mail'];
      $mail->FromName = Config::$APPEARANCE['productname'] . " Invitation System";
      $mail->addAddress(Config::$APPEARANCE['abuse-mail']);
      $mail->Subject = "testing CAT configuration mail";
      $mail->Body = "Testing CAT mailing\n";
      $sent = $mail->send();
      if($sent)
          $this->test_return(L_OK,"mailer settings appear to be working, check ".Config::$APPEARANCE['abuse-mail']." mailbox if the message was receiced.");
      else
          $this->test_return(L_ERROR,"mailer settings failed, check the Config::MAILSETTINGS");

   }

    /**
      * TODO test if RADIUS connections work
      */
    private function UDPhosts_test() {
//        if(empty)
    }
}

?>
