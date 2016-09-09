<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_TTLS_PAP extends Device_XML {
    final public function __construct() {
        parent::__construct();
      $this->supportedEapMethods  =
            [
              EAP::$TTLS_PAP,
       ];
      $this->lang_scope = 'single';
    }
}

?>
