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
abstract class DeviceConfig extends \core\common\Entity
{

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
     * 
     * @var string the realm attached to the profile (possibly substituted with fallback value
     */
    public $realm = NULL;
    
    /**
     * sets the supported EAP methods for a device
     * 
     * @param array $eapArray the list of EAP methods the device supports
     * @return void
     */
    protected function setSupportedEapMethods($eapArray)
    {
        $this->supportedEapMethods = $eapArray;
        $this->loggerInstance->debug(4, "This device (" . __CLASS__ . ") supports the following EAP methods: ");
        $this->loggerInstance->debug(4, $this->supportedEapMethods);
    }

    /**
     * device module constructor should be defined by each module. 
     * The one important thing to do is to call setSupportedEapMethods with an 
     * array of EAP methods the device supports
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * given one or more server name strings, calculate the suffix that is common
     * to all of them
     * 
     * Examples:
     * 
     * ["host.somewhere.com", "gost.somewhere.com"] => "ost.somewhere.com"
     * ["my.server.name"] => "my.server.name"
     * ["foo.bar.de", "baz.bar.ge"] => "e"
     * ["server1.example.com", "server2.example.com", "serverN.example.com"] => ".example.com"

     * @return string
     */
    public function longestNameSuffix()
    {
        // for all configured server names, find the string that is the longest
        // suffix to all of them
        $longestSuffix = "";
        if (!isset($this->attributes["eap:server_name"])) {
            return "";
        }
        $numStrings = count($this->attributes["eap:server_name"]);
        if ($numStrings == 0) {
            return "";
        }
        // always take the candidate character from the first array element, and
        // verify whether the other elements have that character in the same 
        // position, too
        while (TRUE) {
            if ($longestSuffix == $this->attributes["eap:server_name"][0]) {
                break;
            }
            $candidate = substr($this->attributes["eap:server_name"][0], -(strlen($longestSuffix) + 1), 1);
            for ($iterator = 1; $iterator < $numStrings; $iterator++) {
                if (substr($this->attributes["eap:server_name"][$iterator], -(strlen($longestSuffix) + 1), 1) != $candidate) {
                    break 2;
                }
            }
            $longestSuffix = $candidate.$longestSuffix;
        }
        return $longestSuffix;
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
     * @param AbstractProfile $profile        the profile object which will be passed by the caller
     * @param string          $token          the invitation token for silverbullet requests
     * @param string          $importPassword the PIN for the installer for silverbullet requests
     * @return void
     * @throws Exception
     * @final not to be redefined
     */
    final public function setup(AbstractProfile $profile, $token = NULL, $importPassword = NULL, $openRoaming = 0)
    {
        $this->loggerInstance->debug(4, "module setup start\n");
        common\Entity::intoThePotatoes();
        $purpose = 'installer';
        $eaps = $profile->getEapMethodsinOrderOfPreference(1);
        $this->calculatePreferredEapType($eaps);
        if (count($this->selectedEap) == 0) {
            throw new Exception("No EAP type available.");
        }
        $this->attributes = $this->getProfileAttributes($profile);
        $this->deviceUUID = common\Entity::uuid('', 'CAT'.$profile->institution."-".$profile->identifier."-".$this->device_id);

        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {
            $this->realm = $this->attributes['internal:realm'][0];
        }
        // if we are instantiating a Silverbullet profile AND have been given
        // a token, attempt to create the client certificate NOW
        // then, this is the only instance of the device ever which knows the
        // cert and private key. It's not saved anywhere, so it's gone forever
        // after code execution!

        $this->loggerInstance->debug(5, "DeviceConfig->setup() - preliminaries done.\n");
        if ($profile instanceof ProfileSilverbullet && $token !== NULL && $importPassword !== NULL) {
            $this->clientCert = SilverbulletCertificate::issueCertificate($token, $importPassword, $this->options['clientcert']);
            // we need to drag this along; ChromeOS needs it outside the P12 container to encrypt the entire *config* with it.
            // Because encrypted private keys are not supported as per spec!
            $purpose = 'silverbullet';
            // let's keep a record for which device type this token was consumed
            $dbInstance = DBConnection::handle("INST");
            $certId = $this->clientCert['certObject']->dbId;
            $this->attributes['internal:username'] = [$this->clientCert['CN']];
            $dbInstance->exec("UPDATE `silverbullet_certificate` SET `device` = ? WHERE `id` = ?", "si", $this->device_id, $certId);
        }
        $this->loggerInstance->debug(5, "DeviceConfig->setup() - silverbullet checks done.\n");
        // create temporary directory, its full path will be saved in $this->FPATH;
        $tempDir = \core\common\Entity::createTemporaryDirectory($purpose);
        $this->FPATH = $tempDir['dir'];
        mkdir($tempDir['dir'].'/tmp');
        chdir($tempDir['dir'].'/tmp');
        $caList = [];
        $x509 = new \core\common\X509();
        if (isset($this->attributes['eap:ca_file'])) {
            foreach ($this->attributes['eap:ca_file'] as $ca) {
                $processedCert = $x509->processCertificate($ca);
                if (is_array($processedCert)) {
                    // add a UUID for convenience (some devices refer to their CAs by a UUID value)
                    $processedCert['uuid'] = common\Entity::uuid("", $processedCert['pem']);
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
            $this->attributes['internal:logo_file'] = $this->saveLogoFile($this->attributes['general:logo_file'], 'idp');
        }
        if (isset($this->attributes['fed:logo_file'])) {
            $this->loggerInstance->debug(5, "saving FED logo\n");
            $this->attributes['fed:logo_file'] = $this->saveLogoFile($this->attributes['fed:logo_file'], 'fed');
        }
        $this->attributes['internal:SSID'] = $this->getSSIDs()['add'];

        $this->attributes['internal:remove_SSID'] = $this->getSSIDs()['del'];

        $this->attributes['internal:consortia'] = $this->getConsortia();
        if ($openRoaming == 1 && isset($this->attributes['media:openroaming'])) {
            $this->attributes['internal:openroaming'] = TRUE;
        }
        
        $this->attributes['internal:networks'] = $this->getNetworks();

        $this->support_email_substitute = sprintf(_("your local %s support"), \config\ConfAssistant::CONSORTIUM['display_name']);
        $this->support_url_substitute = sprintf(_("your local %s support page"), \config\ConfAssistant::CONSORTIUM['display_name']);

        if ($this->signer && $this->options['sign']) {
            $this->sign = ROOT.'/signer/'.$this->signer;
        }
        $this->installerBasename = $this->getInstallerBasename();
        common\Entity::outOfThePotatoes();
    }

    /**
     * Selects the preferred eap method based on profile EAP configuration and device EAP capabilities
     *
     * @param array $eapArrayofObjects an array of eap methods supported by a given device
     * @return void
     */
    public function calculatePreferredEapType($eapArrayofObjects)
    {
        $this->selectedEap = [];
        foreach ($eapArrayofObjects as $eap) {
            if (in_array($eap->getArrayRep(), $this->supportedEapMethods)) {
                $this->selectedEap = $eap->getArrayRep();
                break;
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
     * @return string HTML text to be displayed
     */
    public function writeDeviceInfo()
    {
        common\Entity::intoThePotatoes();
        $retval = _("Sorry, this should not happen - no additional information is available");
        common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * function to return exactly one attribute type
     * 
     * @param string $attrName the attribute to retrieve
     * @return array|NULL the attributes
     */
    public function getAttribute($attrName)
    {
        return empty($this->attributes[$attrName]) ? NULL : $this->attributes[$attrName];
    }

    /**
     * some modules have a complex directory structure. This helper finds resources
     * in that structure. Mostly used in the Windows modules.
     * 
     * @param  string $file the filename to search for (without path)
     * @return string|boolean the filename as found, with path, or FALSE if it does not exist
     */
    protected function findSourceFile($file)
    {
        if (is_file($this->module_path.'/Files/'.$this->device_id.'/'.$file)) {
            return $this->module_path.'/Files/'.$this->device_id.'/'.$file;
        } elseif (is_file($this->module_path.'/Files/'.$file)) {
            return $this->module_path.'/Files/'.$file;
        } else {
            $this->loggerInstance->debug(2, "requested file $file does not exist\n");
            return FALSE;
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
     * @return boolean result of the copy operation
     * @final not to be redefined
     */
    final protected function copyFile($source_name, $output_name = NULL)
    {
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
     * Save certificate files in either DER or PEM format
     *
     * Certificate files will be saved in the module working directory.
     * 
     * saved certificate file names are avalable under the 'file' index
     * additional array entries are indexed as 'sha1', 'md5', and 'root'.
     * sha1 and md5 are correcponding certificate hashes
     * root is set to 1 for the CA roor certicicate and 0 otherwise
     * 
     * @param string $format only "der" and "pem" are currently allowed
     * @return array an array of arrays or empty array on error
     * @throws Exception
     */
    final protected function saveCertificateFiles($format)
    {
        switch ($format) {
            case "der": // fall-thorugh, same treatment
            case "pem":
                $iterator = 0;
                $caFiles = [];
                $caArray = $this->attributes['internal:CAs'][0];
                if (!$caArray) {
                    return([]);
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
                return([]);
        }
    }

    /**
     * set of characters to remove from filename strings
     */
    private const TRANSLIT_SCRUB = '/[ ()\/\'"]+/';

    /**
     * Does a transliteration from UTF-8 to ASCII to get a sane filename
     * Takes special characters into account, and always uses English CTYPE
     * to avoid introduction of funny characters due to "flying accents"
     * 
     * @param string $input the input string that is to be transliterated
     * @return string the transliterated string
     */
    private function customTranslit($input)
    {
        $oldlocale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, "en_US.UTF-8");
        $retval = preg_replace(DeviceConfig::TRANSLIT_SCRUB, '_', iconv("UTF-8", "US-ASCII//TRANSLIT", $input));
        setlocale(LC_CTYPE, $oldlocale);
        return $retval;
    }

    /**
     * Generate installer filename base.
     * Device module should use this name adding an extension.
     * Normally the device identifier follows the Consortium name.
     * The sting taken for the device identifier equals (by default) to the index in the listDevices array,
     * but can be overriden with the 'device_id' device option.
     * 
     * @return string
     */
    private function getInstallerBasename()
    {
        $baseName = $this->customTranslit(\config\ConfAssistant::CONSORTIUM['name'])."-".$this->getDeviceId();
        if (isset($this->attributes['profile:customsuffix'][1])) {
            // this string will end up as a filename on a filesystem, so always
            // take a latin-based language variant if available
            // and then scrub non-ASCII just in case
            return $baseName.$this->customTranslit($this->attributes['profile:customsuffix'][1]);
        }
        // Okay, no custom suffix. 
        // Use the configured inst name and apply shortening heuristics
        // if an instshortname is set, base on that, otherwise, the normal instname
        $attribToUse = (isset($this->attributes['general:instshortname']) ? 'general:instshortname' : 'general:instname');
        $lang_pointer = \config\Master::LANGUAGES[$this->languageInstance->getLang()]['latin_based'] == TRUE ? 0 : 1;
        $this->loggerInstance->debug(5, "getInstallerBasename1:".$this->attributes[$attribToUse][$lang_pointer]."\n");
        $inst = $this->customTranslit($this->attributes[$attribToUse][$lang_pointer]);
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
                $profTemp = $this->customTranslit($this->attributes['profile:name'][$lang_pointer]);
                $prof = preg_replace('/_+$/', '', $profTemp);
                return $baseName.$inst.'-'.$prof;
            }
        }
        return $baseName . $inst;
    }

    /**
     * returns the device_id of the current device
     * 
     * @return string
     */
    private function getDeviceId()
    {
        $deviceId = $this->device_id;
        if (isset($this->options['device_id'])) {
            $deviceId = $this->options['device_id'];
        }
        if ($deviceId !== '') {
            $deviceId .= '-';
        }
        return $deviceId;
    }

    /**
     * returns the list of SSIDs that installers should treat. 
     * 
     * Includes both SSIDs to be set up (and whether it's a TKIP-mixed or AES-only SSID) and SSIDs to be deleted
     * 
     * @return array
     */
    private function getSSIDs()
    {
        $ssidList = [];
        $ssidList['add'] = [];
        $ssidList['del'] = [];
        
        if (isset(\config\ConfAssistant::CONSORTIUM['ssid'])) {
            foreach (\config\ConfAssistant::CONSORTIUM['ssid'] as $ssid) {
                $ssidList['add'][$ssid] = 'AES';
                $ssidList['del'][$ssid] = 'TKIP';
            }
        }
        if (isset($this->attributes['media:SSID'])) {
            $ssidWpa2 = $this->attributes['media:SSID'];

            foreach ($ssidWpa2 as $ssid) {
                $ssidList['add'][$ssid] = 'AES';
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

    /**
     * returns the list of Hotspot 2.0 / Passpoint roaming consortia to set up
     * 
     * @return array
     */
    private function getConsortia()
    {

        if (!isset(\config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'])) {
            return ([]);
        }
        $consortia = \config\ConfAssistant::CONSORTIUM['interworking-consortium-oi'];
        if (isset($this->attributes['media:consortium_OI'])) {
            foreach ($this->attributes['media:consortium_OI'] as $new_oi) {
                if (!in_array($new_oi, $consortia)) {
                    $consortia[] = $new_oi;
                }
            }
        }
        return $consortia;
    }
    
    /**
     * return a list of SSIDs definded in the Config networks block
     * 
     * @return array $ssids
     */
    private function getConfigSSIDs()
    {
        $ssids = [];
        if (!isset(\config\ConfAssistant::CONSORTIUM['networks'])) {
            return [];
        }
        foreach (\config\ConfAssistant::CONSORTIUM['networks'] as $oneNetwork) {
            if (!empty($oneNetwork['ssid'])) {
                $ssids = array_merge($ssids, $oneNetwork['ssid']);
            }
        }
        return $ssids;
    }
    
    /**
     * return a list of OIs definded in the Config networks block
     * 
     * @return array $ois
     */
    private function getConfigOIs()
    {
        $ois = [];
        if (!isset(\config\ConfAssistant::CONSORTIUM['networks'])) {
            return [];
        }
        foreach (\config\ConfAssistant::CONSORTIUM['networks'] as $oneNetwork) {
            if (!empty($oneNetwork['oi'])) {
                $ois = array_merge($ois, $oneNetwork['oi']);
            }
        }
        return $ois;
    }

    /**
     * returns the list of parameters for predefined networks to be configured
     * 
     * @return array
     */
    private function getNetworks()
    {
        $additionalConsortia = [];
        $additionalSSIDs = [];
        $ssids = $this->getConfigSSIDs();
        $ois = $this->getConfigOIs();
        $networks = [];
        $realm = $this->realm === NULL ? \config\ConfAssistant::CONSORTIUM['CONSORTIUM']['interworking-domainname-fallback'] : $this->realm;
        foreach (\config\ConfAssistant::CONSORTIUM['networks'] ?? [] as $netName => $netDetails) {
            $netName = preg_replace('/%REALM%/', $this->realm, $netName);
            // only add network blocks if their respective condition is met in this profile
            if ($netDetails['condition'] === TRUE || (isset($this->attributes[$netDetails['condition']]) && $this->attributes[$netDetails['condition']] === TRUE)) { 
                $networks[$netName] = $netDetails;
                $this->loggerInstance->debug(5,$netName, "\nAdding network: ");
            }
        }
        // add locally defined SSIDs
        if (isset($this->attributes['media:SSID'])) {
            foreach ($this->attributes['media:SSID'] as $ssid) {
                if (!in_array($ssid, $ssids)) {
                    $additionalSSIDs[] = $ssid;
                }
            }
        }
        // add locally defined OIs
        if (isset($this->attributes['media:consortium_OI'])) {
            foreach ($this->attributes['media:consortium_OI'] as $new_oi) {
                if (!in_array($new_oi, $ois)) {
                    $additionalConsortia[] = $new_oi;
                }
            }
        }
        if (!empty($additionalConsortia) || !empty($additionalSSIDs)) {
            $networks[sprintf('%s Custom Network', CAT::$nomenclature_participant)] = ['ssid' => $additionalSSIDs, 'oi' => $additionalConsortia];
        }
        return $networks;
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

    /**
     * saves a number of logos to a cache directory on disk.
     * 
     * @param array  $logos list of logos (binary strings each)
     * @param string $type  a qualifier what type of logo this is
     * @return array list of filenames and the mime types
     * @throws Exception
     */
    private function saveLogoFile($logos, $type)
    {
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
            $fileName = 'logo-'.$type.$iterator.'.'.$ext;
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

    /**
     * saves the Terms of Use file onto disk
     * 
     * @param string $blob the Terms of Use
     * @return array with one entry, containging the filename and mime type
     * @throws Exception
     */
    private function saveInfoFile($blob)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($blob);
        $ext = isset($this->mime_extensions[$mime]) ? $this->mime_extensions[$mime] : 'usupported';
        $this->loggerInstance->debug(5, "saveInfoFile: $mime : $ext\n");
        $fileHandle = fopen('local-info.'.$ext, "w");
        if ($fileHandle === FALSE) {
            throw new Exception("problem opening the file");
        }
        fwrite($fileHandle, $blob);
        fclose($fileHandle);
        return(['name' => 'local-info.'.$ext, 'mime' => $ext]);
    }

    /**
     * returns the attributes of the profile for which to generate an installer
     * 
     * In condensed notion, and most specific level only (i.e. ignores overriden attributes from a higher level)
     * @param \core\AbstractProfile $profile the Profile in question
     * @return array
     */
    private function getProfileAttributes(AbstractProfile $profile)
    {
        $bestMatchEap = $this->selectedEap;
        if (count($bestMatchEap) > 0) {
            $a = $profile->getCollapsedAttributes($bestMatchEap);
            $a['eap'] = $bestMatchEap;
            $a['all_eaps'] = $profile->getEapMethodsinOrderOfPreference(1);
            return($a);
        }
        echo("No supported eap types found for this profile.\n");
        return [];
    }

    /**
     * dumps attributes for debugging purposes
     *
     * dumpAttibutes method is supplied for debuging purposes, it simply dumps the attribute array
     * to a file with name passed in the attribute.
     * @param string $file the output file name
     * @return void
     */
    protected function dumpAttibutes($file)
    {
        ob_start();
        print_r($this->attributes);
        $output = ob_get_clean();
        file_put_contents($file, $output);
    }

    /**
     * placeholder for the main device method
     * @return string
     */
    abstract public function writeInstaller();

    /**
     * collates the string to use as EAP outer ID
     * 
     * @return string|NULL
     */
    protected function determineOuterIdString()
    {
        $outerId = NULL;
        if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm'])) {
            $outerId = "@".$this->attributes['internal:realm'][0];
            if (isset($this->attributes['internal:anon_local_value'])) {
                $outerId = $this->attributes['internal:anon_local_value'][0].$outerId;
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
     * - <b>internal:consortia</b> an array of consortion IO as declared in the Confassistant config
     * - <b>internal:networks</b> - an array of network parameters  as declared in the Confassistant config
     * - <b>internal:profile_count</b> - the number of profiles for the associated IdP
     *
     *
     * these attributes are available and can be used, but the "internal" attributes are better suited for modules
     * -  eap:ca_file    -      certificate of the CA signing the RADIUS server key                                         
     * - <b>media:SSID</b>       -  additional SSID to configure, WPA2/AES only (device modules should use internal:networks)
     *
     * @var array $attributes
     * @see \core\common\X509::processCertificate()
     * 
     */
    public $attributes;

    /**
     * stores the path to the module source location and is used 
     * by copyFile and translateFile
     * the only reason for it to be a public variable ies that it is set by the DeviceFactory class
     * module_path should not be used by module drivers.
     * 
     * @var string 
     */
    public $module_path;

    /**
     * The optimal EAP type selected given profile and device
     * 
     * @var array
     */
    public $selectedEap;

    /**
     * The optimal EAP type selected given profile and device, as object
     * 
     * @var \core\common\EAP
     */
    public $selectedEapObject;

    /**
     * the full path to the profile signing program
     * device modules which require signing should use this property to exec the signer
     * the signer program must accept two arguments - input and output file names
     * the signer program mus operate in the local directory and filenames are relative to this
     * directory
     *
     * @var string
     */
    public $sign;

    /**
     * the name of the signer program (without full path)
     * 
     * @var string
     */
    public $signer;

    /**
     * The string identifier of the device (don't show this to users)
     * 
     * @var string
     */
    public $device_id;

    /**
     * See devices-template.php for a list of available options
     * 
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
     * stores the PKCS#12 DER representation of a client certificate for 
     * SilverBullet along with some metadata in an array
     * 
     * @var array
     */
    protected $clientCert = [];

    /**
     * stores identifier used by GEANTLink profiles
     * 
     * @var string
     */
    public $deviceUUID;
}
