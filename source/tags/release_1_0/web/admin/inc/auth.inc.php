<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php

require_once(Config::$AUTHENTICATION['ssp-path-to-autoloader']);

require_once("User.php");
require_once("CAT.php");

$as = new SimpleSAML_Auth_Simple(Config::$AUTHENTICATION['ssp-authsource']);
$as->requireAuth();

$admininfo = $as->getAttributes();

if (!isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-identifier']][0])) {
    echo "FATAL ERROR: we did not receive a unique user identifier from the authentication source!";
    exit(1);
}
        
$user = $admininfo[Config::$AUTHENTICATION['ssp-attrib-identifier']][0];

$_SESSION['user'] = $user;
$user_object = new User($user);
if (isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-name']][0]) && (count($user_object->getAttributes('user:realname')) == 0)) {
        $name = $admininfo[Config::$AUTHENTICATION['ssp-attrib-name']][0];
        $user_object->addAttribute('user:realname', $name);
        CAT::writeAudit($_SESSION['user'], "NEW", "User - added real name from external auth source");
    }

if (isset($admininfo[Config::$AUTHENTICATION['ssp-attrib-email']][0]) && (count($user_object->getAttributes('user:email')) == 0)) {
        $mail = $admininfo[Config::$AUTHENTICATION['ssp-attrib-email']][0];
        $user_object->addAttribute('user:email', $mail);
        CAT::writeAudit($_SESSION['user'], "NEW", "User - added email address from external auth source");
    }
    
if (count($user_object->getAttributes('user:realname')) > 0) { // we have a real name ... set it
     $name_array = $user_object->getAttributes("user:realname");
     $_SESSION['name'] = $name_array[0]['value'];
}

?>