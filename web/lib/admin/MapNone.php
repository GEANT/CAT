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

/**
 * This class provides map display functionality
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class MapNone extends AbstractMap {

    /**
     * construct a NULL Map
     * 
     * @param \core\IdP $inst     the IdP for which the map is displayed
     * @param boolean   $readonly do we want a read-only map or editable?
     */
    public function __construct($inst, $readonly) {
        parent::__construct($inst, $readonly);
        return $this;
    }

    /**
     * Code to insert into the <head></head> of a page
     * 
     * @return string
     */
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

    /**
     * Code to insert into the <body></body> of a page
     * 
     * @return string
     */
    public function htmlBodyCode() {
        // no magic required if you want to nothing at all.
        return "";
    }

    /**
     * Code which actually shows the map
     * 
     * @param boolean $wizard     are we in wizard mode?
     * @param boolean $additional is this an additional location or a first?
     * @return string
     */
    public function htmlShowtime($wizard = FALSE, $additional = FALSE) {
        if (!$this->readOnly) {
 //           return $this->htmlPreEdit($wizard, $additional) . $this->htmlPostEdit(TRUE);
            return $this->htmlPreEdit($wizard, $additional) . $this->findLocationHtml() . $this->htmlPostEdit(TRUE);
        }
    }

    /**
     * Code to insert into the <body> tag of the page
     * 
     * @return string
     */
    public function bodyTagCode() {
        return "";
    }

    /**
     * This function produces the code for the "Click to see" text
     * 
     * @param string $coords not needed in this subclass
     * @param int    $number which location is it
     * @return string
     */
    public static function optionListDisplayCode($coords, $number) {
        // quiesce warnings about unused parameter
        if (strlen(sprintf("%d", $number)) <0) {
            throw new \Exception("A miracle! A string with negative length!");
        };
        $pair = json_decode($coords, true);
        return "<table><tr><td>Latitude</td><td><strong>" . $pair['lat'] . "</strong></td></tr><tr><td>Longitude</td><td><strong>" . $pair['lon'] . "</strong></td></tr></table>";
    }
    
    /**
     * code to enable user to find a location by licking the option
     * 
     * @return string
     */
    private function findLocationHtml() {
        \core\common\Entity::intoThePotatoes();
        $retval = "<button type='button' onclick='locateMe()'>" . _("Locate Me!") . "</button></p>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }
}
