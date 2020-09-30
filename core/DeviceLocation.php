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

/*
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */

namespace core;

use GeoIp2\Database\Reader;
use \Exception;

class DeviceLocation
{

    /**
     * find out where the user is currently located
     * set $location with the discovered value
     * 
     * @return array
     * @throws Exception
     */
    public static function locateDevice()
    {
        $geoipVersion = \config\Master::GEOIP['version'] ?? 0;
        switch ($geoipVersion) {
            case 0:
                return(['status' => 'error', 'error' => 'Geolocation not supported']);
            case 1:
                return(self::locateDevice1());
            case 2:
                return(self::locateDevice2());
            default:
                throw new Exception("This version of GeoIP is not known!");
        }
    }

    /**
     * locate end-user with GeoIP version 1
     * 
     * @return array
     */
    private static function locateDevice1()
    {
        if (\config\Master::GEOIP['version'] != 1) {
            return ['status' => 'error', 'error' => 'Function for GEOIPv1 called, but config says this is not the version to use!'];
        }
        //$host = $_SERVER['REMOTE_ADDR'];
        $host = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $record = geoip_record_by_name($host);
        if ($record === FALSE) {
            return ['status' => 'error', 'error' => 'Problem getting the address'];
        }
        $result = ['status' => 'ok'];
        $result['country'] = $record['country_code'];
//  the two lines below are a dirty hack to take of the error in naming the UK federation
        if ($result['country'] == 'GB') {
            $result['country'] = 'UK';
        }
        $result['region'] = $record['region'];
        $result['geo'] = ['lat' => (float) $record['latitude'], 'lon' => (float) $record['longitude']];
        return($result);
    }

    /**
     * find out where the user is currently located, using GeoIP2
     * 
     * @return array
     */
    private static function locateDevice2()
    {
        if (\config\Master::GEOIP['version'] != 2) {
            return ['status' => 'error', 'error' => 'Function for GEOIPv2 called, but config says this is not the version to use!'];
        }
        include_once \config\Master::GEOIP['geoip2-path-to-autoloader'];
        $reader = new Reader(\config\Master::GEOIP['geoip2-path-to-db']);
        $host = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        try {
            $record = $reader->city($host);
        } catch (\Exception $e) {
            $result = ['status' => 'error', 'error' => 'Problem getting the address'];
            return($result);
        }
        $result = ['status' => 'ok'];
        $result['country'] = $record->country->isoCode;
//  the two lines below are a dirty hack to take of the error in naming the UK federation
        if ($result['country'] == 'GB') {
            $result['country'] = 'UK';
        }
        $result['region'] = $record->continent->name;

        $result['geo'] = ['lat' => (float) $record->location->latitude, 'lon' => (float) $record->location->longitude];
        return($result);
    }
}