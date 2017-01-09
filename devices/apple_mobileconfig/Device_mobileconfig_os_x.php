<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

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
namespace devices\apple_mobileconfig;

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
class Device_mobileconfig_os_x extends mobileconfigSuperclass {

    /**
     * this array holds the list of EAP methods supported by this device
     */
    final public function __construct() {
        parent::__construct();
        $this->supportedEapMethods = [\core\EAP::EAPTYPE_PEAP_MSCHAP2, \core\EAP::EAPTYPE_TTLS_PAP, \core\EAP::EAPTYPE_TTLS_MSCHAP2, \core\EAP::EAPTYPE_TLS, \core\EAP::EAPTYPE_SILVERBULLET];
        $this->loggerInstance->debug(4, "This device supports the following EAP methods: ");
        $this->loggerInstance->debug(4, print_r($this->supportedEapMethods,true));
    }
}
