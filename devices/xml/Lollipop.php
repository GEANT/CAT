<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_Lollipop extends Device_XML{
    final public function __construct() {
        parent::__construct();
      $this->supportedEapMethods  =
            [
              EAPTYPE_PEAP_MSCHAP2,
              EAPTYPE_TTLS_PAP,
              EAPTYPE_TTLS_MSCHAP2,
              EAPTYPE_TLS,
       ];
      $this->langScope = 'single';
      $this->allEaps = TRUE;
    }
}