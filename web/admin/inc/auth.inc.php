<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once(Config::$AUTHENTICATION['ssp-path-to-autoloader']);
require_once("User.php");
require_once("CAT.php");

function isAuthenticated() {
    $as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);
    return $as->isAuthenticated();
}

function authenticate() {
    $as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);
    $as->requireAuth();

    $admininfo = $as->getAttributes();

    if (!isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-identifier']][0])) {
        echo "FATAL ERROR: we did not receive a unique user identifier from the authentication source!";
        exit(1);
    }

    $user = $admininfo[Config::$AUTHENTICATION['ssp-attrib-identifier']][0];

    $_SESSION['user'] = $user;
    $new_name_received = FALSE;
    
    $user_object = new User($user);
    if (isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-name']][0]) && (count($user_object->getAttributes('user:realname')) == 0)) {
        $name = $admininfo[Config::$AUTHENTICATION['ssp-attrib-name']][0];
        $user_object->addAttribute('user:realname', $name);
        CAT::writeAudit($_SESSION['user'], "NEW", "User - added real name from external auth source");
        $new_name_received = TRUE;
    }

    if (isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-email']][0]) && (count($user_object->getAttributes('user:email')) == 0)) {
        $mail = $admininfo[Config::$AUTHENTICATION['ssp-attrib-email']][0];
        $user_object->addAttribute('user:email', $mail);
        CAT::writeAudit($_SESSION['user'], "NEW", "User - added email address from external auth source");
    }

    if (count($user_object->getAttributes('user:realname')) > 0 || $new_name_received) { // we have a real name ... set it
        $name_array = $user_object->getAttributes("user:realname");
        $_SESSION['name'] = $name_array[0]['value'];
    }
}

function deauthenticate() {

    $as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);

    $url = $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/inc/logout.php")) . "/logout_check.php";

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
        $url = "https://" . $url;
    else
        $url = "http://" . $url;

    $as->logout([
        'ReturnTo' => $url,
        'ReturnStateParam' => 'LogoutState',
        'ReturnStateStage' => 'MyLogoutState',
    ]);
}

?>
