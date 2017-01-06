<?php
namespace devices\xml;

class Device_XML_ALL extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_PEAP_MSCHAP2,
            \core\EAP::EAPTYPE_TTLS_PAP,
            \core\EAP::EAPTYPE_TTLS_MSCHAP2,
            \core\EAP::EAPTYPE_TLS,
            \core\EAP::EAPTYPE_PWD,
        ]);
        $this->langScope = 'single';
        $this->allEaps = TRUE;
    }

}
