<?php
namespace devices\xml;

class Device_KitKat extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_PEAP_MSCHAP2,
            \core\EAP::EAPTYPE_TTLS_PAP,
            \core\EAP::EAPTYPE_TTLS_MSCHAP2,
        ]);
        $this->langScope = 'single';
        $this->allEaps = TRUE;
    }

}
