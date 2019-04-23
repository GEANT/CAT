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
abstract class AbstractMap extends \core\common\Entity {

    /**
     * the institution name
     * 
     * @var string
     */
    protected $instName;
    
    /**
     * the federation name
     * 
     * @var string
     */
    protected $fedName;
    
    /**
     * are we editing, or merely displaying a map?
     * 
     * @var boolean
     */
    protected $readOnly;

    /**
     * loads the map, taking identifiers from the IdP in question
     * 
     * @param \core\IdP $inst     the IdP for which the map is displayed
     * @param boolean   $readonly whether the HTML code should yield an editable field
     */
    protected function __construct($inst, $readonly) {
        parent::__construct();
        $this->instName = $inst->name;
        $this->fedName = $inst->federation;
        $this->readOnly = $readonly;
    }

    /**
     * loads the configured map type
     * 
     * @param \core\IdP $inst     the institution for which the map is loaded
     * @param boolean   $readonly is this a readonly map?
     * @return \web\lib\admin\MapNone|\web\lib\admin\MapOpenLayers|\web\lib\admin\MapGoogle
     * @throws Exception
     */
    public static function instance($inst, $readonly) {
        $classname = "\web\lib\admin\Map".\config\ConfAssistant::MAPPROVIDER['PROVIDER'];
        return new $classname($inst, $readonly);
    }

    /**
     * If the map needs to inject code into <head>, it is generated in this function.
     * 
     * @return string
     */
    abstract public function htmlHeadCode();

    /**
     * If the map needs to inject code into <body> to enable a map (like 
     * JavaScript code), it is generated in this function. The actual HTML
     * is defined in the htmlShowtime() function below.
     * 
     * @return string
     */
    abstract public function htmlBodyCode();

    /**
     * If the map needs to modify the <body> tag itself (e.g. an onLoad()
     * function), it is generated in this function
     * 
     * @return string
     */
    abstract public function bodyTagCode();

    /**
     * Code to display the map and surrounding HTML to display the map. Providers
     * probably will want to return different pieces of code depending on whether
     * we're $this->readOnly or not.
     * 
     * For edit mode, the option parser (after hitting Submit) expects a coordinate
     * pair in the HTML parameters 'geo_lat' and 'geo_long'. The code in this
     * function should fill these parameters. The parameters themselves are
     * generated if making use of the htmlPostEdit() function, or can of course
     * be written by this htmlShowtime function itself.
     * 
     * @param boolean $wizard     are we in wizard mode?
     * @param boolean $additional is this an additional location or a first?
     * @return string
     */
    abstract public function htmlShowtime($wizard, $additional);

    /**
     * How are coordinates displayed in the enumeration of inst options?
     * This function provides the HTML for that.
     * 
     * @param string $coords JSON encoded array of a coordinate pair
     * @param int    $number the number of the location
     * @return string
     */
    abstract public static function optionListDisplayCode($coords, $number);
    
    /**
     * This HTML goes above the actual map, and is map provider independent.
     * 
     * @param boolean $wizardMode are we in wizard mode?
     * @param boolean $additional is there more than one coordinate pair to display
     * @return string
     */
    protected function htmlPreEdit($wizardMode, $additional) {
        \core\common\Entity::intoThePotatoes();
        $retval = "<fieldset class='option_container'>
        <legend><strong>" . _("Location") . "</strong></legend>";

        if ($wizardMode) {
            $retval .= "<p>" .
                    _("The user download interface (see <a href='../'>here</a>), uses geolocation to suggest possibly matching IdPs to the user. The more precise you define the location here, the easier your users will find you.") .
                    "</p>
                     <ul>" .
                    _("<li>Drag the marker in the map to your place, or</li>
<li>enter your street address in the field below for lookup, or</li>
<li>use the 'Locate Me!' button</li>") .
                    "</ul>
                     <strong>" .
                    _("We will use the coordinates as indicated by the marker for geolocation.") .
                    "</strong>";
        }
        if ($additional) {
            $retval .= _("You can enter an <strong>additional</strong> location here. You can see the already defined locations in the 'General Information' field.");
        }
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

    /**
     * This HTML goes below the actual map, and is map provider independent.
     * 
     * @param boolean $allowDirectInput should the input fields be editable?
     * @return string
     */
    protected function htmlPostEdit($allowDirectInput) {
        \core\common\Entity::intoThePotatoes();
        $retval = "<br/>" . _("Latitude:") . " <input style='width:80px' name='geo_lat' id='geo_lat' " .($allowDirectInput ? "": "readonly"). ">" . _("Longitude:") . " <input name='geo_long' id='geo_long' style='width:80px' " .($allowDirectInput ? "": "readonly"). "></fieldset>";
        \core\common\Entity::outOfThePotatoes();
        return $retval;
    }

}
