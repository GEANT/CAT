<?php
require_once('DeviceConfig.php');

class Device_RedirectDev extends DeviceConfig {
   /**
    * Constructs a Device object.
    *
    * It is CRUTCIAL that the constructor sets $this->supportedEapMethods to an array of methods
    * available for the particular device.
    * {@source}
    * @param string $device a pointer to a device module, which must
    * be an index of one of the devices defined in the {@link Devices}
    * array in {@link devices.php}.
    * @final not to be redefined
    */
    final public function __construct() {
      $this->supportedEapMethods  = array(EAP::$EAP_NONE);
      debug(4,"RedirectEx called");
    }
    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= _("This device is not yet supported by CAT, but your local administrator created a redirect to local installation instructions.");
        return $out;
    }


}
