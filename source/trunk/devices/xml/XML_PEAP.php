<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_PEAP extends Device_XML {
    final public function __construct() {
      $this->supportedEapMethods  =
            array(
              EAP::$PEAP_MSCHAP2,
       );
      $this->lang_scope = 'single';
    }
}

?>
