<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
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
class DeviceFactory extends \core\common\Entity
{

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
     * @throws Exception
     */
    public function __construct($blueprint)
    {
        parent::__construct();
        $Dev = \devices\Devices::listDevices();
        if (isset($Dev[$blueprint])) {
            $this->loggerInstance->debug(4, "loaded: devices/" . $Dev[$blueprint]['directory'] . "/" . $Dev[$blueprint]['module'] . ".php\n");
            $class_name = "\devices\\" . $Dev[$blueprint]['directory'] . "\Device" . $Dev[$blueprint]['module'];
            $this->device = new $class_name();
            if (!$this->device) {
                $this->loggerInstance->debug(2, "module loading failed");
                throw new Exception("module loading failed");
            }
        } else {
            echo("unknown devicename:$blueprint\n");
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