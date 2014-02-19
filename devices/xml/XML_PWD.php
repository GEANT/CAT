<?php

require_once('DeviceConfig.php');
require_once('XML.php');

class Device_XML_PWD extends Device_XML {
    final public function __construct() {
      $this->supportedEapMethods  =
            array(
              EAP::$PWD,
       );
      $this->lang_scope = 'single';
    }
}

?>
