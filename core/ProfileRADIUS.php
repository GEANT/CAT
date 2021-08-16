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
 * This file contains the Profile class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

/**
 * This class represents a profile with third-party EAP handling (i.e. a "real" RADIUS profile).
 * 
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
class ProfileRADIUS extends AbstractProfile
{

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
     * @throws Exception
     */
    public function __construct($profileId, $idpObject = NULL)
    {
        parent::__construct($profileId, $idpObject);

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];

        $profile = $this->databaseHandle->exec("SELECT inst_id, realm, use_anon_outer, checkuser_outer, checkuser_value, verify_userinput_suffix as verify, hint_userinput_suffix as hint FROM profile WHERE profile_id = ?", "i", $profileId);
        // SELECT -> resource, not boolean
        $profileQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $profile);

        $this->realm = $profileQuery->realm;

        $localValueIfAny = "anonymous";
        if (preg_match('/@/', $this->realm)) {
            $position = strpos($this->realm, '@');
            if ($position === FALSE) {
                throw new Exception("Impossible: preg_match found an @, but strpos doesn't?!");
            }
            $localValueIfAny = substr($this->realm, 0, $position);
        }

        // fetch the EAP type and device-specific attributes in this profile from DB

        $this->deviceLevelAttributes = $this->fetchDeviceOrEAPLevelAttributes("DEVICES");
        $this->eapLevelAttributes = $this->fetchDeviceOrEAPLevelAttributes("EAPMETHODS");

        // merge all attributes which are device or eap method specific

        $attributesLowLevel = array_merge($this->deviceLevelAttributes, $this->eapLevelAttributes);

        $this->loggerInstance->debug(5, "Device-Level Attributes: " . /** @scrutinizer ignore-type */ print_r($this->deviceLevelAttributes, true));
        $this->loggerInstance->debug(5, "EAP-Level Attributes: " . /** @scrutinizer ignore-type */ print_r($this->eapLevelAttributes, true));
        $this->loggerInstance->debug(5, "All low-Level Attributes: " . /** @scrutinizer ignore-type */ print_r($attributesLowLevel, true));

        // now fetch and merge profile-level attributes if not already set on deeper level

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

        $tempArrayProfLevel = array_merge($this->addDatabaseAttributes(), $this->addInternalAttributes($internalAttributes));

        $attrUpToProfile = $this->levelPrecedenceAttributeJoin($attributesLowLevel, $tempArrayProfLevel, "Profile");

        // hacky hack: device-specific:redirect can also apply to ALL devices
        // (by setting device_id = NULL in the database; but then it will be
        // retrieved /from/ the database without the "device" array key set
        // so we need to add this here where applicable

        foreach ($attrUpToProfile as $oneAttr) {
            if ($oneAttr['name'] == 'device-specific:redirect' && !isset($oneAttr['device'])) {
                $oneAttr['device'] = NULL;
            }
        }

        $this->loggerInstance->debug(5, "Merged Attributes: " . /** @scrutinizer ignore-type */ print_r($attributesLowLevel, true));

        // now, fetch and merge IdP-wide attributes


        $attrUpToIdp = $this->levelPrecedenceAttributeJoin($attrUpToProfile, $this->idpAttributes, "IdP");
        $this->attributes = $this->levelPrecedenceAttributeJoin($attrUpToIdp, $this->fedAttributes, "FED");
        $this->privEaptypes = $this->fetchEAPMethods();

        $this->name = $this->languageInstance->getLocalisedValue($this->getAttributes('profile:name')); // cannot be set per device or eap type

        // was OpenRoaming enabled for unconditional inclusion into installers?
        // add the internal attribute to that effect
        
        if (isset($this->attributes['media:openroaming_always'])) {
            $this->attributes = array_merge($this->attributes, $this->addInternalAttributes([ "internal:openroaming" => TRUE ] ));
        }
        
        $this->loggerInstance->debug(3, "--- END Constructing new Profile object ... ---\n");
    }

    /**
     * Retrieves attributes which pertain either to a specific EAP type or a specific device type.
     * 
     * @param string $devicesOrEAPMethods is either "DEVICES" or "STRINGS". Any other value throws an Exception
     * @return array the list attributes in an array
     * @throws Exception
     */
    private function fetchDeviceOrEAPLevelAttributes($devicesOrEAPMethods)
    {
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

        $allAttributes = $this->databaseHandle->exec("SELECT option_name, option_lang, option_value, $queryPart as deviceormethod, row 
                FROM $this->entityOptionTable
                WHERE $this->entityIdColumn = $this->identifier $conditionPart");

        // this is a SELECT -> resource, not boolean
        while ($attributeQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allAttributes)) {

            $optinfo = $optioninstance->optionType($attributeQuery->option_name);

            $temparray[] = [
                "name" => $attributeQuery->option_name,
                "lang" => $attributeQuery->option_lang,
                "value" => $attributeQuery->option_value,
                "level" => Options::LEVEL_METHOD,
                "row" => $attributeQuery->row,
                "flag" => $optinfo['flag'],
                "device" => ($devicesOrEAPMethods == "DEVICES" ? $attributeQuery->deviceormethod : NULL),
                "eapmethod" => ($devicesOrEAPMethods == "DEVICES" ? 0 : (new \core\common\EAP($attributeQuery->deviceormethod))->getArrayRep() )];
        }
        return $temparray;
    }

    /**
     * Updates database with new installler location
     * 
     * @param string $device         the device identifier string
     * @param string $path           the path where the new installer can be found
     * @param string $mime           the MIME type of the new installer
     * @param int    $integerEapType the numeric representation of the EAP type for which this installer was generated
     * @return void
     */
    public function updateCache($device, $path, $mime, $integerEapType, $openRoaming)
    {
        $lang = $this->languageInstance->getLang();
        $this->frontendHandle->exec("INSERT INTO downloads (profile_id,device_id,download_path,mime,lang,installer_time,eap_type, openroaming) 
                                        VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,?,?) 
                                        ON DUPLICATE KEY UPDATE download_path = ?, mime = ?, installer_time = CURRENT_TIMESTAMP, eap_type = ?", "issssiissi", $this->identifier, $device, $path, $mime, $lang, $integerEapType, $openRoaming, $path, $mime, $integerEapType);
    }

    /**
     * adds an attribute to this profile; not the usual function from EntityWithDBProperties
     * because this class also has per-EAP-type and per-device sub-settings
     *
     * @param string $attrName  name of the attribute to set
     * @param string $attrLang  language of the attribute to set (if multilang, can be NULL)
     * @param string $attrValue value of the attribute to set
     * @param int    $eapType   identifier of the EAP type in the database. 0 if the attribute is valid for all EAP types.
     * @param string $device    identifier of the device in the databse. Omit the argument if attribute is valid for all devices.
     * @return void
     */
    private function addAttributeAllLevels($attrName, $attrLang, $attrValue, $eapType, $device)
    {
        $prepQuery = "INSERT INTO $this->entityOptionTable ($this->entityIdColumn, option_name, option_lang, option_value, eap_method_id, device_id) 
                          VALUES(?, ?, ?, ?, ?, ?)";
        $this->databaseHandle->exec($prepQuery, "isssis", $this->identifier, $attrName, $attrLang, $attrValue, $eapType, $device);
        $this->updateFreshness();
    }

    /**
     * this is the variant which sets attributes for specific EAP types
     * 
     * @param string $attrName  name of the attribute to set
     * @param string $attrLang  language of the attribute to set (if multilang, can be NULL)
     * @param string $attrValue value of the attribute to set
     * @param int    $eapType   identifier of the EAP type in the database. 0 if the attribute is valid for all EAP types.
     * @return void
     */
    public function addAttributeEAPSpecific($attrName, $attrLang, $attrValue, $eapType)
    {
        $this->addAttributeAllLevels($attrName, $attrLang, $attrValue, $eapType, NULL);
    }

    /**
     * this is the variant which sets attributes for specific devices
     * 
     * @param string $attrName  name of the attribute to set
     * @param string $attrLang  language of the attribute to set (if multilang, can be NULL)
     * @param string $attrValue value of the attribute to set
     * @param string $device    identifier of the device in the databse. Omit the argument if attribute is valid for all devices.
     * @return void
     */
    public function addAttributeDeviceSpecific($attrName, $attrLang, $attrValue, $device)
    {
        $this->addAttributeAllLevels($attrName, $attrLang, $attrValue, 0, $device);
    }

    /**
     * this is the variant which sets attributes which are valid profile-wide
     * 
     * @param string $attrName  name of the attribute to set
     * @param string $attrLang  language of the attribute to set (if multilang, can be NULL)
     * @param string $attrValue value of the attribute to set
     * @return void
     */
    public function addAttribute($attrName, $attrLang, $attrValue)
    {
        $this->addAttributeAllLevels($attrName, $attrLang, $attrValue, 0, NULL);
    }

    /**
     * overrides the parent class definition: in Profile, we additionally need 
     * to delete the supported EAP types list in addition to just flushing the
     * normal DB-based attributes
     * 
     * @param string $extracondition a condition to append to the deletion query. RADIUS Profiles have eap-level or device-level options which shouldn't be purged; this can be steered in the overriding function.
     * @return array list of row id's of file-based attributes which weren't deleted
     * @throws Exception
     */
    public function beginFlushAttributes($extracondition = "")
    {
        // we don't take extraconditions
        if ($extracondition != "") {
            throw new Exception("Parameter only provided for consistent function override. You are not expected to use it.");
        }
        $this->databaseHandle->exec("DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        // parent operates on profile_options and we need the following to exclude eap-specific and device-specific
        return parent::beginFlushAttributes("AND eap_method_id = 0 AND device_id IS NULL");
    }

    /** Toggle anonymous outer ID support.
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     * @return void
     */
    public function setAnonymousIDSupport($shallwe)
    {
        $this->databaseHandle->exec("UPDATE profile SET use_anon_outer = " . ($shallwe === true ? "1" : "0") . " WHERE profile_id = $this->identifier");
    }

    /** Toggle special username for realm checks
     *
     * @param boolean $shallwe   TRUE to enable outer identities (needs valid $realm), FALSE to disable
     * @param string  $localpart the username
     * @return void
     */
    public function setRealmCheckUser($shallwe, $localpart = NULL) {
        $this->databaseHandle->exec("UPDATE profile SET checkuser_outer = " . ($shallwe === true ? "1" : "0") . " WHERE profile_id = $this->identifier");
        if ($localpart !== NULL) {
            $this->databaseHandle->exec("UPDATE profile SET checkuser_value = ? WHERE profile_id = $this->identifier", "s", $localpart);
        }
    }

    /**
     * should username be verified or even prefilled?
     * 
     * @param bool $verify should the user input be verified by the installer?
     * @param bool $hint   should the user be shown username formatting hints?
     * @return void
     */
    public function setInputVerificationPreference($verify, $hint)
    {
        $this->databaseHandle->exec("UPDATE profile SET verify_userinput_suffix = " . ($verify === true ? "1" : "0") .
                ", hint_userinput_suffix = " . ($hint === true ? "1" : "0") .
                " WHERE profile_id = $this->identifier");
    }

    /**
     * deletes all attributes in this profile on the method level
     *
     * @param int    $eapId    the numeric identifier of the EAP method
     * @param string $deviceId the name of the device
     * @return array list of row id's of file-based attributes which weren't deleted
     * @throws Exception
     */
    public function beginFlushMethodLevelAttributes($eapId, $deviceId)
    {
        if ($eapId == 0 && $deviceId == "") {
            throw new Exception("MethodLevel attributes pertain either to an EAP method or a device - none was specified in the parameters.");
        }
        if ($eapId != 0 && $deviceId != "") {
            throw new Exception("MethodLevel attributes pertain either to an EAP method or a device - both were specified in the parameters.");
        }

        $extracondition = "AND eap_method_id = $eapId"; // this string is used for EAP method specifics

        if ($eapId == 0) { // we are filtering on device instead, overwrite condition
            $extracondition = "AND device_id = '$deviceId'";
        }

        $this->databaseHandle->exec("DELETE FROM $this->entityOptionTable WHERE $this->entityIdColumn = $this->identifier AND option_name NOT LIKE '%_file' $extracondition");
        $this->updateFreshness();
        // there are currently none file-based attributes on method level, so result here is always empty, but better be prepared for the future
        $findFlushCandidates = $this->databaseHandle->exec("SELECT row FROM $this->entityOptionTable WHERE $this->entityIdColumn = $this->identifier $extracondition");
        $returnArray = [];
        // SELECT -> resource, not boolean
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $findFlushCandidates)) {
            $returnArray[$queryResult->row] = "KILLME";
        }
        return $returnArray;
    }
}