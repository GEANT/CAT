<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
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
 * - 'redirect if defined and equal to 1 will only show the device on the listing
 *         if device redirect has been defined by the admin
 * - 'message' if defined will cause a display of the contents of this option as
 *         an additional warning
 *
 * - 'device_id' - used in building the installer filename; when this option
 *         is not defined, the filename will use the index from 
 *         the listDevices array; when defined and not empty, it will be 
 *         used in place of this index; when defined as empty will cause
 *         the omission of the device part the filename.
 *         The default is unset, so it is not listed in the Options array.
 * - 'mime' - used to set the MIME type of the installer file;
 *         if not set will default to the value provided by PHP finfo.
 *         The default is unset, so it is not listed in the Options array.
 */

public static $Options=array(
  'sign'=>0,
  'no_cache'=>0,
  'hidden'=>0,
  'redirect'=>0,
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
 * - 'match' - a regular expression which will be matched against HTTP_USER_AGENT
 *             to discover the operating system of the user
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
   'display'=>_("MS Windows 8, 8.1"),
   'match'=>'Windows NT 6[._][23]',
   'directory'=>'ms',
   'module'=>'W8',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'W8',
       'mime'=>'application/x-dosexec',
      ),
   ),
	
 'w7'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows 7"),
   'match'=>'Windows NT 6[._]1',
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'W7',
       'mime'=>'application/x-dosexec',
      ),
   ),
	
 'vista'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows Vista"),
   'match'=>'Windows NT 6[._]0',
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'Vista',
       'mime'=>'application/x-dosexec',
      ),
   ),
	
 'xp'=>array(
   'group' => "microsoft",
   'display'=>_("MS Windows XP SP3"),
   'match'=>'Windows NT 5[._]1',
   'directory'=>'ms',
   'module'=>'XP',
   'signer'=>'ms_windows_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'XP',
       'message' => _("MS Windows XP is no longer supported by Microsoft, therefore it can be unsecure and should not really be used."),
       'mime'=>'application/x-dosexec',
      ),
   ),
    
 'win-rt'=>array(
    'group' => "microsoft",
    'display'=>_("Windows RT"),
    'directory'=>'redirect_dev',
    'module'=>'RedirectDev',
    'options'=>array(
      'hidden'=>0,
      'redirect'=>1,
      ),
   ),
    
	
 'apple_m_lion'=>array(
    'group' => "apple",
    'display'=>_("Apple Mac OS X Mountain Lion"),
    'match'=>'Mac OS X 10[._]8',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'MacOS',
       'mime'=>'application/x-apple-aspen-config',
      ),
    ),
	
 'apple_lion'=>array(
    'group' => "apple",
    'display'=>_("Apple Mac OS X Lion"),
    'match'=>'Mac OS X 10[._]7',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'MacOS',
       'mime'=>'application/x-apple-aspen-config',
      ),
    ),
 'mobileconfig'=>array(
    'group' => "apple",     
    'display'=>_("Apple iOS mobile devices"),
    'match'=>'iOS|iPad',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>0,
       'device_id'=>'',
       'mime'=>'application/x-apple-aspen-config',
      ),
    ),
 'linux'=>array(
     'group' => "linux",
     'display'=>_("Linux"),
     'match'=>'Linux',
     'directory'=>'linux',
     'module' => 'Linux',
     'options'=>array(
       'mime'=>'application/x-sh',
      ),
   ),
 'android'=>array(
     'group' => "android",
     'display'=>_("Android"),
     'directory'=>'redirect_dev',
     'module'=>'RedirectDev',
     'options'=>array(
       'redirect'=>1,
      ),
   ),
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
    
 'xml-ttls-pap'=>array(
    'group' => "generic",
    'display'=>_("Generic profile TTLS-PAP"),
    'directory'=>'xml',
    'module'=>'XML_TTLS_PAP',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
    
 'xml-ttls-mschap2'=>array(
    'group' => "generic",
    'display'=>_("Generic profile TTLS-MSCHAPv2"),
    'directory'=>'xml',
    'module'=>'XML_TTLS_MSCHAP2',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
    
 'xml-peap'=>array(
    'group' => "generic",
    'display'=>_("Generic profile PEAP"),
    'directory'=>'xml',
    'module'=>'XML_PEAP',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
    
 'xml-tls'=>array(
    'group' => "generic",
    'display'=>_("Generic profile TLS"),
    'directory'=>'xml',
    'module'=>'XML_TLS',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
    
 'xml-pwd'=>array(
    'group' => "generic",
    'display'=>_("Generic profile PWD"),
    'directory'=>'xml',
    'module'=>'XML_PWD',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
 'xml-all'=>array(
    'group' => "generic",
    'display'=>_("Generic profile ALL EAPs"),
    'directory'=>'xml',
    'module'=>'XML_ALL',
    'options'=>array(
       'mime'=>'application/eap-config',
      ),
   ),
);
}
}
?>
