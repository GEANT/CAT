<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_ALL extends Device_XML{
    final public function __construct() {
        parent::__construct();
      $this->supportedEapMethods  =
            [
              PEAP_MSCHAP2,
              TTLS_PAP,
              TTLS_MSCHAP2,
              TLS,
              PWD,
       ];
      $this->langScope = 'single';
      $this->allEaps = TRUE;
    }
}