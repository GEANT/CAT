<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php

/**
 * This is the main (and currently: only) configuration file for CAT
 *
 * @package Configuration
 */

/**
 * This classes' members hold the configuration for CAT
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Configuration
 */
const CONFIG = [

    /**
     * Defines parameters how this tool will present itself to users
     * productname: short display name of the tool
     * productname_long: long display name of the tool
     * from-mail: the "From" in email addresses sent by the tool. Typically an unattended mailbox only for sending.
     * support-contact/url: URL pointing to CAT support, can be an email address, possibly with some additional attrinutes like body or a help page address
     * support-contact/display: the displayed part of the support contact link.
     * support-contact/developer-mail: email address where development questions should be addressed to
     * abuse-mail: email address where copyright holders can complain. Should be read by a human.
     * defaultlocale: language to use if user has no preferences in his browser, nor explicitly selects a language
     * @var array 
     */
    'APPEARANCE' => [
        'productname' => 'eduroam CAT',
        'productname_long' => 'eduroam Configuration Assistant Tool',
        'from-mail' => 'cat-invite@your-cat-installation.example',
        'support-contact' => [
            'url' => 'mailto:cat-support@our-cat-installation.example?body=Only%20English%20language%20please!',
            'display' => 'cat-support@our-cat-installation.example',
            'developer-mail' => 'cat-develop@our-cat-installation.example',
        ],
        'abuse-mail' => 'my-abuse-contact@your-cat-installation.example',
        'invitation-bcc-mail' => NULL,
        'defaultlocale' => 'en',
        'MOTD' => "Release Candidate. All bugs to be shot on sight!",
        // # signs before the colour code
        'colour1' => '#BCD7E8',
        'colour2' => '#0A698E',
        // the web server certificate may be checked by browsers against a CRL or OCSP Responder
        // to tell captive portal admins which hosts to allow, list the URLs here (they show up
        // in "About CAT" then)
        'webcert_CRLDP' => ['list', 'of', 'CRL', 'pointers'],
        'webcert_OCSP' => ['list', 'of', 'OCSP', 'pointers'],
        'skins' => ["classic", "eduroam2016", "example"],
        // get your key here: https://developers.google.com/maps/documentation/javascript/get-api-key?refresh=1
        'google_maps_api_key' => '',
    ],
    
    'FUNCTIONALITY_LOCATIONS' => [
        /** these can be either 
         *  - the string "LOCAL" (component is running in this installation
         *  - NULL (component does not live anywhere, trim functionality from display)
         *  - or an absolute URL to the base directory of an installation with the functionality aspect
         */
        'CONFASSISTANT' => 'LOCAL',
        'DIAGNOSTICS' => 'LOCAL',
    ],

    /**
     * Various paths.
     * logdir: directory where all logs will be written to (debug and audit logs)
     * openssl: absolute path to the openssl executable. If you just fill in "openssl" the one from the system $PATH will be taken.
     * @var array
     */
    'PATHS' => [
        'logdir' => '/var/log/CAT/',
        'openssl' => 'openssl',
    ],

    /**
     * Configuration for the simpleSAMLphp instance which authenticates CAT administrative users.
     * ssp-path-to-autoloader: points to the simpleSAMLphp autoloader location
     * ssp-authsource: which authsource should we point to?
     * attribute in which authsource transmits unique user identifier. Required. If multi-valued, first submitted value is taken.
     * attribute in which authsource transmits user's mail address. Receiving this attribute is optional.
     * attribute in which authsource transmits user's real name. Receiving this attribute is optional.
     * @var array
     */
    'AUTHENTICATION' => [
        'ssp-path-to-autoloader' => '/srv/www/simplesamlphp/lib/_autoload.php',
        'ssp-authsource' => 'default-sp',
        'ssp-attrib-identifier' => 'eptid',
        'ssp-attrib-email' => 'mail',
        'ssp-attrib-name' => 'cn',
    ],

    /**
      * Configuration for GeoIP2 
      * Beware, the legacy version does not really work with IPv6 addresses
      * version: set to 2 if you wish to use GeoIP2, to 1 for the legacy version or set to 0 to turn off geolocation service
      * geoip2-path-to-autoloader: points to the GeoIP2 autoloader 
      * geoip2-path-to-db: points to the GeoIP2 city database
      * @var array
      */
      
    'GEOIP' => [
        'version' => 0,
        'geoip2-path-to-autoloader' => '/usr/share/GeoIP2/vendor/autoload.php',
        'geoip2-path-to-db' => '/usr/share/GeoIP2/DB/GeoLite2-City.mmdb',
    ],

    /**
     * Configures the host to use to send emails to the outside world. We assume
     * the host is able to listen on the new Submission port (TCP/587). 
     * host: Submission host
     * user: username for the login to the host
     * pass: password for the username
     * certfilename, keyfilename, keypass: if you want to send S/MIME signed 
     *    mails, just configure the signing cert with these parameters. All must
     *    be non-NULL for signing to happen. If you don't need a keypass, make 
     *    it an empty string instead.

     * @var array
     */
    'MAILSETTINGS' => [ // we always use Submission
        'host' => 'submission.capable.mta',
        'user'=> 'mailuser',
        'pass' => 'mailpass',
        // in case the mails should be signed with S/MIME
        'certfilename' => NULL,
        'keyfilename' => NULL,
        'keypass' => NULL,

    ],
    
    /**
     * List of all supported languages in CAT. Comment some if you want to disable them
     * @var array
     */
    'LANGUAGES' => [
      'bg' => ['display' => 'Български',   'locale' => 'bg_BG.utf8',    'latin_based' => FALSE],
      'ca' => ['display' => 'Català',      'locale' => 'ca_ES.utf8',    'latin_based' => TRUE],
      'cs' => ['display' => 'Čeština',     'locale' => 'cs_CZ.utf8',    'latin_based' => TRUE],
      'de' => ['display' => 'Deutsch',     'locale' => 'de_DE.utf8',    'latin_based' => TRUE],
      'el' => ['display' => 'Ελληνικά',    'locale' => 'el_GR.utf8',    'latin_based' => FALSE],
      'en' => ['display' => 'English(GB)', 'locale' => 'en_GB.utf8',    'latin_based' => TRUE],
      'es' => ['display' => 'Español',     'locale' => 'es_ES.utf8',    'latin_based' => TRUE],
      'fr' => ['display' => 'Français',    'locale' => 'fr_FR.utf8',    'latin_based' => TRUE],
      'gl' => ['display' => 'Galego',      'locale' => 'gl_ES.utf8',    'latin_based' => TRUE],
      'hr' => ['display' => 'Hrvatski',    'locale' => 'hr_HR.utf8',    'latin_based' => TRUE],
      'it' => ['display' => 'Italiano',    'locale' => 'it_IT.utf8',    'latin_based' => TRUE],
      'lt' => ['display' => 'lietuvių',    'locale' => 'lt_LT.utf8',    'latin_based' => TRUE],
      'nb' => ['display' => 'Norsk',       'locale' => 'nb_NO.utf8',    'latin_based' => TRUE],
      'pl' => ['display' => 'Polski',      'locale' => 'pl_PL.utf8',    'latin_based' => TRUE],
      'sl' => ['display' => 'Slovenščina', 'locale' => 'sl_SI.utf8',    'latin_based' => TRUE],
      'sr' => ['display' => 'Srpski',      'locale' => 'sr_RS@latin',   'latin_based' => TRUE],
      'fi' => ['display' => 'Suomi',       'locale' => 'fi_FI.utf8',    'latin_based' => TRUE],
      'hu' => ['display' => 'Magyar',      'locale' => 'hu_HU.utf8',    'latin_based' => TRUE],
      'pt' => ['display' => 'Português',   'locale' => 'pt_PT.utf8',    'latin_based' => TRUE],

// For the following languages, partial translations exist in Transifex, but
// they are not complete enough for display. Their Transifex content is not
// necessarily ported to SVN yet. Contact the authors if you want the current
// state of translation of these languages.
//
// these two were in for 1.0 but didn't make 1.1
//      'sk' => ['display' => 'Slovenčina',  'locale' => 'sk_SK.utf8',    'latin_based' => TRUE],
//
// and these were never complete
//
//      'nl' => ['display' => 'Nederlands', 'locale' => 'nl_NL.utf8',    'latin_based' => TRUE],
//      'sv' => ['display' => 'Svenska', 'locale' => 'sv_SE.utf8',    'latin_based' => TRUE],
//      'cy' => ['display' => 'Cymraeg', 'locale' => 'cy_GB.utf8',    'latin_based' => TRUE],
    ],


    /**
     * Set of database connection details. The third entry is only needed if you set $ENFORCE_EXTERNAL_DB_SYNC to TRUE.
     * See the extra notes on external sync enforcement below.
     * 
     * @var array
     */
    'DB' => [
        // this slice of DB use will deal with all tables in the schema except
        // downloads and user_options. If you give the user below exclusively
        // read-only access, all data manipulation will fail; only existing state
        // can be worked with.
        'INST' => [
            'host' => 'db.host.example',
            'db' => 'cat',
            'user' => 'someuser',
            'pass' => 'somepass'],
        // this slice of DB user is about the downloads table. The corresponding
        // DB user should have write access to update statistics and the cache
        // locations of installers.
        'FRONTEND' => [
            'host' => 'db.host.example',
            'db' => 'cat',
            'user' => 'someuser',
            'pass' => 'somepass'],
        // this slice of DB use is about user management in the user_options
        // table. Giving the corresponding user only read-only access means that
        // all user properties have to "magically" occur in the table by OOB
        // means (custom queries are also possible of course).
        'USER' => [
            'host' => 'db.host.example',
            'db' => 'cat',
            'user' => 'someuser',
            'pass' => 'somepass'],
        /*   If you use this tool in conjunction with an external customer management database, you can configure that every 
         * institution entry in CAT MUST correspond to a customer entry in an external database. If you want this, set this
         * config variable to TRUE.
         * ### BEWARE: You need to write custom code for the mapping of CAT IDs to the external DB YOURSELF. ###
         * ### The functions where you need to add custom code are:
         * 
         * Federation::listExternalEntities();
         * Federation::getExternalDBEntityDetails($external_id);
         * IdP::getExternalDBSyncCandidates();
         * IdP::getExternalDBSyncState();
         * IdP::setExternalDBId($identifier);
         * 
         * The code for the consortium "eduroam" is already written and may serve as template. See the functions in question. */
        'EXTERNAL' => [
            'host' => 'customerdb.otherhost.example',
            'db' => 'customer_db',
            'user' => 'customerservice',
            'pass' => '2lame4u'],
         'enforce-external-sync' => TRUE,
         /* if you feed your user database from a third-party source and do not want CAT to update it on its own, you can 
          * make it read-only
          */
         'userdb-readonly' => FALSE,
    ],

    /**
     * Maximum size of files to be uploaded. Clever people can circumvent this; in the end, the hard limit is configured in php.ini
     * @var int
     */
    'MAX_UPLOAD_SIZE' => 10000000,

    /**
     * Verbosity of some of the core code. The following debug levels are supported:
     *   1 = production (silence)
     *   2 = normal debug
     *   3 = more debug
     *   4 = annoyingly much debug output
     *   5 = way too much debug output (level 4 + SQL query dump)
     *
     * @var int
     *
     */
    'DEBUG_LEVEL' => 5,

    'SUPERADMINS' =>  [
        'eptid:someuser',
        'http://sommeopenid.example/anotheruser',
        'I do not care about security!',
    ],
];
