<?php
namespace devices\xml;

class Device_XML_TTLS_PAP extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_TTLS_PAP,
        ]);
        $this->langScope = 'single';
    }

}
