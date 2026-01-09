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
 *  This is the definition of the CAT class
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
/**
 * necessary includes
 */

namespace core;

/**
 * Define some variables which need to be globally accessible
 * and some general purpose methods
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
class CAT extends \core\common\Entity
{

    /**
     * which version is this?
     * even if we are unreleased, keep track of internal version-to-be
     * developers need to set this in code. The user-displayed string
     * is generated into $CAT_VERSION_STRING and $CAT_COPYRIGHT below
     */
    public const VERSION_MAJOR = 2;
    public const VERSION_MINOR = 2;
    public const VERSION_PATCH = 1;
    public const VERSION_EXTRA = "";
    private const RELEASE_VERSION = TRUE;
    private const USER_API_VERSION = 2;

    /**
     * trying to keep up with the name changes of copyright holder and consortia
     * updating those on *one* place should change display everywhere!
     */
    private const COPYRIGHT_HOLDER = "G&Eacute;ANT Association";
    private const COPYRIGHT_CONSORTIA = "the G&Eacute;ANT Projects funded by EU";
    private const COPYRIGHT_MIN_YEAR = 2011;
    private const COPYRIGHT_MAX_YEAR = 2025;

    /**
     * This is the user-displayed string; controlled by the four options above
     * It is generated in the constructor.
     * 
     * @var string
     */
    public $catVersionString;

    /**
     * The entire copyright line, generated in constructor
     * 
     * @var string
     */
    public $catCopyright;
    
    public $catVersion;
    
    public $catCopyrifhtAndLicense;

    /**
     * all known federation, in an array with ISO short name as an index, and localised version of the pretty-print name as value.
     * The static value is only filled with meaningful content after the first object has been instantiated. That is because it is not
     * possible to define static properties with function calls like _().
     * 
     * @var array of all known federations
     */
    public $knownFederations;

    /**
     * the default database to query in this class.
     */
    const DB_TYPE = "INST";

    /**
     *  Constructor sets the language by calling set_lang 
     *  and stores language settings in object properties
     */
    public function __construct()
    {
        parent::__construct();
        common\Entity::intoThePotatoes();

        $this->catVersionString = sprintf("Unreleased %s Git Revision"    , "<a href='https://github.com/GEANT/CAT/tree/master/Changes.md'>") . "</a>";

        if (CAT::RELEASE_VERSION) {
            $major = CAT::VERSION_MAJOR;
            $minor = CAT::VERSION_MINOR;
            $patch = CAT::VERSION_PATCH;
            $extra = CAT::VERSION_EXTRA;
            $temp_version = "CAT-$major.$minor.$patch";
//            $branch = "release_$major" . "_$minor";
            $branch = "v$major.$minor.$patch";
//            if (CAT::VERSION_PATCH != 0) {
//                $temp_version .= ".$patch";
//            }
            if (CAT::VERSION_EXTRA != "") {
                $temp_version .= "-$extra";
            }
            $this->catVersionString = sprintf("Release <a href='%s'>%s</a>", "https://github.com/GEANT/CAT/tree/" . $branch . "/Changes.md", $temp_version);

        }
        $product = \config\Master::APPEARANCE['productname'];
        $minYear = self::COPYRIGHT_MIN_YEAR;
        $maxYear = self::COPYRIGHT_MAX_YEAR;
        $holder = self::COPYRIGHT_HOLDER;
        $consortia = self::COPYRIGHT_CONSORTIA;
        $this->catCopyright = "$product - " . $this->catVersionString . " &copy; $minYear-$maxYear $holder<br/>on behalf of $consortia; and others <a href='copyright.php'>Full Copyright and Licenses</a>";
        $this->catCopyrifhtAndLicense = "&copy; $minYear-$maxYear $holder<br/>on behalf of $consortia; and others <a href='copyright.php'>Full Copyright and Licenses</a>";
        $this->catVersion = "$product<br>" . $this->catVersionString;

        /* Federations are created in DB with bootstrapFederation, and listed via listFederations
         */

        $this->knownFederations = [
            'AD' => ['name'=>_("Andorra")],
            'AT' => ['name'=>_("Austria")],
            'BE' => ['name'=>_("Belgium")],
            'BG' => ['name'=>_("Bulgaria")],
            'CY' => ['name'=>_("Cyprus")],
            'CZ' => ['name'=>_("Czech Republic")],
            'DK' => ['name'=>_("Denmark")],
            'EE' => ['name'=>_("Estonia")],
            'FI' => ['name'=>_("Finland")],
            'FR' => ['name'=>_("France")],
            'DE' => ['name'=>_("Germany")],
            'GR' => ['name'=>_("Greece")],
            'HR' => ['name'=>_("Croatia")],
            'IE' => ['name'=>_("Ireland")],
            'IS' => ['name'=>_("Iceland")],
            'IT' => ['name'=>_("Italy")],
            'HU' => ['name'=>_("Hungary")],
            'KS' => ['name'=>_("Kosovo")],
            'LU' => ['name'=>_("Luxembourg")],
            'LV' => ['name'=>_("Latvia")],
            'LT' => ['name'=>_("Lithuania")],
            'MK' => ['name'=>_("Macedonia")],
            'RS' => ['name'=>_("Serbia")],
            'NL' => ['name'=>_("Netherlands")],
            'NO' => ['name'=>_("Norway")],
            'PL' => ['name'=>_("Poland")],
            'PT' => ['name'=>_("Portugal")],
            'RO' => ['name'=>_("Romania")],
            'SI' => ['name'=>_("Slovenia")],
            'ES' => ['name'=>_("Spain")],
            'SE' => ['name'=>_("Sweden")],
            'SK' => ['name'=>_("Slovakia")],
            'CH' => ['name'=>_("Switzerland")],
            'TR' => ['name'=>_("Turkey")],
            'UK' => ['name'=>_("United Kingdom"), 'code'=>'GB'],
            'TEST' => ['name'=>'TEST Country'],
            'AU' => ['name'=>_("Australia")],
            'CA' => ['name'=>_("Canada")],
            'IL' => ['name'=>_("Israel")],
            'JP' => ['name'=>_("Japan")],
            'NZ' => ['name'=>_("New Zealand")],
            'US' => ['name'=>_("U.S.A.")],
            'BR' => ['name'=>_("Brazil")],
            'CL' => ['name'=>_("Chile")],
            'PE' => ['name'=>_("Peru")],
            'VE' => ['name'=>_("Venezuela")],
            'DEFAULT' => ['name'=>_("Default")],
            'AM' => ['name'=>_("Armenia")],
            'AZ' => ['name'=>_("Azerbaijan")],
//            'BY' => ['name'=>_("Belarus")],
            'EC' => ['name'=>_("Ecuador")],
            'HK' => ['name'=>_("Hong Kong")],
            'KE' => ['name'=>_("Kenya")],
            'KG' => ['name'=>_("Kyrgyzstan")],
            'KR' => ['name'=>_("Korea")],
            'KZ' => ['name'=>_("Kazakhstan")],
            'MA' => ['name'=>_("Morocco")],
            'MD' => ['name'=>_("Moldova")],
            'ME' => ['name'=>_("Montenegro")],
            'MO' => ['name'=>_("Macau")],
            'MT' => ['name'=>_("Malta")],
//            'RU' => ['name'=>_("Russia")],
            'SG' => ['name'=>_("Singapore")],
            'TH' => ['name'=>_("Thailand")],
            'TW' => ['name'=>_("Taiwan")],
            'ZA' => ['name'=>_("South Africa")],
            'AF' => ['name'=>'Afghanistan'],
            'AL' => ['name'=>'Albania'],
            'DZ' => ['name'=>'Algeria'],
            'AS' => ['name'=>'American Samoa'],
            'AO' => ['name'=>'Angola'],
            'AI' => ['name'=>'Anguilla'],
            'AQ' => ['name'=>'Antarctica'],
            'AG' => ['name'=>'Antigua And Barbuda'],
            'AR' => ['name'=>'Argentina'],
            'AW' => ['name'=>'Aruba'],
            'BS' => ['name'=>'Bahamas], The'],
            'BH' => ['name'=>'Bahrain'],
            'BD' => ['name'=>'Bangladesh'],
            'BB' => ['name'=>'Barbados'],
            'BZ' => ['name'=>'Belize'],
            'BJ' => ['name'=>'Benin'],
            'BM' => ['name'=>'Bermuda'],
            'BT' => ['name'=>'Bhutan'],
            'BO' => ['name'=>'Bolivia'],
            'BA' => ['name'=>'Bosnia And Herzegovina'],
            'BW' => ['name'=>'Botswana'],
            'BV' => ['name'=>'Bouvet Island'],
            'IO' => ['name'=>'British Indian Ocean Territory'],
            'BN' => ['name'=>'Brunei'],
            'BF' => ['name'=>'Burkina Faso'],
            'MM' => ['name'=>'Burma'],
            'BI' => ['name'=>'Burundi'],
            'KH' => ['name'=>'Cambodia'],
            'CM' => ['name'=>'Cameroon'],
            'CV' => ['name'=>'Cape Verde'],
            'KY' => ['name'=>'Cayman Islands'],
            'CF' => ['name'=>'Central African Republic'],
            'TD' => ['name'=>'Chad'],
            'CN' => ['name'=>'China'],
            'CX' => ['name'=>'Christmas Island'],
            'CC' => ['name'=>'Cocos (keeling) Islands'],
            'CO' => ['name'=>'Colombia'],
            'KM' => ['name'=>'Comoros'],
            'CG' => ['name'=>'Congo (brazzaville) '],
            'CD' => ['name'=>'Congo (kinshasa)'],
            'CK' => ['name'=>'Cook Islands'],
            'CR' => ['name'=>'Costa Rica'],
            'CI' => ['name'=>'CÃ”te Dâ€™ivoire'],
            'CU' => ['name'=>'Cuba'],
            'CW' => ['name'=>'CuraÃ‡ao'],
            'DJ' => ['name'=>'Djibouti'],
            'DM' => ['name'=>'Dominica'],
            'DO' => ['name'=>'Dominican Republic'],
            'EG' => ['name'=>'Egypt'],
            'SV' => ['name'=>'El Salvador'],
            'GQ' => ['name'=>'Equatorial Guinea'],
            'ER' => ['name'=>'Eritrea'],
            'ET' => ['name'=>'Ethiopia'],
            'FK' => ['name'=>'Falkland Islands (islas Malvinas)'],
            'FO' => ['name'=>'Faroe Islands'],
            'FJ' => ['name'=>'Fiji'],
            'GF' => ['name'=>'French Guiana'],
            'PF' => ['name'=>'French Polynesia'],
            'TF' => ['name'=>'French Southern And Antarctic Lands'],
            'GA' => ['name'=>'Gabon'],
            'GM' => ['name'=>'Gambia], The'],
            'GE' => ['name'=>'Georgia'],
            'GEANT' => ['name'=>'The GEANT country'],
            'GH' => ['name'=>'Ghana'],
            'GI' => ['name'=>'Gibraltar'],
            'GL' => ['name'=>'Greenland'],
            'GD' => ['name'=>'Grenada'],
            'GP' => ['name'=>'Guadeloupe'],
            'GU' => ['name'=>'Guam'],
            'GT' => ['name'=>'Guatemala'],
            'GG' => ['name'=>'Guernsey'],
            'GN' => ['name'=>'Guinea'],
            'GW' => ['name'=>'Guinea-bissau'],
            'GY' => ['name'=>'Guyana'],
            'HT' => ['name'=>'Haiti'],
            'HM' => ['name'=>'Heard Island And Mcdonald Islands'],
            'HN' => ['name'=>'Honduras'],
            'IN' => ['name'=>'India'],
            'ID' => ['name'=>'Indonesia'],
            'IR' => ['name'=>'Iran'],
            'IQ' => ['name'=>'Iraq'],
            'IM' => ['name'=>'Isle Of Man'],
            'JM' => ['name'=>'Jamaica'],
            'JE' => ['name'=>'Jersey'],
            'JO' => ['name'=>'Jordan'],
            'KI' => ['name'=>'Kiribati'],
            'KP' => ['name'=>'Korea], North'],
            'KW' => ['name'=>'Kuwait'],
            'LA' => ['name'=>'Laos'],
            'LB' => ['name'=>'Lebanon'],
            'LS' => ['name'=>'Lesotho'],
            'LR' => ['name'=>'Liberia'],
            'LY' => ['name'=>'Libya'],
            'LI' => ['name'=>'Liechtenstein'],
            'MG' => ['name'=>'Madagascar'],
            'MW' => ['name'=>'Malawi'],
            'MY' => ['name'=>'Malaysia'],
            'MV' => ['name'=>'Maldives'],
            'ML' => ['name'=>'Mali'],
            'MH' => ['name'=>'Marshall Islands'],
            'MQ' => ['name'=>'Martinique'],
            'MR' => ['name'=>'Mauritania'],
            'MU' => ['name'=>'Mauritius'],
            'YT' => ['name'=>'Mayotte'],
            'MX' => ['name'=>'Mexico'],
            'FM' => ['name'=>'Micronesia, Federated States Of'],
            'MC' => ['name'=>'Monaco'],
            'MN' => ['name'=>'Mongolia'],
            'MS' => ['name'=>'Montserrat'],
            'MZ' => ['name'=>'Mozambique'],
            'NA' => ['name'=>'Namibia'],
            'NR' => ['name'=>'Nauru'],
            'NP' => ['name'=>'Nepal'],
            'NC' => ['name'=>'New Caledonia'],
            'NI' => ['name'=>'Nicaragua'],
            'NE' => ['name'=>'Niger'],
            'NG' => ['name'=>'Nigeria'],
            'NU' => ['name'=>'Niue'],
            'NF' => ['name'=>'Norfolk Island'],
            'MP' => ['name'=>'Northern Mariana Islands'],
            'OM' => ['name'=>'Oman'],
            'PK' => ['name'=>'Pakistan'],
            'PW' => ['name'=>'Palau'],
            'PA' => ['name'=>'Panama'],
            'PG' => ['name'=>'Papua New Guinea'],
            'PY' => ['name'=>'Paraguay'],
            'PH' => ['name'=>'Philippines'],
            'PN' => ['name'=>'Pitcairn Islands'],
            'PR' => ['name'=>'Puerto Rico'],
            'QA' => ['name'=>'Qatar'],
            'RE' => ['name'=>'Reunion'],
            'RW' => ['name'=>'Rwanda'],
            'BL' => ['name'=>'Saint Barthelemy'],
            'SH' => ['name'=>'Saint Helena, Ascension, And Tristan Da Cunha'],
            'KN' => ['name'=>'Saint Kitts And Nevis'],
            'LC' => ['name'=>'Saint Lucia'],
            'MF' => ['name'=>'Saint Martin'],
            'PM' => ['name'=>'Saint Pierre And Miquelon'],
            'VC' => ['name'=>'Saint Vincent And The Grenadines'],
            'WS' => ['name'=>'Samoa'],
            'SM' => ['name'=>'San Marino'],
            'ST' => ['name'=>'Sao Tome And Principe'],
            'SA' => ['name'=>'Saudi Arabia'],
            'SN' => ['name'=>'Senegal'],
            'SC' => ['name'=>'Seychelles'],
            'SL' => ['name'=>'Sierra Leone'],
            'SX' => ['name'=>'Sint Maarten'],
            'SB' => ['name'=>'Solomon Islands'],
            'SO' => ['name'=>'Somalia'],
            'GS' => ['name'=>'South Georgia And South Sandwich Islands'],
            'SS' => ['name'=>'South Sudan'],
            'LK' => ['name'=>'Sri Lanka'],
            'SD' => ['name'=>'Sudan'],
            'SR' => ['name'=>'Suriname'],
            'SZ' => ['name'=>'Swaziland'],
            'SY' => ['name'=>'Syria'],
            'TJ' => ['name'=>'Tajikistan'],
            'TZ' => ['name'=>'Tanzania'],
            'TL' => ['name'=>'Timor-leste'],
            'TG' => ['name'=>'Togo'],
            'TK' => ['name'=>'Tokelau'],
            'TO' => ['name'=>'Tonga'],
            'TT' => ['name'=>'Trinidad And Tobago'],
            'TN' => ['name'=>'Tunisia'],
            'TM' => ['name'=>'Turkmenistan'],
            'TC' => ['name'=>'Turks And Caicos Islands'],
            'TV' => ['name'=>'Tuvalu'],
            'UG' => ['name'=>'Uganda'],
            'UA' => ['name'=>'Ukraine'],
            'AE' => ['name'=>'United Arab Emirates'],
            'GB' => ['name'=>_('United Kingdom')],
            'UY' => ['name'=>'Uruguay'],
            'UZ' => ['name'=>'Uzbekistan'],
            'VU' => ['name'=>'Vanuatu'],
            'VA' => ['name'=>'Vatican City'],
            'VN' => ['name'=>'Vietnam'],
            'VG' => ['name'=>'Virgin Islands, British'],
            'VI' => ['name'=>'Virgin Islands, United States '],
            'WF' => ['name'=>'Wallis And Futuna'],
            'EH' => ['name'=>'Western Sahara'],
            'YE' => ['name'=>'Yemen'],
            'ZM' => ['name'=>'Zambia'],
            'ZW' => ['name'=>'Zimbabwe'],
        ];

        common\Entity::outOfThePotatoes();
    }

    /**
     * Calculates the number of IdPs overall in the system
     * 
     * @param string $level completeness level of IdPs that are to be taken into consideration for counting
     * @return int
     */
    public function totalIdPs($level)
    {
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
        // SELECTs never return a booleans, always an object
        $dbresult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $idpcount);
        return $dbresult->instcount;
    }

    /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * 
     * @param int    $activeOnly if set to non-zero will cause listing of only those institutions which have some valid profiles defined.
     * @param string $country    if set, only list IdPs in a specific country
     * @return array the list of identity providers
     *
     */
    public function listAllIdentityProviders($activeOnly = 0, $country = "")
    {
        common\Entity::intoThePotatoes();
        $handle = DBConnection::handle("INST");
        $handle->exec("SET SESSION group_concat_max_len=10000");
        $query = "SELECT distinct institution.inst_id AS inst_id, institution.country AS country,
                     group_concat(concat_ws('===',institution_option.option_name,LEFT(institution_option.option_value,200), institution_option.option_lang) separator '---') AS options
                     FROM institution ";
        if ($activeOnly == 1) {
            $query .= "JOIN v_active_inst ON institution.inst_id = v_active_inst.inst_id ";
        }
        $query .= "JOIN institution_option ON institution.inst_id = institution_option.institution_id ";
        $query .= "WHERE (institution_option.option_name = 'general:instname' 
                          OR institution_option.option_name = 'general:geo_coordinates'
                          OR institution_option.option_name = 'general:logo_file') ";

        $query .= ($country != "" ? "AND institution.country = ? " : "");

        $query .= "GROUP BY institution.inst_id ORDER BY inst_id";

        $allIDPs = ($country != "" ? $handle->exec($query, "s", $country) : $handle->exec($query));
        $returnarray = [];
        // SELECTs never return a booleans, always an object
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allIDPs)) {
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
                        $at1 = json_decode($opt[1], true);
                        $geo[] = $at1;
                        break;
                    case 'general:instname':
                        $names[] = [
                            'lang' => $opt[2],
                            'value' => $opt[1]
                        ];
                        break;
                    default:
                        break;
                }
            }

            $name = _("Unnamed Entity");
            if (count($names) != 0) {
                $langObject = new \core\common\Language();
                $name = $langObject->getLocalisedValue($names);
            }
            $oneInstitutionResult['title'] = $name;
            if (count($geo) > 0) {
                $oneInstitutionResult['geo'] = $geo;
            }
            $returnarray[] = $oneInstitutionResult;
        }
        common\Entity::outOfThePotatoes();
        return $returnarray;
    }

    /**
     * Prepares a list of countries known to the CAT.
     * 
     * @param int $activeOnly is set and nonzero will cause that only countries with some institutions underneath will be listed
     * @return array Array indexed by (uppercase) lang codes and sorted according to the current locale
     */
    public function printCountryList($activeOnly = 0)
    {
        $olddomain = $this->languageInstance->setTextDomain("core");
        $handle = DBConnection::handle(CAT::DB_TYPE);
        $returnArray = []; // in if -> the while might never be executed, so initialise
        if ($activeOnly) {
            $federations = $handle->exec("SELECT DISTINCT UPPER(institution.country) AS country FROM institution JOIN profile
                          ON institution.inst_id = profile.inst_id WHERE profile.showtime = 1 ORDER BY country");
            // SELECT never returns a boolean, always a mysqli_object
            while ($activeFederations = mysqli_fetch_object(/** @scrutinizer ignore-type */ $federations)) {
                $fedIdentifier = $activeFederations->country; // UPPER() has capitalised this for us
                $returnArray[$fedIdentifier] = isset($this->knownFederations[$fedIdentifier]) ? $this->knownFederations[$fedIdentifier]['name'] : $fedIdentifier;
            }
        } else {
            foreach ($this->knownFederations as $fedIdentifier => $value) {
                $returnArray[$fedIdentifier] = $value['name'];
            }
        }
        asort($returnArray, SORT_LOCALE_STRING);
        $this->languageInstance->setTextDomain($olddomain);
        return($returnArray);
    }

    /**
     * get additional details about an institution from the EXTERNAL customer DB
     * (if any; for eduroam, this would be the official eduroam database)
     * 
     * @param string $externalId the ID of the institution in the external DB
     * @param string $ROid - the ID of the federation in the external DB - we assume that it is always of the form strtoupper($country_code).'01'
     * @param string $realm      the function can also try to find an inst by its realm in the external DB
     * @return array a list of institutions, ideally with only one member
     * @throws \Exception
     */
    public function getExternalDBEntityDetails($externalId, $ROid, $realm = NULL)
    {
        $list = [];
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $scanforrealm = "";
            if ($realm !== NULL) {
                $scanforrealm = "OR inst_realm LIKE '%$realm%'";
            }
            $externalHandle = DBConnection::handle("EXTERNAL");
            $infoList = $externalHandle->exec("SELECT name AS collapsed_name, inst_realm as realmlist, contact AS collapsed_contact, country, type FROM view_active_institution WHERE instid = '$externalId' AND ROid = '$ROid' $scanforrealm");
            // split names and contacts into proper pairs
            // SELECT never returns a boolean, always a mysqli_object
            while ($externalEntityQuery = mysqli_fetch_object(/** @scrutinizer ignore-type */ $infoList)) {
                $list['names'] = \core\ExternalEduroamDBData::dissectCollapsedInstitutionNames($externalEntityQuery->collapsed_name)['perlang'];
                $contacts = \core\ExternalEduroamDBData::dissectCollapsedContacts($externalEntityQuery->collapsed_contact);
                foreach ($contacts as $contact) {
                    $list['admins'][] = ["email" => $contact['mail']];
                }
                $list['country'] = strtoupper($externalEntityQuery->country);
                $list['realmlist'] = $externalEntityQuery->realmlist;
                switch ($externalEntityQuery->type) {
                    case ExternalEduroamDBData::TYPE_IDP:
                        $list['type'] = IdP::TYPE_IDP;
                        break;
                    case ExternalEduroamDBData::TYPE_SP:
                        $list['type'] = IdP::TYPE_SP;
                        break;
                    case ExternalEduroamDBData::TYPE_IDPSP:
                        $list['type'] = IdP::TYPE_IDPSP;
                        break;
                    default:
                        throw new \Exception("Eduroam DB returned a participant type we do not know.");
                }
            }
        }
        return $list;
    }

    /**
     * the list of countries as per external DB
     * @return array the list
     */
    public function getExternalCountriesList()
    {
        $olddomain = $this->languageInstance->setTextDomain("core");
        $returnArray = []; // in if -> the while might never be executed, so initialise
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $handle = DBConnection::handle("EXTERNAL");
            $timeStart = microtime(true);
            $federations = $handle->exec("SELECT DISTINCT UPPER(country) AS country FROM view_active_institution ORDER BY country");
            $timeEnd = microtime(true);
            $timeElapsed = $timeEnd - $timeStart;
            // the query yielded a mysqli_result because it's a SELECT, this never gives back a boolean
            while ($eduroamFederations = mysqli_fetch_object(/** @scrutinizer ignore-type */ $federations)) {
                $fedIdentifier = $eduroamFederations->country;
                $returnArray[$fedIdentifier] = isset($this->knownFederations[$fedIdentifier]) ? $this->knownFederations[$fedIdentifier]['name'] : $fedIdentifier;
            }
            asort($returnArray, SORT_LOCALE_STRING);
            $returnArray['time'] = $timeElapsed;
        }
        $this->languageInstance->setTextDomain($olddomain);
        return($returnArray);
    }

    /**
     * the (HTML) root path of the CAT deployment
     * 
     * @return string
     */
    public static function getRootUrlPath()
    {
        return substr(\config\Master::PATHS['cat_base_url'], -1) === '/' ? substr(\config\Master::PATHS['cat_base_url'], 0, -1) : \config\Master::PATHS['cat_base_url'];
    }

    /**
     * takes care of starting our session
     * 
     * @return void
     */
    public static function sessionStart()
    {
        $loggerInstance = new \core\common\Logging();
        if (session_status() != PHP_SESSION_ACTIVE) {
            $loggerInstance->debug(4, "Session start\n");
            session_name("CAT");
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => "/",
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => (isset($_SERVER['HTTPS']) ? TRUE : FALSE),
                'httponly' => false,
                'samesite' => 'strict'
        ]);               
            session_start();
        }
    }

    /**
     * determines which external DB to use, and returns an object instance
     * 
     * @return \core\ExternalEduroamDBData|\core\ExternalNothing
     */
    public static function determineExternalConnection()
    {
        if (\config\ConfAssistant::CONSORTIUM['name'] == "eduroam" && isset(\config\ConfAssistant::CONSORTIUM['deployment-voodoo']) && \config\ConfAssistant::CONSORTIUM['deployment-voodoo'] == "Operations Team") {
            return new ExternalEduroamDBData();
        }
        return new ExternalNothing();
    }

    public function getSuperglueZone()
    {
        $externalDB = CAT::determineExternalConnection();
        return $externalDB->listExternalRealms();
    }

    public static function hostedSPEnabled() {
        return \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_MSP'] === 'LOCAL';    
    }
    
    public static function hostedIDPEnabled() {
        return \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_MIDP'] === 'LOCAL';
    }
    
    public static function radiusProfilesEnabled() {
        return \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] === 'LOCAL';        
    }
    
    public static function hostedServicesEnabled() {
        return \core\CAT::hostedIDPEnabled() || \core\CAT::hostedSPEnabled();
    }
    
    public static function diagnosticsEnabled() {
        return \config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSYCS'] === 'LOCAL';
    }
}
