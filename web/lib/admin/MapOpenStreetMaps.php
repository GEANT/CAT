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
class MapOpenStreetMaps extends AbstractMap {

    public function __construct($inst, $readonly) {
        parent::__construct($inst, $readonly);
        return $this;
    }

    public function htmlHeadCode() {
        // your magic here
        return "";
    }

    public function htmlBodyCode() {
        // your magic here
        return "";
    }

    public function htmlShowtime($wizard = FALSE, $additional = FALSE) {
        // your magic here
        return "";
    }
    
    public function bodyTagCode() {
        // your magic here
        return "";
    }
}
