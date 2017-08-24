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
 * The $Tests array lists the config tests to be run
 */
$Tests = [
    'ssp',
    'security',
    'php',
    'phpModules',
    'geoip',
    'directories',
    'openssl',
    'makensis',
    'makensis=>NSISmodules',
    'zip',
    'eapol_test',
    'locales',
    'defaults',
    'databases',
    'device_cache',
    'mailer',
];

ini_set('display_errors', '0');
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

function print_test_results($test) {
    $out = '';
    switch ($test->test_result['global']) {
        case \core\common\Entity::L_OK:
            $message = "Your configuration appears to be fine.";
            break;
        case \core\common\Entity::L_WARN:
            $message = "There were some warnings, but your configuration should work.";
            break;
        case \core\common\Entity::L_ERROR:
            $message = "Your configuration appears to be broken, please fix the errors.";
            break;
        case \core\common\Entity::L_NOTICE:
            $message = "Your configuration appears to be fine.";
            break;
        default:
            throw new Exception("The result code level " . $test->test_result['global'] . " is not defined!");
    }
    $uiElements = new web\lib\admin\UIElements();
    $out .= $uiElements->boxFlexible($test->test_result['global'], "<br><strong>Test Summary</strong><br>" . $message . "<br>See below for details<br><hr>");
    foreach ($test->out as $testValue) {
        foreach ($testValue as $o) {
            $out .= $uiElements->boxFlexible($o['level'], $o['message']);
        }
    }
    return($out);
}

if (!in_array("I do not care about security!", CONFIG['SUPERADMINS'])) {
    $auth = new \web\lib\admin\Authentication();
    $auth->authenticate();
    $user = new \core\User($_SESSION['user']);
    if (!$user->isSuperadmin()) {
        throw new Exception("Not Superadmin");
    }
}
$test = new \core\SanityTests();
$test->run_tests($Tests);
$format = empty($_REQUEST['format']) ? 'include' : $_REQUEST['format'];
switch ($format) {
    case 'include':
        $o = print_test_results($test);
        print "<table>$o</table>";
        break;
    case 'html':
        header("Content-Type:text/html;charset=utf-8");
        echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='$ourlocale'>
          <head lang='$ourlocale'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'></head>";

        $o = print_test_results($test);
        print "<body><table>$o</table></body></html>";
        break;
    case 'json':
        header('Content-type: application/json; utf-8');
        print json_encode(['global' => $test->test_result, 'details' => $test->out]);
        break;
    case 'print_r':
        echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='$ourlocale'>
          <head lang='$ourlocale'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'></head>";
        print "<body><pre>";
        print_r(['global' => $test->test_result, 'details' => $test->out]);
        print "</pre><body>";
        break;
    default:
        break;
}
