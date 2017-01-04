<?php
namespace devices\xml;

class Device_XML_PEAP extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_PEAP_MSCHAP2,
        ]);
        $this->langScope = 'single';
    }

}
