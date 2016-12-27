Installing and Configuring CAT
==============================

Packages required prior to installing CAT
-----------------------------------------
The CAT generates installers for numerous operating systems. Consequently, many console tools need to be installed for all the profile generators to work correctly. The prerequisites are:

* Apache2 Web Server
* MySQL/MariaDB Server
* PHP 7.0.0 or higher; on Ubuntu also the "php7.0-intl" package
* Required PHP extensions: "gettext", "openssl", "PECL:IMagick", "GD" and "MySQL"
* Optional PHP extensions: "GeoIP" (deprecated v1 GeoIP API)
* simpleSAMLphp
* NSIS 2.46 or higher - either as a native Linux binary or on Wine
* zip
* wpa_supplicant (eapol_test utility)
* OpenSSL

Configuring the required prerequisite packages
----------------------------------------------
Here are some extra configuration hints for these packages:

* Operating System

	language display needs the corresponding locales to be installed (check config/config-template.php for the exact list of locales that CAT can support right now)
* Apache

	the Directory for installer downloads (configurable, defaults to web/downloads/ ) needs to have "AllowOverrides FileInfo" set

	the directories under web/ need to be accessible from DocumentRoot
	
	the CAT log dir (configurable, defaults to /var/log/CAT/ ) needs to be accessible for writing
	
	for general server hardening, the following vhost configuration tokens should be set: "ServerSignature Off" and "ServerTokens ProductOnly"
	
	if you want to use client certificates for administrative user authentication, be sure set a sufficiently large SSL Renegotiation Buffer size (e.g. SSLRenegBufferSize 10486000 for 10 MB max. upload size)
	
	There are custom error pages for 404 etc. in web/404.php etc. If you want to use them, set ErrorDocument 404 /.../404.php etc. in your Apache virtual host config.
* PHP

	for general server hardening, the following option should be set in php.ini: "expose_php 0"

	for cookie security, the following options should be set in php.ini: "session.cookie_httponly 1" and "session.cookie_secure 1"
* simpleSAMLphp

	configure it as a service provider, authenticating towards an IdP of your choice. Attribute mapping is defined in config.php
* NSIS

	needs to have the plug-in "nsArray"
	
	"makensis" needs to be configured in the config/config.php file and executable
* GeoIP

	API Version 1:

	best install as a system package or use instructions from http://dev.maxmind.com/geoip/downloadable#PHP-7 or http://php.net/manual/en/geoip.installation.php
	
	download GeoLiteCity and GeoLiteCityv6 databases from http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz and http://geolite.maxmind.com/download/geoip/database/GeoLiteCityv6.dat.gz
	
	unzip them and copy into GeoIP directory changing names to GeoCity.dat and GeoCityv6.dat (the directory is /usr/share/GeoIP or something similar, GeoIP will display an error message if the database is missing and you will be able to guess the required location).
	
	arrange for downloads each month (databases are udated on the first Thursday of each month)
	
	API Version 2:
	
	TBD

Installing CAT
--------------
1.  unpack the distribution
2.  create the config/config.php file from the supplied template config-template.php
3.  create the devices/devices.php file from the supplied template
4.  on a MySQL/MariaDB server, create the databases as per the schema definition in schema/schema.sql
5.  make sure that you can connect to that database
6.  make sure that the web/downloads directory exists and is writeable to the Apache web server user
7.  make sure that simplesamlphp is installed
8.  make sure that simplesamlphp openid module is enabled and google (or any IdP of your choice) is uncommented in authsources
9.  using your browser, check if the main interface is running (web subdirectory)
10. if so, go to the master management page to have your system prerequisites checked (web/admin/112365365321.php)

Configuring CAT
---------------
After creating config.php as above, adapt it to your needs and the realities on your server. A full description of the config options in this file can be read by clicking on "Classes: Config" on the left-hand side. In particular, pay attention to the following:

* reference the autoloader of your simpleSAMLphp installation correctly in config.php
* enter the connection details to the database(s)

The device configuration file is in devices/devices.php. There is a template file devices-template.php - you can simply copy it to have a devices.php. Unless you want to disable specific device modules, or have custom ways to digitally sign installers, it is not necessary to change this file.

Customisation / Look and Feel
-----------------------------
CAT ships with a default look-and-feel for the eduroam consortium. You can adapt most of its appearance to your local needs by changing colours and images. The sources for the images are scattered around the source tree. Here is a list of LOGOs to edit:

* web/resources/images/consortium_logo.png (website main logo)
* web/resources/images/gradient-bg.png (gradient top-down for the adverising 'film roll'
* web/resources/images/screenshots/* (sample installers on 'film roll' - supplied ones carry digital sig from TERENA and eduroam logo; might not be appropriate for your use
* web/favicon.ico (website favicon)
* devices/ms/Files/eduroam_150.bmp (logo to embed in Microsoft installers)
* devices/ms/Files/eduroam32.ico (window icon for Microsoft installers)
* devices/ms/Files/cat_bg.bmp (background for front page on windows installers)

The colours are all configured in the web/resources/css/ directory.

* The default eduroam colour number 1 is #BCD7E8 (light blue). Change it to your own colours as needed.
* The default eduroam colour number 2 is #0A698E (dark blue). Change it to your own colours as needed.

