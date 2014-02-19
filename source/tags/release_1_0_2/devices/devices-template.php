<?php
/* *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file contains the Devices class.
 *
 * @package ModuleWriting
 */
/**
 * The Devices class holds a list of all devices the CAT knows about
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @license see LICENSE file in root directory
 * 
 * @package ModuleWriting
 */
class Devices{

/**
 * This array lists available configuration options for local device management.
 * Values from this array will be taken as defaults.
 * Do not modify this array unless you really konw what you are doing.
 * Default values will be overriden by the settings of options inside
 * each device definition
 *
 * - 'sign' - if set to nonzero will cause installer signing if the module
 *         supports this
 * - 'no_cache' if defined and equal to 1 will block installer caching - useful
 *         for device development, should not be used in production
 * - 'hidden' if defined and equal to 1 will hide the device form listing - 
 *         useful for device development 
 * - 'device_id' - used in building the installer filename; when this option
 *         is not defined, the filename will use the index from 
 *         the listDevices array; when defined and not empty, it will be 
 *         used in place of this index; when defined as empty will cause
 *         the omission of the device part the filename.
 *         The default is unset, so it is not listed in the Options array.
 */

public static $Options=array(
  'sign'=>0,
  'no_cache'=>0,
  'hidden'=>0,
);

/**
 * Each device is defined as a sub-array within this array
 *
 * Except for changing/adding things inside the options arrays, do not modify
 * this array unless you really know what you are doing.
 *
 * Beware that the entrance page of CAT contains a rolling ad which 
 * lists some devices, and also states that certain device modules are signed,
 * you should keep this information in sync with your settings in this file
 * See web/user/roll.php for settings and more information.
 *
 * Settings
 * - 'group' - caused device grouping used by the entrance screen
 * - 'display' is the name shown on the GUI button
 * - 'directory' is the subdirectory of devices directory, where
 *       the device module resides
 * - 'module' is the name of the module class, the same name with .php
 *       added will be used as the name of the main include file for the module
 * - 'signer' if defined points to a script which will sign a file. 
 *       The script must be located in the signer subdirectory of CAT.
 *       The first argument of this script must be the input file name, 
 *       the second - the signed file filename. Signer will not be used
 *       unless the sign option is set to nonzero.
 * - 'options' - the array of options overriding the default settings.
 *       See the descripption of options above.
 *
 * @example devices/devices-template.php file listing
 * @return array the device modules
 */


public static function listDevices() {
    return array(
 'w8'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows 8"),
   'directory'=>'ms',
   'module'=>'W8',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'W8',
      ),
   ),
	
 'w7'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows 7"),
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'W7',
      ),
   ),
	
 'vista'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows Vista"),
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'Vista',
      ),
   ),
	
 'xp'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows XP SP3"),
   'directory'=>'ms',
   'module'=>'XP',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'XP',
      ),
   ),
	
 'apple_m_lion'=>array(
    'group' => "apple",
    'display'=>_("Apple Mac OS X Mountain Lion"),
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'MacOS',
      ),
    ),
	
 'apple_lion'=>array(
    'group' => "apple",
    'display'=>_("Apple Mac OS X Lion"),
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'MacOS',
      ),
    ),
 'mobileconfig'=>array(
    'group' => "apple",     
    'display'=>_("Apple iOS mobile devices"),
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'',
      ),
    ),
 'linux'=>array(
     'group' => "linux",
     'display'=>_("Linux"),
     'directory'=>'linux',
     'module' => 'Linux',
    'options'=>array(
      ),
   ),
/* these two devices are not meant for production

 'welcomeletter'=>array(
    'group' => "other",
    'display'=>_("Welcome Letter"),
    'directory'=>'welcomeletter',
    'module'=>'welcomeletter',
    'options'=>array(
       'device_id'=>'welcome',
      ),
   ),
    
 'test'=>array(
    'group' => "other",
    'display'=>_("Test"),
    'directory'=>'test_module',
    'module'=>'TestModule',
    'options'=>array(
      ),
   ),
*/
    
);
}
}
?>
