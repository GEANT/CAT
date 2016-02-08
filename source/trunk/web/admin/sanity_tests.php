<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/**
 * The $Tests array lists the config tests to be run
 */
$Tests = [
'ssp',
'security',
'php',
'phpModules',
'openssl',
'makensis',
'makensis=>NSISmodules',
'makensis=>NSIS_GetVersion',
'zip',
'eapol_test',
'directories',
'locales',
'defaults',
'databases',
'device_cache',
'mailer',
];

ini_set('display_errors', '0');
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
require_once("User.php");
require_once("inc/common.inc.php");
//require_once("DBConnection.php");
require_once("SanityTests.php");

function print_test_results($t) {
   $out = '';
   switch($t->test_result['global']) {
       case L_OK:
         $message = "Your configuration appears to be fine.";
         break;
       case L_WARN:
         $message = "There were some warnings, but your configuration should work.";
         break;
       case L_ERROR:
         $message = "Your configuration appears to be broken, please fix the errors.";
         break;
       case L_NOTICE:
         $message = "Your configuration appears to be fine.";
         break;
   }
   $out .= UI_message($t->test_result['global'],"<br><strong>Test Summary</strong><br>".$message."<br>See below for details<br><hr>");
   foreach ($t->out as $test => $test_val)  {
   foreach ($test_val as $o)  {
       $out .= UI_message($o['level'],$o['message']);
   }
   }
   return($out);
}

function return_test_results($t) {
   $out = '';
   switch($t->test_result['global']) {
       case L_OK:
         $message = "Your configuration appears to be fine.";
         break;
       case L_WARN:
         $message = "There were some warnings, but your configuration should work.";
         break;
       case L_ERROR:
         $message = "Your configuration appears to be broken, please fix the errors.";
         break;
       case L_NOTICE:
         $message = "Your configuration appears to be fine.";
         break;
   }
   $out .= UI_message($t->test_result['global'],"<br><strong>Test Summary</strong><br>".$message."<br>See below for details<br><hr>");
   foreach ($t->out as $test => $test_val)  {
   foreach ($test_val as $o)  {
       $out .= UI_message($o['level'],$o['message']);
   }
   }
   return($out);
}




if (!in_array("I do not care about security!", Config::$SUPERADMINS)) {
    require_once("inc/auth.inc.php");
    authenticate();
    $user = new User($_SESSION['user']);
    if (!$user->isSuperadmin()) {
         print "Not Superadmin";
         exit;
    }
    
}
$test = new SanityTest();
$test->run_tests($Tests);
$format =  empty($_REQUEST['format']) ? 'html' : $_REQUEST['format'];
switch ($format) {
    case 'html':
        $o = print_test_results($test);
        print "<table>$o</table>";
        break;
    case 'json':
        header('Content-type: application/json; utf-8');
        print json_encode(['global'=>$test->test_result, 'details'=>$test->out]);
        break;
    case 'print_r':
        print "<pre>";
        print_r(['global'=>$test->test_result, 'details'=>$test->out]);
        break;
    default:
        break;
}
?>
