<?php

/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the Federation class.
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
require_once("CAT.php");
require_once('IdP.php');
require_once('EntityWithDBProperties.php');

/**
 * This class represents an consortium federation.
 * It is semantically a country(!). Do not confuse this with a TLD; a federation
 * may span more than one TLD, and a TLD may be distributed across multiple federations.
 *
 * Example: a federation "fr" => "France" may also contain other TLDs which
 *              belong to France in spite of their different TLD
 * Example 2: Domains ending in .edu are present in multiple different
 *              federations
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class Federation extends EntityWithDBProperties {

    /**
     * all known federation, in an array with ISO short name as an index, and localised version of the pretty-print name as value.
     * The static value is only filled with meaningful content after the first object has been instantiated. That is because it is not
     * possible to define static properties with function calls like _().
     * 
     * @var array of all known federations
     */
    public static $FederationList = [];

    private static function downloadStatsCore($federationid = NULL) {
        $grossAdmin = 0;
        $grossUser = 0;

        $dataArray = [];

        foreach (Devices::listDevices() as $index => $device_array) {
            $query = "SELECT SUM(downloads_admin) AS admin, SUM(downloads_user) AS user FROM downloads, profile, institution WHERE device_id = '$index' AND downloads.profile_id = profile.profile_id AND profile.inst_id = institution.inst_id ";
            if ($federationid != NULL) {
                $query .= "AND institution.country = '" . $federationid . "'";
            }
            $number_query = DBConnection::exec("INST", $query);
            while ($queryResult = mysqli_fetch_object($number_query)) {
                $dataArray[$device_array['display']] = ["ADMIN" => ( $queryResult->admin === NULL ? "0" : $queryResult->admin), "USER" => ($queryResult->user === NULL ? "0" : $queryResult->user)];
                $grossAdmin = $grossAdmin + $queryResult->admin;
                $grossUser = $grossUser + $queryResult->user;
            }
        }
        $dataArray["TOTAL"] = ["ADMIN" => $grossAdmin, "USER" => $grossUser];
        return $dataArray;
    }

    public static function downloadStats($format, $federationid = NULL) {
        $data = Federation::downloadStatsCore($federationid);
        $retstring = "";
        
        switch ($format) {
            case "table":
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "<tr><td>$device</td><td>".$numbers['ADMIN']."</td><td>".$numbers['USER']."</td></tr>";
                }
                $retstring .= "<tr><td><strong>TOTAL</strong></td><td><strong>".$data['TOTAL']['ADMIN']."</strong></td><td><strong>".$data['TOTAL']['USER']."</strong></td></tr>";
                break;
            case "XML":
                $retstring .= "<federation id='" . ( $federationid == NULL ? "ALL" : $federationid ) . "' ts='".date("Y-m-d") . "T" . date("H:i:s")."'>\n";
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "  <device name='" . $device . "'>\n    <downloads group='admin'>".$numbers['ADMIN']."</downloads>\n    <downloads group='user'>".$numbers['USER']."</downloads>\n  </device>";
                }
                $retstring .= "<total>\n  <downloads group='admin'>".$data['TOTAL']['ADMIN']."</downloads>\n  <downloads group='user'>".$data['TOTAL']['USER']."</downloads>\n</total>\n";
                $retstring .= "</federation>";
                break;
            default:
               return false;
        }

        return $retstring;
    }

    /**
     *
     * Constructs a Federation object.
     *
     * @param string $fedname - textual representation of the Federation object
     *        Example: "lu" (for Luxembourg)
     */
    public function __construct($fedname = "") {

        // initialise the superclass variables

        $this->databaseType = "INST";
        $this->entityOptionTable = "federation_option";
        $this->entityIdColumn = "federation_id";
        $this->identifier = $fedname;
        $this->name = $fedname;
        $this->attributes = [];

        /* Federations are created in DB with bootstrapFederation, and listed via listFederations
         */
        $oldlocale = CAT::set_locale('core');

        Federation::$FederationList = [
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

        CAT::set_locale($oldlocale);

        $fedAttributes = DBConnection::exec($this->databaseType, "SELECT DISTINCT option_name,option_value, row FROM federation_option
              WHERE federation_id = '$this->name'  ORDER BY option_name");

        $optioninstance = Options::instance();

        while ($queryResult = mysqli_fetch_object($fedAttributes)) {
            $lang = "";
            // decode base64 for files (respecting multi-lang)
            $optinfo = $optioninstance->optionType($queryResult->option_name);
            $flag = $optinfo['flag'];

            if ($optinfo['type'] != "file") {
                $this->attributes[] = array("name" => $queryResult->option_name, "value" => $queryResult->option_value, "level" => "FED", "row" => $queryResult->row, "flag" => $flag);
            } else {
                
                $decodedAttribute = $this->decodeFileAttribute($queryResult->option_value);

                $this->attributes[] = array("name" => $queryResult->option_name, "value" => ($decodedAttribute['lang'] == "" ? $decodedAttribute['content'] : serialize($decodedAttribute)), "level" => "FED", "row" => $queryResult->row, "flag" => $flag);
            }
        }
        $this->attributes[] = array("name" => "internal:country",
            "value" => $this->name,
            "level" => "FED",
            "row" => 0,
            "flag" => NULL);
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $owner_id Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @return int identifier of the new IdP
     */
    public function newIdP($owner_id, $level, $mail) {
        DBConnection::exec($this->databaseType, "INSERT INTO institution (country) VALUES('$this->name')");
        $identifier = DBConnection::lastID($this->databaseType);
        if ($identifier == 0 || !CAT::writeAudit($owner_id, "NEW", "IdP $identifier")) {
            echo "<p>" . _("Could not create a new Institution!") . "</p>";
            exit(1);
        }
        // escape all strings
        $escapedOwnerId = DBConnection::escape_value($this->databaseType, $owner_id);
        $escapedLevel = DBConnection::escape_value($this->databaseType, $level);
        $escapedMail = DBConnection::escape_value($this->databaseType, $mail);

        if ($escapedOwnerId != "PENDING") {
            DBConnection::exec($this->databaseType, "INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES('$escapedOwnerId', $identifier, '$escapedLevel', '$escapedMail')");
        }
        return $identifier;
    }

    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $active_only if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($active_only = 0) {
        if ($active_only) {
            $allIDPs = DBConnection::exec($this->databaseType, "SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->name' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        } else {
            $allIDPs = DBConnection::exec($this->databaseType, "SELECT inst_id FROM institution
               WHERE country = '$this->name' ORDER BY inst_id");
        }

        $returnarray = [];
        while ($a = mysqli_fetch_object($allIDPs)) {
            $idp = new IdP($a->inst_id);
            $name = $idp->name;
            $A = ['entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp];
            $returnarray[$idp->identifier] = $A;
        }
        return $returnarray;
    }

    public function listFederationAdmins() {
        $returnarray = [];
        $query = "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = '" . strtoupper($this->name) . "'";
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = '" . strtolower($this->name) . "'";
        }
        
        $admins = DBConnection::exec("USER", $query);
        
        while ($a = mysqli_fetch_object($admins)) {
            $returnarray[] = $a->user_id;
        }
        return $returnarray;
    }

    public function listExternalEntities($unmapped_only) {
        $returnarray = [];
        $countrysuffix = "";

        if ($this->name != "") {
            $countrysuffix = " WHERE country = '" . strtolower($this->name) . "'";
        }
        
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $usedarray = [];
            $externals = DBConnection::exec("EXTERNAL", "SELECT id_institution AS id, country, inst_realm as realmlist, name AS collapsed_name, contact AS collapsed_contact 
                                                                                FROM view_active_idp_institution $countrysuffix");
            $already_used = DBConnection::exec($this->databaseType, "SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            $pending_invite = DBConnection::exec($this->databaseType, "SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
            while ($a = mysqli_fetch_object($already_used)) {
                $usedarray[] = $a->external_db_id;
            }
            while ($a = mysqli_fetch_object($pending_invite)) {
                if (!in_array($a->external_db_uniquehandle, $usedarray)) {
                    $usedarray[] = $a->external_db_uniquehandle;
                }
            }
            while ($a = mysqli_fetch_object($externals)) {
                if (($unmapped_only === TRUE) && (in_array($a->id, $usedarray))) {
                        continue;
                }
                $names = explode('#', $a->collapsed_name);
                // trim name list to current best language match
                $available_languages = [];
                foreach ($names as $name) {
                    $thislang = explode(': ', $name, 2);
                    $available_languages[$thislang[0]] = $thislang[1];
                }
                if (array_key_exists(CAT::get_lang(), $available_languages)) {
                    $thelangauge = $available_languages[CAT::get_lang()];
                } else if (array_key_exists("en", $available_languages)) {
                    $thelangauge = $available_languages["en"];
                } else { // whatever. Pick one out of the list
                    $thelangauge = array_pop($available_languages);
                }
                $contacts = explode('#', $a->collapsed_contact);


                $mailnames = "";
                foreach ($contacts as $contact) {
                    $matches = [];
                    preg_match("/^n: (.*), e: (.*), p: .*$/", $contact, $matches);
                    if ($matches[2] != "") {
                        if ($mailnames != "") {
                            $mailnames .= ", ";
                        }
                        // extracting real names is nice, but the <> notation
                        // really gets screwed up on POSTs and HTML safety
                        // so better not do this; use only mail addresses
                        // keeping the old codeline in case we revive this
                        // $mailnames .= '"'.$matches[1].'" <'.$matches[2].'>';
                        $mailnames .= $matches[2];
                    }
                }
                $returnarray[] = ["ID" => $a->id, "name" => $thelangauge, "contactlist" => $mailnames, "country" => $a->country, "realmlist" => $a->realmlist];
            }
        }

        return $returnarray;
    }

    public static function getExternalDBEntityDetails($external_id, $realm = NULL) {
        $list = [];
        if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $scanforrealm = "";
            if ($realm !== NULL) {
                $scanforrealm = "OR inst_realm LIKE '%$realm%'";
            }
            $info_list = DBConnection::exec("EXTERNAL", "SELECT name AS collapsed_name, inst_realm as realmlist, contact AS collapsed_contact, country FROM view_active_idp_institution WHERE id_institution = $external_id $scanforrealm");
            // split names and contacts into proper pairs
            while ($a = mysqli_fetch_object($info_list)) {
                $names = explode('#', $a->collapsed_name);
                foreach ($names as $name) {
                    $perlang = explode(': ', $name, 2);
                    $list['names'][$perlang[0]] = $perlang[1];
                }
                $contacts = explode('#', $a->collapsed_contact);
                foreach ($contacts as $contact) {
                    $email_1 = explode('e: ', $contact);
                    $email_2 = explode(',', $email_1[1]);
                    $list['admins'][] = ["email" => $email_2[0]];
                }
                $list['country'] = $a->country;
                $list['realmlist'] = $a->realmlist;
            }
        }
        return $list;
    }

    /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * @param int $active_only if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     *
     */
    public static function listAllIdentityProviders($active_only = 0, $country = 0) {
        DBConnection::exec($this->databaseType, "SET SESSION group_concat_max_len=10000");
        $query = "SELECT distinct institution.inst_id AS inst_id, institution.country AS country,
                     group_concat(concat_ws('===',institution_option.option_name,LEFT(institution_option.option_value,200)) separator '---') AS options
                     FROM institution ";
        if ($active_only == 1) {
            $query .= "JOIN profile ON institution.inst_id = profile.inst_id ";
        }
        $query .= "JOIN institution_option ON institution.inst_id = institution_option.institution_id ";
        $query .= "WHERE (institution_option.option_name = 'general:instname' 
                          OR institution_option.option_name = 'general:geo_coordinates'
                          OR institution_option.option_name = 'general:logo_file') ";
        if ($active_only == 1) {
            $query .= "AND profile.showtime = 1 ";
        }
        if ($country) {
            // escape the parameter
            $country = DBConnection::escape_value($this->databaseType, $country);
            $query .= "AND institution.country = '$country' ";
        }
        $query .= "GROUP BY institution.inst_id ORDER BY inst_id";
        $allIDPs = DBConnection::exec($this->databaseType, $query);
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
                if ($opt[0] == 'general:logo_file') {
                    $oneInstitutionResult['icon'] = $queryResult->inst_id;
                }
                if ($opt[0] == 'general:geo_coordinates') {
                    $at1 = unserialize($opt[1]);
                    $geo[] = $at1;
                }
                if ($opt[0] == 'general:instname') {
                    $names[] = ['value' => $opt[1]];
                }
            }

            $name = _("Unnamed Entity");
            if (count($names) != 0) {
                $name = getLocalisedValue($names, CAT::get_lang());
            }
            $oneInstitutionResult['title'] = $name;
            if (count($geo) > 0) {
                $oneInstitutionResult['geo'] = $geo;
            }
            $returnarray[] = $oneInstitutionResult;
        }
        return $returnarray;
    }

}
