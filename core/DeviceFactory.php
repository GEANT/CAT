<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * This file contains the Factory for Device module instantiation
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */
namespace core;
use Exception;
/**
 * This factory instantiates a device module and makes it available in its member $device.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */
class DeviceFactory extends \core\common\Entity {

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
        parent::__construct();
        $Dev = \devices\Devices::listDevices();
        if (isset($Dev[$blueprint])) {
            $this->loggerInstance->debug(4, "loaded: devices/" . $Dev[$blueprint]['directory'] . "/" . $Dev[$blueprint]['module'] . ".php\n");
            $class_name = "\devices\\".$Dev[$blueprint]['directory']."\Device_" . $Dev[$blueprint]['module'];
            $this->device = new $class_name();
            if (!$this->device) {
                $this->loggerInstance->debug(2, "module loading failed");
                throw new Exception("module loading failed");
            }
        } else {
            print("unknown devicename:$blueprint\n");
        }
        $this->device->module_path = ROOT . '/devices/' . $Dev[$blueprint]['directory'];
        $this->device->signer = isset($Dev[$blueprint]['signer']) ? $Dev[$blueprint]['signer'] : 0;
        $this->device->device_id = $blueprint;
        $options = \devices\Devices::$Options;
        if (isset($Dev[$blueprint]['options'])) {
            $Opt = $Dev[$blueprint]['options'];
            foreach ($Opt as $option => $value) {
                $options[$option] = $value;
            }
        }
        $this->device->options = $options;
    }

}
