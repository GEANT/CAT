<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\common;

/**
 * This class defines constants for HTML form handling.
 * 
 * When submitting forms, the sending page's form and receiving page's POST/GET 
 * must have a common understanding on what's being transmitted. Rather than
 * using strings or raw integers, named constants are much prettier.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class FormElements {

    const BUTTON_CLOSE = 0;
    const BUTTON_CONTINUE = 1;
    const BUTTON_DELETE = 2;
    const BUTTON_SAVE = 3;
    const BUTTON_EDIT = 4;
    const BUTTON_TAKECONTROL = 5;
    const BUTTON_PURGECACHE = 6;
    const BUTTON_FLUSH_AND_RESTART = 7;
    const BUTTON_SANITY_TESTS = 8;

}
