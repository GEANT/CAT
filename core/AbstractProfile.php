<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the AbstractProfile class. It contains common methods for
 * both RADIUS/EAP profiles and SilverBullet profiles
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
require_once('EAP.php');
require_once('X509.php');
require_once('EntityWithDBProperties.php');
require_once('IdP.php');
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
abstract class AbstractProfile extends EntityWithDBProperties {

    /**
     * DB identifier of the parent institution of this profile
     * @var int
     */
    public $institution;

    /**
     * name of the parent institution of this profile in the current language
     * @var string
     */
    public $instName;

    /**
     * realm of this profile (empty string if unset)
     * @var string
     */
    public $realm;

    /**
     * This array holds the supported EAP types (in "array" OUTER/INNER representation). They are not synced against the DB after instantiation.
     * 
     * @var array
     */
    protected $privEaptypes;

    /**
     * current language
     * @var string
     */
    protected $langIndex;

    /**
     * number of profiles of the IdP this profile is attached to
     */
    protected $idpNumberOfProfiles;

    /**
     * IdP-wide attributes of the IdP this profile is attached to
     */
    protected $idpAttributes;

    /**
     * each profile has supported EAP methods, so get this from DB, Silver Bullet has one
     * static EAP method.
     */
    protected function fetchEAPMethods() {
        $eapMethod = $this->databaseHandle->exec("SELECT eap_method_id 
                                                        FROM supported_eap supp 
                                                        WHERE supp.profile_id = $this->identifier 
                                                        ORDER by preference");
        $eapTypeArray = [];
        while ($eapQuery = (mysqli_fetch_object($eapMethod))) {
            $eaptype = EAP::EAPMethodArrayFromId($eapQuery->eap_method_id);
            $eapTypeArray[] = $eaptype;
        }
        $this->loggerInstance->debug(4, "Looks like this profile supports the following EAP types:\n" . print_r($eapTypeArray, true));
        return $eapTypeArray;
    }

    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * sub-classes need to set the property $realm, $name themselves!
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject = NULL) {
        $this->databaseType = "INST";
        parent::__construct(); // we now have access to our database handle and logging
        $this->loggerInstance->debug(3, "--- BEGIN Constructing new AbstractProfile object ... ---\n");
        $profile = $this->databaseHandle->exec("SELECT inst_id FROM profile WHERE profile_id = $profileId");
        if (!$profile || $profile->num_rows == 0) {
            $this->loggerInstance->debug(2, "Profile $profileId not found in database!\n");
            throw new Exception("Profile $profileId not found in database!");
        }
        $this->identifier = $profileId;
        $profileQuery = mysqli_fetch_object($profile);
        if (!($idpObject instanceof IdP)) {
            $this->institution = $profileQuery->inst_id;
            $idp = new IdP($this->institution);
        } else {
            $idp = $idpObject;
            $this->institution = $idp->identifier;
        }

        $this->instName = $idp->name;
        $this->idpNumberOfProfiles = $idp->profileCount();
        $this->idpAttributes = $idp->getAttributes();
        $this->loggerInstance->debug(3, "--- END Constructing new AbstractProfile object ... ---\n");
    }

    /**
     * join new attributes to existing ones, but only if not already defined on
     * a different level in the existing set
     * @param array $existing the already existing attributes
     * @param array $new the new set of attributes
     * @param string $newlevel the level of the new attributes
     * @return array the new set of attributes
     */
    protected function levelPrecedenceAttributeJoin($existing, $new, $newlevel) {
        foreach ($new as $attrib) {
            $ignore = "";
            foreach ($existing as $approvedAttrib) {
                if ($attrib["name"] == $approvedAttrib["name"] && $approvedAttrib["level"] != $newlevel) {
                    $ignore = "YES";
                }
            }
            if ($ignore != "YES") {
                $existing[] = $attrib;
            }
        }
        return $existing;
    }

    /**
     * find a profile, given its realm
     */
    public static function profileFromRealm($realm) {
        // static, need to create our own handle
        $handle = DBConnection::handle("INST");
        $execQuery = $handle->exec("SELECT profile_id FROM profile WHERE realm LIKE '%@$realm'");
        if ($profileIdQuery = mysqli_fetch_object($execQuery)) {
            return $profileIdQuery->profile_id;
        }
        return FALSE;
    }

    /**
     * update the last_changed timestamp for this profile
     */
    public function updateFreshness() {
        $this->databaseHandle->exec("UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE profile_id = $this->identifier");
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     */
    public function getFreshness() {
        $execUpdate = $this->databaseHandle->exec("SELECT last_change FROM profile WHERE profile_id = $this->identifier");
        if ($freshnessQuery = mysqli_fetch_object($execUpdate)) {
            return $freshnessQuery->last_change;
        }
    }

    /**
     * tests if the configurator needs to be regenerated
     * returns the configurator path or NULL if regeneration is required
     */
    /**
     * This function tests if the configurator needs to be regenerated 
     * (properties of the Profile may have changed since the last configurator 
     * generation).
     * SilverBullet will always return NULL here because all installers are new!
     * 
     * @param string $device device ID to check
     * @return mixed a string with the path to the configurator download, or NULL if it needs to be regenerated
     */

    /**
     * This function tests if the configurator needs to be regenerated (properties of the Profile may have changed since the last configurator generation).
     * 
     * @param string $device device ID to check
     * @return mixed a string with the path to the configurator download, or NULL if it needs to be regenerated
     */
    public function testCache($device) {
        $returnValue = NULL;
        $escapedDevice = $this->databaseHandle->escapeValue($device);
        $result = $this->databaseHandle->exec("SELECT download_path, mime, UNIX_TIMESTAMP(installer_time) AS tm FROM downloads WHERE profile_id = $this->identifier AND device_id = '$escapedDevice' AND lang = '$this->langIndex'");
        if ($result && $cache = mysqli_fetch_object($result)) {
            $execUpdate = $this->databaseHandle->exec("SELECT UNIX_TIMESTAMP(last_change) AS last_change FROM profile WHERE profile_id = $this->identifier");
            if ($lastChange = mysqli_fetch_object($execUpdate)->last_change) {
                if ($lastChange < $cache->tm) {
                    $this->loggerInstance->debug(4, "Installer cached:$cache->download_path\n");
                    $returnValue = ['cache' => $cache->download_path, 'mime' => $cache->mime];
                }
            }
        }
        return $returnValue;
    }

    /**
     * Updates database with new installer location. Actually does stuff when
     * caching is possible; is a noop if not
     * 
     * @param string device the device identifier string
     * @param string path the path where the new installer can be found
     */
    abstract public function updateCache($device, $path, $mime);

    /**
     * Log a new download for our stats
     * 
     * @param string $device the device id string
     * @param string $area either admin or user
     * @return boolean TRUE if incrementing worked, FALSE if not
     */
    public function incrementDownloadStats($device, $area) {
        $escapedDevice = $this->databaseHandle->escapeValue($device);
        if ($area == "admin" || $area == "user") {
            $this->databaseHandle->exec("INSERT INTO downloads (profile_id, device_id, lang, downloads_$area) VALUES ($this->identifier, '$escapedDevice','$this->langIndex', 1) ON DUPLICATE KEY UPDATE downloads_$area = downloads_$area + 1");
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
        $numbers = $this->databaseHandle->exec("SELECT device_id, SUM(downloads_user) AS downloads_user FROM downloads WHERE profile_id = $this->identifier GROUP BY device_id");
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
     * Deletes the profile from database and uninstantiates itself.
     * Works fine also for Silver Bullet; the first query will simply do nothing
     * because there are no stored options
     *
     */
    public function destroy() {
        $this->databaseHandle->exec("DELETE FROM profile_option WHERE profile_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM profile WHERE profile_id = $this->identifier");
        unset($this);
    }

    /**
     * Specifies the realm of this profile.
     * 
     * @param string $realm the realm (potentially with the local@ part that should be used for anonymous identities)
     */
    public function setRealm($realm) {
        $escapedRealm = $this->databaseHandle->escapeValue($realm);
        $this->databaseHandle->exec("UPDATE profile SET realm = '$escapedRealm' WHERE profile_id = $this->identifier");
        $this->realm = $escapedRealm;
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param array $type The EAP Type, as defined in class EAP
     * @param int $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     *
     */
    public function addSupportedEapMethod($type, $preference) {
        $this->databaseHandle->exec("INSERT INTO supported_eap (profile_id, eap_method_id, preference) VALUES ("
                . $this->identifier . ", "
                . EAP::EAPMethodIdFromArray($type) . ", "
                . $preference . ")");
        $this->updateFreshness();
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
        }
        foreach ($this->privEaptypes as $type) {
            if ($this->isEapTypeDefinitionComplete($type) === true) {
                $temparray[] = $type;
            }
        }
        return($temparray);
    }

    /**
     * Performs a sanity check for a given EAP type - did the admin submit enough information to create installers for him?
     * 
     * @param array $eaptype the EAP type in "array" OUTER/INNER representation
     * @return mixed TRUE if the EAP type is complete; an array of missing attribues if it's incomplete; FALSE if it's incomplete for other reasons
     */
    public function isEapTypeDefinitionComplete($eaptype) {
        // TLS, TTLS, PEAP outer phase need a CA certficate and a Server Name
        switch ($eaptype['OUTER']) {
            case TLS:
                if ($eaptype['INNER'] == NE_SILVERBULLET) {
                    // silverbullet does not have any configurable properties
                    return true;
                }
            // intentionally fall through: normal TLS must go through all
            // cert and name checks!
            case PEAP:
            case TTLS:
            case FAST:
                $missing = [];
                $cnOption = $this->getAttributes("eap:server_name"); // cannot be set per device or eap type
                $caOption = $this->getAttributes("eap:ca_file"); // cannot be set per device or eap type

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
                if (count($missing) == 0) {
                    return TRUE;
                }
                return $missing;
            case PWD:
                // well actually this EAP type has a server name; but it's optional
                // so no reason to be picky on it
                return true;
            default:
                return false;
        }
    }

    /**
     * list all devices marking their availabiblity and possible redirects
     *
     * @param string $locale for text-based attributes, either returns values for the default value, or if specified here, in the locale specified
     * @return array of device ids display names and their status
     */
    public function listDevices($locale = NULL) {
        if ($locale === NULL) {
            $locale = $this->langIndex;
        }
        $returnarray = [];
        $redirect = $this->getAttributes("device-specific:redirect"); // this might return per-device ones or the general one
        // if it was a general one, we are done. Find out if there is one such
        // which has device = NULL
        $generalRedirect = NULL;
        foreach ($redirect as $index => $oneRedirect) {
            if ($oneRedirect["level"] == "Profile") {
                $generalRedirect = $index;
            }
        }
        if ($generalRedirect !== NULL) { // could be index 0
            $unserialised = unserialize($redirect[$generalRedirect]['value']);
            return [['id' => '0', 'redirect' => $unserialised['content']]];
        }
        $preferredEap = $this->getEapMethodsinOrderOfPreference(1);
        $eAPOptions = [];
        foreach (Devices::listDevices() as $deviceIndex => $deviceProperties) {
            $factory = new DeviceFactory($deviceIndex);
            $dev = $factory->device;
            // find the attribute pertaining to the specific device
            $redirectUrl = 0;
            foreach ($redirect as $index => $oneRedirect) {
                if ($oneRedirect["device"] == $deviceIndex) {
                    $redirectUrl = getLocalisedValue($oneRedirect, $locale);
                }
            }
            $devStatus = AVAILABLE;
            $message = 0;
            if (isset($deviceProperties['options']) && isset($deviceProperties['options']['message']) && $deviceProperties['options']['message']) {
                $message = $deviceProperties['options']['message'];
            }
            $eapCustomtext = 0;
            $deviceCustomtext = 0;
            if ($redirectUrl === 0) {
                if (isset($deviceProperties['options']) && isset($deviceProperties['options']['redirect']) && $deviceProperties['options']['redirect']) {
                    $devStatus = HIDDEN;
                } else {
                    $eap = $dev->getPreferredEapType($preferredEap);
                    if (count($eap) > 0) {
                        if (isset($eAPOptions["eap-specific:customtext"][serialize($eap)])) {
                            $eapCustomtext = $eAPOptions["eap-specific:customtext"][serialize($eap)];
                        } else {
                            // fetch customtexts from method-level attributes
                            $eapCustomtext = 0;
                            $customTextAttributes = [];
                            $attributeList = $this->getAttributes("eap-specific:redirect"); // eap-specific attributes always have the array index 'eapmethod' set
                            foreach ($attributeList as $oneAttribute) {
                                if ($oneAttribute["eapmethod"] == $eap) {
                                    $customTextAttributes[] = $oneAttribute;
                                }
                            }
                            if (count($customTextAttributes) > 0) {
                                $eapCustomtext = getLocalisedValue($customTextAttributes, $locale);
                            }
                            $eAPOptions["eap-specific:customtext"][serialize($eap)] = $eapCustomtext;
                        }
                        // fetch customtexts for device
                        $customTextAttributes = [];
                        $attributeList = $this->getAttributes("device-specific:redirect");
                        foreach ($attributeList as $oneAttribute) {
                            if ($oneAttribute["device"] == $deviceIndex) { // device-specific attributes always have the array index "device" set
                                $customTextAttributes[] = $oneAttribute;
                            }
                        }
                        $deviceCustomtext = getLocalisedValue($customTextAttributes, $locale);
                    } else {
                        $devStatus = UNAVAILABLE;
                    }
                }
            }
            $returnarray[] = ['id' => $deviceIndex, 'display' => $deviceProperties['display'], 'status' => $devStatus, 'redirect' => $redirectUrl, 'eap_customtext' => $eapCustomtext, 'device_customtext' => $deviceCustomtext, 'message' => $message, 'options' => $deviceProperties['options']];
        }
        return $returnarray;
    }

    /**
     * prepare profile attributes for device modules
     * Gets profile attributes taking into account the most specific level on which they may be defined
     * as wel as the chosen language.
     * can be called with an optional $eap argument
     * 
     * @param array $eap if specified, retrieves all attributes except those not pertaining to the given EAP type
     * @return array list of attributes in collapsed style (index is the attrib name, value is an array of different values)
     */
    public function getCollapsedAttributes($eap = []) {
        $attrBefore = $this->getAttributes();
        $attr = [];
        if (count($eap) > 0) { // filter out eap-level attributes not pertaining to EAP type $eap
            foreach ($attrBefore as $index => $attrib) {
                if (!isset($attrib['eapmethod']) || $attrib['eapmethod'] == $eap || $attrib['eapmethod'] == 0) {
                    $attr[$index] = $attrib;
                }
            }
        } else {
            $attr = $attrBefore;
        }

        $temp1 = [];
        $temp = [];
        $flags = [];
        $out = [];
        foreach ($attr as $b) {
            $name = $b['name'];
            $temp1[] = $name;
            $level = $b['level'];
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
                $nameCandidate = [];
                if (isset($temp[$name]['Profile'])) {
                    foreach ($temp[$name]['Profile'] as $oneProfileName) {
                        foreach ($oneProfileName as $language => $nameInLanguage) {
                            $nameCandidate[$language] = $nameInLanguage;
                        }
                    }
                }
                if (empty($nameCandidate) && isset($temp[$name]['IdP'])) {
                    foreach ($temp[$name]['IdP'] as $oneIdPName) {
                        foreach ($oneIdPName as $language => $nameInLanguage) {
                            $nameCandidate[$language] = $nameInLanguage;
                        }
                    }
                }
                $out[$name]['langs'] = $nameCandidate;
                if (isset($nameCandidate[$this->langIndex]) || isset($nameCandidate['C'])) {
                    $out[$name][0] = (isset($nameCandidate[$this->langIndex])) ? $nameCandidate[$this->langIndex] : $nameCandidate['C'];
                }
                if (isset($nameCandidate['en'])) {
                    $out[$name][1] = $nameCandidate['en'];
                } elseif (isset($nameCandidate['C'])) {
                    $out[$name][1] = $nameCandidate['C'];
                } elseif (isset($out[$name][0])) {
                    $out[$name][1] = $out[$name][0];
                }
            } else {
                if (isset($temp[$name]['Method'])) {
                    $out[$name] = $temp[$name]['Method'];
                } elseif (isset($temp[$name]['Profile'])) {
                    $out[$name] = $temp[$name]['Profile'];
                } else {
                    $out[$name] = $temp[$name]['IdP'];
                }
            }
        }
        return($out);
    }

    /**
     * Does the profile contain enough information to generate installers with
     * it? Silverbullet will always return TRUE; RADIUS profiles need to do some
     * heavy lifting here.
     * 
     * * @return boolean TRUE if enough info is set to enable installers
     */
    abstract public function hasSufficientConfig();

    /**
     * Checks if the profile has enough information to have something to show to end users. This does not necessarily mean
     * that there's a fully configured EAP type - it is sufficient if a redirect has been set for at least one device.
     * Silverbullet is always TRUE here.
     * 
     * @return boolean TRUE if enough information for showtime is set; FALSE if not
     */
    abstract public function readyForShowtime();

    /**
     * set the showtime attribute if readyForShowTime says that there is enough info *and* the admin flagged the profile for showing
     * since Silverbullet doesn't allow the admin to flag anything, this is is always the case
     */
    abstract public function prepShowtime();

    /**
     * Checks if the profile is shown (showable) to end users
     * @return boolean TRUE if profile is shown; FALSE if not
     */
    public function isShowtime() {
        $result = $this->databaseHandle->exec("SELECT showtime FROM profile WHERE profile_id = " . $this->identifier);
        $resultRow = mysqli_fetch_row($result);
        if ($resultRow[0] == "0") {
            return FALSE;
        }
        return TRUE;
    }

    protected function addInternalAttributes($internalAttributes) {
        // internal attributes share many attribute properties, so condense the generation
        $retArray = [];
        foreach ($internalAttributes as $attName => $attValue) {
            $retArray[] = ["name" => $attName,
                "value" => $attValue,
                "level" => "Profile",
                "row" => 0,
                "flag" => NULL,
            ];
        }
        return $retArray;
    }

}
