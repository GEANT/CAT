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
    public static $federationList = [];

    private static function downloadStatsCore($federationid = NULL) {
        $grossAdmin = 0;
        $grossUser = 0;

        $dataArray = [];

        $handle = DBConnection::handle("INST");
        foreach (Devices::listDevices() as $index => $deviceArray) {
            $query = "SELECT SUM(downloads_admin) AS admin, SUM(downloads_user) AS user FROM downloads, profile, institution WHERE device_id = '$index' AND downloads.profile_id = profile.profile_id AND profile.inst_id = institution.inst_id ";
            if ($federationid != NULL) {
                $query .= "AND institution.country = '" . $federationid . "'";
            }
            $numberQuery = $handle->exec($query);
            while ($queryResult = mysqli_fetch_object($numberQuery)) {
                $dataArray[$deviceArray['display']] = ["ADMIN" => ( $queryResult->admin === NULL ? "0" : $queryResult->admin), "USER" => ($queryResult->user === NULL ? "0" : $queryResult->user)];
                $grossAdmin = $grossAdmin + $queryResult->admin;
                $grossUser = $grossUser + $queryResult->user;
            }
        }
        $dataArray["TOTAL"] = ["ADMIN" => $grossAdmin, "USER" => $grossUser];
        return $dataArray;
    }

    public function updateFreshness() {
        // Federation is always fresh
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
                    $retstring .= "<tr><td>$device</td><td>" . $numbers['ADMIN'] . "</td><td>" . $numbers['USER'] . "</td></tr>";
                }
                $retstring .= "<tr><td><strong>TOTAL</strong></td><td><strong>" . $data['TOTAL']['ADMIN'] . "</strong></td><td><strong>" . $data['TOTAL']['USER'] . "</strong></td></tr>";
                break;
            case "XML":
                $retstring .= "<federation id='" . ( $federationid == NULL ? "ALL" : $federationid ) . "' ts='" . date("Y-m-d") . "T" . date("H:i:s") . "'>\n";
                foreach ($data as $device => $numbers) {
                    if ($device == "TOTAL") {
                        continue;
                    }
                    $retstring .= "  <device name='" . $device . "'>\n    <downloads group='admin'>" . $numbers['ADMIN'] . "</downloads>\n    <downloads group='user'>" . $numbers['USER'] . "</downloads>\n  </device>";
                }
                $retstring .= "<total>\n  <downloads group='admin'>" . $data['TOTAL']['ADMIN'] . "</downloads>\n  <downloads group='user'>" . $data['TOTAL']['USER'] . "</downloads>\n</total>\n";
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

        parent::__construct(); // we now have access to our database handle

        /* Federations are created in DB with bootstrapFederation, and listed via listFederations
         */
        $oldlocale = CAT::set_locale('core');

        Federation::$federationList = [
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

        // fetch attributes from DB; populates $this->attributes array
        $this->attributes = $this->retrieveOptionsFromDatabase("SELECT DISTINCT option_name,option_value, row 
                                            FROM $this->entityOptionTable
                                            WHERE $this->entityIdColumn = '$this->name' 
                                            ORDER BY option_name", "FED");


        $this->attributes[] = array("name" => "internal:country",
            "value" => $this->name,
            "level" => "FED",
            "row" => 0,
            "flag" => NULL);
    }

    /**
     * Creates a new IdP inside the federation.
     * 
     * @param string $ownerId Persistent identifier of the user for whom this IdP is created (first administrator)
     * @param string $level Privilege level of the first administrator (was he blessed by a federation admin or a peer?)
     * @param string $mail e-mail address with which the user was invited to administer (useful for later user identification if the user chooses a "funny" real name)
     * @return int identifier of the new IdP
     */
    public function newIdP($ownerId, $level, $mail) {
        $this->databaseHandle->exec("INSERT INTO institution (country) VALUES('$this->name')");
        $identifier = $this->databaseHandle->lastID();
        if ($identifier == 0 || !$this->loggerInstance->writeAudit($ownerId, "NEW", "IdP $identifier")) {
            echo "<p>" . _("Could not create a new Institution!") . "</p>";
            throw new Exception("Could not create a new Institution!");
        }
        // escape all strings
        $escapedOwnerId = $this->databaseHandle->escapeValue($ownerId);
        $escapedLevel = $this->databaseHandle->escapeValue($level);
        $escapedMail = $this->databaseHandle->escapeValue($mail);

        if ($escapedOwnerId != "PENDING") {
            $this->databaseHandle->exec("INSERT INTO ownership (user_id,institution_id, blesslevel, orig_mail) VALUES('$escapedOwnerId', $identifier, '$escapedLevel', '$escapedMail')");
        }
        return $identifier;
    }

    /**
     * Lists all Identity Providers in this federation
     *
     * @param int $activeOnly if set to non-zero will list only those institutions which have some valid profiles defined.
     * @return array (Array of IdP instances)
     *
     */
    public function listIdentityProviders($activeOnly = 0) {
        // default query is:
        $allIDPs = $this->databaseHandle->exec("SELECT inst_id FROM institution
               WHERE country = '$this->name' ORDER BY inst_id");
        // the one for activeOnly is much more complex:
        if ($activeOnly) {
            $allIDPs = $this->databaseHandle->exec("SELECT distinct institution.inst_id AS inst_id
               FROM institution
               JOIN profile ON institution.inst_id = profile.inst_id
               WHERE institution.country = '$this->name' 
               AND profile.showtime = 1
               ORDER BY inst_id");
        }

        $returnarray = [];
        while ($idpQuery = mysqli_fetch_object($allIDPs)) {
            $idp = new IdP($idpQuery->inst_id);
            $name = $idp->name;
            $idpInfo = ['entityID' => $idp->identifier,
                'title' => $name,
                'country' => strtoupper($idp->federation),
                'instance' => $idp];
            $returnarray[$idp->identifier] = $idpInfo;
        }
        return $returnarray;
    }

    public function listFederationAdmins() {
        $returnarray = [];
        $query = "SELECT user_id FROM user_options WHERE option_name = 'user:fedadmin' AND option_value = '" . strtoupper($this->name) . "'";
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $query = "SELECT eptid as user_id FROM view_admin WHERE role = 'fedadmin' AND realm = '" . strtolower($this->name) . "'";
        }
        $userHandle = DBConnection::handle("USER"); // we need something from the USER database for a change
        $admins = $userHandle->exec($query);

        while ($fedAdminQuery = mysqli_fetch_object($admins)) {
            $returnarray[] = $fedAdminQuery->user_id;
        }
        return $returnarray;
    }

    public function listExternalEntities($unmappedOnly) {
        $returnarray = [];
        $countrysuffix = "";

        if ($this->name != "") {
            $countrysuffix = " WHERE country = '" . strtolower($this->name) . "'";
        }

        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
            $usedarray = [];
            $externalHandle = DBConnection::handle("EXTERNAL");
            $externals = $externalHandle->exec("SELECT id_institution AS id, country, inst_realm as realmlist, name AS collapsed_name, contact AS collapsed_contact 
                                                                                FROM view_active_idp_institution $countrysuffix");
            $alreadyUsed = $this->databaseHandle->exec("SELECT DISTINCT external_db_id FROM institution 
                                                                                                     WHERE external_db_id IS NOT NULL 
                                                                                                     AND external_db_syncstate = " . EXTERNAL_DB_SYNCSTATE_SYNCED);
            $pendingInvite = $this->databaseHandle->exec("SELECT DISTINCT external_db_uniquehandle FROM invitations 
                                                                                                      WHERE external_db_uniquehandle IS NOT NULL 
                                                                                                      AND invite_created >= TIMESTAMPADD(DAY, -1, NOW()) 
                                                                                                      AND used = 0");
            while ($alreadyUsedQuery = mysqli_fetch_object($alreadyUsed)) {
                $usedarray[] = $alreadyUsedQuery->external_db_id;
            }
            while ($pendingInviteQuery = mysqli_fetch_object($pendingInvite)) {
                if (!in_array($pendingInviteQuery->external_db_uniquehandle, $usedarray)) {
                    $usedarray[] = $pendingInviteQuery->external_db_uniquehandle;
                }
            }
            while ($externalQuery = mysqli_fetch_object($externals)) {
                if (($unmappedOnly === TRUE) && (in_array($externalQuery->id, $usedarray))) {
                    continue;
                }
                $names = explode('#', $externalQuery->collapsed_name);
                // trim name list to current best language match
                $availableLanguages = [];
                foreach ($names as $name) {
                    $thislang = explode(': ', $name, 2);
                    $availableLanguages[$thislang[0]] = $thislang[1];
                }
                if (array_key_exists(CAT::get_lang(), $availableLanguages)) {
                    $thelangauge = $availableLanguages[CAT::get_lang()];
                } else if (array_key_exists("en", $availableLanguages)) {
                    $thelangauge = $availableLanguages["en"];
                } else { // whatever. Pick one out of the list
                    $thelangauge = array_pop($availableLanguages);
                }
                $contacts = explode('#', $externalQuery->collapsed_contact);


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
                        $mailnames .= $matches[2];
                    }
                }
                $returnarray[] = ["ID" => $externalQuery->id, "name" => $thelangauge, "contactlist" => $mailnames, "country" => $externalQuery->country, "realmlist" => $externalQuery->realmlist];
            }
        }

        return $returnarray;
    }

    public static function getExternalDBEntityDetails($externalId, $realm = NULL) {
        $list = [];
        if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
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
                $list['country'] = $externalEntityQuery->country;
                $list['realmlist'] = $externalEntityQuery->realmlist;
            }
        }
        return $list;
    }

    /**
     * Lists all identity providers in the database
     * adding information required by DiscoJuice.
     * @param int $activeOnly if and set to non-zero will
     * cause listing of only those institutions which have some valid profiles defined.
     *
     */
    public static function listAllIdentityProviders($activeOnly = 0, $country = 0) {
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
