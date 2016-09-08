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
require_once('AbstractProfile.php');
require_once('devices/devices.php');

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
class ProfileRADIUS extends AbstractProfile {

    /**
     * This array holds all attributes which are defined on device level only
     * 
     * @var array
     */
    private $deviceLevelAttributes;

    /**
     * This array holds all attributes which are defined on device level only
     * 
     * @var array
     */
    private $eapLevelAttributes;
    
    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject) {
        parent::__construct($profileId, $idpObject);
        debug(3, "--- BEGIN Constructing new Profile object ... ---\n");

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];
        $this->langIndex = CAT::get_lang();

        $profile = $this->databaseHandle->exec("SELECT inst_id, realm, use_anon_outer, checkuser_outer, checkuser_value, verify_userinput_suffix as verify, hint_userinput_suffix as hint FROM profile WHERE profile_id = $profileId");
        debug(4, $profile);
        $profileQuery = mysqli_fetch_object($profile);

        $this->realm = $profileQuery->realm;

        $localValueIfAny = (preg_match('/@/', $this->realm) ? substr($this->realm, 0, strpos($this->realm, '@')) : "anonymous" );

        $internalAttributes = [
            "internal:profile_count" => $this->idpNumberOfProfiles,
            "internal:checkuser_outer" => $profileQuery->checkuser_outer,
            "internal:checkuser_value" => $profileQuery->checkuser_value,
            "internal:verify_userinput_suffix" => $profileQuery->verify,
            "internal:hint_userinput_suffix" => $profileQuery->hint,
            "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
            "internal:use_anon_outer" => $profileQuery->use_anon_outer,
            "internal:anon_local_value" => $localValueIfAny,
        ];

        // fetch the EAP type and device-specific attributes in this profile from DB

        $this->deviceLevelAttributes = $this->fetchDeviceOrEAPLevelAttributes("DEVICES");
        $this->eapLevelAttributes = $this->fetchDeviceOrEAPLevelAttributes("EAPMETHODS");

        // merge all attributes which are device or eap method specific

        $attributesLowLevel = array_merge($this->deviceLevelAttributes, $this->eapLevelAttributes);

        // now fetch and merge profile-level attributes if not already set on deeper level

        $tempArrayProfLevel = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name,option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = $this->identifier  
                                            AND device_id IS NULL AND eap_method_id = 0
                                            ORDER BY option_name", "Profile");

        // internal attributes share many attribute properties, so condense the generation

        foreach ($internalAttributes as $attName => $attValue) {
            $tempArrayProfLevel[] = ["name" => $attName,
                "value" => $attValue,
                "level" => "Profile",
                "row" => 0,
                "flag" => NULL,
                "device" => NULL,
                "eapmethod" => 0];
        }

        debug(5, "Device-Level Attributes: ".print_r($this->deviceLevelAttributes, true));
        debug(5, "EAP-Level Attributes: ".print_r($this->eapLevelAttributes, true));
        
        debug(5, "Profile-Level Attributes: ".print_r($attributesLowLevel, true));
        
        $attrUpToProfile = $this->levelPrecedenceAttributeJoin($attributesLowLevel, $tempArrayProfLevel, "Profile");

        debug(5, "Merged Attributes: ".print_r($attributesLowLevel, true));
        
        // now, fetch and merge IdP-wide attributes

        
        $idpoptions = [];
        // add "device" and "eapmethod" keys just to remain in sync with those
        // attributes that came from the Profile level
        foreach ($this->idpAttributes as $theAttr) {
            $idpoptions[] = [
                "name" => $theAttr["name"],
                "value" => $theAttr["value"],
                "level" => $theAttr["level"],
                "row" => $theAttr["row"],
                "flag" => $theAttr["flag"],
                "device" => NULL,
                "eapmethod" => 0,
            ];
        }

        $this->attributes = $this->levelPrecedenceAttributeJoin($attrUpToProfile, $idpoptions, "IdP");

        $this->privEaptypes = $this->fetchEAPMethods();

        $this->name = getLocalisedValue($this->getAttributes('profile:name'), $this->langIndex); // cannot be set per device or eap type
        
        debug(3, "--- END Constructing new Profile object ... ---\n");
    }

    private function fetchDeviceOrEAPLevelAttributes($devicesOrEAPMethods) {
        // only one of the two is allowed to be set
        $temparray = [];
        $optioninstance = Options::instance();
        switch ($devicesOrEAPMethods) {
            case "DEVICES":
                $queryPart = "device_id";
                $conditionPart = "AND eap_method_id = 0 AND device_id IS NOT NULL";
                break;
            case "EAPMETHODS":
                $queryPart = "eap_method_id";
                $conditionPart = "AND device_id IS NULL AND eap_method_id != 0";
                break;
            default:
                throw new Exception("fetchDeviceOrEAPLevelAttributes: unexpected keyword $devicesOrEAPMethods");
        }

        $allAttributes = $this->databaseHandle->exec("SELECT option_name, option_value, $queryPart as deviceormethod, row 
                FROM $this->entityOptionTable
                WHERE $this->entityIdColumn = $this->identifier $conditionPart");

        while ($attributeQuery = mysqli_fetch_object($allAttributes)) {

            $optinfo = $optioninstance->optionType($attributeQuery->option_name);
            if ($optinfo['type'] != "file") {
                $temparray[] = [
                    "name" => $attributeQuery->option_name,
                    "value" => $attributeQuery->option_value,
                    "level" => "Method",
                    "row" => $attributeQuery->row,
                    "flag" => $optinfo['flag'],
                    "device" => ($devicesOrEAPMethods == "DEVICES" ? $attributeQuery->deviceormethod : NULL),
                    "eapmethod" => ($devicesOrEAPMethods == "DEVICES" ? 0 : EAP::EAPMethodArrayFromId($attributeQuery->deviceormethod))];
            } else {
                $decodedAttribute = $this->decodeFileAttribute($attributeQuery->option_value);

                $temparray[] = [
                    "name" => $attributeQuery->option_name,
                    "value" => ( $decodedAttribute['lang'] == "" ? $decodedAttribute['content'] : serialize($decodedAttribute)),
                    "level" => "Method",
                    "row" => $attributeQuery->row,
                    "flag" => $optinfo['flag'],
                    "device" => ($devicesOrEAPMethods == "DEVICES" ? $attributeQuery->deviceormethod : NULL),
                    "eapmethod" => ($devicesOrEAPMethods == "DEVICES" ? 0 : EAP::EAPMethodArrayFromId($attributeQuery->deviceormethod))];
            }
        }
        return $temparray;
    }

    /**
     * Updates database with new installler location
     * 
     * @param string device the device identifier string
     * @param string path the path where the new installer can be found
     */
    public function updateCache($device, $path, $mime) {
        $escapedDevice = $this->databaseHandle->escapeValue($device);
        $escapedPath = $this->databaseHandle->escapeValue($path);
        $this->databaseHandle->exec("INSERT INTO downloads (profile_id,device_id,download_path,mime,lang,installer_time) 
                                        VALUES ($this->identifier, '$escapedDevice', '$escapedPath', '$mime', '$this->langIndex', CURRENT_TIMESTAMP ) 
                                        ON DUPLICATE KEY UPDATE download_path = '$escapedPath', mime = '$mime', installer_time = CURRENT_TIMESTAMP");
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
    private function addAttributeAllLevels($attrName, $attrValue, $eapType, $device) {
        $escapedAttrName = $this->databaseHandle->escapeValue($attrName);
        $escapedAttrValue = $this->databaseHandle->escapeValue($attrValue);
        $escapedDevice = $this->databaseHandle->escapeValue($device);

        $this->databaseHandle->exec("INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_value, eap_method_id, device_id) 
                          VALUES(" . $this->identifier . ", '$escapedAttrName', '$escapedAttrValue', $eapType, " . ($device === NULL ? "NULL" : "'".$escapedDevice."'") . ")");
        $this->updateFreshness();
    }

    public function addAttributeEAPSpecific($attrName, $attrValue, $eapType) {
        $this->addAttributeAllLevels($attrName, $attrValue, $eapType, NULL);
    }

    public function addAttributeDeviceSpecific($attrName, $attrValue, $device) {
        $this->addAttributeAllLevels($attrName, $attrValue, 0, $device);
    }

    public function addAttribute($attrName, $attrValue) {
        $this->addAttributeAllLevels($attrName, $attrValue, 0, NULL);
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
     * overrides the parent class definition: in Profile, we additionally need 
     * to delete the supported EAP types list in addition to just flushing the
     * normal DB-based attributes
     */
    public function beginFlushAttributes() {
        $this->databaseHandle->exec("DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        return parent::beginFlushAttributes();
    }

    /** Toggle anonymous outer ID support.
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     *
     */
    public function setAnonymousIDSupport($shallwe) {
        $this->databaseHandle->exec("UPDATE profile SET use_anon_outer = " . ($shallwe === true ? "1" : "0") . " WHERE profile_id = $this->identifier");
    }

    /** Toggle special username for realm checks
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     * @param string $localpart the username
     *
     */
    public function setRealmCheckUser($shallwe, $localpart = NULL) {
        $this->databaseHandle->exec("UPDATE profile SET checkuser_outer = " . ($shallwe === true ? "1" : "0") .
                ( $localpart !== NULL ? ", checkuser_value = '$localpart' " : "") .
                " WHERE profile_id = $this->identifier");
    }

    /** should username be verified or even prefilled?
     * 
     */
    public function setInputVerificationPreference($verify, $hint) {
        $this->databaseHandle->exec("UPDATE profile SET verify_userinput_suffix = " . ($verify == true ? "1" : "0") .
                ", hint_userinput_suffix = " . ($hint == true ? "1" : "0") .
                " WHERE profile_id = $this->identifier");
    }

    /**
     * 
     */
    public function getSufficientConfig() {
        $result = $this->databaseHandle->exec("SELECT sufficient_config FROM profile WHERE profile_id = " . $this->identifier);
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
            // just a per-device redirect? would be good enough... but this is not actually possible:
            // per-device redirects can only be set on the "fine-tuning" page, which is only accessible
            // if at least one EAP type is fully configured - which is caught above and makes readyForShowtime TRUE already
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
        $this->databaseHandle->exec("UPDATE profile SET sufficient_config = ". ($properConfig ? "TRUE" : "FALSE") ." WHERE profile_id = " . $this->identifier);
        
        $attribs = $this->getCollapsedAttributes();
        // if not enough info to go live, set FALSE
        // even if enough info is there, admin has the ultimate say: 
        //   if he doesn't want to go live, no further checks are needed, set FALSE as well
        if (!$properConfig || !isset($attribs['profile:production']) || (isset($attribs['profile:production']) && $attribs['profile:production'][0] != "on")) {
            $this->databaseHandle->exec("UPDATE profile SET showtime = FALSE WHERE profile_id = " . $this->identifier);
            return;
        }
        $this->databaseHandle->exec("UPDATE profile SET showtime = TRUE WHERE profile_id = " . $this->identifier);
    }
}
