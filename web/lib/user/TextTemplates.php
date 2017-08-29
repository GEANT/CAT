<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
namespace web\lib\user;

/**
 * these constants live in the global space just to ease their use - with class
 * prefix, the names simply get too long for comfort
 */

const WELCOME_ABOARD_HEADING = 1000;
const WELCOME_ABOARD_DOWNLOAD = 1001;
    

/**
 * provides various translated texts which are hopefully of common interest for
 * a number of skins.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class TextTemplates {
    
    /**
     * An array with lots of template texts. 
     * 
     * HTML markup is used sparingly. Expect <br> <p> <strong> <a> but nothing else.
     * 
     * @var array
     */
    public $templates;
    
    /**
     * Initialises the texts.
     */
    public function __construct() {
        $this->templates[WELCOME_ABOARD_HEADING] = sprintf(_("Welcome aboard the %s user community!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[WELCOME_ABOARD_DOWNLOAD] = _("Your download will start shortly. In case of problems with the automatic download please use this direct <a href=''>link</a>.");
    }
}