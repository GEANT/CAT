<?php

/* 
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 * 
 *  License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/*
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */

namespace core;

use GeoIp2\Database\Reader;
use \Exception;

class DeviceLocation {
    /**
     * find out where the user is currently located
     * set $location with the discovered value
     */
    
    public static locateDevice() {
        $geoipVersion = CONFIG['GEOIP']['version'] ?? 0;
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
    
    private static function locateDevice1() {
        if (CONFIG['GEOIP']['version'] != 1) {
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
     * @return array
     */
    private static function locateDevice2() {
        if (CONFIG['GEOIP']['version'] != 2) {
            return ['status' => 'error', 'error' => 'Function for GEOIPv2 called, but config says this is not the version to use!'];
        }
        require_once CONFIG['GEOIP']['geoip2-path-to-autoloader'];
        $reader = new Reader(CONFIG['GEOIP']['geoip2-path-to-db']);
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
