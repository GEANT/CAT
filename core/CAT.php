<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
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
/**
 * necessary includes
 */

namespace core;

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Define some variables which need to be globally accessible
 * and some general purpose methods
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
class CAT extends \core\common\Entity {

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

    /**
     * trying to keep up with the name changes of copyright holder and consortia
     * updating those on *one* place should change display everywhere!
     */
    const COPYRIGHT_HOLDER = "DANTE Ltd. and G&Eacute;ANT";
    const COPYRIGHT_CONSORTIA = "the GN3, GN3+, GN4-1 and GN4-2 consortia";
    const COPYRIGHT_MIN_YEAR = 2011;
    const COPYRIGHT_MAX_YEAR = 2017;

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
     * the custom displayable variant of the term 'federation'
     * @var string
     */
    public $nomenclature_fed;

    /**
     * the custom displayable variant of the term 'institution'
     * @var string
     */
    public $nomenclature_inst;

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
     *  additionally it also sets static variables $laing_index and $root
     */
    public function __construct() {
        parent::__construct();
        $olddomain = $this->languageInstance->setTextDomain("user");
        $this->CAT_VERSION_STRING = _("Unreleased <a href='https://github.com/GEANT/CAT/tree/master/Changes.md'>Git Revision</a>");
        if (CAT::RELEASE_VERSION) {
            $temp_version = "CAT-" . CAT::VERSION_MAJOR . "." . CAT::VERSION_MINOR;
            $branch = "release_" . CAT::VERSION_MAJOR . "_" . CAT::VERSION_MINOR;
            if (CAT::VERSION_PATCH != 0) {
                $temp_version .= "." . CAT::VERSION_PATCH;
            }
            if (CAT::VERSION_EXTRA != "") {
                $temp_version .= "-" . CAT::VERSION_EXTRA;
            }
            $this->CAT_VERSION_STRING = sprintf(_("Release <a href='%s'>%s</a>"), "https://github.com/GEANT/CAT/tree/" . $branch . "/Changes.md", $temp_version);
        }
        $this->CAT_COPYRIGHT = CONFIG['APPEARANCE']['productname'] . " - " . $this->CAT_VERSION_STRING . " &copy; " . CAT::COPYRIGHT_MIN_YEAR . "-" . CAT::COPYRIGHT_MAX_YEAR . " " . CAT::COPYRIGHT_HOLDER . "<br/>on behalf of " . CAT::COPYRIGHT_CONSORTIA . "; and others <a href='copyright.php'>Full Copyright and Licenses</a>";
        $this->languageInstance->setTextDomain($olddomain);

        /* Federations are created in DB with bootstrapFederation, and listed via listFederations
         */
        $oldlocale = $this->languageInstance->setTextDomain('core');

        // some config elements are displayable. We need some dummies to 
        // translate the common values for them. If a deployment chooses a 
        // different wording, no translation, sorry

        $dummy_NRO = _("National Roaming Operator");
        $dummy_inst1 = _("identity provider");
        $dummy_inst2 = _("organisation");
        $dummy_inst3 = _("Identity Provider");
        // and do something useless with the strings so that there's no "unused" complaint
        if (strlen($dummy_NRO . $dummy_inst1 . $dummy_inst2 . $dummy_inst3) < 0 ) {
            throw new Exception("Strings are usually not shorter than 0 characters. We've encountered a string blackhole.");
        }

        $this->nomenclature_fed = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation']);
        $this->nomenclature_inst = _(CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution']);

        $this->knownFederations = [
            'AD' => _("Andorra"),
            'AT' => _("Austria"),
            'BE' => _("Belgium"),
            'BG' => _("Bulgaria"),
            'CY' => _("Cyprus"),
            'CZ' => _("Czech Republic"),
            'DK' => _("Denmark"),
            'EE' => _("Estonia"),
            'FI' => _("Finland"),
            'FR' => _("France"),
            'DE' => _("Germany"),
            'GR' => _("Greece"),
            'HR' => _("Croatia"),
            'IE' => _("Ireland"),
            'IS' => _("Iceland"),
            'IT' => _("Italy"),
            'HU' => _("Hungary"),
            'LU' => _("Luxembourg"),
            'LV' => _("Latvia"),
            'LT' => _("Lithuania"),
            'MK' => _("Macedonia"),
            'RS' => _("Serbia"),
            'NL' => _("Netherlands"),
            'NO' => _("Norway"),
            'PL' => _("Poland"),
            'PT' => _("Portugal"),
            'RO' => _("Romania"),
            'SI' => _("Slovenia"),
            'ES' => _("Spain"),
            'SE' => _("Sweden"),
            'SK' => _("Slovakia"),
            'CH' => _("Switzerland"),
            'TR' => _("Turkey"),
            'UK' => _("United Kingdom"),
            'TEST' => 'TEST Country',
            'AU' => _("Australia"),
            'CA' => _("Canada"),
            'IL' => _("Israel"),
            'JP' => _("Japan"),
            'NZ' => _("New Zealand"),
            'US' => _("U.S.A."),
            'BR' => _("Brazil"),
            'CL' => _("Chile"),
            'PE' => _("Peru"),
            'VE' => _("Venezuela"),
            'DEFAULT' => _("Default"),
            'AM' => _("Armenia"),
            'AZ' => _("Azerbaijan"),
            'BY' => _("Belarus"),
            'EC' => _("Ecuador"),
            'HK' => _("Hong Kong"),
            'KE' => _("Kenya"),
            'KG' => _("Kyrgyzstan"),
            'KR' => _("Korea"),
            'KZ' => _("Kazakhstan"),
            'MA' => _("Morocco"),
            'MD' => _("Moldova"),
            'ME' => _("Montenegro"),
            'MO' => _("Macau"),
            'MT' => _("Malta"),
            'RU' => _("Russia"),
            'SG' => _("Singapore"),
            'TH' => _("Thailand"),
            'TW' => _("Taiwan"),
            'ZA' => _("South Africa"),
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AW' => 'Aruba',
            'BS' => 'Bahamas, The',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei',
            'BF' => 'Burkina Faso',
            'MM' => 'Burma',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo (brazzaville) ',
            'CD' => 'Congo (kinshasa)',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'CÃ”te Dâ€™ivoire',
            'CU' => 'Cuba',
            'CW' => 'CuraÃ‡ao',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (islas Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern And Antarctic Lands',
            'GA' => 'Gabon',
            'GM' => 'Gambia, The',
            'GE' => 'Georgia',
            'GEANT' => 'The GEANT country',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island And Mcdonald Islands',
            'HN' => 'Honduras',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IM' => 'Isle Of Man',
            'JM' => 'Jamaica',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KI' => 'Kiribati',
            'KP' => 'Korea, North',
            'KW' => 'Kuwait',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'MS' => 'Montserrat',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NC' => 'New Caledonia',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn Islands',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena, Ascension, And Tristan Da Cunha',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And The Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SX' => 'Sint Maarten',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'GS' => 'South Georgia And South Sandwich Islands',
            'SS' => 'South Sudan',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SZ' => 'Swaziland',
            'SY' => 'Syria',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TL' => 'Timor-leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VA' => 'Vatican City',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, United States ',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];

        $this->languageInstance->setTextDomain($oldlocale);
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
                     group_concat(concat_ws('===',institution_option.option_name,LEFT(institution_option.option_value,200), institution_option.option_lang) separator '---') AS options
                     FROM institution ";
        if ($activeOnly == 1) {
            $query .= "JOIN v_active_inst ON institution.inst_id = v_active_inst.inst_id ";
        }
        $query .= "JOIN institution_option ON institution.inst_id = institution_option.institution_id ";
        $query .= "WHERE (institution_option.option_name = 'general:instname' 
                          OR institution_option.option_name = 'general:geo_coordinates'
                          OR institution_option.option_name = 'general:logo_file') ";

        $query .= ($country ? "AND institution.country = ? " : "");

        $query .= "GROUP BY institution.inst_id ORDER BY inst_id";
        
        $allIDPs = ($country ? $handle->exec($query, "s", $country) : $handle->exec($query));
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
            $federations = $handle->exec("SELECT DISTINCT UPPER(institution.country) AS country FROM institution JOIN profile
                          ON institution.inst_id = profile.inst_id WHERE profile.showtime = 1 ORDER BY country");
            while ($activeFederations = mysqli_fetch_object($federations)) {
                $fedIdentifier = $activeFederations->country; // UPPER() has capitalised this for us
                $returnArray[$fedIdentifier] = isset($this->knownFederations[$fedIdentifier]) ? $this->knownFederations[$fedIdentifier] : $fedIdentifier;
            }
        } else {
            $returnArray = $this->knownFederations;
        }
        asort($returnArray, SORT_LOCALE_STRING);
        $this->languageInstance->setTextDomain($olddomain);
        return($returnArray);
    }

    public function getExternalDBEntityDetails($externalId, $realm = NULL) {
        $list = [];
        if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $scanforrealm = "";
            if ($realm !== NULL) {
                $scanforrealm = "OR inst_realm LIKE '%$realm%'";
            }
            $externalHandle = DBConnection::handle("EXTERNAL");
            $infoList = $externalHandle->exec("SELECT name AS collapsed_name, inst_realm as realmlist, contact AS collapsed_contact, country FROM view_active_idp_institution WHERE id_institution = $externalId $scanforrealm");
            // split names and contacts into proper pairs
            while ($externalEntityQuery = mysqli_fetch_object($infoList)) {
                $names = explode('#', $externalEntityQuery->collapsed_name);
                foreach ($names as $name) {
                    $perlang = explode(': ', $name, 2);
                    $list['names'][$perlang[0]] = $perlang[1];
                }
                $contacts = explode('#', $externalEntityQuery->collapsed_contact);
                foreach ($contacts as $contact) {
                    $email1 = explode('e: ', $contact);
                    $email2 = explode(',', $email1[1]);
                    $list['admins'][] = ["email" => $email2[0]];
                }
                $list['country'] = strtoupper($externalEntityQuery->country);
                $list['realmlist'] = $externalEntityQuery->realmlist;
            }
        }
        return $list;
    }

}
