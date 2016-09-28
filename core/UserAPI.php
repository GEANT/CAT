<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This is the collection of methods dedicated for the user GUI
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserAPI
 *
 * Parts of this code are based on simpleSAMLPhp discojuice module.
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */
/**
 * includes required by this class
 */
require_once("Helper.php");
require_once("Options.php");
require_once("CAT.php");
require_once("User.php");
require_once("ProfileFactory.php");
require_once("AbstractProfile.php");
require_once("Federation.php");
require_once("DeviceFactory.php");
require_once("Logging.php");
require_once("devices/devices.php");

use GeoIp2\Database\Reader;

/**
 * The basic methoods for the user GUI
 * @package UserAPI
 *
 */
class UserAPI extends CAT {

    public function __construct() {
        parent::__construct();
        $this->loggerInstance = new Logging();
    }

    /**
     * Prepare the device module environment and send back the link
     * This method creates a device module instance via the {@link DeviceFactory} call, 
     * then sets up the device module environment for the specific profile by calling 
     * {@link DeviceConfig::setup()} method and finally, called the devide writeInstaller meethod
     * passing the returned path name.
     * 
     * @param string $device identifier as in {@link devices.php}
     * @param int $profileId profile identifier
     *
     * @return array 
     *  array with the following fields: 
     *  profile - the profile identifier; 
     *  device - the device identifier; 
     *  link - the path name of the resulting installer
     *  mime - the mimetype of the installer
     */
    public function generateInstaller($device, $profileId, $generatedFor = "user") {
        $this->setTextDomain("devices");
        $this->loggerInstance->debug(4, "installer:$device:$profileId\n");
        $profile = ProfileFactory::instantiate($profileId);
        $attribs = $profile->getCollapsedAttributes();
        // test if the profile is production-ready and if not if the authenticated user is an owner
        if (!isset($attribs['profile:production']) || (isset($attribs['profile:production']) && $attribs['profile:production'][0] != "on")) {
            $this->loggerInstance->debug(4, "Attempt to download a non-production ready installer fir profile: $profileId\n");
            require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);
            $authSource = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
            if (!$authSource->isAuthenticated()) {
                $this->loggerInstance->debug(2, "User NOT authenticated, rejecting request for a non-production installer\n");
                header("HTTP/1.0 403 Not Authorized");
                return;
            }

            $userObject = new User($_SESSION['user']);
            if (!$userObject->isIdPOwner($profile->institution)) {
                $this->loggerInstance->debug(2, "User not an owner of a non-production profile - access forbidden\n");
                header("HTTP/1.0 403 Not Authorized");
                return;
            }
            $this->loggerInstance->debug(4, "User is the owner - allowing access\n");
        }
        $installerProperties = [];
        $installerProperties['profile'] = $profileId;
        $installerProperties['device'] = $device;
        $this->installerPath = $this->getCachedPath($device, $profile);
        if ($this->installerPath) {
            $this->loggerInstance->debug(4, "Using cached installer for: $device\n");
            $installerProperties['link'] = "API.php?api_version=$this->version&action=downloadInstaller&lang=" . CAT::getLang() . "&profile=$profileId&device=$device&generatedfor=$generatedFor";
            $installerProperties['mime'] = $cache['mime'];
        } else {
            $myInstaller = $this->generateNewInstaller($device, $profile);
            $installerProperties['mime'] = $myInstaller['mime'];
            $installerProperties['link'] = $myInstaller['link'];
        }
        $this->setTextDomain("web_user");
        return($installerProperties);
    }

    /**
     * This function tries to find a cached copy of an installer for a given
     * combination of Profile and device
     * @param string $device
     * @param AbstractProfile $profile
     * @return boolean|string the string with the path to the cached copy, or FALSE if no cached copy exists
     */
    private function getCachedPath($device, $profile) {
        $deviceList = Devices::listDevices();
        $deviceConfig = $deviceList[$device];
        $noCache = (isset(Devices::$Options['no_cache']) && Devices::$Options['no_cache']) ? 1 : 0;
        if (isset($deviceConfig['options']['no_cache'])) {
            $noCache = $deviceConfig['options']['no_cache'] ? 1 : 0;
        }
        if ($noCache) {
            $this->loggerInstance->debug(4, "getCachedPath: the no_cache option set for this device\n");
            return(FALSE);
        }
        $this->loggerInstance->debug(4, "getCachedPath: caching option set for this device\n");
        $cache = $profile->testCache($device);
        $iPath = $cache['cache'];
        if ($iPath && is_file($iPath)) {
            return($iPath);
        }
        return(FALSE);
    }

    /**
     * Generates a new installer for the given combination of device and Profile
     * 
     * @param string $device
     * @param AbstractProfile $profile
     * @return array info about the new installer (mime and link)
     */
    private function generateNewInstaller($device, $profile) {
        $factory = new DeviceFactory($device);
        $dev = $factory->device;
        $out = [];
        if (isset($dev)) {
            $dev->setup($profile);
            $installer = $dev->writeInstaller();
            $iPath = $dev->FPATH . '/tmp/' . $installer;
            if ($iPath && is_file($iPath)) {
                if (isset($dev->options['mime'])) {
                    $out['mime'] = $dev->options['mime'];
                } else {
                    $info = new finfo();
                    $out['mime'] = $info->file($iPath, FILEINFO_MIME_TYPE);
                }
                $this->installerPath = $dev->FPATH . '/' . $installer;
                rename($iPath, $this->installerPath);
                $profile->updateCache($device, $this->installerPath, $out['mime']);
                rrmdir($dev->FPATH . '/tmp');
                $this->loggerInstance->debug(4, "Generated installer: " . $this->installerPath . ": for: $device\n");
                $out['link'] = "API.php?api_version=$this->version&action=downloadInstaller&lang=" . CAT::getLang() . "&profile=" . $profile->identifier . "&device=$device&generatedfor=$generated_for";
            } else {
                $this->loggerInstance->debug(2, "Installer generation failed for: " . $profile->identifier . ":$device:" . CAT::getLang() . "\n");
                $out['link'] = 0;
            }
        }
        return($out);
    }

    /**
     * interface to Devices::listDevices() 
     */
    public function listDevices($showHidden = 0) {
        $dev = Devices::listDevices();
        $returnList = [];
        $count = 0;
        if ($showHidden !== 0 && $showHidden != 1) {
            throw new Exception("show_hidden is only be allowed to be 0 or 1, but it is $showHidden!");
        }
        foreach ($dev as $device => $deviceProperties) {
            if (isset($deviceProperties['options']['hidden']) && $deviceProperties['options']['hidden'] && $showHidden == 0) {
                continue;
            }
            $count ++;
            if ($this->version == 1) {
                $deviceProperties['device'] = $device;
            } else {
                $deviceProperties['device'] = $device;
            }
            $group = isset($deviceProperties['group']) ? $deviceProperties['group'] : 'other';
            if (!isset($returnList[$group])) {
                $returnList[$group] = [];
            }
            $returnList[$group][$device] = $deviceProperties;
        }
        return $returnList;
    }

    public function deviceInfo($device, $profileId) {
        $this->setTextDomain("devices");
        $out = 0;
        $profile = ProfileFactory::instantiate($profileId);
        $factory = new DeviceFactory($device);
        $dev = $factory->device;
        if (isset($dev)) {
            $out = $dev->writeDeviceInfo();
        }
        $this->setTextDomain("web_user");
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
     */
    public function profileAttributes($profId) {
        $this->setTextDomain("devices");
        $profile = ProfileFactory::instantiate($profId);
        $attr = $profile->getCollapsedAttributes();
        $returnArray = [];
        if (isset($attr['support:email'])) {
            $returnArray['local_email'] = $attr['support:email'][0];
        }
        if (isset($attr['support:phone'])) {
            $returnArray['local_phone'] = $attr['support:phone'][0];
        }
        if (isset($attr['support:url'])) {
            $returnArray['local_url'] = $attr['support:url'][0];
        }
        if (isset($attr['profile:description'])) {
            $returnArray['description'] = $attr['profile:description'][0];
        }
        $returnArray['devices'] = $profile->listDevices();
        $this->setTextDomain("web_user");
        return($returnArray);
    }

    /*
      this method needs to be used with care, it could give wrong results in some
      cicumstances
     */

    private function GetRootURL() {
        $backtrace = debug_backtrace();
        $backtraceFileInfo = array_pop($backtrace);
        $file = $backtraceFileInfo['file'];
        $file = substr($file, strlen(dirname(__DIR__)));
        while (substr($file, 0, 1) == '/') {
            $file = substr($file, 1);
        }
        $slashCount = count(explode('/', $file));
        $out = $_SERVER['SCRIPT_NAME'];
        for ($iterator = 0; $iterator < $slashCount; $iterator++) {
            $out = dirname($out);
        }
        if ($out == '/') {
            $out = '';
        }
        $urlString = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http';
        $urlString .= '://' . $_SERVER['HTTP_HOST'] . $out;
        return $urlString;
    }

    /* JSON functions */

    public function return_json($data, $status = 1) {
        $returnArray = [];
        $returnArray['status'] = $status;
        $returnArray['data'] = $data;
        $returnArray['tou'] = "Please consult Terms of Use at: " . $this->GetRootURL() . "/tou.php";
        return(json_encode($returnArray));
    }

    /**
     * Return the list of supported languages.
     *
     * 
     */
    public function JSON_listLanguages() {
        $returnArray = [];
        foreach (CONFIG['LANGUAGES'] as $id => $val) {
            if ($this->version == 1) {
                $returnArray[] = ['id' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
            } else {
                $returnArray[] = ['lang' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * Return the list of countiers with configured IdPs
     *
     * @return string JSON encoded data
     */
    public function JSON_listCountries() {
        $federations = $this->printCountryList(1);
        $returnArray = [];
        foreach ($federations as $id => $val) {
            if ($this->version == 1) {
                $returnArray[] = ['id' => $id, 'display' => $val];
            } else {
                $returnArray[] = ['federation' => $id, 'display' => $val];
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * Return the list of IdPs in a given country
     *
     * @param string $country the country we are interested in
     * @return string JSON encoded data
     */
    public function JSON_listIdentityProviders($country) {
        $idps = Federation::listAllIdentityProviders(1, $country);
        $returnArray = [];
        foreach ($idps as $idp) {
            if ($this->version == 1) {
                $returnArray[] = ['id' => $idp['entityID'], 'display' => $idp['title']];
            } else {
                $returnArray[] = ['idp' => $idp['entityID'], 'display' => $idp['title']];
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * return the list of all active IdPs
     *
     * The IdP list is formatted for DiscoJuice
     * @return string JSON encoded data
     */
    public function JSON_listIdentityProvidersForDisco() {
        $idps = Federation::listAllIdentityProviders(1);
        $returnArray = [];
        foreach ($idps as $idp) {
            if ($this->version == 1) {
                $idp['id'] = $idp['entityID'];
            } else {
                $idp['idp'] = $idp['entityID'];
            }
            $returnArray[] = $idp;
        }
        echo json_encode($returnArray);
    }

    /**
     * Return the list of IdPs in a given country ordered with respect to the user location
     *
     * @return string JSON encoded data
     */
    public function JSON_orderIdentityProviders($country, $location = NULL) {
        $idps = $this->orderIdentityProviders($country, $location);
        $returnArray = [];
        foreach ($idps as $idp) {
            if ($this->version == 1) {
                $returnArray[] = ['id' => $idp['id'], 'display' => $idp['title']];
            } else {
                $returnArray[] = ['idp' => $idp['id'], 'display' => $idp['title']];
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * Produce a list of profiles available for a given IdP
     *
     * @param int $idpIdentifier the IdP identifier
     * @return string JSON encoded data
     */
    public function JSON_listProfiles($idpIdentifier, $sort = 0) {
        $this->setTextDomain("web_user");
        $returnArray = [];
        try {
            $idp = new IdP($idpIdentifier);
        } catch (Exception $fail) {
            echo $this->return_json($returnArray, 0);
            return;
        }
        $hasLogo = FALSE;
        $logo = $idp->getAttributes('general:logo_file');
        if (count($logo) > 0) {
            $hasLogo = 1;
        }
        $profiles = $idp->listProfiles(1);
        if ($sort == 1) {
            usort($profiles, "profile_sort");
        }
        foreach ($profiles as $P) {
            if ($this->version == 1) {
                $returnArray[] = ['id' => $P->identifier, 'display' => $P->name, 'idp_name' => $P->instName, 'logo' => $hasLogo];
            } else {
                $returnArray[] = ['profile' => $P->identifier, 'display' => $P->name, 'idp_name' => $P->instName, 'logo' => $hasLogo];
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * Return the list of devices available for the given profile
     *
     * @param int $profileId the Profile identifier
     * @return string JSON encoded data
     */
    public function JSON_listDevices($profileId) {
        $this->setTextDomain("web_user");
        $returnArray = [];
        $profileAttributes = $this->profileAttributes($profileId);
        $thedevices = $profileAttributes['devices'];
        if (!isset($profile_redirect) || !$profile_redirect) {
            $profile_redirect = 0;
            foreach ($thedevices as $D) {
                if (isset($D['options']) && isset($D['options']['hidden']) && $D['options']['hidden']) {
                    continue;
                }
                $disp = $D['display'];
                if ($this->version == 1) {
                    if ($D['id'] === '0') {
                        $profile_redirect = 1;
                        $disp = $c;
                    }
                    $returnArray[] = ['id' => $D['id'], 'display' => $disp, 'status' => $D['status'], 'redirect' => $D['redirect']];
                } else {
                    if ($D['device'] === '0') {
                        $profile_redirect = 1;
                        $disp = $c;
                    }
                    $returnArray[] = ['device' => $D['id'], 'display' => $disp, 'status' => $D['status'], 'redirect' => $D['redirect']];
                }
            }
        }
        echo $this->return_json($returnArray);
    }

    /**
     * Call installer generation and return the link
     *
     * @param string $device identifier as in {@link devices.php}
     * @param int $prof_id profile identifier
     * @return string JSON encoded data
     */
    public function JSON_generateInstaller($device, $prof_id) {
        $this->loggerInstance->debug(4, "JSON::generateInstaller arguments: $device,$prof_id\n");
        $output = $this->generateInstaller($device, $prof_id);
        $this->loggerInstance->debug(4, "output from GUI::generateInstaller:");
        $this->loggerInstance->debug(4, print_r($output, true));
        $this->loggerInstance->debug(4, json_encode($output));
//    header('Content-type: application/json; utf-8');
        echo $this->return_json($output);
    }

    /**
     * Generate and send the installer
     *
     * @param string $device identifier as in {@link devices.php}
     * @param int $prof_id profile identifier
     * @return binary installerFile
     */
    public function downloadInstaller($device, $prof_id, $generated_for = 'user') {
        $this->loggerInstance->debug(4, "downloadInstaller arguments: $device,$prof_id,$generated_for\n");
        $output = $this->generateInstaller($device, $prof_id);
        $this->loggerInstance->debug(4, "output from GUI::generateInstaller:");
        $this->loggerInstance->debug(4, print_r($output, true));
        if (!$output['link']) {
            header("HTTP/1.0 404 Not Found");
            return;
        }
        $profile = ProfileFactory::instantiate($prof_id);
        $profile->incrementDownloadStats($device, $generated_for);
        $file = $this->installerPath;
        $filetype = $output['mime'];
        $this->loggerInstance->debug(4, "installer MIME type:$filetype\n");
        header("Content-type: " . $filetype);
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }

    /**
     * Get and prepare logo file 
     *
     * When called for DiscoJuice, first check if file cache exists
     * If not then generate the file and save it in the cache
     * @param int $idpIdentifier IdP identifier
     * @param int $disco flag turning on image generation for DiscoJuice
     * @param int $width maximum width of the generated image 
     * @param int $height  maximum height of the generated image
     * if one of these is 0 then it is treated as no upper bound
     *
     */
    public function sendLogo($idpIdentifier, $disco = FALSE, $width = 0, $height = 0) {
        $expiresString = '';
        $resize = 0;
        $logoFile = "";
        if (($width || $height) && is_numeric($width) && is_numeric($height)) {
            $resize = 1;
            if ($height == 0) {
                $height = 10000;
            }
            if ($width == 0) {
                $width = 10000;
            }
            $logoFile = ROOT . '/web/downloads/logos/' . $idpIdentifier . '_' . $width . '_' . $height . '.png';
        } elseif ($disco == 1) {
            $width = 120;
            $height = 40;
            $resize = 1;
            $logoFile = ROOT . '/web/downloads/logos/' . $idpIdentifier . '_' . $width . '_' . $height . '.png';
        }

        if ($resize && is_file($logoFile)) {
            $this->loggerInstance->debug(4, "Using cached logo $logoFile for: $idpIdentifier\n");
            $blob = file_get_contents($logoFile);
            $filetype = 'image/png';
        } else {
            $idp = new IdP($idpIdentifier);
            $logoAttribute = $idp->getAttributes('general:logo_file');
            $blob = $logoAttribute[0]['value'];
            $info = new finfo();
            $filetype = $info->buffer($blob, FILEINFO_MIME_TYPE);
            $offset = 60 * 60 * 24 * 30;
            $expiresString = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
            if ($resize) {
                $filetype = 'image/png';
                $image = new Imagick();
                $image->readImageBlob($blob);
                if ($image->setImageFormat('PNG')) {
                    $image->thumbnailImage($width, $height, 1);
                    $blob = $image->getImageBlob();
                    $this->loggerInstance->debug(4, "Writing cached logo $logoFile for: $idpIdentifier\n");
                    file_put_contents($logoFile, $blob);
                } else {
                    $blob = "XXXXXX";
                }
            }
        }
        header("Content-type: " . $filetype);
        header("Cache-Control:max-age=36000, must-revalidate");
        header($expiresString);
        echo $blob;
    }

    public function locateUser() {
        $host = $_SERVER['REMOTE_ADDR'];
        $record = geoip_record_by_name($host);
        if ($record === FALSE) {
            return ['status' => 'error', 'error' => 'Problem listing countries'];
        }
        $result = ['status' => 'ok'];
        $result['country'] = $record['country_code'];
//  the two lines below are a dirty hack to take of the error in naming the UK federation
        if ($result['country'] == 'GB') {
            $result['country'] = 'UK';
        }
        $result['region'] = $record['region'];
        $result['geo'] = ['lat' => (float) $record['latitude'], 'lon' => (float) $record['longitude']];
        return($result);
    }

    public function locateUser2() {
        require_once CONFIG['GEOIP']['geoip2-path-to-autoloader'];
        $reader = new Reader(CONFIG['GEOIP']['geoip2-path-to-db']);
        $host = $_SERVER['REMOTE_ADDR'];
        try {
            $record = $reader->city($host);
        } catch (Exception $e) {
            $result = ['status' => 'error', 'error' => 'Problem listing countries'];
            return($result);
        }
        $result = ['status' => 'ok'];
        $result['country'] = $record->country->isoCode;
//  the two lines below are a dirty hack to take of the error in naming the UK federation
        if ($result['country'] == 'GB') {
            $result['country'] = 'UK';
        }
        $result['region'] = $record->continent->name;

        $result['geo'] = ['lat' => (float) $record->location->latitude, 'lon' => (float) $record->location->longitude];
        return($result);
    }

    public function JSON_locateUser() {
        header('Content-type: application/json; utf-8');

        $geoipVersion = CONFIG['GEOIP']['version'] ?? 0;

        switch ($geoipVersion) {
            case 0:
                echo json_encode(['status' => 'error', 'error' => 'Geolocation not supported']);
                break;
            case 1:
                echo json_encode($this->locateUser());
                break;
            case 2:
                echo json_encode($this->locateUser2());
                break;
            default:
                throw new Exception("This version of GeoIP is not known!");
        }
    }

    /**
     * Produce support data prepared within {@link GUI::profileAttributes()}
     * @return string JSON encoded data
     */
    public function JSON_profileAttributes($prof_id) {
//    header('Content-type: application/json; utf-8');
        echo $this->return_json($this->profileAttributes($prof_id));
    }

    /**
     * Calculate the distence in km between two points given their
     * geo coordinates.
     * @param array $point1 - first point as an 'lat', 'lon' array 
     * @param array $profile1 - second point as an 'lat', 'lon' array 
     * @return float distance in km
     */
    private function geoDistance($point1, $profile1) {

        $distIntermediate = sin(deg2rad($point1['lat'])) * sin(deg2rad($profile1['lat'])) +
                cos(deg2rad($point1['lat'])) * cos(deg2rad($profile1['lat'])) * cos(deg2rad($point1['lon'] - $profile1['lon']));
        $dist = rad2deg(acos($distIntermediate)) * 60 * 1.1852;
        return(round($dist));
    }

    /**
     * Order active identity providers according to their distance and name
     * @param array $currentLocation - current location
     * @return array $IdPs -  list of arrays ('id', 'name');
     */
    public function orderIdentityProviders($country, $currentLocation = NULL) {
        $idps = Federation::listAllIdentityProviders(1, $country);

        if (is_null($currentLocation)) {
            $currentLocation = ['lat' => "90", 'lon' => "0"];
            $userLocation = $this->locateUser();
            if ($userLocation['status'] == 'ok') {
                $currentLocation = $userLocation['geo'];
            }
        }
        $idpTitle = [];
        $resultSet = [];
        foreach ($idps as $idp) {
            $idpTitle[$idp['entityID']] = $idp['title'];
            $dist = 10000;
            if (isset($idp['geo'])) {
                $G = $idp['geo'];
                if (isset($G['lon'])) {
                    $d1 = $this->geoDistance($currentLocation, $G);
                    if ($d1 < $dist) {
                        $dist = $d1;
                    }
                } else {
                    foreach ($G as $g) {
                        $d1 = $this->geoDistance($currentLocation, $g);
                        if ($d1 < $dist) {
                            $dist = $d1;
                        }
                    }
                }
            }
            if ($dist > 100) {
                $dist = 10000;
            }
            $d = sprintf("%06d", $dist);
            $resultSet[$idp['entityID']] = $d . " " . $idp['title'];
        }
        asort($resultSet);
        $outarray = [];
        foreach (array_keys($resultSet) as $r) {
            if ($this->version == 1) {
                $outarray[] = ['id' => $r, 'title' => $idpTitle[$r]];
            } else {
                $outarray[] = ['idp' => $r, 'title' => $idpTitle[$r]];
            }
        }
        return($outarray);
    }

    /**
     * Detect the best device driver form the browser
     *
     * Detects the operating system and returns its id 
     * display name and group membership (as in devices.php)
     * @return array indexed by 'id', 'display', 'group'
     */
    public function detectOS() {
        $Dev = Devices::listDevices();
        if (isset($_REQUEST['device']) && isset($Dev[$_REQUEST['device']]) && (!isset($device['options']['hidden']) || $device['options']['hidden'] == 0)) {
            $dev_id = $_REQUEST['device'];
            $device = $Dev[$dev_id];
            if ($this->version == 1) {
                return(['id' => $dev_id, 'display' => $device['display'], 'group' => $device['group']]);
            } else {
                return(['device' => $dev_id, 'display' => $device['display'], 'group' => $device['group']]);
            }
        }
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $this->loggerInstance->debug(4, "HTTP_USER_AGENT=$browser\n");
        foreach ($Dev as $dev_id => $device) {
            if (!isset($device['match'])) {
                continue;
            }
            if (preg_match('/' . $device['match'] . '/', $browser)) {
                if (!isset($device['options']['hidden']) || $device['options']['hidden'] == 0) {
                    $this->loggerInstance->debug(4, "Browser_id: $dev_id\n");
                    if ($this->version == 1) {
                        return(['id' => $dev_id, 'display' => $device['display'], 'group' => $device['group']]);
                    } else {
                        return(['device' => $dev_id, 'display' => $device['display'], 'group' => $device['group']]);
                    }
                } else {
                    $this->loggerInstance->debug(2, "Unrecognised system: " . $_SERVER['HTTP_USER_AGENT'] . "\n");
                    return(false);
                }
            }
        }
        $this->loggerInstance->debug(2, "Unrecognised system: " . $_SERVER['HTTP_USER_AGENT'] . "\n");
        return(false);
    }

    public function JSON_detectOS() {
        $returnArray = $this->detectOS();
        if ($returnArray) {
            $status = 1;
        } else {
            $status = 0;
        }
        echo $this->return_json($returnArray, $status);
    }

    public $device;
    public $version;
    private $installerPath;

    /**
     * access to the logging system
     * @var Logging
     */
    protected $loggerInstance;

}

function profile_sort($profile1, $profile2) {
    return strcasecmp($profile1->name, $profile2->name);
}
