<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_TTLS_MSCHAP2 extends Device_XML {
    final public function __construct() {
      $this->supportedEapMethods  =
            array(
              EAP::$TTLS_MSCHAP2,
       );
      $this->lang_scope = 'single';
    }
}

?>
