<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_PEAP extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            EAPTYPE_PEAP_MSCHAP2,
        ]);
        $this->langScope = 'single';
    }

}
