<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
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
        $A = $this->set_lang();
        self::$locale = $A[1];
        $a = __DIR__;
        self::$root = dirname($a);


        if (CAT::$RELEASE_VERSION) {
            $temp_version = "CAT-".CAT::$VERSION_MAJOR.".".CAT::$VERSION_MINOR;
            if (CAT::$VERSION_PATCH != 0)
                    $temp_version .= ".".CAT::$VERSION_PATCH;
            if (CAT::$VERSION_EXTRA != "")
                $temp_version .= "-".CAT::$VERSION_EXTRA;
            CAT::$VERSION = sprintf(_("Release %s"), $temp_version );
        }
        else
            CAT::$VERSION = _("Unreleased SVN Revision");
    }

    /** 
     * 
     */
    public function totalIdPs($level) {
        switch ($level) {
            case "ALL":
                $idpcount = DBConnection::exec(CAT::$DB_TYPE, "SELECT COUNT(inst_id) AS instcount FROM institution");
                break;
            case "VALIDPROFILE":
                $idpcount = DBConnection::exec(CAT::$DB_TYPE, "SELECT COUNT(DISTINCT institution.inst_id) AS instcount FROM institution,profile WHERE institution.inst_id = profile.inst_id AND profile.sufficient_config = 1");
                break;
            case "PUBLICPROFILE":
                $idpcount = DBConnection::exec(CAT::$DB_TYPE, "SELECT COUNT(DISTINCT institution.inst_id) AS instcount FROM institution,profile WHERE institution.inst_id = profile.inst_id AND profile.showtime = 1");
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
        $olddomain = textdomain(NULL);
        debug(4, "set_locale($domain)\n");
        debug(4, CAT::$root . "\n");
        textdomain($domain);
        bindtextdomain($domain, CAT::$root . "/translation/");
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
                preg_match("/(.*);+.*/", $lang, $result);
                $lang_converted[] = (isset($result[1]) && $result[1] ? $result[1] : $lang);
            }
        };
        // always add configured locale as last resort
        $defaultlocale = Config::$APPEARANCE['defaultlocale'];
        $lang_converted[] = Config::$LANGUAGES[$defaultlocale]['locale'];
        $lang_index = $defaultlocale;

        setlocale(LC_ALL, 0);

        // initialise this variabe (code analysers complain that $lang_converted
        // could be empty
        $thelang = Config::$LANGUAGES[$defaultlocale]['locale'];
        foreach ($lang_converted as $try_lang) {
            // madness! setlocale is completely unflexible. If $try_lang is "en"
            // it will fail, because it only knows en_US, en_GB a.s.o.
            // we need to map stuff manually
            $thelang = $try_lang;

            foreach (Config::$LANGUAGES as $language => $value)
                if (preg_match("/^" . $language . ".*/", $try_lang)) {
                    $thelang = $value['locale'];
                    $lang_index = $language;
                }

            if (setlocale(LC_ALL, $thelang))
                break;
        }
        putenv("LC_ALL=" . $thelang);
        debug(4, "selected lang:$lang_index:$thelang\n");
        debug(4, $lang_converted);
        return([$lang_index, $thelang]);
    }

    /**
     * gets the language setting in CAT
     */
    static public function get_lang() {
       if(self::$LANG === '')
         list(self::$LANG, ) = self::set_lang();
       return self::$LANG;
    }

    /**
     * Prepares a list of countries known to the CAT.
     * 
     * @param int $active_only is set and nonzero will cause that only countries with some institutions underneath will be listed
     * @return array Array indexed by (uppercase) lang codes and sorted according to the current locale
     */
    public function printCountryList($active_only = 0) {
        $olddomain = $this->set_locale("core");
        if ($active_only) {
            $federations = DBConnection::exec(CAT::$DB_TYPE, "SELECT DISTINCT LOWER(institution.country) AS country FROM institution JOIN profile
                          ON institution.inst_id = profile.inst_id WHERE profile.showtime = 1 ORDER BY country");
            while ($a = mysqli_fetch_object($federations)) {
                $b = $a->country;
                $F = new Federation($b);
                $c = strtoupper($F->name);
                $C[$c] = isset(Federation::$federationList[$c]) ? Federation::$federationList[$c] : $c;
            }
        } else {
            new Federation;
            $C = Federation::$federationList;
        }
        asort($C, SORT_LOCALE_STRING);
        $this->set_locale($olddomain);
        return($C);
    }

    /**
     * Writes an audit log entry to the audit log file. These audits are semantic logs; they don't record every single modification
     * in the database, but provide a logical "who did what" overview. The exact modification SQL statements are logged
     * automatically with writeSQLAudit() instead. The log file path is configurable in _config.php.
     * 
     * @param string $user persistent identifier of the user who triggered the action
     * @param string $category type of modification, from the fixed vocabulary: "NEW", "OWN", "MOD", "DEL"
     * @param string $message message to log into the audit log
     * @return boolean TRUE if successful. Will terminate script execution on failure. 
     */
    public static function writeAudit($user, $category, $message) {
        switch ($category) {
            case "NEW": // created a new object
            case "OWN": // ownership changes
            case "MOD": // modified existing object
            case "DEL": // deleted an object
                ob_start();
                printf("%-015s",microtime(TRUE));
                print " ($category) ";
                print_r(" ".$user.": ".$message."\n");
                $output = ob_get_clean();
                if (Config::$PATHS['logdir']) {
                    $f = fopen(Config::$PATHS['logdir'] . "/audit-activity.log", "a");
                    fwrite($f, $output);
                    fclose($f);
                } else {
                    print $output;
                }

                return TRUE;
            default:
                throw new Exception("Unable to write to AUDIT file (requested data was $user, $category, $message!");
        }
    }

    /**
     * Write an audit log entry to the SQL log file. Every SQL statement which is not a simple SELECT one will be written
     * to the log file. The log file path is configurable in _config.php.
     * 
     * @param string $query the SQL query to be logged
     */
    public static function writeSQLAudit($query) {
        // clean up text to be in one line, with no extra spaces
        $logtext1 = preg_replace("/[\n\r]/", "", $query);
        $logtext = preg_replace("/ +/", " ", $logtext1);
        ob_start();
        printf("%-015s",microtime(TRUE));
        print(" ".$logtext."\n");
        $output = ob_get_clean();
        if (Config::$PATHS['logdir']) {
            $f = fopen(Config::$PATHS['logdir'] . "/audit-SQL.log", "a");
            fwrite($f, $output);
            fclose($f);
        } else {
            print $output;
        }
    }

    /**
     * stores the location of the root directory
     * @static string $root
     */
    public static $root;

    /**
     * language display name for the language set by the constructor
     */
    public static $locale;

}