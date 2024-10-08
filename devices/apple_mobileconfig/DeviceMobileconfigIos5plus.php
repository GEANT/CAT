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
class DeviceMobileconfigIos5plus extends MobileconfigSuperclass {

    /**
     * construct device and load specialities array
     */
    public function __construct() {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->specialities['media:force_proxy'] = _("This device does not support forcing setting an HTTPS proxy.");
        \core\common\Entity::outOfThePotatoes();
    }
    
    /**
     * We don't support any proxy settings, just override the parent class empty
     * 
     * @return string
     */
    protected function proxySettings() {
        // iOS 5 and 6 do not support the Proxy auto-detect block properly, so 
        // override the function to do nothing.
        // it might support the force_proxy_ settings, but there are so few
        // specimen of that iOS version remaining that it's not worth the effort
        // to write the code
        return "";
    }

}
