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
     * @param int $prof_id profile identifier
     *
     * @return array 
     *  array with the following fields: 
     *  profile - the profile identifier; 
     *  device - the device identifier; 
     *  link - the path name of the resulting installer
     *  mime - the mimetype of the installer
     */
    public function generateInstaller($device, $prof_id, $generated_for = "user") {
        $this->set_locale("devices");
        $this->loggerInstance->debug(4, "installer:$device:$prof_id\n");
        $profile = ProfileFactory::instantiate($prof_id);
        $attribs = $profile->getCollapsedAttributes();
        // test if the profile is production-ready and if not if the authenticated user is an owner
        if (!isset($attribs['profile:production']) || (isset($attribs['profile:production']) && $attribs['profile:production'][0] != "on")) {
            $this->loggerInstance->debug(4, "Attempt to download a non-production ready installer fir profile: $prof_id\n");
            require_once(CONFIG['AUTHENTICATION']['ssp-path-to-autoloader']);
            $as = new SimpleSAML_Auth_Simple(CONFIG['AUTHENTICATION']['ssp-authsource']);
            if (!$as->isAuthenticated()) {
                $this->loggerInstance->debug(2, "User NOT authenticated, rejecting request for a non-production installer\n");
                header("HTTP/1.0 403 Not Authorized");
                return;
            }

            $user_object = new User($_SESSION['user']);
            if (!$user_object->isIdPOwner($profile->institution)) {
                $this->loggerInstance->debug(2, "User not an owner of a non-production profile - access forbidden\n");
                header("HTTP/1.0 403 Not Authorized");
                return;
            }
            $this->loggerInstance->debug(4, "User is the owner - allowing access\n");
        }
        $installerProperties = [];
        $installerProperties['profile'] = $prof_id;
        $installerProperties['device'] = $device;
        $this->i_path = $this->getCachedPath($device, $profile);
        if ($this->i_path) {
            $this->loggerInstance->debug(4, "Using cached installer for: $device\n");
            $installerProperties['link'] = "API.php?api_version=$this->version&action=downloadInstaller&lang=" . CAT::get_lang() . "&profile=$prof_id&device=$device&generatedfor=$generated_for";
            $installerProperties['mime'] = $cache['mime'];
        } else {
            $myInstaller = $this->generateNewInstaller($device, $profile);
            $installerProperties['mime'] = $myInstaller['mime'];
            $installerProperties['link'] = $myInstaller['link'];
        }
        $this->set_locale("web_user");
        return($installerProperties);
    }

    private function getCachedPath($device, $profile) {
        $Dev = Devices::listDevices();
        $Config = $Dev[$device];
        $no_cache = (isset(Devices::$Options['no_cache']) && Devices::$Options['no_cache']) ? 1 : 0;
        if (isset($Config['options']['no_cache'])) {
            $no_cache = $Config['options']['no_cache'] ? 1 : 0;
        }
        if ($no_cache) {
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
                $this->i_path = $dev->FPATH . '/' . $installer;
                rename($iPath, $this->i_path);
                $profile->updateCache($device, $this->i_path, $out['mime']);
                rrmdir($dev->FPATH . '/tmp');
                $this->loggerInstance->debug(4, "Generated installer: " . $this->i_path . ": for: $device\n");
                $out['link'] = "API.php?api_version=$this->version&action=downloadInstaller&lang=" . CAT::get_lang() . "&profile=$prof_id&device=$device&generatedfor=$generated_for";
            } else {
                $this->loggerInstance->debug(2, "Installer generation failed for: $prof_id:$device:" . CAT::get_lang() . "\n");
                $out['link'] = 0;
            }
        }
        return($out);
    }

    /**
     * interface to Devices::listDevices() 
     */
    public function listDevices($show_hidden = 0) {
        $Dev = Devices::listDevices();
        $returnList = [];
        $ct = 0;
        if ($show_hidden !== 0 && $show_hidden != 1) {
            throw new Exception("show_hidden is only be allowed to be 0 or 1, but it is $show_hidden!");
        }
        foreach ($Dev as $device => $deviceProperties) {
            if (isset($deviceProperties['options']['hidden']) && $deviceProperties['options']['hidden'] && $show_hidden == 0) {
                continue;
            }
            $ct ++;
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

    public function deviceInfo($device, $prof_id) {
        $this->set_locale("devices");
        $out = 0;
        $profile = ProfileFactory::instantiate($prof_id);
        $factory = new DeviceFactory($device);
        $dev = $factory->device;
        if (isset($dev)) {
//       $dev->setup($profile);
            $out = $dev->writeDeviceInfo();
        }
        $this->set_locale("web_user");
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
        $this->set_locale("devices");
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
        $this->set_locale("web_user");
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
        $return_array = [];
        $return_array['status'] = $status;
        $return_array['data'] = $data;
        $return_array['tou'] = "Please consult Terms of Use at: " . $this->GetRootURL() . "/tou.php";
        return(json_encode($return_array));
    }

    /**
     * Return the list of supported languages.
     *
     * 
     */
    public function JSON_listLanguages() {
        $return_array = [];
        foreach (CONFIG['LANGUAGES'] as $id => $val) {
            if ($this->version == 1)
                $return_array[] = ['id' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
            else
                $return_array[] = ['lang' => $id, 'display' => $val['display'], 'locale' => $val['locale']];
        }
        echo $this->return_json($return_array);
    }

    /**
     * Return the list of countiers with configured IdPs
     *
     * @return string JSON encoded data
     */
    public function JSON_listCountries() {
        $FED = $this->printCountryList(1);
        $return_array = [];
        foreach ($FED as $id => $val) {
            if ($this->version == 1)
                $return_array[] = ['id' => $id, 'display' => $val];
            else
                $return_array[] = ['federation' => $id, 'display' => $val];
        }
        echo $this->return_json($return_array);
    }

    /**
     * Return the list of IdPs in a given country
     *
     * @param int $idp_id the IdP identifier
     * @return string JSON encoded data
     */
    public function JSON_listIdentityProviders($country) {
        $idps = Federation::listAllIdentityProviders(1, $country);
        $return_array = [];
        foreach ($idps as $idp) {
            if ($this->version == 1)
                $return_array[] = ['id' => $idp['entityID'], 'display' => $idp['title']];
            else
                $return_array[] = ['idp' => $idp['entityID'], 'display' => $idp['title']];
        }
        echo $this->return_json($return_array);
    }

    /**
     * return the list of all active IdPs
     *
     * The IdP list is formatted for DiscoJuice
     * @return string JSON encoded data
     */
    public function JSON_listIdentityProvidersForDisco() {
        $idps = Federation::listAllIdentityProviders(1);
        $return_array = [];
        foreach ($idps as $idp) {
            if ($this->version == 1)
                $idp['id'] = $idp['entityID'];
            else
                $idp['idp'] = $idp['entityID'];
            $return_array[] = $idp;
        }
        echo json_encode($return_array);
    }

    /**
     * Return the list of IdPs in a given country ordered with respect to the user location
     *
     * @param int $idp_id the IdP identifier
     * @return string JSON encoded data
     */
    public function JSON_orderIdentityProviders($country, $L = NULL) {
        $idps = $this->orderIdentityProviders($country, $L);
        $return_array = [];
        foreach ($idps as $idp) {
            if ($this->version == 1)
                $return_array[] = ['id' => $idp['id'], 'display' => $idp['title']];
            else
                $return_array[] = ['idp' => $idp['id'], 'display' => $idp['title']];
        }
        echo $this->return_json($return_array);
    }

    /**
     * Produce a list of profiles available for a given IdP
     *
     * @param int $idp_id the IdP identifier
     * @return string JSON encoded data
     */
    public function JSON_listProfiles($idp_id, $sort = 0) {
        $this->set_locale("web_user");
        $return_array = [];
        try {
            $idp = new IdP($idp_id);
        } catch (Exception $fail) {
            echo $this->return_json($return_array, 0);
            return;
        }
        $l = 0;
        $logo = $idp->getAttributes('general:logo_file');
        if ($logo) {
            $l = 1;
        }
        $profiles = $idp->listProfiles(1);
        if ($sort == 1) {
            usort($profiles, "profile_sort");
        }
        foreach ($profiles as $P) {
            if ($this->version == 1) {
                $return_array[] = ['id' => $P->identifier, 'display' => $P->name, 'idp_name' => $P->instName, 'logo' => $l];
            }
            else {
                $return_array[] = ['profile' => $P->identifier, 'display' => $P->name, 'idp_name' => $P->instName, 'logo' => $l];
            }
        }
        echo $this->return_json($return_array);
    }

    /**
     * Return the list of devices available for the given profile
     *
     * @param int $profile_id the Profile identifier
     * @return string JSON encoded data
     */
    public function JSON_listDevices($profile_id) {
        $this->set_locale("web_user");
        $return_array = [];
        $a = $this->profileAttributes($profile_id);
        $thedevices = $a['devices'];
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
                    $return_array[] = ['id' => $D['id'], 'display' => $disp, 'status' => $D['status'], 'redirect' => $D['redirect']];
                } else {
                    if ($D['device'] === '0') {
                        $profile_redirect = 1;
                        $disp = $c;
                    }
                    $return_array[] = ['device' => $D['id'], 'display' => $disp, 'status' => $D['status'], 'redirect' => $D['redirect']];
                }
            }
        }
        echo $this->return_json($return_array);
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
        $file = $this->i_path;
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
     * @param int $idp_id IdP identifier
     * @param int $disco flag turning on image generation for DiscoJuice
     * @param int $width maximum width of the generated image 
     * @param int $height  maximum height of the generated image
     * if one of these is 0 then it is treated as no upper bound
     *
     */
    public function sendLogo($idp_id, $disco = FALSE, $width = 0, $height = 0) {
        $expiresString = '';
        $resize = 0;
        $logoFile = "";
        if (($width || $height) && is_numeric($width) && is_numeric($height)) {
            $resize = 1;
            if ($height == 0)
                $height = 10000;
            if ($width == 0)
                $width = 10000;
            $logoFile = ROOT . '/web/downloads/logos/' . $idp_id . '_' . $width . '_' . $height . '.png';
        } elseif ($disco == 1) {
            $width = 120;
            $height = 40;
            $resize = 1;
            $logoFile = ROOT . '/web/downloads/logos/' . $idp_id . '_' . $width . '_' . $height . '.png';
        }

        if ($resize && is_file($logoFile)) {
            $this->loggerInstance->debug(4, "Using cached logo $logoFile for: $idp_id\n");
            $blob = file_get_contents($logoFile);
            $filetype = 'image/png';
        } else {
            $idp = new IdP($idp_id);
            $at = $idp->getAttributes('general:logo_file');
            $blob = $at[0]['value'];
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
                    $this->loggerInstance->debug(4, "Writing cached logo $logoFile for: $idp_id\n");
                    file_put_contents($logoFile, $blob);
                } else
                    $blob = "XXXXXX";
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
        if ($result['country'] == 'GB')
            $result['country'] = 'UK';
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
        if ($result['country'] == 'GB')
            $result['country'] = 'UK';
        $result['region'] = $record->continent->name;

        $result['geo'] = ['lat' => (float) $record->location->latitude, 'lon' => (float) $record->location->longitude];
        return($result);
    }

    public function JSON_locateUser() {
        header('Content-type: application/json; utf-8');

        if (empty(CONFIG['GEOIP']['version']) || CONFIG['GEOIP']['version'] == 0)
            echo json_encode(['status' => 'error', 'error' => 'Geolocation not supported']);
        if (CONFIG['GEOIP']['version'] == 1)
            echo json_encode($this->locateUser());
        if (CONFIG['GEOIP']['version'] == 2)
            echo json_encode($this->locateUser2());
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
     * @param array $P1 - first point as an 'lat', 'lon' array 
     * @param array $P2 - second point as an 'lat', 'lon' array 
     * @return float distance in km
     */
    private function geoDistance($P1, $P2) {

        $dist = sin(deg2rad($P1['lat'])) * sin(deg2rad($P2['lat'])) +
                cos(deg2rad($P1['lat'])) * cos(deg2rad($P2['lat'])) * cos(deg2rad($P1['lon'] - $P2['lon']));
        $dist = rad2deg(acos($dist)) * 60 * 1.1852;
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
                    if ($d1 < $dist)
                        $dist = $d1;
                } else {
                    foreach ($G as $g) {
                        $d1 = $this->geoDistance($currentLocation, $g);
                        if ($d1 < $dist)
                            $dist = $d1;
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
            }
            else {
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
                    }
                    else {
                        return(['device' => $dev_id, 'display' => $device['display'], 'group' => $device['group']]);
                    }
                }
                else {
                    $this->loggerInstance->debug(2, "Unrecognised system: " . $_SERVER['HTTP_USER_AGENT'] . "\n");
                    return(false);
                }
            }
        }
        $this->loggerInstance->debug(2, "Unrecognised system: " . $_SERVER['HTTP_USER_AGENT'] . "\n");
        return(false);
    }

    public function JSON_detectOS() {
        $return_array = $this->detectOS();
        if ($return_array) {
            $status = 1;
        }
        else {
            $status = 0;
        }
        echo $this->return_json($return_array, $status);
    }

    public $device;
    public $version;
    private $i_path;

    /**
     * access to the logging system
     * @var Logging
     */
    protected $loggerInstance;

}

function profile_sort($P1, $P2) {
    return strcasecmp($P1->name, $P2->name);
}
