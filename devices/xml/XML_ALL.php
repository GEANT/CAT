<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_ALL extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            EAPTYPE_PEAP_MSCHAP2,
            EAPTYPE_TTLS_PAP,
            EAPTYPE_TTLS_MSCHAP2,
            EAPTYPE_TLS,
            EAPTYPE_PWD,
        ]);
        $this->langScope = 'single';
        $this->allEaps = TRUE;
    }

}
