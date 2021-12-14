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
 * This is the collection of methods dedicated for the user GUI
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserAPI
 *
 * Parts of this code are based on simpleSAMLPhp discojuice module.
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */

namespace core;

use \Exception;

/**
 * The basic methoods for the user GUI
 * @package UserAPI
 *
 */
class UserAPI extends CAT
{

    /**
     * nothing special to be done here.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Prepare the device module environment and send back the link
     * This method creates a device module instance via the {@link DeviceFactory} call, 
     * then sets up the device module environment for the specific profile by calling 
     * {@link DeviceConfig::setup()} method and finally, called the devide writeInstaller meethod
     * passing the returned path name.
     * 
     * @param string $device       identifier as in {@link devices.php}
     * @param int    $profileId    profile identifier
     * @param string $generatedFor which download area does this pertain to
     * @param string $token        for silverbullet: invitation token to consume
     * @param string $password     for silverbull: import PIN for the future certificate
     *
     * @return array|NULL array with the following fields: 
     *  profile - the profile identifier; 
     *  device - the device identifier; 
     *  link - the path name of the resulting installer
     *  mime - the mimetype of the installer
     */
    public function generateInstaller($device, $profileId, $generatedFor = "user", $openRoaming = 0, $token = NULL, $password = NULL)
    {
        $this->loggerInstance->debug(4, "generateInstaller arguments:$device:$profileId:$openRoaming\n");
        $validator = new \web\lib\common\InputValidation();
        $profile = $validator->existingProfile($profileId);
        // test if the profile is production-ready and if not if the authenticated user is an owner
        if ($this->verifyDownloadAccess($profile) === FALSE) {
            return;
        }
        $installerProperties = [];
        $installerProperties['profile'] = $profileId;
        $installerProperties['device'] = $device;
        $cache = $this->getCache($device, $profile, $openRoaming);
        $this->installerPath = $cache['path'];
        if ($this->installerPath !== NULL && $token === NULL && $password === NULL) {
            $this->loggerInstance->debug(4, "Using cached installer for: $device\n");
            $installerProperties['link'] = "user/API.php?action=downloadInstaller&lang=".$this->languageInstance->getLang()."&profile=$profileId&device=$device&generatedfor=$generatedFor&openroaming=$openRoaming";
            $installerProperties['mime'] = $cache['mime'];
        } else {
            $myInstaller = $this->generateNewInstaller($device, $profile, $generatedFor, $openRoaming, $token, $password);
            if ($myInstaller['link'] !== 0) {
                $installerProperties['mime'] = $myInstaller['mime'];
            }
            $installerProperties['link'] = $myInstaller['link'];
        }
        return $installerProperties;
    }

    /**
     * checks whether the requested profile data is public, XOR was requested by
     * its own admin.
     * @param \core\AbstractProfile $profile the profile in question
     * @return boolean
     */
    private function verifyDownloadAccess($profile)
    {
        $attribs = $profile->getCollapsedAttributes();
        if (\core\common\Entity::getAttributeValue($attribs, 'profile:production', 0) !== 'on') {
            $this->loggerInstance->debug(4, "Attempt to download a non-production ready installer for profile: $profile->identifier\n");
            $auth = new \web\lib\admin\Authentication();
            if (!$auth->isAuthenticated()) {
                $this->loggerInstance->debug(2, "User NOT authenticated, rejecting request for a non-production installer\n");
                header("HTTP/1.0 403 Not Authorized");
                return FALSE;
            }
            $auth->authenticate();
            $userObject = new User($_SESSION['user']);
            if (!$userObject->isIdPOwner($profile->institution)) {
                $this->loggerInstance->debug(2, "User not an owner of a non-production profile - access forbidden\n");
                header("HTTP/1.0 403 Not Authorized");
                return FALSE;
            }
            $this->loggerInstance->debug(4, "User is the owner - allowing access\n");
        }
        return TRUE;
    }

    /**
     * This function tries to find a cached copy of an installer for a given
     * combination of Profile and device
     * 
     * @param string          $device  the device for which the installer is searched in cache
     * @param AbstractProfile $profile the profile for which the installer is searched in cache
     * @return array containing path to the installer and mime type of the file, the path is set to NULL if no cache can be returned
     */
    private function getCache($device, $profile, $openRoaming)
    {
        $deviceConfig = \devices\Devices::listDevices()[$device];
        $noCache = (isset(\devices\Devices::$Options['no_cache']) && \devices\Devices::$Options['no_cache']) ? 1 : 0;
        if (isset($deviceConfig['options']['no_cache'])) {
            $noCache = $deviceConfig['options']['no_cache'] ? 1 : 0;
        }
        if ($noCache) {
            $this->loggerInstance->debug(5, "getCache: the no_cache option set for this device\n");
            return ['path' => NULL, 'mime' => NULL];
        }
        $this->loggerInstance->debug(5, "getCache: caching option set for this device\n");
        $cache = $profile->testCache($device, $openRoaming);
        $iPath = $cache['cache'];
        if ($iPath && is_file($iPath)) {
            return ['path' => $iPath, 'mime' => $cache['mime']];
        }
        return ['path' => NULL, 'mime' => NULL];
    }

    /**
     * Generates a new installer for the given combination of device and Profile
     * 
     * @param string          $device       the device for which we want an installer
     * @param AbstractProfile $profile      the profile for which we want an installer
     * @param string          $generatedFor type of download requested (admin/user/silverbullet)
     * @param int             $openRoaming values 0 o 1 to indicate support for open roaming in the installer
     * @param string          $token        in case of silverbullet, the token that was used to trigger the generation
     * @param string          $password     in case of silverbullet, the import PIN for the future client certificate
     * @return array info about the new installer (mime and link)
     */
    private function generateNewInstaller($device, $profile, $generatedFor, $openRoaming, $token, $password)
    {
        $this->loggerInstance->debug(5, "generateNewInstaller() - Enter");
        $this->loggerInstance->debug(5, "generateNewInstaller:openRoaming:$openRoaming\n");
        $factory = new DeviceFactory($device);
        $this->loggerInstance->debug(5, "generateNewInstaller() - created Device");
        $dev = $factory->device;
        $out = [];
        if (isset($dev)) {
            $dev->setup($profile, $token, $password, $openRoaming);
            $this->loggerInstance->debug(5, "generateNewInstaller() - Device setup done");
            $installer = $dev->writeInstaller();
            $this->loggerInstance->debug(5, "generateNewInstaller() - writeInstaller complete");
            $iPath = $dev->FPATH.'/tmp/'.$installer;
            if ($iPath && is_file($iPath)) {
                if (isset($dev->options['mime'])) {
                    $out['mime'] = $dev->options['mime'];
                } else {
                    $info = new \finfo();
                    $out['mime'] = $info->file($iPath, FILEINFO_MIME_TYPE);
                }
                $this->installerPath = $dev->FPATH.'/'.$installer;
                rename($iPath, $this->installerPath);
                $integerEap = (new \core\common\EAP($dev->selectedEap))->getIntegerRep();
                $profile->updateCache($device, $this->installerPath, $out['mime'], $integerEap, $openRoaming);
                if (\config\Master::DEBUG_LEVEL < 4) {
                    \core\common\Entity::rrmdir($dev->FPATH.'/tmp');
                }
                $this->loggerInstance->debug(4, "Generated installer: ".$this->installerPath.": for: $device, EAP:".$integerEap.", openRoaming: $openRoaming\n");
                $out['link'] = "user/API.php?action=downloadInstaller&lang=".$this->languageInstance->getLang()."&profile=".$profile->identifier."&device=$device&generatedfor=$generatedFor&openroaming=$openRoaming";
            } else {
                $this->loggerInstance->debug(2, "Installer generation failed for: ".$profile->identifier.":$device:".$this->languageInstance->getLang()."openRoaming: $openRoaming\n");
                $out['link'] = 0;
            }
        }
        return $out;
    }

    /**
     * interface to Devices::listDevices() 
     * 
     * @param int $showHidden whether or not hidden devices should be shown
     * @return array the list of devices
     * @throws Exception
     */
    public function listDevices($showHidden = 0)
    {
        $returnList = [];
        $count = 0;
        if ($showHidden !== 0 && $showHidden != 1) {
            throw new Exception("show_hidden is only be allowed to be 0 or 1, but it is $showHidden!");
        }
        foreach (\devices\Devices::listDevices() as $device => $deviceProperties) {
            if (\core\common\Entity::getAttributeValue($deviceProperties, 'options', 'hidden') === 1 && $showHidden === 0) {
                continue;
            }
            $count++;
            $deviceProperties['device'] = $device;
            $group = isset($deviceProperties['group']) ? $deviceProperties['group'] : 'other';
            if (!isset($returnList[$group])) {
                $returnList[$group] = [];
            }
            $returnList[$group][$device] = $deviceProperties;
        }
        return $returnList;
    }

    /**
     * 
     * @param string $device    identifier of the device
     * @param int    $profileId identifier of the profile
     * @return void
     */
    public function deviceInfo($device, $profileId)
    {
        $validator = new \web\lib\common\InputValidation();
        $out = 0;
        $profile = $validator->existingProfile($profileId);
        $factory = new DeviceFactory($device);
        $dev = $factory->device;
        if (isset($dev)) {
            $dev->setup($profile);
            $out = $dev->writeDeviceInfo();
        }
        echo $out;
    }

    /**
     * Prepare the support data for a given profile
     *
     * @param int $profId profile identifier
     * @return array
     * array with the following fields:
     * - local_email
     * - local_phone
     * - local_url
     * - description
     * - devices - an array of device names and their statuses (for a given profile)
     * - last_changed
     */
    public function profileAttributes($profId)
    {
        $validator = new \web\lib\common\InputValidation();
        $profile = $validator->existingProfile($profId);
        $attribs = $profile->getCollapsedAttributes();
        $returnArray = [];
        $returnArray['silverbullet'] = $profile instanceof ProfileSilverbullet ? 1 : 0;
        if (isset($attribs['support:email'])) {
            $returnArray['local_email'] = $attribs['support:email'][0];
        }
        if (isset($attribs['support:phone'])) {
            $returnArray['local_phone'] = $attribs['support:phone'][0];
        }
        if (isset($attribs['support:url'])) {
            $returnArray['local_url'] = $attribs['support:url'][0];
        }
        if (isset($attribs['profile:description'])) {
            $returnArray['description'] = $attribs['profile:description'][0];
        }
        if (isset($attribs['media:openroaming'])) {
            $returnArray['openroaming'] = $attribs['media:openroaming'][0];
        } else {
            $returnArray['openroaming'] = 'none';
        }
        $returnArray['devices'] = $profile->listDevices();
        $returnArray['last_changed'] = $profile->getFreshness();
        return $returnArray;
    }

    /**
     * Generate and send the installer
     *
     * @param string $device        identifier as in {@link devices.php}
     * @param int    $prof_id       profile identifier
     * @param string $generated_for which download area does this pertain to
     * @param string $token         for silverbullet: invitation token to consume
     * @param string $password      for silverbull: import PIN for the future certificate
     * @return string binary stream: installerFile
     */
    public function downloadInstaller($device, $prof_id, $generated_for = 'user', $openRoaming = 0, $token = NULL, $password = NULL)
    {
        $this->loggerInstance->debug(4, "downloadInstaller arguments: $device,$prof_id,$generated_for, $openRoaming\n");
        $output = $this->generateInstaller($device, $prof_id, $generated_for, $openRoaming, $token, $password);
        $this->loggerInstance->debug(4, "output from GUI::generateInstaller:");
        $this->loggerInstance->debug(4, print_r($output, true));
        if (empty($output['link']) || $output['link'] === 0) {
            header("HTTP/1.0 404 Not Found");
            return;
        }
        $validator = new \web\lib\common\InputValidation();
        $profile = $validator->existingProfile($prof_id);
        $profile->incrementDownloadStats($device, $generated_for, $openRoaming);
        $file = $this->installerPath;
        $filetype = $output['mime'];
        $this->loggerInstance->debug(4, "installer MIME type:$filetype\n");
        header("Content-type: ".$filetype);
        if ($filetype !== "application/x-wifi-config") { // for those installers to work on Android, Content-Disposition MUST NOT be set
            header('Content-Disposition: inline; filename="'.basename($file).'"');
        } else {
            header('Content-Transfer-Encoding: base64');
        }
        header('Content-Length: '.filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }

    /**
     * resizes image files
     * 
     * @param string $inputImage the image we want to process
     * @param string $destFile   the output file for the processed image
     * @param int    $width      if resizing, the target width
     * @param int    $height     if resizing, the target height
     * @param bool   $resize     shall we do resizing? width and height are ignored otherwise
     * @return array
     */
    private function processImage($inputImage, $destFile, $width, $height, $resize)
    {
        $info = new \finfo();
        $filetype = $info->buffer($inputImage, FILEINFO_MIME_TYPE);
        $expiresString = $this->logoExpireTime();
        $blob = $inputImage;

        if ($resize === TRUE) {
            if (class_exists('\\Gmagick')) { 
                $image = new \Gmagick(); 
            } else {
                $image = new \Imagick();
            }
            $image->readImageBlob($inputImage);
            $image->setImageFormat('PNG');
            $image->thumbnailImage($width, $height, 1);
            $blob = $image->getImageBlob();
            $this->loggerInstance->debug(4, "Writing cached logo $destFile for IdP/Federation.\n");
            file_put_contents($destFile, $blob);
        }

        return ["filetype" => $filetype, "expires" => $expiresString, "blob" => $blob];
    }

    protected function logoExpireTime()
    {
        $offset = 60 * 60 * 24 * 30;
        // gmdate cannot fail here - time() is its default argument (and integer), and we are adding an integer to it
        return("Expires: "./** @scrutinizer ignore-type */ gmdate("D, d M Y H:i:s", time() + $offset)." GMT");
    }
    /**
     * Get and prepare logo file 
     *
     * When called for DiscoJuice, first check if file cache exists
     * If not then generate the file and save it in the cache
     * @param int|string $identifier IdP or Federation identifier
     * @param string     $type       either 'idp' or 'federation' is allowed 
     * @param integer    $widthIn    maximum width of the generated image - if 0 then it is treated as no upper bound
     * @param integer    $heightIn   maximum height of the generated image - if 0 then it is treated as no upper bound
     * @return array|null array with image information or NULL if there is no logo
     * @throws Exception
     */
    protected function getLogo($identifier, $type, $widthIn, $heightIn)
    {
        $expiresString = '';
        $attributeName = [
            'federation' => "fed:logo_file",
            'federation_from_idp' => "fed:logo_file",
            'idp' => "general:logo_file",
        ];

        $logoFile = "";
        $validator = new \web\lib\common\InputValidation();
        switch ($type) {
            case "federation":
                $entity = $validator->existingFederation($identifier);
                break;
            case "idp":
                $entity = $validator->existingIdP($identifier);
                break;
            case "federation_from_idp":
                $idp = $validator->existingIdP($identifier);
                $entity = $validator->existingFederation($idp->federation);
                break;
            default:
                throw new Exception("Unknown type of logo requested!");
        }
        $filetype = 'image/png'; // default, only one code path where it can become different
        list($width, $height, $resize) = $this->testForResize($widthIn, $heightIn);
        if ($resize) {
            $logoFile = ROOT.'/web/downloads/logos/'.$identifier.'_'.$width.'_'.$height.'.png';
        }
        if (is_file($logoFile)) { // $logoFile could be an empty string but then we will get a FALSE
            $this->loggerInstance->debug(4, "Using cached logo $logoFile for: $identifier\n");
            $blob = file_get_contents($logoFile);
        } else {
            $logoAttribute = $entity->getAttributes($attributeName[$type]);
            if (count($logoAttribute) == 0) {
                $blob = file_get_contents(ROOT.'/web/resources/images/empty.png');
                $expiresString = $this->logoExpireTime();
            } else {
                $this->loggerInstance->debug(4, "RESIZE:$width:$height\n");
                $meta = $this->processImage($logoAttribute[0]['value'], $logoFile, $width, $height, $resize);
                $filetype = $meta['filetype'];
                $expiresString = $meta['expires'];
                $blob = $meta['blob'];
            }
        }
        return ["filetype" => $filetype, "expires" => $expiresString, "blob" => $blob];
    }

    /**
     * see if we have to resize an image
     * 
     * @param integer $width  the desired max width (0 = unbounded)
     * @param integer $height the desired max height (0 = unbounded)
     * @return array
     */
    private function testForResize($width, $height)
    {
        if (is_numeric($width) && is_numeric($height) && ($width > 0 || $height > 0)) {
            if ($height == 0) {
                $height = 10000;
            }
            if ($width == 0) {
                $width = 10000;
            }
            return [$width, $height, TRUE];
        }
        return [0, 0, FALSE];
    }

    /**
     * find out where the device is currently located
     * @return array
     */
    public function locateDevice()
    {
        return \core\DeviceLocation::locateDevice();
    }

    /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * 
     * @param int    $activeOnly if set to non-zero will cause listing of only those institutions which have some valid profiles defined.
     * @param string $country    if set, only list IdPs in a specific country
     * @return array the list of identity providers
     *
     */
    public function listAllIdentityProviders($activeOnly = 0, $country = "")
    {
        return IdPlist::listAllIdentityProviders($activeOnly, $country);
    }

    /**
     * Order active identity providers according to their distance and name
     * @param string $country         NRO to work with
     * @param array  $currentLocation current location
     *
     * @return array $IdPs -  list of arrays ('id', 'name');
     */
    public function orderIdentityProviders($country, $currentLocation)
    {
        return IdPlist::orderIdentityProviders($country, $currentLocation);
    }
    
    /**
     * outputs a full list of IdPs containing the fllowing data:
     * institution_is, institution name in all available languages,
     * list of production profiles.
     * For eache profile the profile identifier, profile name in all languages
     * and redirect values (empty rediret value means that no redirect has been
     * set).
     * 
     * @return array of identity providers with attributes
     */
    public function listIdentityProvidersWithProfiles() {
        return IdPlist::listIdentityProvidersWithProfiles();
    }
    
    /**
     * Detect the best device driver form the browser
     * Detects the operating system and returns its id 
     * display name and group membership (as in devices.php)
     * @return array|boolean OS information, indexed by 'id', 'display', 'group'
     */
    public function detectOS()
    {
        $Dev = \devices\Devices::listDevices();
        $devId = $this->deviceFromRequest();
        if ($devId !== NULL) {
            $ret = $this->returnDevice($devId, $Dev[$devId]);
            if ($ret !== FALSE) {
                return $ret;
            }
        }
// the device has not been specified or not specified correctly, try to detect if from the browser ID
        $browser = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING);
        $this->loggerInstance->debug(4, "HTTP_USER_AGENT=$browser\n");
        foreach ($Dev as $devId => $device) {
            if (!isset($device['match'])) {
                continue;
            }
            if (preg_match('/'.$device['match'].'/', $browser)) {
                return $this->returnDevice($devId, $device);
            }
        }
        $this->loggerInstance->debug(2, "Unrecognised system: $browser\n");
        return FALSE;
    }

    /**
     * test if devise is defined and is not hidden. If all is fine return extracted information.
     * 
     * @param string $devId  device id as defined as index in Devices.php
     * @param array  $device device info as defined in Devices.php
     * @return array|FALSE if the device has not been correctly specified
     */
    private function returnDevice($devId, $device)
    {
        if (\core\common\Entity::getAttributeValue($device, 'options', 'hidden') !== 1) {
            $this->loggerInstance->debug(4, "Browser_id: $devId\n");
            if (isset($device['options']['hs20']) && $device['options']['hs20'] === 1) {
                $hs20 = 1;
            } else {
                $hs20 = 0;
            }
            return ['device' => $devId, 'display' => $device['display'], 'group' => $device['group'], 'hs20' => $hs20];
        }
        return FALSE;
    }

    /**
     * This methods cheks if the devide has been specified as the HTTP parameters
     * 
     * @return device id|NULL if correcty specified or FALSE otherwise
     */
    private function deviceFromRequest()
    {
        $devId = filter_input(INPUT_GET, 'device', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'device', FILTER_SANITIZE_STRING);
        if ($devId === NULL || $devId === FALSE) {
            $this->loggerInstance->debug(2, "Invalid device id provided\n");
            return NULL;
        }
        if (!isset(\devices\Devices::listDevices()[$devId])) {
            $this->loggerInstance->debug(2, "Unrecognised system: $devId\n");
            return NULL;
        }
        return $devId;
    }

    /**
     * finds all the user certificates that originated in a given token
     * 
     * @param string $token the token for which we are fetching all associated user certs
     * @return array|boolean returns FALSE if a token is invalid, otherwise array of certs
     */
    public function getUserCerts($token)
    {
        $validator = new \web\lib\common\InputValidation();
        $cleanToken = $validator->token($token);
        if ($cleanToken) {
            // check status of this silverbullet token according to info in DB:
            // it can be VALID (exists and not redeemed, EXPIRED, REDEEMED or INVALID (non existent)
            $invitationObject = new \core\SilverbulletInvitation($cleanToken);
        } else {
            return false;
        }
        $profile = new \core\ProfileSilverbullet($invitationObject->profile, NULL);
        $userdata = $profile->userStatus($invitationObject->userId);
        $allcerts = [];
        foreach ($userdata as $content) {
            $allcerts = array_merge($allcerts, $content->associatedCertificates);
        }
        return $allcerts;
    }

    /**
     * device name
     * 
     * @var string
     */
    public $device;

    /**
     * path to installer
     * 
     * @var string
     */
    private $installerPath;

    /**
     * helper function to sort profiles by their name
     * @param \core\AbstractProfile $profile1 the first profile's information
     * @param \core\AbstractProfile $profile2 the second profile's information
     * @return int
     */
    private static function profileSort($profile1, $profile2)
    {
        return strcasecmp($profile1->name, $profile2->name);
    }
}