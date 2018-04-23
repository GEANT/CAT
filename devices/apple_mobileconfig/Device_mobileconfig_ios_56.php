<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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
class Device_mobileconfig_ios_56 extends mobileconfigSuperclass {

    public function __construct() {
        parent::__construct();
        $this->specialities['media:force_proxy'] = _("This device does not support forcing setting an HTTPS proxy.");
    }
    protected function proxySettings() {
        // iOS 5 and 6 do not support the Proxy auto-detect block properly, so 
        // override the function to do nothing.
        // it might support the force_proxy_ settings, but there are so few
        // specimen of that iOS version remaining that it's not worth the effort
        // to write the code
        return "";
    }

}
