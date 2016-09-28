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
require_once("Logging.php");
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
class Language {
    
    /**
     * 
     * @var string
     */
    private $LANG = '';
    
    /**
     * language display name for the language set by the constructor
     */
    public $locale;

    /**
     *  Constructor sets the language by calling set_lang 
     *  and stores language settings in object properties
     *  additionally it also sets static variables $laing_index and $root
     */
    public function __construct() {
        $language = $this->setLang();
        Language::$LANG = $language[0];
        Language::$locale = $language[1];
    }

    /**
     * Sets the gettext domain
     *
     * @param string $domain
     * @return string previous seting so that you can restore it later
     */
    public function setTextDomain($domain) {
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
    private function setLang($hardsetlang = 0) {
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
    public function getLang() {
        return Language::$LANG;
    }
}
