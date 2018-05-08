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
        return "";
    }

    public function htmlBodyCode() {
        // no magic required if you want to nothing at all.
        return "";
    }

    public function htmlShowtime($wizard = FALSE, $additional = FALSE) {
        if (!$this->readOnly) {
            return $this->htmlPreEdit($wizard, $additional) . $this->htmlPostEdit(TRUE);
        }
    }
    
    public function bodyTagCode() {
        return "";
    }

    private function findLocationHtml() {
        return "";
    }
}
