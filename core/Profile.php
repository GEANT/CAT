<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the Profile class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */
/**
 * necessary includes
 */
require_once('Helper.php');
require_once('IdP.php');
require_once('EAP.php');
require_once('X509.php');
require_once('EntityWithDBProperties.php');
require_once('devices/devices.php');

define("HIDDEN", -1);
define("AVAILABLE", 0);
define("UNAVAILABLE", 1);
define("INCOMPLETE", 2);
define("NOTCONFIGURED", 3);

/**
 * This class represents an EAP Profile.
 * Profiles can inherit attributes from their IdP, if the IdP has some. Otherwise,
 * one can set attribute in the Profile directly. If there is a conflict between
 * IdP-wide and Profile-wide attributes, the more specific ones (i.e. Profile) win.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class Profile extends EntityWithDBProperties {

    /**
     * This array holds the supported EAP types (in "array" OUTER/INNER representation). They are not synced against the DB after instantiation.
     * 
     * @var array
     */
    private $privEaptypes;

    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject = 0) {
        debug(3, "--- BEGIN Constructing new Profile object ... ---\n");

        $this->databaseType = "INST";
        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->identifier = $profileId;
        $this->attributes = [];

        $profile = DBConnection::exec($this->databaseType, "SELECT inst_id, realm, use_anon_outer, checkuser_outer, checkuser_value, verify_userinput_suffix as verify, hint_userinput_suffix as hint FROM profile WHERE profile_id = $profileId");
        debug(4, $profile);
        if (!$profile || $profile->num_rows == 0) {
            debug(2, "Profile $profileId not found in database!\n");
            throw new Exception("Profile $profileId not found in database!");
        }
        $profileQuery = mysqli_fetch_object($profile);
        if (!($idpObject instanceof IdP)) {
            $this->institution = $profileQuery->inst_id;
            $idp = new IdP($this->institution);
        } else {
            $idp = $idpObject;
            $this->institution = $idp->name;
        }
        $temparray = [];
        $optioninstance = Options::instance();

        $this->realm = $profileQuery->realm;
        $this->use_anon_outer = $profileQuery->use_anon_outer;
        $this->langIndex = CAT::get_lang();
        $this->inst_name = $idp->name;

        $this->checkuser_outer = $profileQuery->checkuser_outer;
        $this->checkuser_value = $profileQuery->checkuser_value;
        $this->verify = $profileQuery->verify;
        $this->hint = $profileQuery->hint;

        // fetch all atributes from this profile from DB

        $allAttributes = DBConnection::exec($this->databaseType, "SELECT option_name, option_value, device_id, eap_method_id as method, row 
                FROM $this->entityOptionTable
                WHERE $this->entityIdColumn = $this->identifier");

        while ($attributeQuery = mysqli_fetch_object($allAttributes)) {

            $optinfo = $optioninstance->optionType($attributeQuery->option_name);
            if ($optinfo['type'] != "file") {
                $temparray[] = [
                    "name" => $attributeQuery->option_name,
                    "value" => $attributeQuery->option_value,
                    "level" => ($attributeQuery->device_id == NULL && $attributeQuery->method == 0 ? "Profile" : "Method" ),
                    "row" => $attributeQuery->row,
                    "device" => $attributeQuery->device_id,
                    "flag" => $optinfo['flag'],
                    "eapmethod" => EAP::EAPMethodArrayFromId($attributeQuery->method)];
            } else {
                $decodedAttribute = $this->decodeFileAttribute($attributeQuery->option_value);

                $temparray[] = [
                    "name" => $attributeQuery->option_name,
                    "value" => ( $decodedAttribute['lang'] == "" ? $decodedAttribute['content'] : serialize($decodedAttribute)),
                    "level" => ($attributeQuery->device_id == NULL && $attributeQuery->method == 0 ? "Profile" : "Method" ),
                    "row" => $attributeQuery->row,
                    "flag" => $optinfo['flag'],
                    "device" => $attributeQuery->device_id,
                    "eapmethod" => EAP::EAPMethodArrayFromId($attributeQuery->method)];
            }
        }
        // add internal attributes
        // they share many attribute properties, so condense the generation
        
        $localValueIfAny = (preg_match('/@/', $this->realm) ? substr($this->realm, 0, strpos($this->realm, '@')) : "anonymous" );
        
        $internalAttributes = [
          "internal:profile_count" => $idp->profileCount(),
          "internal:checkuser_outer" => $this->checkuser_outer,
          "internal:checkuser_value" => $this->checkuser_value,
          "internal:verify_userinput_suffix" => $this->verify,
          "internal:hint_userinput_suffix" => $this->hint,
          "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
          "internal:use_anon_outer" => $this->use_anon_outer,
          "internal:anon_local_value" => $localValueIfAny,
        ];

        foreach ($internalAttributes as $attName => $attValue) {
            $temparray[] = ["name" => $attName,
            "value" => $attValue,
            "level" => "Profile",
            "row" => 0,
            "flag" => NULL,
            "device" => NULL,
            "eapmethod" => NULL];
        }
        
        // now, fetch IdP-wide attributes

        $idpoptions = $idp->getAttributes();

        foreach ($idpoptions as $theAttr) {
            $temparray[] = [
                "name" => $theAttr["name"],
                "value" => $theAttr["value"],
                "level" => $theAttr["level"],
                "row" => $theAttr["row"],
                "flag" => $theAttr["flag"],
                "device" => NULL,
                "eapmethod" => NULL,
            ];
        }

        // check sanity (device and eapmethod are mutually exclusive) and first batch of adding (method level)

        foreach ($temparray as $attrib) {
            if ($attrib["device"] != NULL && $attrib["eapmethod"] != NULL) {
                debug(2, "Sanity check failed - device and eapmethod are set!\n");
            }
        }

        foreach ($temparray as $attrib) {
            if ($attrib["device"] != NULL || $attrib["eapmethod"] != NULL) {
                $this->attributes[] = $attrib;
            }
        }
        // pick all attributes which are profile specific and place into final array if no eap/device-specific exists

        foreach ($temparray as $attrib) {
            if ($attrib["level"] == "Profile") {
                $ignore = "";
                foreach ($this->attributes as $approvedAttrib) {
                    if ($attrib["name"] == $approvedAttrib["name"] && $approvedAttrib["level"] != "IdP" && $approvedAttrib["level"] != "Profile") {
                        $ignore = "YES";
                    }
                }
                if ($ignore != "YES") {
                    $this->attributes[] = $attrib;
                }
            }
        }

        // now, add IdP-wide attribs

        foreach ($temparray as $attrib) {
            if ($attrib["level"] == "IdP") {
                $ignore = "";
                foreach ($this->attributes as $approvedAttrib) {
                    if ($attrib["name"] == $approvedAttrib["name"] && $approvedAttrib["level"] != "IdP") {
                        $ignore = "YES";
                    }
                }
                if ($ignore != "YES") {
                    $this->attributes[] = $attrib;
                }
            }
        }

        $this->name = getLocalisedValue($this->getAttributes('profile:name', 0, 0), $this->langIndex);

        $eapMethod = DBConnection::exec($this->databaseType, "SELECT eap_method_id 
                                                        FROM supported_eap supp 
                                                        WHERE supp.profile_id = $this->identifier 
                                                        ORDER by preference");
        $returnarray = [];
        while ($eapQuery = (mysqli_fetch_object($eapMethod))) {
            $eaptype = EAP::EAPMethodArrayFromId($eapQuery->eap_method_id);
            $returnarray[] = $eaptype;
        }
        debug(4, "Looks like this profile supports the following EAP types: ");
        debug(4, $returnarray);
        $this->privEaptypes = $returnarray;

        debug(3, "--- END Constructing new Profile object ... ---\n");
    }

    /**
     * find a profile, given its realm
     */
    public static function profileFromRealm($realm) {
        $execQuery = DBConnection::exec($this->databaseType, "SELECT profile_id FROM profile WHERE realm LIKE '%@$realm'");
        if ($profileIdQuery = mysqli_fetch_object($execQuery)) {
            return $profileIdQuery->profile_id;
        }
        return FALSE;
    }

    /**
     * update the last_changed timestamp for this profile
     */
    public function updateFreshness() {
        DBConnection::exec($this->databaseType, "UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE profile_id = $this->identifier");
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     */
    public function getFreshness() {
        $execUpdate = DBConnection::exec($this->databaseType, "SELECT last_change FROM profile WHERE profile_id = $this->identifier");
        if ($freshnessQuery = mysqli_fetch_object($execUpdate)) {
            return $freshnessQuery->last_change;
        }
    }

    /**
     * tests if the configurator needs to be regenerated
     * returns the configurator path or NULL if regeneration is required
     */

    /**
     * This function tests if the configurator needs to be regenerated (properties of the Profile may have changed since the last configurator generation).
     * 
     * @param string $device device ID to check
     * @return mixed a string with the path to the configurator download, or NULL if it needs to be regenerated
     */
    public function testCache($device) {
        $returnValue = NULL;
        $escapedDevice = DBConnection::escape_value($this->databaseType, $device);
        $result = DBConnection::exec($this->databaseType, "SELECT download_path, mime, UNIX_TIMESTAMP(installer_time) AS tm FROM downloads WHERE profile_id = $this->identifier AND device_id = '$escapedDevice' AND lang = '$this->langIndex'");
        if ($result && $cache = mysqli_fetch_object($result)) {
            $exec_update = DBConnection::exec($this->databaseType, "SELECT UNIX_TIMESTAMP(last_change) AS last_change FROM profile WHERE profile_id = $this->identifier");
            if ($lastChange = mysqli_fetch_object($exec_update)->last_change) {
                if ($lastChange < $cache->tm) {
                    debug(4, "Installer cached:$cache->download_path\n");
                    $returnValue = ['cache' => $cache->download_path, 'mime' => $cache->mime];
                }
            }
        }
        return $returnValue;
    }

    /**
     * Updates database with new installler location
     * 
     * @param string device the device identifier string
     * @param string path the path where the new installer can be found
     */
    public function updateCache($device, $path, $mime) {
        $escapedDevice = DBConnection::escape_value($this->databaseType, $device);
        $escapedPath = DBConnection::escape_value($this->databaseType, $path);
        DBConnection::exec($this->databaseType, "INSERT INTO downloads (profile_id,device_id,download_path,mime,lang,installer_time) 
                                        VALUES ($this->identifier, '$escapedDevice', '$escapedPath', '$mime', '$this->langIndex', CURRENT_TIMESTAMP ) 
                                        ON DUPLICATE KEY UPDATE download_path = '$escapedPath', mime = '$mime', installer_time = CURRENT_TIMESTAMP");
    }

    /**
     * Log a new download for our stats
     * 
     * @param device the device id string
     * @param area either admin or user
     * @return TRUE if incrementing worked, FALSE if not
     */
    public function incrementDownloadStats($device, $area) {
        $escapedDevice = DBConnection::escape_value($this->databaseType, $device);
        if ($area == "admin" || $area == "user") {
            DBConnection::exec($this->databaseType, "INSERT INTO downloads (profile_id, device_id, lang, downloads_$area) VALUES ($this->identifier, '$escapedDevice','$this->langIndex', 1) ON DUPLICATE KEY UPDATE downloads_$area = downloads_$area + 1");
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Retrieve current download stats from database, either for one specific device or for all devices
     * @param string $device the device id string
     * @return mixed user downloads of this profile; if device is given, returns the counter as int, otherwise an array with devicename => counter
     */
    public function getUserDownloadStats($device = 0) {
        $returnarray = [];
        $numbers = DBConnection::exec($this->databaseType, "SELECT device_id, SUM(downloads_user) AS downloads_user FROM downloads WHERE profile_id = $this->identifier GROUP BY device_id");
        while ($statsQuery = mysqli_fetch_object($numbers)) {
            $returnarray[$statsQuery->device_id] = $statsQuery->downloads_user;
        }
        if ($device !== 0) {
            if (isset($returnarray[$device])) {
                return $returnarray[$device];
            }
            return 0;
        }
        // we should pretty-print the device names
        $finalarray = [];
        $devlist = Devices::listDevices();
        foreach ($returnarray as $devId => $count) {
            if (isset($devlist[$devId])) {
                $finalarray[$devlist[$devId]['display']] = $count;
            }
        }
        return $finalarray;
    }

    /**
     * adds an attribute to this profile; not the usual function from EntityWithDBProperties
     * because this class also has per-EAP-type and per-device sub-settings
     *
     * @param string $attrName name of the attribute to set
     * @param string $attrValue value of the attribute to set
     * @param int $eapType identifier of the EAP type in the database. 0 if the attribute is valid for all EAP types.
     * @param string $device identifier of the device in the databse. Omit the argument if attribute is valid for all devices.
     */
    public function addAttribute($attrName, $attrValue, $eapType, $device = 0) {
        $escapedAttrName = DBConnection::escape_value($this->databaseType, $attrName);
        $escapedAttrValue = DBConnection::escape_value($this->databaseType, $attrValue);

        DBConnection::exec($this->databaseType, "INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_value, eap_method_id" . ($device !== 0 ? ",device_id" : "") . ") 
                          VALUES(" . $this->identifier . ", '$escapedAttrName', '$escapedAttrValue', $eapType" . ($device !== 0 ? ",'" . DBConnection::escape_value($this->databaseType, $device) . "'" : "" ) . ")");
        $this->updateFreshness();
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param array $type The EAP Type, as defined in class EAP
     * @param int $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     *
     */
    public function addSupportedEapMethod($type, $preference) {
        DBConnection::exec($this->databaseType, "INSERT INTO supported_eap (profile_id, eap_method_id, preference) VALUES ("
                . $this->identifier . ", "
                . EAP::EAPMethodIdFromArray($type) . ", "
                . $preference . ")");
        $this->updateFreshness();
    }

    /**
     * Deletes the profile from database and uninstantiates itself.
     *
     */
    public function destroy() {
        DBConnection::exec($this->databaseType, "DELETE FROM profile_option WHERE profile_id = $this->identifier");
        DBConnection::exec($this->databaseType, "DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        DBConnection::exec($this->databaseType, "DELETE FROM profile WHERE profile_id = $this->identifier");
        unset($this);
    }

    /**
     * Removes all supported EAP methods
     */
    public function flushSupportedEapMethods() {
        DBConnection::exec($this->databaseType, "DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        $this->updateFreshness();
    }

    /** Toggle anonymous outer ID support.
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     *
     */
    public function setAnonymousIDSupport($shallwe) {
        DBConnection::exec($this->databaseType, "UPDATE profile SET use_anon_outer = " . ($shallwe == true ? "1" : "0") . " WHERE profile_id = $this->identifier");
    }

    /** Toggle special username for realm checks
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     * @param string $localpart the username
     *
     */
    public function setRealmCheckUser($shallwe, $localpart = NULL) {
        DBConnection::exec($this->databaseType, "UPDATE profile SET checkuser_outer = " . ($shallwe == true ? "1" : "0") .
                ( $localpart !== NULL ? ", checkuser_value = '$localpart' " : "") .
                " WHERE profile_id = $this->identifier");
    }

    /** should username be verified or even prefilled?
     * 
     */
    public function setInputVerificationPreference($verify, $hint) {
        DBConnection::exec($this->databaseType, "UPDATE profile SET verify_userinput_suffix = " . ($verify == true ? "1" : "0") .
                ", hint_userinput_suffix = " . ($hint == true ? "1" : "0") .
                " WHERE profile_id = $this->identifier");
    }

    /**
     * Specifies the realm of this profile.
     * 
     * @param string $realm the realm (potentially with the local@ part that should be used for anonymous identities)
     */
    public function setRealm($realm) {
        $escapedRealm = DBConnection::escape_value($this->databaseType, $realm);
        DBConnection::exec($this->databaseType, "UPDATE profile SET realm = '$escapedRealm' WHERE profile_id = $this->identifier");
        $this->realm = $escapedRealm;
    }

    /**
     * Produces an array of EAP methods supported by this profile, ordered by preference
     * 
     * @param int $completeOnly if set and non-zero limits the output to methods with complete information
     * @return array list of EAP methods, (in "array" OUTER/INNER representation)
     */
    public function getEapMethodsinOrderOfPreference($completeOnly = 0) {
        $temparray = [];

        if ($completeOnly == 0) {
            return $this->privEaptypes;
        } else {
            foreach ($this->privEaptypes as $type) {
                if ($this->isEapTypeDefinitionComplete($type) === true) {
                    $temparray[] = $type;
                }
            }
            return($temparray);
        }
    }

    /** Returns an array of the profile's attributes.
     * 
     * @param string option_name the name of a specific option. If set, only returns option values for this option name
     * @param eapmethod the EAP type, in array ("OUTER/INNER") notation. If set, returns only attributes which are specific to that EAP type
     * @param string device the device ID string. If set, returns only attributes which are specific to that device
     * @return array attributes of the profile
     */
    public function getAttributes($optionName = 0, $eapmethod = 0, $device = 0) {

        $outarray = [];
        $temparray = [];
        if ($eapmethod) {
            foreach ($this->attributes as $theAttr) {
                if ($theAttr["eapmethod"] == $eapmethod) {
                    $temparray[] = $theAttr;
                }
            }
        } else
        if ($device) {
            foreach ($this->attributes as $theAttr) {
                if ($theAttr["device"] == $device) {
                    $temparray[] = $theAttr;
                }
            }
        }

        foreach ($this->attributes as $theAttr) {
            if ($theAttr["device"] == NULL && $theAttr["eapmethod"] == NULL) {
                $temparray[] = $theAttr;
            }
        }

        // return only options by one name, if asked for

        if ($optionName) {
            foreach ($temparray as $theAttr) {
                if ($theAttr["name"] == $optionName) {
                    $outarray[] = $theAttr;
                }
            }
        } else {
            $outarray = $temparray;
        }
        
        return $outarray;
    }

    /**
     * Performs a sanity check for a given EAP type - did the admin submit enough information to create installers for him?
     * 
     * @param array $eaptype the EAP type in "array" OUTER/INNER representation
     * @return mixed TRUE if the EAP type is complete; an array of missing attribues if it's incomplete; FALSE if it's incomplete for other reasons
     */
    public function isEapTypeDefinitionComplete($eaptype) {
        $missing = [];
        // TLS, TTLS, PEAP outer phase need a CA certficate and a Server Name
        if ($eaptype["OUTER"] == PEAP || $eaptype["OUTER"] == TLS || $eaptype["OUTER"] == TTLS || $eaptype["OUTER"] == FAST) {

            $cnOption = $this->getAttributes("eap:server_name", $eaptype);
            $caOption = $this->getAttributes("eap:ca_file", $eaptype);
            /* echo "<pre>";
              print_r($options);
              echo "</pre>"; */
            if (count($caOption) > 0 && count($cnOption) > 0) {// see if we have at least one root CA cert
                foreach ($caOption as $oneCa) {
                    $x509 = new X509();
                    $caParsed = $x509->processCertificate($oneCa['value']);
                    if ($caParsed['root'] == 1) {
                        return true;
                    }
                }
                $missing[] = "eap:ca_file";
            }
            if (count($caOption) == 0) {
                $missing[] = "eap:ca_file";
            }
            if (count($cnOption) == 0) {
                $missing[] = "eap:server_name";
            }
            return $missing;
        } elseif ($eaptype["OUTER"] == PWD || $eaptype["INNER"] == NE_SILVERBULLET) {
            /*
              $cn_option = $this->getAttributes("eap:server_name", $eaptype);
              if (count($cn_option) > 0) */
            return true;
            /* $missing[] = "eap:server_name";
              return $missing; */
        }

        // we have no idea; let's say false

        return false;
    }

    /**
     * list all devices marking their availabiblity and possible redirects
     *
     * @param string $locale for text-based attributes, either returns values for the default value, or if specified here, in the locale specified
     * @return array of device ids display names and their status
     */
    public function listDevices($locale = 0) {
        if ($locale == 0) {
            $locale = $this->langIndex;
        }
        $redirectUrl = 0;
        $returnarray = [];
        $redirect = $this->getAttributes("device-specific:redirect", 0, 0);
        if ($redirect) {
            $unserialised = unserialize($redirect[0]['value']);
            return [['id' => '0', 'redirect' => $unserialised['content']]];
        }
        $preferredEap = $this->getEapMethodsinOrderOfPreference(1);
        $eAPOptions = [];
        foreach (Devices::listDevices() as $devideIndex => $deviceProperties) {
            $factory = new DeviceFactory($devideIndex);
            $dev = $factory->device;
            $redirectUrl = getLocalisedValue($this->getAttributes("device-specific:redirect", 0, $devideIndex), $locale);
            $devStatus = AVAILABLE;
            $message = 0;
            if (isset($deviceProperties['options']) && isset($deviceProperties['options']['message']) && $deviceProperties['options']['message']) {
                $message = $deviceProperties['options']['message'];
            }

            if ($redirectUrl === 0) {
                if (isset($deviceProperties['options']) && isset($deviceProperties['options']['redirect']) && $deviceProperties['options']['redirect']) {
                    $devStatus = HIDDEN;
                } else {
                    $eap = $dev->getPreferredEapType($preferredEap);
                    if ($eap) {
                        if (isset($eAPOptions["eap-specific:customtext"][serialize($eap)])) {
                            $eapCustomtext = $eAPOptions["eap-specific:customtext"][serialize($eap)];
                        }
                        else {
                            $eapCustomtext = getLocalisedValue($this->getAttributes("eap-specific:customtext", $eap, 0), $locale);
                            $eAPOptions["eap-specific:customtext"][serialize($eap)] = $eapCustomtext;
                        }
                        $deviceCustomtext = getLocalisedValue($this->getAttributes("device-specific:customtext", 0, $devideIndex), $locale);
                    } else {
                        $devStatus = UNAVAILABLE;
                    }
                }
            }
            $returnarray[] = ['id' => $devideIndex, 'display' => $deviceProperties['display'], 'status' => $devStatus, 'redirect' => $redirectUrl, 'eap_customtext' => $eapCustomtext, 'device_customtext' => $deviceCustomtext, 'message' => $message, 'options' => $deviceProperties['options']];
        }
        return $returnarray;
    }

    /**
     * prepare profile attributes for device modules
     * Gets profile attributes taking into account the most specific level on which they may be defined
     * as wel as the chosen language.
     * can be called with an optional $eap argument
     * 
     * @param array $eap if specified, retrieves attributes specific to the given EAP type
     * @return array list of attributes in collapsed style (index is the attrib name, value is an array of different values)
     */
    public function getCollapsedAttributes($eap = 0) {
        $attr = $this->getAttributes(0, $eap);
        $temp1 = [];
        foreach ($attr as $b) {
            $name = $b['name'];
            $temp1[] = $name;
            $level = $b['level'];
//            $S[$l] = $z[$l];
            $value = $b['value'];
            if (!isset($temp[$name][$level])) {
                $temp[$name][$level] = [];
            }
            if ($b['flag'] == 'ML') {
                $v = unserialize($value);
                $value = [$v['lang'] => $v['content']];
            }
            $temp[$name][$level][] = $value;
            $flags[$name] = $b['flag'];
        }
        foreach ($temp1 as $name) {
            if ($flags[$name] == 'ML') {
                $S = [];
                if (isset($temp[$name]['Profile'])) {
                    foreach ($temp[$name]['Profile'] as $z) {
                        foreach ($z as $l => $w) {
                            $S[$l] = $w;
                        }
                    }
                }
                if (!$S && isset($temp[$name]['IdP'])) {
                    foreach ($temp[$name]['IdP'] as $z) {
                        foreach ($z as $l => $w) {
                            $S[$l] = $w;
                        }
                    }
                }
                $out[$name]['langs'] = $S;
                if (isset($S[$this->langIndex]) || isset($S['C'])) {
                    $out[$name][0] = (isset($S[$this->langIndex])) ? $S[$this->langIndex] : $S['C'];
                }
            } else {
                if (isset($temp[$name]['Method'])) {
                    $out[$name] = $temp[$name]['Method'];
                }
                elseif (isset($temp[$name]['Profile'])) {
                    $out[$name] = $temp[$name]['Profile'];
                }
                else {
                    $out[$name] = $temp[$name]['IdP'];
                }
            }
        }
        return($out);
    }

    /**
     * 
     */
    public function getSufficientConfig() {
        $result = DBConnection::exec($this->databaseType, "SELECT sufficient_config FROM profile WHERE profile_id = " . $this->identifier);
        $configQuery = mysqli_fetch_row($result);
        if ($configQuery[0] == "0") {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Checks if the profile has enough information to have something to show to end users. This does not necessarily mean
     * that there's a fully configured EAP type - it is sufficient if a redirect has been set for at least one device.
     * 
     * @return boolean TRUE if enough information for showtime is set; FALSE if not
     */
    public function readyForShowtime() {
        $properConfig = FALSE;
        $attribs = $this->getCollapsedAttributes();
        // do we have enough to go live? Check if any of the configured EAP methods is completely configured ...
        if (sizeof($this->getEapMethodsinOrderOfPreference(1)) > 0) {
            $properConfig = TRUE;
        }
        // if not, it could still be that general redirect has been set
        if (!$properConfig) {
            if (isset($attribs['device-specific:redirect'])) {
                $properConfig = TRUE;
            }
            // TODO: or maybe just a per-device redirect? would be good enough...
        }
        // do we know at least one SSID to configure, or work with wired? If not, it's not ready...
        if (!isset($attribs['media:SSID']) &&
                !isset($attribs['media:SSID_with_legacy']) &&
                (!isset(Config::$CONSORTIUM['ssid']) || count(Config::$CONSORTIUM['ssid']) == 0) &&
                !isset($attribs['media:wired'])) {
            $properConfig = FALSE;
                }
        return $properConfig;
    }

    /**
     * set the showtime and QR-user attributes if prepShowTime says that there is enough info *and* the admin flagged the profile for showing
     */
    public function prepShowtime() {
        $properConfig = $this->readyForShowtime();
        if ($properConfig) {
            DBConnection::exec($this->databaseType, "UPDATE profile SET sufficient_config = TRUE WHERE profile_id = " . $this->identifier);
        }
        else {
            DBConnection::exec($this->databaseType, "UPDATE profile SET sufficient_config = FALSE WHERE profile_id = " . $this->identifier);
        }
        $attribs = $this->getCollapsedAttributes();
        // if not enough info to go live, set FALSE
        // even if enough info is there, admin has the ultimate say: 
        //   if he doesn't want to go live, no further checks are needed, set FALSE as well
        if (!$properConfig || !isset($attribs['profile:production']) || (isset($attribs['profile:production']) && $attribs['profile:production'][0] != "on")) {
            DBConnection::exec($this->databaseType, "UPDATE profile SET showtime = FALSE WHERE profile_id = " . $this->identifier);
            return;
        } else {
            DBConnection::exec($this->databaseType, "UPDATE profile SET showtime = TRUE WHERE profile_id = " . $this->identifier);
            return;
        }
    }

    /**
     * Checks if the profile is shown (showable) to end users
     * @return boolean TRUE if profile is shown; FALSE if not
     */
    public function getShowtime() {
        $result = DBConnection::exec($this->databaseType, "SELECT showtime FROM profile WHERE profile_id = " . $this->identifier);
        $resultRow = mysqli_fetch_row($result);
        if ($resultRow[0] == "0") {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * current language
     * @var string
     */
    private $langIndex;

    /**
     * DB identifier of the parent institution of this profile
     * @var int
     */
    public $institution;

    /**
     * name of the parent institution of this profile in the current language
     * @var string
     */
    public $inst_name;

    /**
     * realm of this profile (empty string if unset)
     * @var string
     */
    public $realm;

    /**
     * boolean value: should anonymous outer IDs be used or not?
     * @var boolean
     */
    public $use_anon_outer;

}
