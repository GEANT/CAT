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
class Devices extends \core\common\Entity {

    const SUPPORT_EMBEDDED_RSA = 'RSA';
    const SUPPORT_EMBEDDED_ECDSA = 'ECDSA';
    const SUPPORT_EDUPKI = 'EDUPKI';

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
     * - 'sb_message' aplickable only in the distribuition of Silverbullet profiles,
     *         if defined will cause a display of the contents of this option as
     *         an additional message. If the 'message' option is also defined then
     *         the sb_message will be displayed in the same window AFTER the contents
     *         of the 'message' option if that one.
     * - 'device_id' - used in building the installer filename; when this option
     *         is not defined, the filename will use the index from 
     *         the listDevices array; when defined and not empty, it will be 
     *         used in place of this index; when defined as empty will cause
     *         the omission of the device part the filename.
     *         The default is unset, so it is not listed in the Options array.
     * - 'mime' - used to set the MIME type of the installer file;
     *         if not set will default to the value provided by PHP finfo.
     *         The default is unset, so it is not listed in the Options array.
     * - 'hs20' - if defined and equal to 1 will mark the device as potenially supporting
     *         Hotspot 2.0.
     * 
     * @var array
     */
    public static $Options = [
        'sign' => 0,
        'no_cache' => 0,
        'hidden' => 0,
        'redirect' => 0,
        'clientcert' => Devices::SUPPORT_EMBEDDED_RSA,
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
        \core\common\Entity::intoThePotatoes();
        $retArray = [
            'w10' => [
                'group' => "microsoft",
                'display' => _("MS Windows 10"),
                'match' => 'Windows NT 10',
                'directory' => 'ms',
                'module' => 'W8W10',
                'signer' => 'ms_windows_sign',
                'options' => [
                    'sign' => 1,
                    'device_id' => 'W10',
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'mime' => 'application/x-dosexec',
                ],
            ],
            'w8' => [
                'group' => "microsoft",
                'display' => _("MS Windows 8, 8.1"),
                'match' => 'Windows NT 6[._][23]',
                'directory' => 'ms',
                'module' => 'W8W10',
                'signer' => 'ms_windows_sign',
                'options' => [
                    'sign' => 1,
                    'device_id' => 'W8',
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'mime' => 'application/x-dosexec',
                ],
            ],
            'w7' => [
                'group' => "microsoft",
                'display' => _("MS Windows 7"),
                'match' => 'Windows NT 6[._]1',
                'directory' => 'ms',
                'module' => 'Vista7',
                'signer' => 'ms_windows_sign',
                'options' => [
                    'sign' => 1,
                    'device_id' => 'W7',
                    'mime' => 'application/x-dosexec',
                ],
            ],
            'vista' => [
                'group' => "microsoft",
                'display' => _("MS Windows Vista"),
                'match' => 'Windows NT 6[._]0',
                'directory' => 'ms',
                'module' => 'Vista7',
                'signer' => 'ms_windows_sign',
                'options' => [
                    'sign' => 1,
                    'device_id' => 'Vista',
                    'mime' => 'application/x-dosexec',
                ],
            ],
            'win-rt' => [
                'group' => "microsoft",
                'display' => _("Windows RT"),
                'directory' => 'redirect_dev',
                'module' => 'RedirectDev',
                'options' => [
                    'hidden' => 0,
                    'redirect' => 1,
                ],
            ],
            'apple_global' => [
                'group' => "apple",
                'display' => _("Apple device"),
                'match' => '(Mac OS X 1[01][._][0-9])|((iPad|iPhone|iPod);.*OS (\d+)_)',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'hs20' => 1,
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_catalina' => [
                'group' => "apple",
                'display' => _("Apple macOS Catalina"),
                'match' => 'Mac OS X 10[._]15',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_mojave' => [
                'group' => "apple",
                'display' => _("Apple macOS Mojave"),
                'match' => 'Mac OS X 10[._]14',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_hi_sierra' => [
                'group' => "apple",
                'display' => _("Apple macOS High Sierra"),
                'match' => 'Mac OS X 10[._]13',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'clientcert' => Devices::SUPPORT_EMBEDDED_ECDSA,
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_sierra' => [
                'group' => "apple",
                'display' => _("Apple macOS Sierra"),
                'match' => 'Mac OS X 10[._]12',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_el_cap' => [
                'group' => "apple",
                'display' => _("Apple OS X El Capitan"),
                'match' => 'Mac OS X 10[._]11',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_yos' => [
                'group' => "apple",
                'display' => _("Apple OS X Yosemite"),
                'match' => 'Mac OS X 10[._]10',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_mav' => [
                'group' => "apple",
                'display' => _("Apple OS X Mavericks"),
                'match' => 'Mac OS X 10[._]9',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_m_lion' => [
                'group' => "apple",
                'display' => _("Apple OS X Mountain Lion"),
                'match' => 'Mac OS X 10[._]8',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'apple_lion' => [
                'group' => "apple",
                'display' => _("Apple OS X Lion"),
                'match' => 'Mac OS X 10[._]7',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigOsX',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'OS_X',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter settings for certificate and there you need to enter the import PIN shown on this page. Later you will be prompted to enter your password to allow making changes to the profile, this time it is your computer password."),
                ],
            ],
            'mobileconfig' => [
                'group' => "apple",
                'display' => _("Apple iOS mobile device (iOS 7-11)"),
                'match' => '(iPad|iPhone|iPod);.*OS ([7-9]|1[0-1])_',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigIos7plus',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'iOS',
                    'mime' => 'application/x-apple-aspen-config',
                    'sb_message' => _("During the installation you will be first asked to enter your passcode - this is your device security code! Later on you will be prompted for the password to the certificate and there you need to enter the import PIN shown on this page."),
                ],
            ],
            'mobileconfig-56' => [
                'group' => "apple",
                'display' => _("Apple iOS mobile device (iOS 5 and 6)"),
                'match' => '(iPad|iPhone|iPod);.*OS [56]_',
                'directory' => 'apple_mobileconfig',
                'module' => 'MobileconfigIos5plus',
                'signer' => 'mobileconfig_sign',
                'options' => [
                    'hidden' => 1,
                    'sign' => 1,
                    'device_id' => 'iOS',
                    'mime' => 'application/x-apple-aspen-config',
                ],
            ],
            'linux' => [
                'group' => "linux",
                'display' => _("Linux"),
                'match' => 'Linux(?!.*Android)',
                'directory' => 'linux',
                'module' => 'Linux',
                'options' => [
                    'mime' => 'application/x-sh',
                ],
            ],
            'linux_sh' => [
                'group' => "linux",
                'display' => _("Linux"),
                'match' => 'Linux(?!.*Android)',
                'directory' => 'linux',
                'module' => 'LinuxSh',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/x-sh',
                ],
            ],
            'chromeos' => [
                'group' => "chrome",
                'display' => _("Chrome OS"),
                'match' => 'CrOS',
                'directory' => 'chromebook',
                'module' => 'Chromebook',
                'options' => [
                    'mime' => 'application/x-onc',
                    'message' => sprintf(_("After downloading the file, open the Chrome browser and browse to this URL: <a href='chrome://net-internals/#chromeos'>chrome://net-internals/#chromeos</a>. Then, use the 'Import ONC file' button. The import is silent; the new network definitions will be added to the preferred networks.")),
                ],
            ],
            'android_recent' => [
                'group' => "android",
                'display' => _("Android 11 and higher"),
                'match' => 'Android 1[1-9]',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'mime' => 'application/eap-config',
                    'hs20' => 1,
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "geteduroam",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=app.eduroam.geteduroam'>Google Play</a>, <a target='_blank' href='geteduroam-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],            
            
            'android_8_10' => [
                'group' => "android",
                'display' => _("Android 8 to 10"),
                'match' => 'Android ([89]|10)',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "geteduroam",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=app.eduroam.geteduroam'>Google Play</a>, <a target='_blank' href='geteduroam-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],  
            'android_4_7' => [
                'group' => "android",
                'display' => _("Android 4.3 to 7"),
                'match' => 'Android [4-7]',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],            
            
            'android_q' => [
                'group' => "android",
                'display' => _("Android 10.0 Q"),
                'match' => 'Android 10',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_pie' => [
                'group' => "android",
                'display' => _("Android 9.0 Pie"),
                'match' => 'Android 9',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_oreo' => [
                'group' => "android",
                'display' => _("Android 8.0 Oreo"),
                'match' => 'Android 8',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_nougat' => [
                'group' => "android",
                'display' => _("Android 7.0 Nougat"),
                'match' => 'Android 7',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_marshmallow' => [
                'group' => "android",
                'display' => _("Android 6.0 Marshmallow"),
                'match' => 'Android 6',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_lollipop' => [
                'group' => "android",
                'display' => _("Android 5.0 Lollipop"),
                'match' => 'Android 5',
                'directory' => 'xml',
                'module' => 'Lollipop',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_kitkat' => [
                'group' => "android",
                'display' => _("Android 4.4 KitKat"),
                'match' => 'Android 4\.[4-9]',
                'directory' => 'xml',
                'module' => 'KitKat',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_43' => [
                'group' => "android",
                'display' => _("Android 4.3"),
                'match' => 'Android 4\.3',
                'directory' => 'xml',
                'module' => 'KitKat',
                'options' => [
                    'hidden' => 1,
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("Before you proceed with installation on Android systems, please make sure that you have installed the %s application. This application is available from these sites: %s and will use the configuration file downloaded from CAT to create all necessary settings."),
                            "eduroamCAT",
                            "<a target='_blank' href='https://play.google.com/store/apps/details?id=uk.ac.swansea.eduroamcat'>Google Play</a>, <a target='_blank' href='https://www.amazon.com/dp/B01EACCX0S/'>Amazon Appstore</a>, <a target='_blank' href='eduroamCAT-stable.apk'>" . _("as local download") . "</a>"),
                ],
            ],
            'android_legacy' => [
                'group' => "android",
                'display' => _("Android"),
                'match' => 'Android',
                'directory' => 'redirect_dev',
                'module' => 'RedirectDev',
                'options' => [
                    'redirect' => 1,
                ],
            ],
            'eap-config' => [
                'group' => "eap-config",
                'display' => _("EAP config"),
                'directory' => 'xml',
                'module' => 'XMLAll',
                'options' => [
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("This option provides an EAP config XML file, which can be consumed by the eduroamCAT app for Android.")),
                ],
            ],
            'eap-generic' => [
                'group' => "eap-config",
                'display' => _("EAP generic"),
                'directory' => 'xml',
                'module' => 'Generic',
                'options' => [
                    'mime' => 'application/eap-config',
                    'message' => sprintf(_("This option provides a generic EAP config XML file, which can be consumed by the GetEduroam applications.")),
                    'hidden' => 1,
                ],
            ],
            'test' => [
                'group' => "other",
                'display' => _("Test"),
                'directory' => 'test_module',
                'module' => 'TestModule',
                'options' => [
                    'hidden' => 1,
                ],
            ],
        /*

            'xml-ttls-pap'=> [
                'group' => "generic",
                'display'=>_("Generic profile TTLS-PAP"),
                'directory'=>'xml',
                'module'=>'XML_TTLS_PAP',
                'options'=>[
                    'mime'=>'application/eap-config',
                ],
            ],

            'xml-ttls-mschap2'=> [
                'group' => "generic",
                'display'=>_("Generic profile TTLS-MSCHAPv2"),
                'directory'=>'xml',
                'module'=>'XML_TTLS_MSCHAP2',
                'options'=> [
                    'mime'=>'application/eap-config',
                ],
            ],

            'xml-peap'=> [
                'group' => "generic",
                'display'=>_("Generic profile PEAP"),
                'directory'=>'xml',
                'module'=>'XML_PEAP',
                    'options'=> [
                    'mime'=>'application/eap-config',
                ],
            ],

            'xml-tls'=> [
                'group' => "generic",
                'display'=>_("Generic profile TLS"),
                'directory'=>'xml',
                'module'=>'XML_TLS',
                'options'=> [
                    'mime'=>'application/eap-config',
                ],
            ],

            'xml-pwd'=> [
                'group' => "generic",
                'display'=>_("Generic profile PWD"),
                'directory'=>'xml',
                'module'=>'XML_PWD',
                'options'=> [
                    'mime'=>'application/eap-config',
                ],
            ],
                'xml-all'=> [
                'group' => "generic",
                'display'=>_("Generic profile ALL EAPs"),
                'directory'=>'xml',
                'module'=>'XML_ALL',
                'options'=> [
                    'mime'=>'application/eap-config',
                ],
            ],
        */
        ];
        \core\common\Entity::outOfThePotatoes();
        return $retArray;
    }
}
