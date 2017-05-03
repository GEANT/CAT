<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace devices\xml;

class Device_XML_TTLS_MSCHAP2 extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
        ]);
        $this->langScope = 'single';
    }

}
