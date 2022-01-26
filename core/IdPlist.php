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

namespace core;

class IdPlist extends common\Entity
{

    /**
     * Order active identity providers according to their distance and name
     * @param string $country         the country from which to list IdPs
     * @param array  $currentLocation current location
     * @return array $IdPs -  list of arrays ('id', 'name');
     */
    public static function orderIdentityProviders($country, $currentLocation)
    {
        $idps = self::listAllIdentityProviders(1, $country);
        $here = self::setCurrentLocation($currentLocation);
        $idpTitle = [];
        $resultSet = [];
        foreach ($idps as $idp) {
            $idpTitle[$idp['entityID']] = $idp['title'];
            $d = self::getIdpDistance($idp, $here);
            $resultSet[$idp['entityID']] = $d." ".$idp['title'];
        }
        asort($resultSet);
        $outarray = [];
        foreach (array_keys($resultSet) as $r) {
            $outarray[] = ['idp' => $r, 'title' => $idpTitle[$r]];
        }
        return($outarray);
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
    public static function listAllIdentityProviders($activeOnly = 0, $country = "")
    {
        common\Entity::intoThePotatoes();
        $handle = DBConnection::handle("INST");
        $handle->exec("SET SESSION group_concat_max_len=10000");
        $query = IdPlist::setAllIdentyProvidersQuery($activeOnly, $country);
        $allIDPs = ($country != "" ? $handle->exec($query, "s", $country) : $handle->exec($query));
        $idpArray = [];
        // SELECTs never return a booleans, always an object
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allIDPs)) {
            $options = IdPlist::setIdentityProviderAttributes($queryResult);
            $oneInstitutionResult = [];
            $oneInstitutionResult['entityID'] = $queryResult->inst_id;
            $oneInstitutionResult['country'] = strtoupper($queryResult->country);
            if ($options['icon'] > 0) {
                $oneInstitutionResult['icon'] = $options['icon'];
            }
            $name = _("Unnamed Entity");
            if (count($options['names']) != 0) {
                $langObject = new \core\common\Language();
                $name = $langObject->getLocalisedValue($options['names']);
            }          
            $oneInstitutionResult['title'] = $name;
            $keywords = [];
            foreach ($options['names'] as $keyword) {
                $value = $keyword['value'];
                $keywords[$keyword['lang']] = $keyword['value'];
                $keywords[$keyword['lang'].'_7'] =
                        iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            }
            
            if (\config\ConfAssistant::USE_KEYWORDS) {
                $keywords_final = array_unique($keywords);

                if (!empty($keywords_final)) {
                    $oneInstitutionResult['keywords'] = [];
                    foreach (array_keys($keywords_final) as $key) {
                    $oneInstitutionResult['keywords'][] = [$keywords_final[$key]];
                    }
                }
            }
            if (count($options['geo']) > 0) {
                $oneInstitutionResult['geo'] = $options['geo'];
            }
            $idpArray[] = $oneInstitutionResult;
        }
        common\Entity::outOfThePotatoes();
        return $idpArray;
    }
    
    /**
     * outputs a full list of IdPs containing the fllowing data:
     * institution_is, institution name in all available languages,
     * list of production profiles.
     * For eache profile the profile identifier, profile name in all languages
     * and redirect values (empty rediret value means that no redirect has been
     * set).
     * 
     * @return array of identity providers with attributes
     */
    public static function listIdentityProvidersWithProfiles() {
        $handle = DBConnection::handle("INST");
        $handle->exec("SET SESSION group_concat_max_len=10000");
        $idpQuery = IdPlist::setAllIdentyProvidersQuery(1);
        $allIDPs = $handle->exec($idpQuery);
        $idpArray = [];
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allIDPs)) {
            $options = IdPlist::setIdentityProviderAttributes($queryResult);
            $oneInstitutionResult = [];
            $oneInstitutionResult['country'] = strtoupper($queryResult->country);
            $oneInstitutionResult['entityID'] = (int) $queryResult->inst_id;
            if ($options['icon'] > 0) {
                $oneInstitutionResult['icon'] = $options['icon'];
            }
            $oneInstitutionResult['names'] = $options['names'];
            if (count($options['geo']) > 0) {
                $geoArray = [];
                foreach ($options['geo'] as $coords) {
                    $geoArray[] = ['lon' => (float) $coords['lon'],
                        'lat' => (float) $coords['lat']];
                }
                $oneInstitutionResult['geo'] = $geoArray;
            }

            $idpArray[$queryResult->inst_id] = $oneInstitutionResult;
        }
        
        $profileQuery = IdPlist::setAllProfileQuery();
        $allProfiles = $handle->exec($profileQuery);
        while ($queryResult = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allProfiles)) {
            $profileOptions = IdPlist::setProfileAttributes($queryResult);
            $idpId = $queryResult->inst_id;
            if (empty($idpArray[$idpId])) {
                continue;
            }
            if (empty($idpArray[$idpId]['profiles'])) {
                $idpArray[$idpId]['profiles'] = [];
            }
            if (!$profileOptions['production']) {
                continue;
            }
            $idpArray[$idpId]['profiles'][] = [
                'id'=> (int) $queryResult->profile_id,
                'names' => $profileOptions['profileNames'],
                'redirect' => $profileOptions['redirect'],
                'openroaming' => $profileOptions['openroaming'],
//                'options' => $profileOptions['options'],
                ];
        }       
        return $idpArray;
    }

    /**
     * sets the current location
     * 
     * @param array $currentLocation the location to set
     * @return array
     */
    private static function setCurrentLocation($currentLocation)
    {
        if (is_null($currentLocation)) {
            $currentLocation = ['lat' => "90", 'lon' => "0"];
            $userLocation = DeviceLocation::locateDevice();
            if ($userLocation['status'] == 'ok') {
                $currentLocation = $userLocation['geo'];
            }
        }
        return $currentLocation;
    }

    /**
     * calculate surface distance from user location to IdP location
     * @param array $idp      the IdP in question
     * @param array $location user location
     * @return string
     */
    private static function getIdpDistance($idp, $location)
    {
        $dist = 10000;
        if (isset($idp['geo'])) {
            $G = $idp['geo'];
            if (isset($G['lon'])) {
                $d1 = self::geoDistance($location, $G);
                if ($d1 < $dist) {
                    $dist = $d1;
                }
            } else {
                foreach ($G as $g) {
                    $d1 = self::geoDistance($location, $g);
                    if ($d1 < $dist) {
                        $dist = $d1;
                    }
                }
            }
        }
        if ($dist > 100) {
            $dist = 10000;
        }
        return(sprintf("%06d", $dist));
    }
    
    /**
     * set the IdP query string for listAllIdentityProviders and 
     * listIdentityProvidersWithProfiles
     * @param int    $activeOnly if set to non-zero will cause listing of only those institutions which have some valid profiles defined.
     * @param string $country    if set, only list IdPs in a specific country
     * 
     * @return string the query
     */
    private static function setAllIdentyProvidersQuery($activeOnly = 0, $country = "")
    {
        $query = "SELECT distinct institution.inst_id AS inst_id,
            institution.country AS country,
            group_concat(concat_ws('===',institution_option.option_name,
                LEFT(institution_option.option_value,200),
                institution_option.option_lang) separator '---') AS options
            FROM institution ";
        if ($activeOnly == 1) {
            $query .= "JOIN v_active_inst ON institution.inst_id = v_active_inst.inst_id ";
        }
        $query .= 
            "JOIN institution_option ON institution.inst_id = institution_option.institution_id
            WHERE (institution_option.option_name = 'general:instname' 
                OR institution_option.option_name = 'general:geo_coordinates'
                OR institution_option.option_name = 'general:logo_file') ";

        $query .= ($country != "" ? "AND institution.country = ? " : "");

        $query .= "GROUP BY institution.inst_id ORDER BY inst_id";
        return $query;
    }

    /**
     * set the Profile query string for listIdentityProvidersWithProfiles
     * 
     * @return string query
     */
    private static function setAllProfileQuery() {
        $query = "SELECT profile.inst_id AS inst_id,
            profile.profile_id,
            group_concat(concat_ws('===',profile_option.option_name, 
                LEFT(profile_option.option_value, 200),
                profile_option.option_lang) separator '---') AS profile_options
            FROM profile
            JOIN profile_option ON profile.profile_id = profile_option.profile_id
            WHERE profile.sufficient_config = 1
                AND profile_option.eap_method_id = 0
                AND (profile_option.option_name = 'profile:name'
                OR (profile_option.option_name = 'device-specific:redirect'
                    AND isnull(profile_option.device_id))
                OR profile_option.option_name = 'media:openroaming'
                OR profile_option.option_name = 'profile:production')        
            GROUP BY profile.profile_id ORDER BY inst_id";
        return $query;
    }
    
    /**
     * Extract IdP attributes for listAllIdentityProviders and 
     * listIdentityProvidersWithProfiles
     * @param  $idp object - the row object returned by the IdP search
     * @return array the IdP attributes
     */
    private static function setIdentityProviderAttributes($idp) {
        $options = explode('---', $idp->options);
        $names = [];
        $geo = [];
        $icon = 0;
        foreach ($options as $option) {
            $opt = explode('===', $option);
            switch ($opt[0]) {
                case 'general:logo_file':
                    $icon = $idp->inst_id;
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
        return ['names' => $names, 'geo' => $geo, 'icon' => $icon];       
    }
    
    /**
     * Extract Profie attributes for listIdentityProvidersWithProfiles
     * 
     * @param object $profile - the row object returned by the profile search
     * @return array the profile attributes
     */
    private static function setProfileAttributes($profile)
    {
        $profileOptions = explode('---', $profile->profile_options);
        $productionProfile = false;
        $profileNames = [];
        $redirect = '';
        $openroaming = 'none';

        foreach ($profileOptions as $profileOption) {
            $opt = explode('===', $profileOption);
            switch ($opt[0]) {
                case 'profile:production':
                    if ($opt[1] == 'on') {
                        $productionProfile = true;
                    }
                    break;
                case 'device-specific:redirect':
                    $redirect = $opt[1];
                    if (!empty($profile->device_id)) {
                        $redirect .= ':' . $profile->device_id;
                    }
                    break;
                case 'profile:name': 
                    $profileNames[] = [
                        'lang' => $opt[2],
                        'value' => $opt[1]
                    ];
                    break;
                case 'media:openroaming':
                    $openroaming = $opt[1];
                    break;
                default:
                    break; 
            }
        }
        return ['production' => $productionProfile,
            'profileNames' => $profileNames,
            'redirect' => $redirect,
            'openroaming' => $openroaming,
            ];
    }
    
    private static function setKeywords($names)
    {
        if (!\config\ConfAssistant::USE_KEYWORDS) {
            return null;
        }
        $keywords = [];
        $returnArray = [];
        foreach ($names as $keyword) {
            $value = $keyword['value'];
            $keywords[$keyword['lang']] = $keyword['value'];
            $keywords[$keyword['lang'].'_7'] =
                    iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        }
        $keywords_final = array_unique($keywords);
        if (!empty($keywords_final)) {
            foreach (array_keys($keywords_final) as $key) {
            $returnArray[] = [$keywords_final[$key]];
            }
        }
        return $returnArray;
    }

    /**
     * Calculate the distance in km between two points given their
     * geo coordinates.
     * @param array $point1   first point as an 'lat', 'lon' array 
     * @param array $profile1 second point as an 'lat', 'lon' array 
     * @return float distance in km
     */
    public static function geoDistance($point1, $profile1)
    {

        $distIntermediate = sin(deg2rad($point1['lat'])) * sin(deg2rad($profile1['lat'])) +
            cos(deg2rad($point1['lat'])) * cos(deg2rad($profile1['lat'])) * cos(deg2rad($point1['lon'] - $profile1['lon']));
        $dist = rad2deg(acos($distIntermediate)) * 60 * 1.1852;
        return(round($dist));
    }
}