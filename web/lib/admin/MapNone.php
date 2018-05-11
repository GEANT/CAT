<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\admin;

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

/**
 * This class provides map display functionality
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class MapNone extends AbstractMap {

    public function __construct($inst, $readonly) {
        parent::__construct($inst, $readonly);
        return $this;
    }

    public function htmlHeadCode() {
        // no magic required if you want to nothing at all.
        return "<script>
            function locateMe() {
                navigator.geolocation.getCurrentPosition(locate_succes,locate_fail,{maximumAge:3600000, timeout:5000});
            }

            function locate_succes(p) {
                $('#geo_long').val(p.coords.longitude);
                $('#geo_lat').val(p.coords.latitude);
            }
            function locate_fail(p) {
                alert('failure: '+p.message);
            }
        </script>
        ";
    }

    public function htmlBodyCode() {
        // no magic required if you want to nothing at all.
        return "";
    }

    public function htmlShowtime($wizard = FALSE, $additional = FALSE) {
        if (!$this->readOnly) {
 //           return $this->htmlPreEdit($wizard, $additional) . $this->htmlPostEdit(TRUE);
            return $this->htmlPreEdit($wizard, $additional) . $this->findLocationHtml() . $this->htmlPostEdit(TRUE);
        }
    }

    public function bodyTagCode() {
        return "";
    }

    public static function optionListDisplayCode($coords, $number) {
        $pair = json_decode($coords, true);
        return "<table><tr><td>Latitude</td><td><strong>" . $pair['lat'] . "</strong></td></tr><tr><td>Longitude</td><td><strong>" . $pair['lon'] . "</strong></td></tr></table>";
    }
    private function findLocationHtml() {
        return "<button type='button' onclick='locateMe()'>" . _("Locate Me!") . "</button></p>";
    }
}
