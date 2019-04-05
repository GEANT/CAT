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
 * Go to the {@link DeviceTestModule} and {@link DeviceConfig} class definitions to learn more.
 *  
 * @package ModuleWriting
 */

namespace devices\test_module;

/**
 * This is the main implementation class of the module
 *
 * The name of the class must the the 'Device' followed by the name of the module file
 * (without the '.php' extension), so in this case the file is "TestModule.php" and
 * the class is DeviceTestModule.
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
class DeviceTestModule extends \core\DeviceConfig {

    /**
     * Constructs a Device object.
     *
     * It is CRUCIAL that the constructor sets $this->supportedEapMethods to an array of methods
     * available for the particular device.
     * 
     * @final not to be redefined
     */
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods(\core\common\EAP::EAPTYPES_CONVERSION);
    }

    /**
     * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
     *
     * @return string installer path name
     */
    public function writeInstaller() {
        $this->loggerInstance->debug(4, "Test Module Installer start\n");
        // create certificate files and save their names in $cAfiles arrary
        $cAfiles = $this->saveCertificateFiles('der');
        if ($cAfiles === []) {
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
        return $this->zipInstaller();
    }

    /**
     * prepare module desctiption and usage information
     * 
     * @return string HTML text to be displayed in the information window
     */
    public function writeDeviceInfo() {
        \core\common\Entity::intoThePotatoes();
        $ssidCount = count($this->attributes['internal:SSID']);
        $out = "<p>";
        $out .= sprintf(_("This installer is an example only. It produces a zip file containig the IdP certificates, info and logo files (if such have been defined by the IdP administrator) and a dump of all available attributes. The installer is called with %d SSIDs to configure."), $ssidCount);
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }

    /**
     * zip files and return the archive name
     *
     * @return string
     */
    private function zipInstaller() {
        // one can always access $this->attributes to check things
        $fileName = $this->installerBasename . '.zip';
        $output = system('zip -q ' . $fileName . ' *');
        if ($output === FALSE) {
            $this->loggerInstance->debug(2, "unable to zip the installer\n");
        }
        return $fileName;
    }
}
