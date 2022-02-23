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
 * 
 * 
 *  This is the definition of the CAT class
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */

namespace core\common;

/**
 * This class maintains state of the selected language and can set the language.
 */
class Language
{

    /**
     * the current language
     * 
     * @var string
     */
    private $LANG = '';

    /**
     * language display name for the language set by the constructor
     * 
     * @var string
     */
    public $locale;

    /**
     *  Constructor sets the language by calling set_lang 
     *  and stores language settings in object properties
     *  additionally it also sets static variables $laing_index and $root
     */
    public function __construct()
    {
        $language = $this->setLang();
        $this->LANG = $language[0];
        $this->locale = $language[1];
    }

    /**
     * Sets the gettext domain
     *
     * @param string $domain the text domain
     * @return string previous seting so that you can restore it later
     */
    public function setTextDomain($domain)
    {
        $loggerInstance = new \core\common\Logging();
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
     * @param int $hardSetLang - this is currently not used but will allow to force lang setting if this was ever required
     * @return array the language that was set
     */
    private function setLang($hardSetLang = 0)
    {
        // $langConverted will contain candidates for the language setting in the order
        // of prefference
        $langConverted = [];
        if ($hardSetLang !== 0) {
            $langConverted[] = $hardSetLang;
        }
        if (!empty($_REQUEST['lang'])) {
            $recoverLang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'lang', FILTER_SANITIZE_STRING);
            $langConverted[] = $recoverLang;
        }
        \core\CAT::sessionStart();
        if (!empty($_SESSION['language'])) {
            $langConverted[] = $_SESSION['language'];
        }
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(",", filter_input(INPUT_SERVER, "HTTP_ACCEPT_LANGUAGE", FILTER_SANITIZE_STRING));
            foreach ($langs as $lang) {
                $result = [];
                preg_match("/(.*);+.*/", $lang, $result);
                $langConverted[] = (empty($result[1]) ? $lang : $result[1]);
            }
        }
        $langIndex = \config\Master::APPEARANCE['defaultlocale'];
        $theLocale = \config\Master::LANGUAGES[$langIndex]['locale'];
        // always add configured default language as the last resort
        $langConverted[] = $langIndex;
        setlocale(LC_ALL, 0);
        foreach ($langConverted as $tryLang) {
            // madness! setlocale is completely unflexible. If $tryLang is "en"
            // it will fail, because it only knows en_US, en_GB a.s.o.
            // we need to map stuff manually
            $localeTmp = FALSE;

            // check if this language is supported by the CAT config
            foreach (\config\Master::LANGUAGES as $language => $value) {
                if (preg_match("/^" . $language . ".*/", $tryLang)) {
                    $localeTmp = $value['locale'];
                    $langIndex = $language; // ???
                    break;
                }
            }
            // make sure that the selected locale is actually instlled on this system
            // normally this should not be needed, but it is a safeguard agains misconfiguration
            if ($localeTmp) {
                if (setlocale(LC_ALL, $localeTmp)) {
                    $theLocale = $localeTmp;
                    break;
                }
            }
        }
        putenv("LC_ALL=" . $theLocale);
        $_SESSION['language'] = $langIndex;
        $loggerInstance = new \core\common\Logging();
        $loggerInstance->debug(4, "selected lang:$langIndex:$theLocale\n");
        $loggerInstance->debug(4, print_r($langConverted, true));
        return([$langIndex, $theLocale]);
    }

    /**
     * gets the language setting in CAT
     * 
     * @return string
     */
    public function getLang()
    {
        return $this->LANG;
    }

    /**
     * pick a proper value for a given language
     * @param array $valueArray an array of (locale,content) records
     * @return string localised value corresponding to the chosen
     * locale or to the defalut locale C if a better mach was not available
     */
    public function getLocalisedValue($valueArray)
    {
        $loggerInstance = new \core\common\Logging();
        $out = 0;
        if (count($valueArray) > 0) {
            $returnValue = [];
            foreach ($valueArray as $val) {
                $returnValue[$val["lang"]] = $val['value'];
            }
            $out = $returnValue[$this->LANG] ?? $returnValue['C'] ?? array_shift($returnValue);
        }
        $loggerInstance->debug(4, "getLocalisedValue:$this->LANG:$out\n");
        return $out;
    }
    
    /**
     * Return all available translations of a given message
     * 
     * @param string $message
     * @param string $domain
     * @return array indexed by language indexes
     */
    public function getAllTranslations($message, $domain) {
        $currentLocale = setlocale(LC_ALL, 0);
        $allValues = [];    
        foreach (\config\Master::LANGUAGES as $lang => $onelanguage) {
            setlocale(LC_ALL, $onelanguage['locale']);
            $allValues[$lang] = dgettext($domain, $message);
        }
        setlocale(LC_ALL, $currentLocale);
        return $allValues;
    }
}