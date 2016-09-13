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
require_once('AbstractProfile.php');

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
class ProfileSilverbullet extends AbstractProfile {
    
    /**
     * Class constructor for existing profiles (use IdP::newProfile() to actually create one). Retrieves all attributes and 
     * supported EAP types from the DB and stores them in the priv_ arrays.
     * 
     * @param int $profileId identifier of the profile in the DB
     * @param IdP $idpObject optionally, the institution to which this Profile belongs. Saves the construction of the IdP instance. If omitted, an extra query and instantiation is executed to find out.
     */
    public function __construct($profileId, $idpObject) {
        parent::__construct($profileId, $idpObject);
        $this->loggerInstance->debug(3, "--- BEGIN Constructing new Profile object ... ---\n");

        $this->entityOptionTable = "profile_option";
        $this->entityIdColumn = "profile_id";
        $this->attributes = [];
        $this->langIndex = CAT::get_lang();

        $tempMaxUsers = 200; // abolutely last resort fallback if no per-fed and no config option
        
        // set to global config value
        
        if (isset(CONFIG['CONSORTIUM']['silverbullet_default_maxusers'])) {
            $tempMaxUsers = CONFIG['CONSORTIUM']['silverbullet_default_maxusers'];
        }
        $myInst = new IdP ($this->institution);
        $myFed = new Federation($myInst->federation);
        $fedMaxusers = $myFed->getAttributes("fed:silverbullet-maxusers");
        if (isset($fedMaxusers[0])) {
            $tempMaxUsers = $fedMaxusers[0]['value'];
        }
        
        // realm is automatically calculated, then stored in DB
        
        $this->realm = "opaquehash@$myInst->identifier-$this->identifier.".strtolower($myInst->federation).CONFIG['CONSORTIUM']['silverbullet_realm_suffix'];
        $this->setRealm("$myInst->identifier-$this->identifier.".strtolower($myInst->federation).CONFIG['CONSORTIUM']['silverbullet_realm_suffix']);
        $localValueIfAny = "";

        $internalAttributes = [
            "internal:profile_count" => $this->idpNumberOfProfiles,
            "internal:realm" => preg_replace('/^.*@/', '', $this->realm),
            "internal:use_anon_outer" => FALSE,
            "internal:anon_local_value" => $localValueIfAny,
            "internal:silverbullet_maxusers" => $tempMaxUsers,
        ];

        $tempArrayProfLevel = []; // we don't currently store any profile-level attributes for SB in the database

        // internal attributes share many attribute properties, so condense the generation

        $tempArrayProfLevel = array_merge($tempArrayProfLevel, $this->addInternalAttributes($internalAttributes));
        
        // now, fetch and merge IdP-wide attributes
       
        $this->attributes = $this->levelPrecedenceAttributeJoin($tempArrayProfLevel, $this->idpAttributes, "IdP");

        $this->privEaptypes = $this->fetchEAPMethods();

        $this->name = _("eduroam-as-a-service");
        
        $this->loggerInstance->debug(3, "--- END Constructing new Profile object ... ---\n");
    }

    
    /**
     * Updates database with new installer location; NOOP because we do not
     * cache anything in Silverbullet
     * 
     * @param string device the device identifier string
     * @param string path the path where the new installer can be found
     */
    public function updateCache($device, $path, $mime) {
        // params are needed for proper overriding, but not needed at all.
    }


    /**
     * register new supported EAP method for this profile
     *
     * @param array $type The EAP Type, as defined in class EAP
     * @param int $preference preference of this EAP Type. If a preference value is re-used, the order of EAP types of the same preference level is undefined.
     *
     */
    public function addSupportedEapMethod($type, $preference) {
        // params are needed for proper overriding, but not used at all.
        $this->databaseHandle->exec("INSERT INTO supported_eap (profile_id, eap_method_id, preference) VALUES ("
                . $this->identifier . ", "
                . EAP::EAPMethodIdFromArray(EAP::$SILVERBULLET) . ", "
                . 1 . ")");
        $this->updateFreshness();
    }

    /**
     * It's EAP-TLS and there is no point in anonymity
     * @param boolean $shallwe
     */
    public function setAnonymousIDSupport($shallwe) {
        // params are needed for proper overriding, but not used at all.
        $this->databaseHandle->exec("UPDATE profile SET use_anon_outer = 0 WHERE profile_id = $this->identifier");
    }

    /**
     * We can't be *NOT* ready
     */
    public function hasSufficientConfig() {
        return TRUE;
    }

    /**
     * Checks if the profile has enough information to have something to show to end users. This does not necessarily mean
     * that there's a fully configured EAP type - it is sufficient if a redirect has been set for at least one device.
     * 
     * @return boolean TRUE if enough information for showtime is set; FALSE if not
     */
    public function readyForShowtime() {
        return TRUE;
    }

    /**
     * set the showtime and QR-user attributes if prepShowTime says that there is enough info *and* the admin flagged the profile for showing
     */
    public function prepShowtime() {
        $this->databaseHandle->exec("UPDATE profile SET sufficient_config = TRUE WHERE profile_id = " . $this->identifier);
        $this->databaseHandle->exec("UPDATE profile SET showtime = TRUE WHERE profile_id = " . $this->identifier);
    }
}
