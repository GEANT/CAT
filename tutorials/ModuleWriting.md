Writing device modules for the eduroam Configuration Assistant Tool
===================================================================

Introduction
------------
eduroam Configuration Assistant Tool (CAT) is a user-oriented system helping to configure various wireless devices for eduroam.

The CAT database contains information provided by local eduroam admins, i.e. items like supported EAP methods, trusted RADIUS server names, trusted server certificates, etc.

CAT installers are device dependant entities (Windows installers, XML profiles, etc.) which carry all institution related information. Such an installer is created when a user selects his/her institution, possibly also user group and one of supported devices. Installers are provided in a number of supported languages (please help in the translation effort).

* **Device**
	An operating system instance which covers a group of wireless client devices; it may be just a group of operating systems like "Microsoft Windows Vista and newer" or "Android smartphones" or "iOS devices"
* **Profile**
	A group of users which share the same network configuration parameters (except for user credentials), thus a profile shares the same supported EAP methods, the same trusted servers etc. Even if the entire institution needs only one profile it will always be created; installers are prepared per (profile, device) tuple.

What is a device module and how does it interface with the CAT system?
----------------------------------------------------------------------
Device modules use CAT API to access information in the CAT database and produce installer files.

The module MUST configure the device for accessing the the list of SSIDs passed to it by the CAT module API. The module publishes the set of EAP methods it can support, CAT API compares this to the prioritised list of EAP types supported by a given profile and returns the most appropriate one. The device module creates a configurator for this EAP method. If the device requires to be separately configured for WPA2/AES and WPA/TKIP then it should use the information passed by the API, specifying which encryptions must be supported. As a rule, if WPA/TKIP is specified, then WPA2/AES must also be configured for this SSID.

What does the CAT API do for device modules?
--------------------------------------------
Before passing control to the device module, the CAT system prepares a few things for the module to use. These include:

* interfacing with the user
* getting all configuration parameters from the CAT database
* creating a temporary working directory
* preparing certificate files and making them ready for storing in the working directory
* storing information files in the working directory (possibly changing the character set)

After the device module produces the installer file, it leaves it in its working directory and passes back its name. CAT API delivers the file to the user.

It is important to understand how the device module fits into the whole picture, so here is s short description. An external caller (for instance GUI::generateInstaller()) creates the module device instance and prepares its environment for a given user profile by calling DeviceConfig::setup() method. Finally, the function DeviceConfig::writeInstaller() is called and the returned path name is used for user download.

Directory structure and naming
------------------------------
All device modules reside in the devices directory. Each device module has its own subdirectory. If a device module requires additional files that will need to be copied to the working directory, then these files should be placed in the Files subdirectory of the module directory.

The name of the module directory may be arbitrary, but the name of the module file and the name of the device class must be synchronised. For instance, if the name of the module is TestModule, then it's source file should be called TestModule.php and the name of the class must be DeviceTestModule.

Naming is defined in the devices.php file, it is a configuration feature and is irrelevant external from the module point of view.

Device driver code
------------------
See the TestModule.php for a working example.

We assume, that you know how to write a device installer itself. If not, then this tutorial will not teach you that. It will only show how to get hold of parameters that you need to build into your code.

Typically, to produce an installer you need to know:

* the certificate of the CA which has signed the RADIUS server certificate
* the names of the trusted RADIUS servers
* the EAP method to be used
* the SSIDs to connect to

The installer will only work properly for users from one institution and, possibly one user group, it would be wise to display an appropriate warning. If an institution has only one user group, then it makes no sense to make users aware that this concept exists and device modules should probably try to hide this. The internal:profile_count can be used to check how many profiles a given institution has.

In some environments it is possible to display additional text information (perhaps a list of usage terms), it may be also possible to customise the installer graphics by adding the institution logo. All these will be available to the device module.

The device module class MUST extend the DeviceConfig class, thus obtaining access to all methods and properties provided by this class. Check the documentation of that class for a complete list.

The module class MUST define a constructor and this constructor MUST set $this->supportedEapMethods to to an array listing EAP methods supported by this particular device.

The module MUST define a writeInstaller() method which is to produce the actual installer file. All useful profile properties are provided within the device's attributes property (see DeviceConfig) and set by the setup(Profile $profile) method when a device module is being prepared to be called.

The writeInstaller method must create the installer in the form of a single file in the module.
