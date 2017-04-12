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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);

class Authentication {

    public function isAuthenticated() {
        $authSimple = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
        return $authSimple->isAuthenticated();
    }

    public function authenticate() {
        $loggerInstance = new \core\Logging();
        $authSimple = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
        $authSimple->requireAuth();

        $admininfo = $authSimple->getAttributes();

        if (!isset($admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-identifier']][0])) {
            $failtext = "FATAL ERROR: we did not receive a unique user identifier from the authentication source!";
            echo $failtext;
            throw new Exception($failtext);
        }

        $user = $admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-identifier']][0];

        $_SESSION['user'] = $user;
        $newNameReceived = FALSE;

        $userObject = new \core\User($user);
        if (isset($admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-name']][0]) && (count($userObject->getAttributes('user:realname')) == 0)) {
            $name = $admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-name']][0];
            $userObject->addAttribute('user:realname', NULL, $name);
            $loggerInstance->writeAudit($_SESSION['user'], "NEW", "User - added real name from external auth source");
            $newNameReceived = TRUE;
        }

        if (isset($admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-email']][0]) && (count($userObject->getAttributes('user:email')) == 0)) {
            $mail = $admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-email']][0];
            $userObject->addAttribute('user:email', NULL, $mail);
            $loggerInstance->writeAudit($_SESSION['user'], "NEW", "User - added email address from external auth source");
        }

        if (count($userObject->getAttributes('user:realname')) > 0 || $newNameReceived) { // we have a real name ... set it
            $nameArray = $userObject->getAttributes("user:realname");
            if (!empty($nameArray[0])) {
                $_SESSION['name'] = $nameArray[0]['value'];
            }
        }
    }

    public function deauthenticate() {

        $as = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);

        $url = "//" . $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/inc/logout.php")) . "/logout_check.php";

        $as->logout([
            'ReturnTo' => $url,
            'ReturnStateParam' => 'LogoutState',
            'ReturnStateStage' => 'MyLogoutState',
        ]);
    }

}
