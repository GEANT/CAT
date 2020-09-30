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

namespace web\lib\admin;

use Exception;

/**
 * This class handles admin user authentication.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class Authentication extends \core\common\Entity {

    /**
     * initialise ourselves, and simpleSAMLphp
     */
    public function __construct() {
        parent::__construct();
        include_once \config\Master::AUTHENTICATION['ssp-path-to-autoloader'];
    }
    /**
     * finds out whether the user is already authenticated. Does not trigger an authentication if not.
     *
     * @return boolean auth state
     */
    public function isAuthenticated() {

        $authSimple = new \SimpleSAML\Auth\Simple(\config\Master::AUTHENTICATION['ssp-authsource']);
        $session = \SimpleSAML\Session::getSessionFromRequest();
        $status = $authSimple->isAuthenticated();
        $session->cleanup();
        return $status;
    }

    /**
     * authenticates a user.
     * 
     * @return void
     * @throws Exception
     */
    public function authenticate() {
        \core\common\Entity::intoThePotatoes();
        $loggerInstance = new \core\common\Logging();
        $authSimple = new \SimpleSAML\Auth\Simple(\config\Master::AUTHENTICATION['ssp-authsource']);
        $authSimple->requireAuth();
        $admininfo = $authSimple->getAttributes();
        $session = \SimpleSAML\Session::getSessionFromRequest();
        $session->cleanup();

        if (!isset($admininfo[\config\Master::AUTHENTICATION['ssp-attrib-identifier']][0])) {
            $failtext = "FATAL ERROR: we did not receive a unique user identifier from the authentication source!";
            echo $failtext;
            throw new Exception($failtext);
        }

        $user = $admininfo[\config\Master::AUTHENTICATION['ssp-attrib-identifier']][0];

        $_SESSION['user'] = $user;
        $_SESSION['name'] = $admininfo[\config\Master::AUTHENTICATION['ssp-attrib-name']][0] ?? _("Unnamed User");
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
            if (isset($admininfo[\config\Master::AUTHENTICATION[$SSPside]][0]) && (count($userObject->getAttributes($CATside)) == 0) && \config\Master::DB['USER']['readonly'] === FALSE) {
                $name = $admininfo[\config\Master::AUTHENTICATION[$SSPside]][0];
                $userObject->addAttribute($CATside, NULL, $name);
                $loggerInstance->writeAudit($_SESSION['user'], "NEW", "User - added $CATside from external auth source");
                if ($CATside == "user:realname") {
                    $newNameReceived = TRUE;
                }
            }
        }

        if (count($userObject->getAttributes('user:realname')) > 0 || $newNameReceived) { // we have a real name in the DB. We trust this more than a session one, so set it
            $nameArray = $userObject->getAttributes("user:realname");
            if (!empty($nameArray[0])) {
                $_SESSION['name'] = $nameArray[0]['value'];
            }
        }
        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * deauthenticates the user.
     * Sends a SAML LogoutRequest to the IdP, which will kill the SSO session and return us to our own logout_check page.
     * 
     * @return void
     */
    public function deauthenticate() {

        $as = new \SimpleSAML\Auth\Simple(\config\Master::AUTHENTICATION['ssp-authsource']);
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
