<?php
namespace devices\xml;

class Device_XML_TLS extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_TLS,
        ]);
        $this->langScope = 'single';
    }

}
