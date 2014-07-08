<?php

/* * ********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the installer for iOS devices and Apple 10.7 Lion
 *
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Developer
 */
/**
 * 
 */
require_once('mobileconfig_superclass.php');

// set_locale("devices");

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
class Device_mobileconfig_os_x extends mobileconfig_superclass {

    /**
     * this array holds the list of EAP methods supported by this device
     */
    // public static $my_eap_methods = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$FAST_GTC);
    final public function __construct() {
        $this->supportedEapMethods = array(EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_MSCHAP2, EAP::$TLS);
        debug(4, "This device supports the following EAP methods: ");
        debug(4, $this->supportedEapMethods);
    }

}
