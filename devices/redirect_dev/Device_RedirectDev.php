<?php
namespace devices\redirect_dev;

class Device_RedirectDev extends \core\DeviceConfig {
   /**
    * Constructs a Device object.
    *
    * @final not to be redefined
    */
    final public function __construct() {
        parent::__construct();
      $this->setSupportedEapMethods([\core\EAP::EAPTYPE_NONE]);
      $this->loggerInstance->debug(4,"RedirectEx called");
    }
    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= _("This device is not yet supported by CAT, but your local administrator created a redirect to local installation instructions.");
        return $out;
    }


}
