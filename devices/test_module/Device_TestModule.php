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
 * This file contains the TestModule class
 *
 * This is a very basic example of using the CAT API.  
 *
 * The module contains two files
 * in the Files directory. They will illustrate the use of the {@link DeviceConfig::copyFile()} method.
 * One fille will be coppied without the name change, for the second we will provide a new name.
 * The API also contains a similar {@link DeviceConfig::translateFile()} method, which is special to Windows installers and not used in this example.
 *
 * This module will collect all certificate files stored in the database for a given profile and will copy them to the working directory.
 *
 * If, for the given profile, an information file is available, this will also be copied to the working directory.
 *
 * The installer will collect all available configuration attributes and save them to a file in the form of the PHP print_r output.
 *
 * Finally, the installer will create a zip archive containing all above files and this file 
 * will be sent to the user as the configurator file.
 *
 * Go to the {@link Device_TestModule} and {@link DeviceConfig} class definitions to learn more.
 *  
 * @package ModuleWriting
 */
/**
 * this array holds the list of EAP methods supported by this device
 */
/**
 * 
 */
namespace devices\test_module;

/**
 * This is the main implementation class of the module
 *
 * The name of the class must the the 'Device_' followed by the name of the module file
 * (without the '.php' extension), so in this case the file is "TestModule.php" and
 * the class is Device_TestModule.
 *
 * The class MUST define the constructor method and one additional 
 * public method: {@link writeInstaller()}.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * It is important to understand how the device module fits into the whole picture, so here is s short descrption.
 * An external caller (for instance {@link GUI::generateInstaller()}) creates the module device instance and prepares
 * its environment for a given user profile by calling {@link DeviceConfig::setup()} method.
 *      this will:
 *       - create the temporary directory and save its path as $this->FPATH
 *       - process the CA certificates and store results in $this->attributes['internal:CAs'][0]
 *            $this->attributes['internal:CAs'][0] is an array of processed CA certificates
 *            a processed certifincate is an array 
 *               'pem' points to pem feromat certificate
 *               'der' points to der format certificate
 *               'md5' points to md5 fingerprint
 *               'sha1' points to sha1 fingerprint
 *               'name' points to the certificate subject
 *               'root' can be 1 for self-signed certificate or 0 otherwise
 *       - save the info_file (if exists) and put the name in $this->attributes['internal:info_file_name'][0]
 * Finally, the module {@link DeviceConfig::writeInstaller ()} is called and the returned path name is used for user download.
 *
 * @package ModuleWriting
 */
class Device_TestModule extends \core\DeviceConfig {

    /**
     * Constructs a Device object.
     *
     * It is CRUCIAL that the constructor sets $this->supportedEapMethods to an array of methods
     * available for the particular device.
     * {@source}
     * @final not to be redefined
     */
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods(\core\common\EAP::listKnownEAPTypes());
    }

    /**
     * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
     *
     * {@source}
     * @return string installer path name
     */
    public function writeInstaller() {
        $this->loggerInstance->debug(4, "Test Module Installer start\n");
        // create certificate files and save their names in $cAfiles arrary
        $cAfiles = $this->saveCertificateFiles('der');
        if ($cAfiles === FALSE) {
            $this->loggerInstance->debug(2, "copying of certificates failed\n");
        }

        // copy a fixed file from the module Files directory
        if (!$this->copyFile('Module.howto')) {
            $this->loggerInstance->debug(2, "copying of Module.howto failed\n");
        }

        // copy a fixed file from the module Files directory and saveunde a different name
        if (!$this->copyFile('test_file', 'copied_test_file')) {
            $this->loggerInstance->debug(2, "copying of Module.howto to copied_test_file failed\n");
        }
        $this->dumpAttibutes('profile_attributes');
        return $this->zipInstaller($this->attributes);
    }

    /**
     * prepare module desctiption and usage information
     * {@source}
     * @return string HTML text to be displayed in the information window
     */
    public function writeDeviceInfo() {
        $ssidCount = count($this->attributes['internal:SSID']);
        $out = "<p>";
        $out .= _("This installer is an example only. It produces a zip file containig the IdP certificates, info and logo files (if such have been defined by the IdP administrator) and a dump of all available attributes. The installer is called with $ssidCount SSIDs to configure.");
        return $out;
    }

    /**
     * zip files and return the archive name
     *
     * inline{@source}
     * return string
     */
    private function zipInstaller($attr) {
        if (count($attr)==0) {
            // never mind, just checking. You CAN use the $attr array to extract
            // information about the IdP/Profile if there's a need
        }
        $fileName = $this->installerBasename . '.zip';
        $output = system('zip -q ' . $fileName . ' *');
        if ($output === FALSE) {
            $this->loggerInstance->debug(2, "unable to zip the installer\n");
        }
        return $fileName;
    }
}
