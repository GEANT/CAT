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
 * This file contains the Devices class.
 *
 * @package ModuleWriting
 */
namespace devices;
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
 *         supports this. The default settings for Microsoft and Apple systems
 *         is 1, since without signing, installation makes liitle sense. Be aware
 *         that you need to set up signers and have proper certificates, if
 *         you do not want to do that and you are just testing CAT, then you can
 *         switch sign to 0, of course.
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

public static $Options=[
  'sign'=>0,
  'no_cache'=>0,
  'hidden'=>0,
  'redirect'=>0,
];

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
    return [
 'w10'=>[
   'group' => "microsoft",
   'display'=>_("MS Windows 10"),
   'match'=>'Windows NT 10',
   'directory'=>'ms',
   'module'=>'W10',
   'signer'=>'ms_windows_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'W10',
       'mime'=>'application/x-dosexec',
      ],
   ],
	
 'w8'=>[
   'group' => "microsoft",
   'display'=>_("MS Windows 8, 8.1"),
   'match'=>'Windows NT 6[._][23]',
   'directory'=>'ms',
   'module'=>'W8',
   'signer'=>'ms_windows_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'W8',
       'mime'=>'application/x-dosexec',
      ],
   ],
	
 'w7'=>[
   'group' => "microsoft",
   'display'=>_("MS Windows 7"),
   'match'=>'Windows NT 6[._]1',
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'W7',
       'mime'=>'application/x-dosexec',
      ],
   ],
	
 'vista'=>[
   'group' => "microsoft",
   'display'=>_("MS Windows Vista"),
   'match'=>'Windows NT 6[._]0',
   'directory'=>'ms',
   'module'=>'Vista7',
   'signer'=>'ms_windows_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'Vista',
       'mime'=>'application/x-dosexec',
      ],
   ],
	
 'win-rt'=>[
    'group' => "microsoft",
    'display'=>_("Windows RT"),
    'directory'=>'redirect_dev',
    'module'=>'RedirectDev',
    'options'=>[
      'hidden'=>0,
      'redirect'=>1,
      ],
   ],
    
    
 'apple_sierra'=>array(
    'group' => "apple",
    'display'=>_("Apple macOS Sierra"),
    'match'=>'Mac OS X 10[._]12',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ),
    ),
	

 'apple_el_cap'=>[
    'group' => "apple",
    'display'=>_("Apple OS X El Capitan"),
    'match'=>'Mac OS X 10[._]11',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>array(
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ),
    ],

 'apple_yos'=>[
    'group' => "apple",
    'display'=>_("Apple OS X Yosemite"),
    'match'=>'Mac OS X 10[._]10',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],

 'apple_mav'=>[
    'group' => "apple",
    'display'=>_("Apple OS X Mavericks"),
    'match'=>'Mac OS X 10[._]9',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],

 'apple_m_lion'=>[
    'group' => "apple",
    'display'=>_("Apple OS X Mountain Lion"),
    'match'=>'Mac OS X 10[._]8',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],
	
 'apple_lion'=>[
    'group' => "apple",
    'display'=>_("Apple OS X Lion"),
    'match'=>'Mac OS X 10[._]7',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_os_x',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'OS_X',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],
        
 'mobileconfig'=>[
    'group' => "apple",     
    'display'=>_("Apple iOS mobile devices"),
    'match'=>'(iPad|iPhone|iPod);.*OS ([7-9]|1[0-5])_',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_ios',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'iOS',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],

 'mobileconfig-56'=>[
    'group' => "apple",
    'display'=>_("Apple iOS mobile devices (iOS 5 and 6)"),
    'match'=>'(iPad|iPhone|iPod);.*OS [56]_',
    'directory'=>'apple_mobileconfig',
    'module'=>'mobileconfig_ios_56',
    'signer'=>'mobileconfig_sign',
    'options'=>[
       'sign'=>1,
       'device_id'=>'iOS',
       'mime'=>'application/x-apple-aspen-config',
      ],
    ],

        
 'linux'=>[
     'group' => "linux",
     'display'=>_("Linux"),
     'match'=>'Linux(?!.*Android)',
     'directory'=>'linux',
     'module' => 'Linux',
     'options'=>[
       'mime'=>'application/x-sh',
      ],
   ],

 'chromeos'=>[
    'group' => "chrome",
    'display'=>_("Chrome OS"),
    'match'=>'CrOS',
    'directory'=>'chromebook',
    'module'=>'chromebook',
    'options'=>[
       'mime'=>'application/x-onc',
       'message'=>sprintf(_("After downloading the file, open the Chrome browser and browse to this URL: <a href='chrome://net-internals/#chromeos'>chrome://net-internals/#chromeos</a>. Then, use the 'Import ONC file' button. The import is silent; the new network definitions will be added to the preferred networks.")),
      ],
   ],
        
 'android_marshmallow'=>[
    'group' => "android",
    'display'=>_("Android 6.0 Marshmallow"),
     'match'=>'Android 6\.[0-9]',
    'directory'=>'xml',
    'module'=>'Lollipop',
    'options'=>[
       'mime'=>'application/eap-config',
       'message'=>sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from %s, %s and %s, and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>",
                            "<a target='_blank' href='unbeknownst'>Amazon Appstore</a>",
                            "<a target='_blank' href='eduroamCAT-stable.apk'>"._("as local download")."</a>"),
      ],
   ],

 'android_lollipop'=>[
    'group' => "android",
    'display'=>_("Android 5.0 Lollipop"),
     'match'=>'Android 5\.[0-9]',
    'directory'=>'xml',
    'module'=>'Lollipop',
    'options'=>[
       'mime'=>'application/eap-config',
       'message'=>sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from %s, %s and %s, and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>",
                            "<a target='_blank' href='unbeknownst'>Amazon Appstore</a>",
                            "<a target='_blank' href='eduroamCAT-stable.apk'>"._("as local download")."</a>"),
      ],
   ],

 'android_kitkat'=>[
    'group' => "android",
    'display'=>_("Android 4.4 KitKat"),
     'match'=>'Android 4\.[4-9]',
    'directory'=>'xml',
    'module'=>'KitKat',
    'options'=>[
       'mime'=>'application/eap-config',
       'message'=>sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from %s, %s and %s, and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>",
                            "<a target='_blank' href='unbeknownst'>Amazon Appstore</a>",
                            "<a target='_blank' href='eduroamCAT-stable.apk'>"._("as local download")."</a>"),
      ],
   ],


 'android_43'=>[
    'group' => "android",
    'display'=>_("Android 4.3"),
     'match'=>'Android 4\.3',
    'directory'=>'xml',
    'module'=>'KitKat',
    'options'=>[
       'mime'=>'application/eap-config',
       'message'=>sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from %s, %s and %s, and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>",
                            "<a target='_blank' href='unbeknownst'>Amazon Appstore</a>",
                            "<a target='_blank' href='eduroamCAT-stable.apk'>"._("as local download")."</a>"),
      ],
   ],

 'android_legacy'=>[
     'group' => "android",
     'display'=>_("Android"),
     'match'=>'Android',
     'directory'=>'redirect_dev',
     'module'=>'RedirectDev',
     'options'=>[
       'redirect'=>1,
      ],
   ],

 'eap-config'=>[
    'group' => "eap-config",
    'display'=>_("EAP config"),
    'directory'=>'xml',
    'module'=>'XML_ALL',
    'options'=>[
       'mime'=>'application/eap-config',
       'message'=>sprintf(_("This option provides a generic EAP config XML file, which can be consumed by dedicated applications like eduroamCAT for Android and Linux platforms. This is still an experimental feature.")),
      ],
    ],

 'test'=>[
    'group' => "other",
    'display'=>_("Test"),
    'directory'=>'test_module',
    'module'=>'TestModule',
    'options'=>[
       'hidden'=>1,
      ],
   ],


/*    
    
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
*/
];
}
}
