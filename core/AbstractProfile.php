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
 * This file contains the AbstractProfile class. It contains common methods for
 * both RADIUS/EAP profiles and SilverBullet profiles
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
abstract class AbstractProfile extends EntityWithDBProperties
{

    const HIDDEN = -1;
    const AVAILABLE = 0;
    const UNAVAILABLE = 1;
    const INCOMPLETE = 2;
    const NOTCONFIGURED = 3;
    const PROFILETYPE_RADIUS = "RADIUS";
    const PROFILETYPE_SILVERBULLET = "SILVERBULLET";
    public const SERVERNAME_ADDED = 2;
    public const CA_ADDED = 3;
    public const CA_CLASH_ADDED = 4;

    /**
     * DB identifier of the parent institution of this profile
     * @var integer
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
     * This array holds the supported EAP types (in object representation). 
     * 
     * They are not synced against the DB after instantiation.
     * 
     * @var array
     */
    protected $privEaptypes;

    /**
     * number of profiles of the IdP this profile is attached to
     * 
     * @var integer
     */
    protected $idpNumberOfProfiles;

    /**
     * IdP-wide attributes of the IdP this profile is attached to
     * 
     * @var array
     */
    protected $idpAttributes;

    /**
     * Federation level attributes that this profile is attached to via its IdP
     * 
     * @var array
     */
    protected $fedAttributes;

    /**
     * This class also needs to handle frontend operations, so needs its own
     * access to the FRONTEND datbase. This member stores the corresponding 
     * handle.
     * 
     * @var DBConnection
     */
    protected $frontendHandle;

    /**
     * readiness levels for OpenRoaming column in profiles)
     */
    const OVERALL_OPENROAMING_LEVEL_NO = 4;
    const OVERALL_OPENROAMING_LEVEL_GOOD = 3;
    const OVERALL_OPENROAMING_LEVEL_NOTE = 2;
    const OVERALL_OPENROAMING_LEVEL_WARN = 1;
    const OVERALL_OPENROAMING_LEVEL_ERROR = 0;
    
    /**
     *  generates a detailed log of which installer was downloaded
     * 
     * @param int    $idpIdentifier the IdP identifier
     * @param int    $profileId     the Profile identifier
     * @param string $deviceId      the Device identifier
     * @param string $area          the download area (user, silverbullet, admin)
     * @param string $lang          the language of the installer
     * @param int    $eapType       the EAP type of the installer
     * @return void
     * @throws Exception
     */
    protected function saveDownloadDetails($idpIdentifier, $profileId, $deviceId, $area, $lang, $eapType, $openRoaming)
    {
        if (\config\Master::PATHS['logdir']) {
            $file = fopen(\config\Master::PATHS['logdir']."/download_details.log", "a");
            if ($file === FALSE) {
                throw new Exception("Unable to open file for append: $file");
            }
            fprintf($file, "%-015s;%d;%d;%s;%s;%s;%d;%d\n", microtime(TRUE), $idpIdentifier, $profileId, $deviceId, $area, $lang, $eapType, $openRoaming);
            fclose($file);
        }
    }

    /**
     * checks if security-relevant parameters have changed
     * 
     * @param AbstractProfile $old old instantiation of a profile to compare against
     * @param AbstractProfile $new new instantiation of a profile 
     * @return array there are never any user-induced changes in SB
     */
    public static function significantChanges($old, $new)
    {
        $retval = [];
        // check if a CA was added
        $x509 = new common\X509();
        $baselineCA = [];
        $baselineCApublicKey = [];
        foreach ($old->getAttributes("eap:ca_file") as $oldCA) {
            $ca = $x509->processCertificate($oldCA['value']);
            $baselineCA[$ca['sha1']] = $ca['name'];
            $baselineCApublicKey[$ca['sha1']] = $ca['full_details']['public_key'];
        }
        // remove the new ones that are identical to the baseline
        foreach ($new->getAttributes("eap:ca_file") as $newCA) {
            $ca = $x509->processCertificate($newCA['value']);
            if (array_key_exists($ca['sha1'], $baselineCA) || $ca['root'] != 1) {
                // do nothing; we assume here that SHA1 doesn't clash
                continue;
            }
            // check if a CA with identical DN was added - alert NRO if so
            $foundSHA1 = array_search($ca['name'], $baselineCA);
            if ($foundSHA1 !== FALSE) {
                // but only if the public key does not match
                if ($baselineCApublicKey[$foundSHA1] === $ca['full_details']['public_key']) {
                    continue;
                }
                $retval[AbstractProfile::CA_CLASH_ADDED] .= "#SHA1 for CA with DN '".$ca['name']."' has SHA1 fingerprints (pre-existing) "./** @scrutinizer ignore-type */ array_search($ca['name'], $baselineCA)." and (added) ".$ca['sha1'];
            } else {
                $retval[AbstractProfile::CA_ADDED] .= "#CA with DN '"./** @scrutinizer ignore-type */ print_r($ca['name'], TRUE)."' and SHA1 fingerprint ".$ca['sha1']." was added as trust anchor";
            }
        }
        // check if a servername was added
        $baselineNames = [];
        foreach ($old->getAttributes("eap:server_name") as $oldName) {
            $baselineNames[] = $oldName['value'];
        }
        foreach ($new->getAttributes("eap:server_name") as $newName) {
            if (!in_array($newName['value'], $baselineNames)) {
                $retval[AbstractProfile::SERVERNAME_ADDED] .= "#New server name '".$newName['value']."' added";
            }
        }
        return $retval;
    }

    /**
     * Takes note of the OpenRoaming participation and conformance level
     * 
     * @param int $level the readiness level, as determined by RFC7585Tests
     * @return void
     */
    public function setOpenRoamingReadinessInfo(int $level)
    {
            $this->databaseHandle->exec("UPDATE profile SET openroaming = ? WHERE profile_id = ?", "ii", $level, $this->identifier);
    }

    /**
     * each profile has supported EAP methods, so get this from DB, Silver Bullet has one
     * static EAP method.
     * 
     * @return array list of supported EAP methods
     */
    protected function fetchEAPMethods()
    {
        $eapMethod = $this->databaseHandle->exec("SELECT eap_method_id 
                                                        FROM supported_eap supp 
                                                        WHERE supp.profile_id = $this->identifier 
                                                        ORDER by preference");
        $eapTypeArray = [];
        // SELECTs never return a boolean, it's always a resource
        while ($eapQuery = (mysqli_fetch_object(/** @scrutinizer ignore-type */ $eapMethod))) {
            $eaptype = new common\EAP($eapQuery->eap_method_id);
            $eapTypeArray[] = $eaptype;
        }
        $this->loggerInstance->debug(4, "This profile supports the following EAP types:\n"./** @scrutinizer ignore-type */ print_r($eapTypeArray, true));
        return $eapTypeArray;
    }

    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * sub-classes need to set the property $realm, $name themselves!
     * 
     * @param int $profileIdRaw identifier of the profile in the DB
     * @param IdP $idpObject    optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     * @throws Exception
     */
    public function __construct($profileIdRaw, $idpObject = NULL)
    {
        $this->databaseType = "INST";
        parent::__construct(); // we now have access to our INST database handle and logging
        $handle = DBConnection::handle("FRONTEND");
        if ($handle instanceof DBConnection) {
            $this->frontendHandle = $handle;
        } else {
            throw new Exception("This database type is never an array!");
        }
        $profile = $this->databaseHandle->exec("SELECT inst_id FROM profile WHERE profile_id = ?", "i", $profileIdRaw);
        // SELECT always yields a resource, never a boolean
        if ($profile->num_rows == 0) {
            $this->loggerInstance->debug(2, "Profile $profileIdRaw not found in database!\n");
            throw new Exception("Profile $profileIdRaw not found in database!");
        }
        $this->identifier = $profileIdRaw;
        $profileQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $profile);
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
        $fedObject = new Federation($idp->federation);
        $this->fedAttributes = $fedObject->getAttributes();
        $this->loggerInstance->debug(3, "--- END Constructing new AbstractProfile object ... ---\n");
    }

    /**
     * find a profile, given its realm
     * 
     * @param string $realm the realm for which we are trying to find a profile
     * @return int|false the profile identifier, if any
     */
    public static function profileFromRealm($realm)
    {
        // static, need to create our own handle
        $handle = DBConnection::handle("INST");
        $execQuery = $handle->exec("SELECT profile_id FROM profile WHERE realm LIKE '%@$realm'");
        // a SELECT query always yields a resource, not a boolean
        if ($profileIdQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $execQuery)) {
            return $profileIdQuery->profile_id;
        }
        return FALSE;
    }

    /**
     * Constructs the outer ID which should be used during realm tests. Obviously
     * can only do something useful if the realm is known to the system.
     * 
     * @return string the outer ID to use for realm check operations
     * @throws Exception
     */
    public function getRealmCheckOuterUsername()
    {
        $realm = $this->getAttributes("internal:realm")[0]['value'] ?? FALSE;
        if ($realm == FALSE) { // we can't really return anything useful here
            throw new Exception("Unable to construct a realmcheck username if the admin did not tell us the realm. You shouldn't have called this function in this context.");
        }
        if (count($this->getAttributes("internal:checkuser_outer")) > 0) {
            // we are supposed to use a specific outer username for checks, 
            // which is different from the outer username we put into installers
            return $this->getAttributes("internal:checkuser_value")[0]['value']."@".$realm;
        }
        if (count($this->getAttributes("internal:use_anon_outer")) > 0) {
            // no special check username, but there is an anon outer ID for
            // installers - so let's use that one
            return $this->getAttributes("internal:anon_local_value")[0]['value']."@".$realm;
        }
        // okay, no guidance on outer IDs at all - but we need *something* to
        // test with for the RealmChecks. So:
        return "@".$realm;
    }

    /**
     * update the last_changed timestamp for this profile
     * 
     * @return void
     */
    public function updateFreshness()
    {
        $this->databaseHandle->exec("UPDATE profile SET last_change = CURRENT_TIMESTAMP WHERE profile_id = $this->identifier");
    }

    /**
     * gets the last-modified timestamp (useful for caching "dirty" check)
     * 
     * @return string the date in string form, as returned by SQL
     */
    public function getFreshness()
    {
        $execLastChange = $this->databaseHandle->exec("SELECT last_change FROM profile WHERE profile_id = $this->identifier");
        // SELECT always returns a resource, never a boolean
        if ($freshnessQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $execLastChange)) {
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
    public function testCache($device, $openRoaming)
    {
        $returnValue = ['cache' => NULL, 'mime' => NULL];
        $lang = $this->languageInstance->getLang();
        $result = $this->frontendHandle->exec("SELECT download_path, mime, UNIX_TIMESTAMP(installer_time) AS tm FROM downloads WHERE profile_id = ? AND device_id = ? AND lang = ? AND openroaming = ?", "issi", $this->identifier, $device, $lang, $openRoaming);
        // SELECT queries always return a resource, not a boolean
        if ($result && $cache = mysqli_fetch_object(/** @scrutinizer ignore-type */ $result)) {
            $execUpdate = $this->databaseHandle->exec("SELECT UNIX_TIMESTAMP(last_change) AS last_change FROM profile WHERE profile_id = $this->identifier");
            // SELECT queries always return a resource, not a boolean
            if ($lastChange = mysqli_fetch_object(/** @scrutinizer ignore-type */ $execUpdate)->last_change) {
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
     * @param string $device         the device identifier string
     * @param string $path           the path where the new installer can be found
     * @param string $mime           the mime type of the new installer
     * @param int    $integerEapType the inter-representation of the EAP type that is configured in this installer
     * @return void
     */
    abstract public function updateCache($device, $path, $mime, $integerEapType, $openRoaming);

    /** Toggle anonymous outer ID support.
     *
     * @param boolean $shallwe TRUE to enable outer identities (needs valid $realm), FALSE to disable
     * @return void
     */
    abstract public function setAnonymousIDSupport($shallwe);

    /**
     * Log a new download for our stats
     * 
     * @param string $device the device id string
     * @param string $area   either admin or user
     * @return boolean TRUE if incrementing worked, FALSE if not
     */
    public function incrementDownloadStats($device, $area, $openRoaming)
    {
        if ($area == "admin" || $area == "user" || $area == "silverbullet") {
            $lang = $this->languageInstance->getLang();
            $this->frontendHandle->exec("INSERT INTO downloads (profile_id, device_id, lang, openroaming, downloads_$area) VALUES (? ,?, ?, ?, 1) ON DUPLICATE KEY UPDATE downloads_$area = downloads_$area + 1", "issi", $this->identifier, $device, $lang, $openRoaming);
            // get eap_type from the downloads table
            $eapTypeQuery = $this->frontendHandle->exec("SELECT eap_type FROM downloads WHERE profile_id = ? AND device_id = ? AND lang = ?", "iss", $this->identifier, $device, $lang);
            // SELECT queries always return a resource, not a boolean
            if (!$eapTypeQuery || !$eapO = mysqli_fetch_object(/** @scrutinizer ignore-type */ $eapTypeQuery)) {
                $this->loggerInstance->debug(2, "Error getting EAP_type from the database\n");
            } else {
                if ($eapO->eap_type == NULL) {
                    $this->loggerInstance->debug(2, "EAP_type not set in the database\n");
                } else {
                    $this->saveDownloadDetails($this->institution, $this->identifier, $device, $area, $this->languageInstance->getLang(), $eapO->eap_type, $openRoaming);
                }
            }
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Retrieve current download stats from database, either for one specific device or for all devices
     * 
     * @param string $device the device id string
     * @return mixed user downloads of this profile; if device is given, returns the counter as int, otherwise an array with devicename => counter
     */
    public function getUserDownloadStats($device = NULL)
    {
        $columnName = "downloads_user";
        if ($this instanceof \core\ProfileSilverbullet) {
            $columnName = "downloads_silverbullet";
        }
        $returnarray = [];
        $numbers = $this->frontendHandle->exec("SELECT device_id, SUM($columnName) AS downloads_user FROM downloads WHERE profile_id = ? GROUP BY device_id", "i", $this->identifier);
        // SELECT queries always return a resource, not a boolean
        while ($statsQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $numbers)) {
            $returnarray[$statsQuery->device_id] = $statsQuery->downloads_user;
        }
        if ($device !== NULL) {
            if (isset($returnarray[$device])) {
                return $returnarray[$device];
            }
            return 0;
        }
        // we should pretty-print the device names
        $finalarray = [];
        $devlist = \devices\Devices::listDevices();
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
     * @return void
     */
    public function destroy()
    {
        $this->databaseHandle->exec("DELETE FROM profile_option WHERE profile_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM supported_eap WHERE profile_id = $this->identifier");
        $this->databaseHandle->exec("DELETE FROM profile WHERE profile_id = $this->identifier");
    }

    /**
     * Specifies the realm of this profile.
     * 
     * Forcefully type-hinting $realm parameter to string - Scrutinizer seems to
     * think that it can alternatively be an array<integer,?> which looks like a
     * false positive. If there really is an issue, let PHP complain about it at
     * runtime.
     * 
     * @param string $realm the realm (potentially with the local@ part that should be used for anonymous identities)
     * @return void
     */
    public function setRealm(string $realm)
    {
        $this->databaseHandle->exec("UPDATE profile SET realm = ? WHERE profile_id = ?", "si", $realm, $this->identifier);
        $this->realm = $realm;
    }

    /**
     * register new supported EAP method for this profile
     *
     * @param \core\common\EAP $type       The EAP Type, as defined in class EAP
     * @param int              $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     * @return void
     */
    public function addSupportedEapMethod(\core\common\EAP $type, $preference)
    {
        $eapInt = $type->getIntegerRep();
        $this->databaseHandle->exec("INSERT INTO supported_eap (profile_id, eap_method_id, preference) VALUES (?, ?, ?)", "iii", $this->identifier, $eapInt, $preference);
        $this->updateFreshness();
    }

    /**
     * Produces an array of EAP methods supported by this profile, ordered by preference
     * 
     * @param int $completeOnly if set and non-zero limits the output to methods with complete information
     * @return array list of EAP methods, (in object representation)
     */
    public function getEapMethodsinOrderOfPreference($completeOnly = 0)
    {
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
     * @param common\EAP $eaptype the EAP type
     * @return mixed TRUE if the EAP type is complete; an array of missing attribues if it's incomplete; FALSE if it's incomplete for other reasons
     */
    public function isEapTypeDefinitionComplete($eaptype)
    {
        if ($eaptype->needsServerCACert() && $eaptype->needsServerName()) {
            $missing = [];
            // silverbullet needs a support email address configured
            if ($eaptype->getIntegerRep() == common\EAP::INTEGER_SILVERBULLET && count($this->getAttributes("support:email")) == 0) {
                return ["support:email"];
            }
            $cnOption = $this->getAttributes("eap:server_name"); // cannot be set per device or eap type
            $caOption = $this->getAttributes("eap:ca_file"); // cannot be set per device or eap type

            if (count($caOption) > 0 && count($cnOption) > 0) {// see if we have at least one root CA cert
                foreach ($caOption as $oneCa) {
                    $x509 = new \core\common\X509();
                    $caParsed = $x509->processCertificate($oneCa['value']);
                    if ($caParsed['root'] == 1) {
                        return TRUE;
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
        }
        return TRUE;
    }

    /**
     * list all devices marking their availabiblity and possible redirects
     *
     * @return array of device ids display names and their status
     */
    public function listDevices()
    {
        $returnarray = [];
        $redirect = $this->getAttributes("device-specific:redirect"); // this might return per-device ones or the general one
        // if it was a general one, we are done. Find out if there is one such
        // which has device = NULL
        $generalRedirect = NULL;
        foreach ($redirect as $index => $oneRedirect) {
            if ($oneRedirect["level"] == Options::LEVEL_PROFILE) {
                $generalRedirect = $index;
            }
        }
        if ($generalRedirect !== NULL) { // could be index 0
            return [['id' => '0', 'redirect' => $redirect[$generalRedirect]['value']]];
        }
        $preferredEap = $this->getEapMethodsinOrderOfPreference(1);
        $eAPOptions = [];
        foreach (\devices\Devices::listDevices() as $deviceIndex => $deviceProperties) {
            $factory = new DeviceFactory($deviceIndex);
            $dev = $factory->device;
            // find the attribute pertaining to the specific device
            $group = '';
            $redirectUrl = 0;
            $redirects = [];
            foreach ($redirect as $index => $oneRedirect) {
                if ($oneRedirect["device"] == $deviceIndex) {
                    $redirects[] = $oneRedirect;
                }
            }
            if (count($redirects) > 0) {
                $redirectUrl = $this->languageInstance->getLocalisedValue($redirects);
            }
            $devStatus = self::AVAILABLE;
            $message = 0;
            if (isset($deviceProperties['options']) && isset($deviceProperties['options']['message']) && $deviceProperties['options']['message']) {
                $message = $deviceProperties['options']['message'];
            }
            if (isset($deviceProperties['group'])) {
                $group = $deviceProperties['group'];
            }
            $eapCustomtext = 0;
            $deviceCustomtext = 0;
            if ($redirectUrl === 0) {
                if (isset($deviceProperties['options']) && isset($deviceProperties['options']['redirect']) && $deviceProperties['options']['redirect']) {
                    $devStatus = self::HIDDEN;
                } else {
                    $dev->calculatePreferredEapType($preferredEap);
                    $eap = $dev->selectedEap;
                    if (count($eap) > 0) {
                        if (isset($eAPOptions["eap-specific:customtext"][serialize($eap)])) {
                            $eapCustomtext = $eAPOptions["eap-specific:customtext"][serialize($eap)];
                        } else {
                            // fetch customtexts from method-level attributes
                            $eapCustomtext = 0;
                            $customTextAttributes = [];
                            $attributeList = $this->getAttributes("eap-specific:customtext"); // eap-specific attributes always have the array index 'eapmethod' set
                            foreach ($attributeList as $oneAttribute) {
                                if ($oneAttribute["eapmethod"] == $eap) {
                                    $customTextAttributes[] = $oneAttribute;
                                }
                            }
                            if (count($customTextAttributes) > 0) {
                                $eapCustomtext = $this->languageInstance->getLocalisedValue($customTextAttributes);
                            }
                            $eAPOptions["eap-specific:customtext"][serialize($eap)] = $eapCustomtext;
                        }
                        // fetch customtexts for device
                        $customTextAttributes = [];
                        $attributeList = $this->getAttributes("device-specific:customtext");
                        foreach ($attributeList as $oneAttribute) {
                            if ($oneAttribute["device"] == $deviceIndex) { // device-specific attributes always have the array index "device" set
                                $customTextAttributes[] = $oneAttribute;
                            }
                        }
                        $deviceCustomtext = $this->languageInstance->getLocalisedValue($customTextAttributes);
                    } else {
                        $devStatus = self::UNAVAILABLE;
                    }
                }
            }
            $returnarray[] = ['id' => $deviceIndex, 'display' => $deviceProperties['display'], 'status' => $devStatus, 'redirect' => $redirectUrl, 'eap_customtext' => $eapCustomtext, 'device_customtext' => $deviceCustomtext, 'message' => $message, 'options' => $deviceProperties['options'], 'group' => $group];
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
    public function getCollapsedAttributes($eap = [])
    {
        $collapsedList = [];
        foreach ($this->getAttributes() as $attribute) {
            // filter out eap-level attributes not pertaining to EAP type $eap
            if (count($eap) > 0 && isset($attribute['eapmethod']) && $attribute['eapmethod'] != 0 && $attribute['eapmethod'] != $eap) {
                continue;
            }
            // create new array indexed by attribute name
            
            if (isset($attribute['device'])) {
                $collapsedList[$attribute['name']][$attribute['device']][] = $attribute['value'];
            } else {
                $collapsedList[$attribute['name']][] = $attribute['value'];
            } 
            // and keep all language-variant names in a separate sub-array
            if ($attribute['flag'] == "ML") {
                $collapsedList[$attribute['name']]['langs'][$attribute['lang']] = $attribute['value'];
            }
        }
        // once we have the final list, populate the respective "best-match"
        // language to choose for the ML attributes
        foreach ($collapsedList as $attribName => $valueArray) {
            if (isset($valueArray['langs'])) { // we have at least one language-dependent name in this attribute
                // for printed names on screen:
                // assign to exact match language, fallback to "default" language, fallback to English, fallback to whatever comes first in the list
                $collapsedList[$attribName][0] = $valueArray['langs'][$this->languageInstance->getLang()] ?? $valueArray['langs']['C'] ?? $valueArray['langs']['en'] ?? array_shift($valueArray['langs']);
                // for names usable in filesystems (closer to good old ASCII...)
                // prefer English, otherwise the "default" language, otherwise the same that we got above
                $collapsedList[$attribName][1] = $valueArray['langs']['en'] ?? $valueArray['langs']['C'] ?? $collapsedList[$attribName][0];
            }
        }
        return $collapsedList;
    }

    const READINESS_LEVEL_NOTREADY = 0;
    const READINESS_LEVEL_SUFFICIENTCONFIG = 1;
    const READINESS_LEVEL_SHOWTIME = 2;

    /**
     * Does the profile contain enough information to generate installers with
     * it? Silverbullet will always return TRUE; RADIUS profiles need to do some
     * heavy lifting here.
     * 
     * @return int one of the constants above which tell if enough info is set to enable installers
     */
    public function readinessLevel()
    {
        $result = $this->databaseHandle->exec("SELECT sufficient_config, showtime FROM profile WHERE profile_id = ?", "i", $this->identifier);
        // SELECT queries always return a resource, not a boolean
        $configQuery = mysqli_fetch_row(/** @scrutinizer ignore-type */ $result);
        if ($configQuery[0] == "0") {
            return self::READINESS_LEVEL_NOTREADY;
        }
        // at least fully configured, if not showtime!
        if ($configQuery[1] == "0") {
            return self::READINESS_LEVEL_SUFFICIENTCONFIG;
        }
        return self::READINESS_LEVEL_SHOWTIME;
    }

    /**
     * Checks if the profile has enough information to have something to show to end users. This does not necessarily mean
     * that there's a fully configured EAP type - it is sufficient if a redirect has been set for at least one device.
     * 
     * @return boolean TRUE if enough information for showtime is set; FALSE if not
     */
    private function readyForShowtime()
    {
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
                (!isset(\config\ConfAssistant::CONSORTIUM['ssid']) || count(\config\ConfAssistant::CONSORTIUM['ssid']) == 0) &&
                !isset($attribs['media:wired'])) {
            $properConfig = FALSE;
        }
        return $properConfig;
    }

    /**
     * set the showtime property if prepShowTime says that there is enough info *and* the admin flagged the profile for showing
     * 
     * @return void
     */
    public function prepShowtime()
    {
        $properConfig = $this->readyForShowtime();
        $this->databaseHandle->exec("UPDATE profile SET sufficient_config = ".($properConfig ? "TRUE" : "FALSE")." WHERE profile_id = ".$this->identifier);

        $attribs = $this->getCollapsedAttributes();
        // if not enough info to go live, set FALSE
        // even if enough info is there, admin has the ultimate say: 
        //   if he doesn't want to go live, no further checks are needed, set FALSE as well
        if (!$properConfig || !isset($attribs['profile:production']) || (isset($attribs['profile:production']) && $attribs['profile:production'][0] != "on")) {
            $this->databaseHandle->exec("UPDATE profile SET showtime = FALSE WHERE profile_id = ?", "i", $this->identifier);
            return;
        }
        $this->databaseHandle->exec("UPDATE profile SET showtime = TRUE WHERE profile_id = ?", "i", $this->identifier);
    }

    /**
     * internal helper - some attributes are added by the constructor "ex officio"
     * without actual input from the admin. We can streamline their addition in
     * this function to avoid duplication.
     * 
     * @param array $internalAttributes - only names and value
     * @return array full attributes with all properties set
     */
    protected function addInternalAttributes($internalAttributes)
    {
        // internal attributes share many attribute properties, so condense the generation
        $retArray = [];
        foreach ($internalAttributes as $attName => $attValue) {
            $retArray[] = ["name" => $attName,
                "lang" => NULL,
                "value" => $attValue,
                "level" => Options::LEVEL_PROFILE,
                "row" => 0,
                "flag" => NULL,
            ];
        }
        return $retArray;
    }

    /**
     * Retrieves profile attributes stored in the database
     * 
     * @return array The attributes in one array
     */
    protected function addDatabaseAttributes()
    {
        $databaseAttributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name, option_lang, option_value, row
                FROM $this->entityOptionTable
                WHERE $this->entityIdColumn = ?
                AND device_id IS NULL AND eap_method_id = 0
                ORDER BY option_name", "Profile");
        return $databaseAttributes;
    }
}