<?php
namespace devices\xml;

class Device_XML_PWD extends Device_XML {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([
            \core\EAP::EAPTYPE_PWD,
        ]);
        $this->langScope = 'single';
    }

}
