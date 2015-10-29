<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file contains the Factory for Device module instantiation
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

/**
 * required includes
 */
include_once("devices/devices.php");
include_once("CAT.php");

/**
 * This factory instantiates a device module and makes it available in its member $device.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */
class DeviceFactory {
    /**
     * Contains the produced device instance
     * 
     * @var DeviceConfig
     */
    public $device;

    /**
     * The constructor of the device factory.
     * Needs to be fed with the correct blueprint to produce a device
     *
     * @param string $blueprint The name of the module to instantiate
     */
    public function __construct($blueprint) {

      $Dev = Devices::listDevices();
        if(isset($Dev[$blueprint])) {
            if($Dev[$blueprint]['directory'] && $Dev[$blueprint]['module'])
                require_once("devices/".$Dev[$blueprint]['directory']."/".$Dev[$blueprint]['module'].".php");
            debug(4,"loaded: devices/".$Dev[$blueprint]['directory']."/".$Dev[$blueprint]['module'].".php\n");
            $class_name = "Device_".$Dev[$blueprint]['module'];
            $this->device = new $class_name();
            if(! $this->device) {
                debug(2,"module loading failed");
                die("module loading failed");
            }
        } else {
            error("unknown devicename:$blueprint");
        }
       $this->device->module_path = CAT::$root.'/devices/'.$Dev[$blueprint]['directory'];
       $this->device->signer = isset($Dev[$blueprint]['signer']) ? $Dev[$blueprint]['signer'] : 0; 
       $this->device->device_id = $blueprint;
       $options = Devices::$Options;
       if(isset($Dev[$blueprint]['options'])) {
          $Opt = $Dev[$blueprint]['options'];
          foreach ($Opt as $option => $value)
            $options[$option] = $value;
       }
       $this->device->options = $options;
    }
}
?>
