<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * 
 * 
 *  This is the definition of the CAT class
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
/**
 * necessary includes
 */
session_start();
require_once("Helper.php");
require_once("Federation.php");
require_once(dirname(__DIR__) . "/config/_config.php");

/**
 * Define some variables which need to be globally accessible
 * and some general purpose methods
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
class CAT extends Entity {

    /**
     * which version is this?
     * even if we are unreleased, keep track of internal version-to-be
     * developers need to set this in code. The user-displayed string
     * is generated into $CAT_VERSION_STRING and $CAT_COPYRIGHT below
     */
    const VERSION_MAJOR = 1;
    const VERSION_MINOR = 2;
    const VERSION_PATCH = 0;
    const VERSION_EXTRA = "";
    const RELEASE_VERSION = FALSE;
    const USER_API_VERSION = 2;

    /*
     * This is the user-displayed string; controlled by the four options above
     * It is generated in the constructor.
     * 
     * @var string
     */
    public $CAT_VERSION_STRING;
    
    /*
     * The entire copyright line, generated in constructor
     */
    public $CAT_COPYRIGHT;
        
    /**
     * the default database to query in this class.
     */
    const DB_TYPE = "INST";

    /**
     *  Constructor sets the language by calling set_lang 
     *  and stores language settings in object properties
     *  additionally it also sets static variables $laing_index and $root
     */
    public function __construct() {
        parent::__construct();
        $olddomain = $this->languageInstance->setTextDomain("user");
        $this->CAT_VERSION_STRING = _("Unreleased SVN Revision");
        if (CAT::RELEASE_VERSION) {
            $temp_version = "CAT-" . CAT::VERSION_MAJOR . "." . CAT::VERSION_MINOR;
            if (CAT::VERSION_PATCH != 0) {
                $temp_version .= "." . CAT::VERSION_PATCH;
            }
            if (CAT::VERSION_EXTRA != "") {
                $temp_version .= "-" . CAT::VERSION_EXTRA;
            }
            $this->CAT_VERSION_STRING = sprintf(_("Release %s"), $temp_version);
        }
        $this->CAT_COPYRIGHT = CONFIG['APPEARANCE']['productname'] . " - " . $this->CAT_VERSION_STRING . " &copy; 2011-16 Dante Ltd. and G&Eacute;ANT on behalf of the GN3, GN3plus, GN4-1 and GN4-2 consortia and others <a href='copyright.php'>Full Copyright and Licenses</a>";
        $this->languageInstance->setTextDomain($olddomain);
    }

    /**
     * 
     */
    public function totalIdPs($level) {
        $handle = DBConnection::handle(CAT::DB_TYPE);
        switch ($level) {
            case "ALL":
                $idpcount = $handle->exec("SELECT COUNT(inst_id) AS instcount FROM institution");
                break;
            case "VALIDPROFILE":
                $idpcount = $handle->exec("SELECT COUNT(DISTINCT institution.inst_id) AS instcount FROM institution,profile WHERE institution.inst_id = profile.inst_id AND profile.sufficient_config = 1");
                break;
            case "PUBLICPROFILE":
                $idpcount = $handle->exec("SELECT COUNT(DISTINCT institution.inst_id) AS instcount FROM institution,profile WHERE institution.inst_id = profile.inst_id AND profile.showtime = 1");
                break;
            default:
                return -1;
        }
        $dbresult = mysqli_fetch_object($idpcount);
        return $dbresult->instcount;
    }

        /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * @param int $activeOnly if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     *
     */
    public function listAllIdentityProviders($activeOnly = 0, $country = 0) {
        $handle = DBConnection::handle("INST");
        $handle->exec("SET SESSION group_concat_max_len=10000");
        $query = "SELECT distinct institution.inst_id AS inst_id, institution.country AS country,
                     group_concat(concat_ws('===',institution_option.option_name,LEFT(institution_option.option_value,200)) separator '---') AS options
                     FROM institution ";
        if ($activeOnly == 1) {
            $query .= "JOIN profile ON institution.inst_id = profile.inst_id ";
        }
        $query .= "JOIN institution_option ON institution.inst_id = institution_option.institution_id ";
        $query .= "WHERE (institution_option.option_name = 'general:instname' 
                          OR institution_option.option_name = 'general:geo_coordinates'
                          OR institution_option.option_name = 'general:logo_file') ";
        if ($activeOnly == 1) {
            $query .= "AND profile.showtime = 1 ";
        }
        if ($country) {
            // escape the parameter
            $country = $handle->escapeValue($country);
            $query .= "AND institution.country = '$country' ";
        }
        $query .= "GROUP BY institution.inst_id ORDER BY inst_id";
        $allIDPs = $handle->exec($query);
        $returnarray = [];
        while ($queryResult = mysqli_fetch_object($allIDPs)) {
            $institutionOptions = explode('---', $queryResult->options);
            $oneInstitutionResult = [];
            $geo = [];
            $names = [];

            $oneInstitutionResult['entityID'] = $queryResult->inst_id;
            $oneInstitutionResult['country'] = strtoupper($queryResult->country);
            foreach ($institutionOptions as $institutionOption) {
                $opt = explode('===', $institutionOption);
                switch ($opt[0]) {
                    case 'general:logo_file':
                        $oneInstitutionResult['icon'] = $queryResult->inst_id;
                        break;
                    case 'general:geo_coordinates':
                        $at1 = unserialize($opt[1]);
                        $geo[] = $at1;
                        break;
                    case 'general:instname':
                        $names[] = ['value' => $opt[1]];
                        break;
                    default:
                        break;
                }
            }

            $name = _("Unnamed Entity");
            if (count($names) != 0) {
                $langObject = new Language();
                $name = getLocalisedValue($names, $langObject->getLang());
            }
            $oneInstitutionResult['title'] = $name;
            if (count($geo) > 0) {
                $oneInstitutionResult['geo'] = $geo;
            }
            $returnarray[] = $oneInstitutionResult;
        }
        return $returnarray;
    }

    /**
     * Prepares a list of countries known to the CAT.
     * 
     * @param int $activeOnly is set and nonzero will cause that only countries with some institutions underneath will be listed
     * @return array Array indexed by (uppercase) lang codes and sorted according to the current locale
     */
    public function printCountryList($activeOnly = 0) {
        $olddomain = $this->languageInstance->setTextDomain("core");
        $handle = DBConnection::handle(CAT::DB_TYPE);
        $returnArray = []; // in if -> the while might never be executed, so initialise
        if ($activeOnly) {
            $federations = $handle->exec("SELECT DISTINCT LOWER(institution.country) AS country FROM institution JOIN profile
                          ON institution.inst_id = profile.inst_id WHERE profile.showtime = 1 ORDER BY country");
            while ($activeFederations = mysqli_fetch_object($federations)) {
                $fedIdentifier = $activeFederations->country;
                $fedObject = new Federation($fedIdentifier);
                $capFedName = strtoupper($fedObject->name);
                $returnArray[$capFedName] = isset(Federation::$federationList[$capFedName]) ? Federation::$federationList[$capFedName] : $capFedName;
            }
        } else {
            new Federation;
            $returnArray = Federation::$federationList;
        }
        asort($returnArray, SORT_LOCALE_STRING);
        $this->languageInstance->setTextDomain($olddomain);
        return($returnArray);
    }
}
