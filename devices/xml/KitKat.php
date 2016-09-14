<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_KitKat extends Device_XML{
    final public function __construct() {
        parent::__construct();
      $this->supportedEapMethods  =
            [
              EAP::$PEAP_MSCHAP2,
              EAP::$TTLS_PAP,
              EAP::$TTLS_MSCHAP2,
       ];
      $this->langScope = 'single';
      $this->allEaps = TRUE;
    }
}