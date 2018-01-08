<?php

/* 
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 * 
 *  License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */


namespace core;

use \Exception;

class IdPlist {
    /**
     * Order active identity providers according to their distance and name
     * @param array $currentLocation - current location
     * @return array $IdPs -  list of arrays ('id', 'name');
     */
    public static function orderIdentityProviders($country, $currentLocation = NULL) {
        $idps = $this->listAllIdentityProviders(1, $country);
        $here = $this->setCurrentLocation($currentLocation);
        $idpTitle = [];
        $resultSet = [];
        foreach ($idps as $idp) {
            $idpTitle[$idp['entityID']] = $idp['title'];
            $d = $this->getIdpDistance($idp, $here);
            $resultSet[$idp['entityID']] = $d . " " . $idp['title'];
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
     * @param int $activeOnly if set to non-zero will cause listing of only those institutions which have some valid profiles defined.
     * @param string $country if set, only list IdPs in a specific country
     * @return array the list of identity providers
     *
     */
    public static function listAllIdentityProviders($activeOnly = 0, $country = "") {
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
        return $returnarray;
    }


    private function setCurrentLocation($currentLocation) {
        if (is_null($currentLocation)) {
            $currentLocation = ['lat' => "90", 'lon' => "0"];
            $loc = new \core\DeviceLocation();
            $userLocation = $loc->locateDevice;
            if ($userLocation['status'] == 'ok') {
                $currentLocation = $userLocation['geo'];
            }
        }
        return($currentLocation);
    }
    
    private function getIdpDistance($idp, $location) {
        $dist = 10000;
        if (isset($idp['geo'])) {
            $G = $idp['geo'];
            if (isset($G['lon'])) {
                $d1 = $this->geoDistance($location, $G);
                if ($d1 < $dist) {
                    $dist = $d1;
                }
            } else {
                foreach ($G as $g) {
                    $d1 = $this->geoDistance($location, $g);
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
     * Calculate the distance in km between two points given their
     * geo coordinates.
     * @param array $point1 - first point as an 'lat', 'lon' array 
     * @param array $profile1 - second point as an 'lat', 'lon' array 
     * @return float distance in km
     */
    private function geoDistance($point1, $profile1) {

        $distIntermediate = sin(deg2rad($point1['lat'])) * sin(deg2rad($profile1['lat'])) +
                cos(deg2rad($point1['lat'])) * cos(deg2rad($profile1['lat'])) * cos(deg2rad($point1['lon'] - $profile1['lon']));
        $dist = rad2deg(acos($distIntermediate)) * 60 * 1.1852;
        return(round($dist));
    }
    
}