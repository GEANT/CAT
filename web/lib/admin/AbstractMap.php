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
abstract class AbstractMap {

    protected $instName;
    protected $fedName;
    protected $readOnly;

    /**
     * loads the map, taking identifiers from the IdP in question
     * 
     * @param \core\IdP $inst the IdP for which the map is displayed
     * @param boolean $readonly whether the HTML code should yield an editable field
     */
    protected function __construct($inst, $readonly) {
        $this->instName = $inst->name;
        $this->fedName = $inst->federation;
        $this->readOnly = $readonly;
    }

    /**
     * loads the configured map type
     * 
     * @param \core\IdP $inst
     * @param boolean $readonly
     * @return \web\lib\admin\MapNone|\web\lib\admin\MapOpenstreetMaps|\web\lib\admin\MapGoogle
     * @throws Exception
     */
    public static function instance($inst, $readonly) {
        switch (CONFIG_CONFASSISTANT['MAPPROVIDER']['PROVIDER']) {
            case "Google":
                return new MapGoogle($inst, $readonly);
            case "OpenStreetMaps":
                return new MapOpenStreetMaps($inst, $readonly);
            case "None":
                return new MapNone($inst, $readonly);
            default:
                throw new \Exception("Unknown map provider.");
        }
    }

    /**
     * If the map needs to inject code into <head>, it is generated in this function.
     */
    abstract public function htmlHeadCode();

    /**
     * If the map needs to inject code into <body> to enable a map (like 
     * JavaScript code), it is generated in this function. The actual HTML
     * is defined in the htmlShowtime() function below.
     */
    abstract public function htmlBodyCode();

    /**
     * If the map needs to modify the <body> tag itself (e.g. an onLoad()
     * function), it is generated in this function
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
     */
    abstract public function htmlShowtime($wizard, $additional);

    /**
     * How are coordinates displayed in the enumeration of inst options?
     * This function provides the HTML for that.
     * 
     * The parameter is the JSON representation of a coordinate pair.
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
        return $retval;
    }

    /**
     * This HTML goes below the actual map, and is map provider independent.
     * 
     * @param boolean $allowDirectInput should the input fields be editable?
     */
    protected function htmlPostEdit($allowDirectInput) {
        return "<br/>" . _("Latitude:") . " <input style='width:80px' name='geo_lat' id='geo_lat' " .($allowDirectInput ? "": "readonly"). ">" . _("Longitude:") . " <input name='geo_long' id='geo_long' style='width:80px' " .($allowDirectInput ? "": "readonly"). "></fieldset>";
    }

}
