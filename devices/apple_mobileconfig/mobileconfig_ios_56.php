<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
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
require_once('mobileconfigSuperclass.php');

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
class Device_mobileconfig_ios_56 extends mobileconfigSuperclass {

    /**
     * this array holds the list of EAP methods supported by this device
     */
    final public function __construct() {
        $this->supportedEapMethods = [EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_MSCHAP2];
        debug(4, "This device supports the following EAP methods: ");
        debug(4, $this->supportedEapMethods);
    }

}
