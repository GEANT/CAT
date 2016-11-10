<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_TTLS_PAP extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            EAPTYPE_TTLS_PAP,
        ]);
        $this->langScope = 'single';
    }

}
