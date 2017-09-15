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
 * This file defines the abstract Device class
 *
 * @package ModuleWriting
 */
/**
 * 
 */

namespace core;

use \Exception;

/**
 * This class defines the API for CAT module writers.
 *
 * A device is a fairly abstract notion. In most cases it represents
 * a particular operating system or a set of operationg systems
 * like MS Windows Vista and newer.
 *
 * The purpose of this class is to preapare a setup for the device configurator,
 * collect all necessary information from the database, taking into account
 * limitations, that a given device may present (like a set of supported EAP methods).
 *
 * All that is required from the device module is to produce a conigurator
 * file and pass its name back to the API.
 *
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 * 
 * @package ModuleWriting
 * @abstract
 */
abstract class DeviceConfig extends \core\common\Entity {

    /**
     * stores the path to the temporary working directory for a module instance
     * @var string $FPATH
     */
    public $FPATH;

    /**
     * array of specialities - will be displayed on the admin download as "footnote"
     * @var array specialities
     */
    public $specialities;

    /**
     * list of supported EAP methods
     * @var array EAP methods
     */
    public $supportedEapMethods;

    /**
     * the custom displayable variant of the term 'federation'
     * @var string
     */
    public $nomenclature_fed;
    
    /**
     * the custom displayable variant of the term 'institution'
     * @var string
     */
    public $nomenclature_inst;
    
    /**
     * 
     * @param array $eapArray the list of EAP methods the device supports
     */
    protected function setSupportedEapMethods($eapArray) {
        $this->supportedEapMethods = $eapArray;
        $this->loggerInstance->debug(4, "This device (" . __CLASS__ . ") supports the following EAP methods: ");
        $this->loggerInstance->debug(4, $this->supportedEapMethods, true);
    }

    /**
     * device module constructor should be defined by each module. 
     * The one important thing to do is to call setSupportedEapMethods with an 
     * array of EAP methods the device supports
     */
    public function __construct() {
        parent::__construct();
        // some config elements are displayable. We need some dummies to 
        // translate the common values for them. If a deployment chooses a 
        // different wording, no translation, sorry

        $dummy_NRO = _("National Roaming Operator");
        $dummy_inst1 = _("identity provider");
        $dummy_inst2 = _("organisation");
        // and do something useless with the strings so that there's no "unused" complaint
        $dummy_NRO = $dummy_NRO . $dummy_inst1 . $dummy_inst2;

        $this->nomenclature_fed = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation']);
        $this->nomenclature_inst = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution']);
    }

    /**
     * generates a UUID, for the devices which identify file contents by UUID
     *
     * @param string $prefix an extra prefix to set before the UUID
     * @return string UUID (possibly prefixed)
     */
    public function uuid($prefix = '', $deterministicSource = NULL) {
        if ($deterministicSource === NULL) {
            $chars = md5(uniqid(mt_rand(), true));
        } else {
            $chars = md5($deterministicSource);
        }
        $uuid = substr($chars, 0, 8) . '-';
        $uuid .= substr($chars, 8, 4) . '-';
        $uuid .= substr($chars, 12, 4) . '-';
        $uuid .= substr($chars, 16, 4) . '-';
        $uuid .= substr($chars, 20, 12);
        return $prefix . $uuid;
    }

    /**
     * Set up working environment for a device module
     *
     * Sets up the device module environment taking into account the actual profile
     * selected by the user in the GUI. The selected profile is passed as the
     * Profile $profile argumant.
     *
     * This method needs to be called after the device instance has been created (the GUI class does that)
     *
     * setup performs the following tasks:
     * - collect profile attributes and pass them as the attributes property;
     * - create the temporary working directory
     * - process CA certificates and store them as 'internal:CAs' attribute
     * - process and save optional info files and store references to them in
     *   'internal:info_file' attribute
     * @param AbstractProfile $profile the profile object which will be passed by the caller
     * @final not to be redefined
     */
    final public function setup(AbstractProfile $profile, $token = NULL, $importPassword = NULL) {
        $this->loggerInstance->debug(4, "module setup start\n");
        $purpose = 'installer';
        if (!$profile instanceof AbstractProfile) {
            $this->loggerInstance->debug(2, "No profile has been set\n");
            throw new Exception("No profile has been set");
        }

        $eaps = $profile->getEapMethodsinOrderOfPreference(1);
        $this->calculatePreferredEapType($eaps);
        if (count($this->selectedEap) == 0) {
            throw new Exception("No EAP type specified.");
        }
        $this->attributes = $this->getProfileAttributes($profile);
        $this->deviceUUID = $this->uuid('', 'CAT' . $profile->institution . "-" . $profile->identifier . "-" . $this->device_id);


        // if we are instantiating a Silverbullet profile AND have been given
        // a token, attempt to create the client certificate NOW
        // then, this is the only instance of the device ever which knows the
        // cert and private key. It's not saved anywhere, so it's gone forever
        // after code execution!

        $this->loggerInstance->debug(5, "DeviceConfig->setup() - preliminaries done.\n");
        if ($profile instanceof ProfileSilverbullet && $token !== NULL && $importPassword !== NULL) {
            $this->clientCert = $profile->generateCertificate($token, $importPassword);
            // add a UUID identifier for the devices that want one
            $this->clientCert['GUID'] = $this->uuid("", $this->clientCert['certdata']);
            // we need to drag this along; ChromeOS needs it outside the P12 container to encrypt the entire *config* with it.
            // Because encrypted private keys are not supported as per spec!
            $purpose = 'silverbullet';
            // let's keep a record for which device type this token was consumed
            $dbInstance = DBConnection::handle("INST");
            $devicename = \devices\Devices::listDevices()[$this->device_id]['display'];

            /*
             * If certificate has been created updating device name for it.
             */
            if ($this->clientCert['certificateId'] != null) {
                $certId = $this->clientCert['certificateId'];
                $dbInstance->exec("UPDATE `silverbullet_certificate` SET `device` = ? WHERE `id` = ?", "si", $devicename, $certId);
            }
        }
        $this->loggerInstance->debug(5, "DeviceConfig->setup() - silverbullet checks done.\n");
        // create temporary directory, its full path will be saved in $this->FPATH;
        $tempDir = $this->createTemporaryDirectory($purpose);
        $this->FPATH = $tempDir['dir'];
        mkdir($tempDir['dir'] . '/tmp');
        chdir($tempDir['dir'] . '/tmp');
        $caList = [];
        $x509 = new \core\common\X509();
        if (isset($this->attributes['eap:ca_file'])) {
            foreach ($this->attributes['eap:ca_file'] as $ca) {
                $processedCert = $x509->processCertificate($ca);
                if ($processedCert) {
                    // add a UUID for convenience (some devices refer to their CAs by a UUID value)
                    $processedCert['uuid'] = $this->uuid("", $processedCert['pem']);
                    $caList[] = $processedCert;
                }
            }
            $this->attributes['internal:CAs'][0] = $caList;
        }

        if (isset($this->attributes['support:info_file'])) {
            $this->attributes['internal:info_file'][0] = $this->saveInfoFile($this->attributes['support:info_file'][0]);
        }
        if (isset($this->attributes['general:logo_file'])) {
            $this->loggerInstance->debug(5, "saving IDP logo\n");
            $this->attributes['internal:logo_file'] = $this->saveLogoFile($this->attributes['general:logo_file'],'idp');
        }
        if (isset($this->attributes['fed:logo_file'])) {
            $this->loggerInstance->debug(5, "saving FED logo\n");
            $this->attributes['fed:logo_file'] = $this->saveLogoFile($this->attributes['fed:logo_file'], 'fed');
        }
        $this->attributes['internal:SSID'] = $this->getSSIDs()['add'];

        $this->attributes['internal:remove_SSID'] = $this->getSSIDs()['del'];

        $this->attributes['internal:consortia'] = $this->getConsortia();
        $olddomain = $this->languageInstance->setTextDomain("core");
        $this->support_email_substitute = sprintf(_("your local %s support"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->support_url_substitute = sprintf(_("your local %s support page"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->languageInstance->setTextDomain($olddomain);

        if ($this->signer && $this->options['sign']) {
            $this->sign = ROOT . '/signer/' . $this->signer;
        }
        $this->installerBasename = $this->getInstallerBasename();
    }

    /**
     * Selects the preferred eap method based on profile EAP configuration and device EAP capabilities
     *
     * @param array eapArrayofObjects an array of eap methods supported by a given device
     */
    public function calculatePreferredEapType($eapArrayofObjects) {
        $this->selectedEap = [];
        foreach ($eapArrayofObjects as $eap) {
            if (in_array($eap->getArrayRep(), $this->supportedEapMethods)) {
                $this->selectedEap = $eap->getArrayRep();
            }
        }
        if ($this->selectedEap != []) {
            $this->selectedEapObject = new common\EAP($this->selectedEap);
        }
    }

    /**
     * prepare usage information for the installer
     * every device module should override this method
     *
     * @return String HTML text to be displayed
     */
    public function writeDeviceInfo() {
        return _("Sorry, this should not happen - no additional information is available");
    }
    
    private function findSourceFile($file) {
        if (is_file($this->module_path . '/Files/' . $this->device_id . '/' . $file)) {
            return $this->module_path . '/Files/' . $this->device_id . '/' . $file;
        } elseif (is_file($this->module_path . '/Files/' . $file)) {
            return $this->module_path . '/Files/' . $file;
        } else {
            $this->loggerInstance->debug(2, "requested file $file does not exist\n");
            return(FALSE);
        }
    }

    /**
     *  Copy a file from the module location to the temporary directory.
     *
     * If the second argument is provided then the file will be saved under the name 
     * taken form this argument. If only one parameter is given, source and destination
     * filenames are the same
     * Source file can be located either in the Files subdirectory or in the sibdirectory of Files
     * named the same as device_id. The second option takes precedence.
     *
     * @param string $source_name The source file name
     * @param string $output_name The destination file name
     *
     * @return bool result of the copy operation
     * @final not to be redefined
     */
    final protected function copyFile($source_name, $output_name = NULL) {
        if ($output_name === NULL) {
            $output_name = $source_name;
        }
        $this->loggerInstance->debug(5, "fileCopy($source_name, $output_name)\n");
        $source = $this->findSourceFile($source_name);
        if ($source === FALSE) {
            return FALSE;
        }
        $this->loggerInstance->debug(5, "Copying $source to $output_name\n");
        $result = copy($source, "$output_name");
        if (!$result) {
            $this->loggerInstance->debug(2, "fileCopy($source_name, $output_name) failed\n");
        }
        return($result);
    }

    /**
     *  Copy a file from the module location to the temporary directory aplying transcoding.
     *
     * Transcoding is only required for Windows installers, and no Unicode support
     * in NSIS (NSIS version below 3)
     * Trancoding is only applied if the third optional parameter is set and nonzero
     * If CONFIG['NSIS']_VERSION is set to 3 or more, no transcoding will be applied
     * regardless of the third parameter value.
     * If the second argument is provided and is not equal to 0, then the file will be
     * saved under the name taken from this argument.
     * If only one parameter is given or the second is equal to 0, source and destination
     * filenames are the same.
     * The third optional parameter, if nonzero, should be the character set understood by iconv
     * This is required by the Windows installer and is expected to go away in the future.
     * Source file can be located either in the Files subdirectory or in the sibdirectory of Files
     * named the same as device_id. The second option takes precedence.
     *
     * @param string $source_name The source file name
     * @param string $output_name The destination file name
     * @param int $encoding Set Windows charset if non-zero
     *
     * @final not to be redefined
     */
    final protected function translateFile($source_name, $output_name = NULL, $encoding = 0) {
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $encoding = 0;
        }
        if ($output_name === NULL) {
            $output_name = $source_name;
        }

        $this->loggerInstance->debug(5, "translateFile($source_name, $output_name, $encoding)\n");
        ob_start();
        $this->loggerInstance->debug(5, $this->module_path . '/Files/' . $this->device_id . '/' . $source_name . "\n");
        $source = $this->findSourceFile($source_name);
        
        if ($source !== FALSE) { // if there is no file found, don't attempt to include an uninitialised variable
            include($source);
        }
        $output = ob_get_clean();
        if ($encoding) {
            $outputClean = iconv('UTF-8', $encoding . '//TRANSLIT', $output);
            if ($outputClean) {
                $output = $outputClean;
            }
        }
        $fileHandle = fopen("$output_name", "w");
        if (!$fileHandle) {
            $this->loggerInstance->debug(2, "translateFile($source, $output_name, $encoding) failed\n");
            return FALSE;
        }
        fwrite($fileHandle, $output);
        fclose($fileHandle);
        $this->loggerInstance->debug(5, "translateFile($source, $output_name, $encoding) end\n");
        return TRUE;
    }

    /**
     * Transcode a string adding double quotes escaping
     *
     * Transcoding is only required for Windows installers, and no Unicode support
     * in NSIS (NSIS version below 3)
     * Trancoding is only applied if the third optional parameter is set and nonzero
     * If CONFIG['NSIS']_VERSION is set to 3 or more, no transcoding will be applied
     * regardless of the second parameter value.
     * The second optional parameter, if nonzero, should be the character set understood by iconv
     * This is required by the Windows installer and is expected to go away in the future.
     *
     * @param string $source_string The source string
     * @param int $encoding Set Windows charset if non-zero
     *
     * @final not to be redefined
     */
    final protected function translateString($source_string, $encoding = 0) {
        $this->loggerInstance->debug(5, "translateString input: \"$source_string\"\n");
        if (empty($source_string)) {
            return($source_string);
        }
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $encoding = 0;
        }
        if ($encoding) {
            $output_c = iconv('UTF-8', $encoding . '//TRANSLIT', $source_string);
        } else {
            $output_c = $source_string;
        }
        if ($output_c) {
            $source_string = str_replace('"', '$\\"', $output_c);
        } else {
            $this->loggerInstance->debug(2, "Failed to convert string \"$source_string\"\n");
        }
        return $source_string;
    }

    /**
     * Save certificate files in either DER or PEM format
     *
     * Certificate files will be saved in the module working directory.
     * @param string $format  only "der" and "pem" are currently allowed
     * @return array an array of arrays or FALSE on error
     * saved certificate file names are avalable under the 'file' index
     * additional array entries are indexed as 'sha1', 'md5', and 'root'.
     * sha1 and md5 are correcponding certificate hashes
     * root is set to 1 for the CA roor certicicate and 0 otherwise
     */
    final protected function saveCertificateFiles($format) {
        switch ($format) {
            case "der": // fall-thorugh, same treatment
            case "pem":
                $iterator = 0;
                $caFiles = [];
                $caArray = $this->attributes['internal:CAs'][0];
                if (!$caArray) {
                    return(FALSE);
                }
                foreach ($caArray as $certAuthority) {
                    $fileHandle = fopen("cert-$iterator.crt", "w");
                    if (!$fileHandle) {
                        throw new Exception("problem opening the file");
                    }
                    if ($format === "pem") {
                        fwrite($fileHandle, $certAuthority['pem']);
                    } else {
                        fwrite($fileHandle, $certAuthority['der']);
                    }
                    fclose($fileHandle);
                    $certAuthorityProps = [];
                    $certAuthorityProps['file'] = "cert-$iterator.crt";
                    $certAuthorityProps['sha1'] = $certAuthority['sha1'];
                    $certAuthorityProps['md5'] = $certAuthority['md5'];
                    $certAuthorityProps['root'] = $certAuthority['root'];
                    $caFiles[] = $certAuthorityProps;
                    $iterator++;
                }
                return($caFiles);
            default:
                $this->loggerInstance->debug(2, 'incorrect format value specified');
                return(FALSE);
        }
    }

    /**
     * Generate installer filename base.
     * Device module should use this name adding an extension.
     * Normally the device identifier follows the Consortium name.
     * The sting taken for the device identifier equals (by default) to the index in the listDevices array,
     * but can be overriden with the 'device_id' device option.
     */
    private function getInstallerBasename() {
        $replace_pattern = '/[ ()\/\'"]+/';
        $consortiumName = iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace($replace_pattern, '_', CONFIG_CONFASSISTANT['CONSORTIUM']['name']));
        if (isset($this->attributes['profile:customsuffix'][1])) { 
            // this string will end up as a filename on a filesystem, so always
            // take a latin-based language variant if available
            // and then scrub non-ASCII just in case
            return $consortiumName . "-" . $this->getDeviceId() . iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace($replace_pattern, '_', $this->attributes['profile:customsuffix'][1]));
        }
        // Okay, no custom suffix. 
        // Use the configured inst name and apply shortening heuristics
        $lang_pointer = CONFIG['LANGUAGES'][$this->languageInstance->getLang()]['latin_based'] == TRUE ? 0 : 1;
        $this->loggerInstance->debug(5, "getInstallerBasename1:" . $this->attributes['general:instname'][$lang_pointer] . "\n");
        $inst = iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace($replace_pattern, '_', $this->attributes['general:instname'][$lang_pointer]));
        $this->loggerInstance->debug(4, "getInstallerBasename2:$inst\n");
        $Inst_a = explode('_', $inst);
        if (count($Inst_a) > 2) {
            $inst = '';
            foreach ($Inst_a as $i) {
                $inst .= $i[0];
            }
        }
        // and if the inst has multiple profiles, add the profile name behin
        if ($this->attributes['internal:profile_count'][0] > 1) {
            if (!empty($this->attributes['profile:name']) && !empty($this->attributes['profile:name'][$lang_pointer])) {
                $profTemp = iconv("UTF-8", "US-ASCII//TRANSLIT", preg_replace($replace_pattern, '_', $this->attributes['profile:name'][$lang_pointer]));
                $prof = preg_replace('/_+$/', '', $profTemp);
                return $consortiumName . '-' . $this->getDeviceId() . $inst . '-' . $prof;
            }
        }
        return $consortiumName . '-' . $this->getDeviceId() . $inst;
    }

    private function getDeviceId() {
        $deviceId = $this->device_id;
        if (isset($this->options['device_id'])) {
            $deviceId = $this->options['device_id'];
        }
        if ($deviceId !== '') {
            $deviceId .= '-';
        }
        return $deviceId;
    }

    private function getSSIDs() {
        $ssidList = [];
        $ssidList['add'] = [];
        $ssidList['del'] = [];
        if (isset(CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) {
            foreach (CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'] as $ssid) {
                if (isset(CONFIG_CONFASSISTANT['CONSORTIUM']['tkipsupport']) && CONFIG_CONFASSISTANT['CONSORTIUM']['tkipsupport'] == TRUE) {
                    $ssidList['add'][$ssid] = 'TKIP';
                } else {
                    $ssidList['add'][$ssid] = 'AES';
                    $ssidList['del'][$ssid] = 'TKIP';
                }
            }
        }
        if (isset($this->attributes['media:SSID'])) {
            $ssidWpa2 = $this->attributes['media:SSID'];

            foreach ($ssidWpa2 as $ssid) {
                $ssidList['add'][$ssid] = 'AES';
            }
        }
        if (isset($this->attributes['media:SSID_with_legacy'])) {
            $ssidTkip = $this->attributes['media:SSID_with_legacy'];
            foreach ($ssidTkip as $ssid) {
                $ssidList['add'][$ssid] = 'TKIP';
            }
        }
        if (isset($this->attributes['media:remove_SSID'])) {
            $ssidRemove = $this->attributes['media:remove_SSID'];
            foreach ($ssidRemove as $ssid) {
                $ssidList['del'][$ssid] = 'DEL';
            }
        }
        return $ssidList;
    }

    private function getConsortia() {
        $consortia = CONFIG_CONFASSISTANT['CONSORTIUM']['interworking-consortium-oi'];
        if (isset($this->attributes['media:consortium_OI'])) {
            foreach ($this->attributes['media:consortium_OI'] as $new_oi) {
                $consortia[] = $new_oi;
            }
        }
        return $consortia;
    }

    /**
     * An array with shorthand definitions for MIME types
     * @var array
     */
    private $mime_extensions = [
        'text/plain' => 'txt',
        'text/rtf' => 'rtf',
        'application/pdf' => 'pdf',
    ];

    private function saveLogoFile($logos,$type) {
        $iterator = 0;
        $returnarray = [];
        foreach ($logos as $blob) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($blob);
            $matches = [];
            if (preg_match('/^image\/(.*)/', $mime, $matches)) {
                $ext = $matches[1];
            } else {
                $ext = 'unsupported';
            }
            $this->loggerInstance->debug(5, "saveLogoFile: $mime : $ext\n");
            $fileName = 'logo-' . $type . $iterator . '.' . $ext;
            $fileHandle = fopen($fileName, "w");
            if (!$fileHandle) {
                $this->loggerInstance->debug(2, "saveLogoFile failed for: $fileName\n");
                throw new Exception("problem opening the file");
            }
            fwrite($fileHandle, $blob);
            fclose($fileHandle);
            $returnarray[] = ['name' => $fileName, 'mime' => $ext];
            $iterator++;
        }
        return($returnarray);
    }

    private function saveInfoFile($blob) {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($blob);
        $ext = isset($this->mime_extensions[$mime]) ? $this->mime_extensions[$mime] : 'usupported';
        $this->loggerInstance->debug(5, "saveInfoFile: $mime : $ext\n");
        $fileHandle = fopen('local-info.' . $ext, "w");
        if (!$fileHandle) {
            throw new Exception("problem opening the file");
        }
        fwrite($fileHandle, $blob);
        fclose($fileHandle);
        return(['name' => 'local-info.' . $ext, 'mime' => $ext]);
    }

    private function getProfileAttributes(AbstractProfile $profile) {
        $bestMatchEap = $this->selectedEap;
        if (count($bestMatchEap) > 0) {
            $a = $profile->getCollapsedAttributes($bestMatchEap);
            $a['eap'] = $bestMatchEap;
            $a['all_eaps'] = $profile->getEapMethodsinOrderOfPreference(1);
            return($a);
        }
        print("No supported eap types found for this profile.\n");
        return [];
    }

    /**
     * dumps attributes for debugging purposes
     *
     * dumpAttibutes method is supplied for debuging purposes, it simply dumps the attribute array
     * to a file with name passed in the attribute.
     * @param string $file the output file name
     */
    protected function dumpAttibutes($file) {
        ob_start();
        print_r($this->attributes);
        $output = ob_get_clean();
        $f = fopen($file, "w");
        fwrite($f, $output);
        fclose($f);
    }

    /**
     * placeholder for the main device method
     *
     */
    protected function writeInstaller() {
        return("download path");
    }

    protected function determineOuterIdString() {
        $outerId = 0;
        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {
            $outerId = "@" . $this->attributes['internal:realm'][0];
            if (isset($this->attributes['internal:anon_local_value'])) {
                $outerId = $this->attributes['internal:anon_local_value'][0] . $outerId;
            }
        }
        return $outerId;
    }

    /**
     * Array passing all options to the device module.
     *
     * $attrbutes array contains option values defined for the institution and a particular
     * profile (possibly overriding one another) ready for the device module to consume.
     * 
     * For each of the options the value is another array of vales (even if only one value is present).
     * Some attributes may be missing if they have not been configured for a viven institution or profile.
     *
     * The following attributes are meant to be used by device modules:
     * - <b>general:geo_coordinates</b> -  geographical coordinates of the institution or a campus
     * - <b>support:info_file</b>  -  consent file displayed to the users                                                         
     * - <b>general:logo_file</b>  -  file data containing institution logo                                                      
     * - <b>support:eap_types</b>  -  URL to a local support page for a specific eap methiod, not to be confused with general:url 
     * - <b>support:email</b>      -  email for users to contact for local instructions                                           
     * - <b>support:phone</b>      -  telephone number for users to contact for local instructions                                
     * - <b>support:url</b>        -  URL where the user will find local instructions       
     * - <b>internal:info_file</b> -  the pathname of the info_file saved in the working directory
     * - <b>internal:logo_file</b>  -  array of pathnames of logo_files saved in the working directory
     * - <b>internal:CAs</b> - the value is an array produced by X509::processCertificate() with the following filds
     * - <b>internal:SSID</b> - an array indexed by SSID strings with values either TKIP or AES; if TKIP is set the both WPA/TKIP and WPA2/AES should be set if AES is set the this is a WPA2/AES only SSID; the consortium's defined SSIDs are always set as the first array elements.
     * -<b>internal:profile_count</b> - the number of profiles for the associated IdP
     *
     *
     * these attributes are available and can be used, but the "internal" attributes are better suited for modules
     * -  eap:ca_file    -      certificate of the CA signing the RADIUS server key                                         
     * - <b>media:SSID</b>       -  additional SSID to configure, WPA2/AES only (device modules should use internal:SSID)
     * - <b>media:SSID_with_legacy</b> -  additional SSID to configure, WPA2/AES and WPA/TKIP (device modules should use internal:SSID)
     *
     * @see \core\common\X509::processCertificate()
     * @var array $attributes
     */
    public $attributes;

    /**
     * stores the path to the module source location and is used 
     * by copyFile and translateFile
     * the only reason for it to be a public variable ies that it is set by the DeviceFactory class
     * module_path should not be used by module drivers.
     * @var string 
     */
    public $module_path;

    /**
     * The optimal EAP type
     *
     */

    /**
     * optimal EAP method selected given profile and device
     * @var array
     */
    public $selectedEap;
    public $selectedEapObject;

    /**
     * the path to the profile signing program
     * device modules which require signing should use this property to exec the signer
     * the signer program must accept two arguments - input and output file names
     * the signer program mus operate in the local directory and filenames are relative to this
     * directory
     *
     * @var string
     */
    public $sign;
    public $signer;

    /**
     * The string identifier of the device (don't show this to users)
     * @var string
     */
    public $device_id;

    /**
     * See devices-template.php for a list of available options
     * @var array
     */
    public $options;

    /**
     * This string will be shown if no support email was configured by the admin
     * 
     * @var string 
     */
    public $support_email_substitute;

    /**
     * This string will be shown if no support URL was configured by the admin
     * 
     * @var string 
     */
    public $support_url_substitute;

    /**
     * This string should be used by all installer modules to set the 
     * installer file basename.
     *
     * @var string 
     */
    public $installerBasename;

    /**
     * stores the PKCS#12 DER representation of a client certificate for SilverBullet
     */
    protected $clientCert;

    /**
     * stores identifier used by GEANTLink profiles
     */
    public $deviceUUID;

}
