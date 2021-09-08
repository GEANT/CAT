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

namespace config;

class Master
{

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
         */
        const APPEARANCE = [
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
            'MOTD' => "If you can read this, then the administrator did not change the default MOTD in config/Master.php",
            // # signs before the colour code
            'colour1' => '#FFFFFF',
            'colour2' => '#0A698E',
            // the web server certificate may be checked by browsers against a CRL or OCSP Responder
            // to tell captive portal admins which hosts to allow, list the URLs here (they show up
            // in "About CAT" then)
            'webcert_CRLDP' => ['list', 'of', 'CRL', 'pointers'],
            'webcert_OCSP' => ['list', 'of', 'OCSP', 'pointers'],
            'skins' => ["modern", "example"],
            // get your key here: https://developers.google.com/maps/documentation/javascript/get-api-key?refresh=1
            'google_maps_api_key' => '',
            'privacy_notice_url' => 'https://www.eduroam.org/privacy/',
        ];
        const FUNCTIONALITY_LOCATIONS = [
            /** these can be either 
             *  - the string "LOCAL" (component is running in this installation
             *  - NULL (component does not live anywhere, trim functionality from display)
             *  - or an absolute URL to the base directory of an installation with the functionality aspect
             */
            'CONFASSISTANT_SILVERBULLET' => 'LOCAL',
            'CONFASSISTANT_RADIUS' => 'LOCAL',
            'DIAGNOSTICS' => 'LOCAL',
        ];
        /**
         * Various paths.
         * logdir: directory where all logs will be written to (debug and audit logs)
         * openssl: absolute path to the openssl executable. If you just fill in "openssl" the one from the system $PATH will be taken.
         * cat_base_url: this the relative URL path of the CAT installation, i.e. the part after 'https://<server>'. E.g. if your DocumentRoot is already the web/ subdir, this is "/"
         * @var array
         */
        const PATHS = [
            'logdir' => '/var/log/CAT/',
            'openssl' => 'openssl',
            'cat_base_url' => '/',
        ];
        /**
         * Configuration for the simpleSAMLphp instance which authenticates CAT administrative users.
         * ssp-path-to-autoloader: points to the simpleSAMLphp autoloader location
         * ssp-authsource: which authsource should we point to?
         * attribute in which authsource transmits unique user identifier. Required. If multi-valued, first submitted value is taken.
         * attribute in which authsource transmits user's mail address. Receiving this attribute is optional.
         * attribute in which authsource transmits user's real name. Receiving this attribute is optional.
         * @var array
         */
        const AUTHENTICATION = [
            'ssp-path-to-autoloader' => '/var/www/simplesamlphp/lib/_autoload.php',
            'ssp-authsource' => 'default-sp',
            'ssp-attrib-identifier' => 'eptid',
            'ssp-attrib-email' => 'mail',
            'ssp-attrib-name' => 'cn',
        ];
        /**
         * Configuration for GeoIP2 
         * Beware, the legacy version does not really work with IPv6 addresses
         * version: set to 2 if you wish to use GeoIP2, to 1 for the legacy version or set to 0 to turn off geolocation service
         * geoip2-path-to-autoloader: points to the GeoIP2 autoloader 
         * geoip2-path-to-db: points to the GeoIP2 city database
         * @var array
         */
        const GEOIP = [
            'version' => 2,
            'geoip2-path-to-autoloader' => '/usr/share/GeoIP2/vendor/autoload.php',
            'geoip2-path-to-db' => '/usr/share/GeoIP2/DB/GeoLite2-City.mmdb',
            'geoip2-license-key' => '',
        ];
        /**
         * Configures the host to use to send emails to the outside world. We assume
         * the host is able to listen on the new Submission port (TCP/587). 
         * host: Submission host
         * user: username for the login to the host. If NULL (and pass is also NULL)
         *       then no SMTP authentication will be triggered.
         * pass: password for the username
         * options: these may be some additional options, for instance setting that to:
         *     [
          'ssl' => [
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true
          ]
          ]
         *    will fix a certificate verification problem with sending mail to localhost
         * certfilename, keyfilename, keypass: if you want to send S/MIME signed 
         *    mails, just configure the signing cert with these parameters. All must
         *    be non-NULL for signing to happen. If you don't need a keypass, make 
         *    it an empty string instead.

         * @var array
         */
        const MAILSETTINGS = [// we always use Submission
            'host' => 'submission.capable.mta',
            'user' => 'mailuser',
            'pass' => 'mailpass',
            'options' => [],
            // in case the mails should be signed with S/MIME
            'certfilename' => NULL,
            'keyfilename' => NULL,
            'keypass' => NULL,
        ];
        /**
         * List of all supported languages in CAT. Comment some if you want to disable them
         * @var array
         */
        const LANGUAGES = [
            'bg' => ['display' => 'Български', 'locale' => 'bg_BG.utf8', 'latin_based' => FALSE],
            'ca' => ['display' => 'Català', 'locale' => 'ca_ES.utf8', 'latin_based' => TRUE],
            'cs' => ['display' => 'Čeština', 'locale' => 'cs_CZ.utf8', 'latin_based' => TRUE],
            'de' => ['display' => 'Deutsch', 'locale' => 'de_DE.utf8', 'latin_based' => TRUE],
            'el' => ['display' => 'Ελληνικά', 'locale' => 'el_GR.utf8', 'latin_based' => FALSE],
            'en' => ['display' => 'English(GB)', 'locale' => 'en_GB.utf8', 'latin_based' => TRUE],
            'es' => ['display' => 'Español', 'locale' => 'es_ES.utf8', 'latin_based' => TRUE],
            'et' => ['display' => 'Eesti', 'locale' => 'et_EE.utf8', 'latin_based' => TRUE],
            'fr' => ['display' => 'Français', 'locale' => 'fr_FR.utf8', 'latin_based' => TRUE],
            'hr' => ['display' => 'Hrvatski', 'locale' => 'hr_HR.utf8', 'latin_based' => TRUE],
            'hu' => ['display' => 'Magyar', 'locale' => 'hu_HU.utf8', 'latin_based' => TRUE],
            'it' => ['display' => 'Italiano', 'locale' => 'it_IT.utf8', 'latin_based' => TRUE],
            'nb' => ['display' => 'Norsk', 'locale' => 'nb_NO.utf8', 'latin_based' => TRUE],
            'pl' => ['display' => 'Polski', 'locale' => 'pl_PL.utf8', 'latin_based' => TRUE],
            'pt' => ['display' => 'Português', 'locale' => 'pt_PT.utf8', 'latin_based' => TRUE],
            'sl' => ['display' => 'Slovenščina', 'locale' => 'sl_SI.utf8', 'latin_based' => TRUE],
            'sr' => ['display' => 'Srpski', 'locale' => 'sr_RS@latin', 'latin_based' => TRUE],
            'fi' => ['display' => 'Suomi', 'locale' => 'fi_FI.utf8', 'latin_based' => TRUE],
            'tr' => ['display' => 'Türkçe', 'locale' => 'tr_TR.utf8', 'latin_based' => TRUE],
// For the following languages, partial translations exist in Transifex, but
// they are not complete enough for display. There are even more in the "translation/" subdir.
//
// Contact the authors if you want to know the current state of translation of these languages.
//
//      'nl' => ['display' => 'Nederlands',  'locale' => 'nl_NL.utf8',    'latin_based' => TRUE],
//      'sv' => ['display' => 'Svenska',     'locale' => 'sv_SE.utf8',    'latin_based' => TRUE],
//      'cy' => ['display' => 'Cymraeg',     'locale' => 'cy_GB.utf8',    'latin_based' => TRUE],
//      'gl' => ['display' => 'Galego',      'locale' => 'gl_ES.utf8',    'latin_based' => TRUE],
//      'lt' => ['display' => 'lietuvių',    'locale' => 'lt_LT.utf8',    'latin_based' => TRUE],
//      'sk' => ['display' => 'Slovenčina',  'locale' => 'sk_SK.utf8',    'latin_based' => TRUE],
        ];
        /**
         * Set of database connection details. The third entry is only needed if you set $ENFORCE_EXTERNAL_DB_SYNC to TRUE.
         * See the extra notes on external sync enforcement below.
         * 
         * @var array
         */
        const DB = [
            // this slice of DB use will deal with all tables in the schema except
            // downloads and user_options. If you give the user below exclusively
            // read-only access, all data manipulation will fail; only existing state
            // can be worked with.
            // if set to readonly, all edit and delete buttons are removed
            'INST' => [
                'host' => 'localhost',
                'db' => 'cat',
                'user' => 'kitty',
                'pass' => 'somepass',
                'readonly' => FALSE,],
            // this DB stores diagnostics data. The connection details can be
            // identical to INST as there is no table overlap
            'DIAGNOSTICS' => [
                'host' => 'localhost',
                'db' => 'cat',
                'user' => 'kitty',
                'pass' => 'somepass',
                'readonly' => FALSE,],
            // this slice of DB user is about the downloads table. The corresponding
            // DB user should have write access to update statistics and the cache
            // locations of installers. 
            // Marking this as READONLY does not make sense!
            'FRONTEND' => [
                'host' => 'localhost',
                'db' => 'cat',
                'user' => 'kitty',
                'pass' => 'somepass',
                'readonly' => FALSE,],
            // this slice of DB use is about user management in the user_options
            // table. Giving the corresponding user only read-only access means that
            // all user properties have to "magically" occur in the table by OOB
            // means (custom queries are also possible of course).
            // Marking this as readonly replaced the obsolete config parameter "userdb-readonly"
            'USER' => [
                'host' => 'localhost',
                'db' => 'cat',
                'user' => 'kitty',
                'pass' => 'somepass',
                'readonly' => FALSE,],
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
            // Marking this as readonly currently has no effect, as we only ever do SELECTs on that database
            'EXTERNAL' => [
                'host' => 'customerdb.otherhost.example',
                'db' => 'customer_db',
                'user' => 'customerservice',
                'pass' => '2lame4u',
                'readonly' => TRUE,],
            'enforce-external-sync' => TRUE,
        ];
        /**
         * Maximum size of files to be uploaded. Clever people can circumvent this; in the end, the hard limit is configured in php.ini
         * @var integer
         */
        const MAX_UPLOAD_SIZE = 10000000;
        /**
         * Verbosity of some of the core code. The following debug levels are supported:
         *   1 = production (silence)
         *   2 = normal debug
         *   3 = more debug
         *   4 = annoyingly much debug output
         *   5 = way too much debug output (level 4 + SQL query dump)
         *
         * @var integer
         *
         */
        const DEBUG_LEVEL = 5;
        const SUPERADMINS = [
            'admin',
            'eptid:someuser',
            'http://sommeopenid.example/anotheruser',
            'I do not care about security!',
        ];
}
