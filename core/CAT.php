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
require_once("Logging.php");
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
class CAT {

    /**
     * which version is this?
     * even if we are unreleased, keep track of internal version-to-be
     * developers need to set this in code. The user-displayed string
     * is generated into $VERSION below
     */
    public static $VERSION_MAJOR = 1;
    public static $VERSION_MINOR = 2;
    public static $VERSION_PATCH = 0;
    public static $VERSION_EXTRA = "";
    public static $RELEASE_VERSION = FALSE;
    public static $USER_API_VERSION = 2;

    /*
     * This is the user-displayed string; controlled by the four options above
     * It is generated in the constructor.
     */
    public static $VERSION;

    /**
      /**
     * database which this class queries by default
     * 
     * @var string
     */
    private static $LANG = '';
    private static $DB_TYPE = "INST";

    /**
     *  Constructor sets the language by calling set_lang 
     *  and stores language settings in object properties
     *  additionally it also sets static variables $laing_index and $root
     */
    public function __construct() {
        $language = $this->set_lang();
        self::$locale = $language[1];
        CAT::$VERSION = _("Unreleased SVN Revision");
        if (CAT::$RELEASE_VERSION) {
            $temp_version = "CAT-" . CAT::$VERSION_MAJOR . "." . CAT::$VERSION_MINOR;
            if (CAT::$VERSION_PATCH != 0) {
                $temp_version .= "." . CAT::$VERSION_PATCH;
            }
            if (CAT::$VERSION_EXTRA != "") {
                $temp_version .= "-" . CAT::$VERSION_EXTRA;
            }
            CAT::$VERSION = sprintf(_("Release %s"), $temp_version);
        }
    }

    /**
     * 
     */
    public function totalIdPs($level) {
        $handle = DBConnection::handle(CAT::$DB_TYPE);
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
     * Sets the gettext domain
     *
     * @param string $domain
     * @return string previous seting so that you can restore it later
     */
    public static function set_locale($domain) {
        $loggerInstance = new Logging();
        $olddomain = textdomain(NULL);
        $loggerInstance->debug(4, "set_locale($domain)\n");
        $loggerInstance->debug(4, ROOT . "\n");
        textdomain($domain);
        bindtextdomain($domain, ROOT . "/translation/");
        return $olddomain;
    }

    /**
     * set_lang does all language setting magic
     * checks if lang has been declared in the http call
     * if not, checks for saved lang in the SESSION
     * or finally checks browser properties.
     * Only one of the supported langiages can be set
     * if a match is not found, the default langiage is used
     * @param $hardsetlang - this is currently not used but
     * will allow to forst lang setting if this was ever required
     */
    private static function set_lang($hardsetlang = 0) {
        $lang_converted = [];
        if ($hardsetlang !== 0) {
            $hardsetlocale = $hardsetlang;
            $lang_converted[] = $hardsetlocale;
            $_SESSION['language'] = $hardsetlocale;
        } elseif (isset($_REQUEST['lang'])) {
            $hardsetlocale = $_REQUEST['lang'];
            $lang_converted[] = $hardsetlocale;
            $_SESSION['language'] = $hardsetlocale;
        } elseif (isset($_SESSION['language'])) {
            $hardsetlocale = $_SESSION['language'];
            $lang_converted[] = $hardsetlocale;
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
            foreach ($langs as $lang) {
                $result = [];
                preg_match("/(.*);+.*/", $lang, $result);
                $lang_converted[] = (isset($result[1]) && $result[1] ? $result[1] : $lang);
            }
        }
        // always add configured locale as last resort
        $defaultlocale = CONFIG['APPEARANCE']['defaultlocale'];
        $lang_converted[] = CONFIG['LANGUAGES'][$defaultlocale]['locale'];
        $lang_index = $defaultlocale;

        setlocale(LC_ALL, 0);

        // initialise this variabe (code analysers complain that $lang_converted
        // could be empty
        $thelang = CONFIG['LANGUAGES'][$defaultlocale]['locale'];
        foreach ($lang_converted as $try_lang) {
            // madness! setlocale is completely unflexible. If $try_lang is "en"
            // it will fail, because it only knows en_US, en_GB a.s.o.
            // we need to map stuff manually
            $thelang = $try_lang;

            foreach (CONFIG['LANGUAGES'] as $language => $value) {
                if (preg_match("/^" . $language . ".*/", $try_lang)) {
                    $thelang = $value['locale'];
                    $lang_index = $language;
                }
            }

            if (setlocale(LC_ALL, $thelang)) {
                break;
            }
        }
        putenv("LC_ALL=" . $thelang);
        $loggerInstance = new Logging();
        $loggerInstance->debug(4, "selected lang:$lang_index:$thelang\n");
        $loggerInstance->debug(4, print_r($lang_converted, true));
        return([$lang_index, $thelang]);
    }

    /**
     * gets the language setting in CAT
     */
    static public function get_lang() {
        if (self::$LANG === '') {
            list(self::$LANG, ) = self::set_lang();
        }
        return self::$LANG;
    }

    /**
     * Prepares a list of countries known to the CAT.
     * 
     * @param int $activeOnly is set and nonzero will cause that only countries with some institutions underneath will be listed
     * @return array Array indexed by (uppercase) lang codes and sorted according to the current locale
     */
    public function printCountryList($activeOnly = 0) {
        $olddomain = $this->set_locale("core");
        $handle = DBConnection::handle(CAT::$DB_TYPE);
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
        $this->set_locale($olddomain);
        return($returnArray);
    }

    /**
     * language display name for the language set by the constructor
     */
    public static $locale;

}
