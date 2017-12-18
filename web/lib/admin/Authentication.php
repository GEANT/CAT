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

use Exception;

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);

/**
 * This class handles admin user authentication.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class Authentication {

    /**
     * finds out whether the user is already authenticated. Does not trigger an authentication if not.
     *
     * @return bool auth state
     */
    public function isAuthenticated() {
        $authSimple = new \SimpleSAML\Auth\Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
        return $authSimple->isAuthenticated();
    }

    /**
     * authenticates a user.
     * 
     * @throws Exception
     */
    public function authenticate() {
        $loggerInstance = new \core\common\Logging();
        $authSimple = new \SimpleSAML\Auth\Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
        $authSimple->requireAuth();

        $admininfo = $authSimple->getAttributes();

        if (!isset($admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-identifier']][0])) {
            $failtext = "FATAL ERROR: we did not receive a unique user identifier from the authentication source!";
            echo $failtext;
            throw new Exception($failtext);
        }

        $user = $admininfo[CONFIG['AUTHENTICATION']['ssp-attrib-identifier']][0];

        $_SESSION['user'] = $user;
        /*
         * This is a nice pathological test case for a user ID.
         *
         * */
        //$_SESSION['user'] = "<saml:NameID xmlns:saml=\"urn:oasis:names:tc:SAML:2.0:assertion\" NameQualifier=\"https://idp.jisc.ac.uk/idp/shibboleth\" SPNameQualifier=\"https://cat-beta.govroam.uk/simplesaml/module.php/saml/sp/metadata.php/default-sp\" Format=\"urn:oasis:names:tc:SAML:2.0:nameid-format:persistent\">XXXXXXXXXXXXXXXX</saml:NameID>";


        $newNameReceived = FALSE;

        $userObject = new \core\User($user);

        $attribMapping = [
            "ssp-attrib-name" => "user:realname",
            "ssp-attrib-email" => "user:email"];

        foreach ($attribMapping as $SSPside => $CATside) {
            if (isset($admininfo[CONFIG['AUTHENTICATION'][$SSPside]][0]) && (count($userObject->getAttributes($CATside)) == 0) && CONFIG['DB']['USER']['readonly'] === FALSE) {
                $name = $admininfo[CONFIG['AUTHENTICATION'][$SSPside]][0];
                $userObject->addAttribute($CATside, NULL, $name);
                $loggerInstance->writeAudit($_SESSION['user'], "NEW", "User - added $CATside from external auth source");
                if ($CATside == "user:realname") {
                    $newNameReceived = TRUE;
                }
            }
        }

        if (count($userObject->getAttributes('user:realname')) > 0 || $newNameReceived) { // we have a real name ... set it
            $nameArray = $userObject->getAttributes("user:realname");
            if (!empty($nameArray[0])) {
                $_SESSION['name'] = $nameArray[0]['value'];
            }
        }
    }

    /**
     * deauthenticates the user.
     * 
     * Sends a SAML LogoutRequest to the IdP, which will kill the SSO session and return us to our own logout_check page.
     */
    public function deauthenticate() {

        $as = new \SimpleSAML\Auth\Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
        $servername = filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_STRING);
        $scriptself = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_STRING);
        $url = "https://www.eduroam.org"; // fallback if something goes wrong during URL construction below
        $trailerPosition = strrpos($scriptself, "/inc/logout.php");
        if ($trailerPosition !== FALSE) {
            $base = substr($scriptself, 0, $trailerPosition);
            if ($base !== FALSE) {
                $url = "//$servername" . $base . "/logout_check.php";
            }
        }

        $as->logout([
            'ReturnTo' => $url,
            'ReturnStateParam' => 'LogoutState',
            'ReturnStateStage' => 'MyLogoutState',
        ]);
    }

}
