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
