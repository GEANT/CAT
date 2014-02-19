<?php

/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
class Config {

    /**
     * Defines parameters how this tool will present itself to users
     *
     * - productname: short display name of the tool
     *
     * - productname_long: long display name of the tool
     *
     * - from-mail: the "From" in email addresses sent by the tool. Typically an unattended mailbox only for sending.
     *
     * - admin-mail: email address where users can complain and comment to. Should be read by a human.
     *
     * - invitation-bcc-mail: if set, will send all invitations with a bcc to this address. Generates LOTS of mostly unncessary email to that address.
     *
     * - defaultlocale: language to use if user has no preferences in his browser, nor explicitly selects a language
     *
     * - MOTD: message of the day - will be displayed in the header of all pages if set. Useful for service announcements.
     *
     * @var string[] 
     */
    public static $APPEARANCE = array(
        'productname'         => 'eduroam CAT',
        'productname_long'    => 'eduroam Configuration Assistant Tool',
        'from-mail'           => 'cat-invite@your-cat-installation.example',
        'admin-mail'          => 'admin@your-cat-installation.example',
        'invitation-bcc-mail' => NULL,
        'defaultlocale'       => 'en',
        'MOTD'                => "Release Candidate. All bugs to be shot on sight!",
    );

    /**
     * Defines various general parameters of the roaming consortium.
     *
     * - name: the display name of the consortium
     *
     * - ssid: an array of default SSIDs for this consortium; they are automatically added to all installers.
     *
     * - tkipsupport: whether the default SSIDs should be configured for WPA/TKIP and WPA2/AES (TRUE) or only for WPA2/AES (FALSE)
     *
     * - homepage: URL of the consortium's general homepage.
     *
     * - signer_name: if installers are configured for digital signature, this parameter should contain the "O" name in the certificate. If left empty, signatures are not advertised even if configured and working
     *
     * - allow_self_service_registration: if set to NULL, federation admins need to invite new inst admins manually. If set to a federation ID string, e.g. "DE" for Germany, new admins can self-register and will be put into that federation.
     *
     * - registration_API_keys: allows select federations to make bulk registrations for new IdPs (e.g. if they have
     *                        an own, opaque, customer management system. The API will be documented at a later stage
     *
     * LOGOS: there are several variants of the consortium logo scattered in the
     *        source. Please change them at the appropriate places:
     *        - web/resources/images/consortium_logo.png
     *        - web/favicon.ico
     *        - devices/ms/Files/eduroam_150.bmp
     *        - devices/ms/Files/eduroam32.ico
     * 
     * @var array
     */
    public static $CONSORTIUM = array(
        'name'                     => 'eduroam',
        'tkipsupport'              => TRUE,
        'homepage'                 => 'http://www.eduroam.org',
        'signer_name'              => 'TERENA',
        'selfservice_registration' => NULL,
#        'deployment-voodoo'        => "Operations Team",
        'ssid'                     => array('eduroam'),
        'registration_API_keys'    => array (
            // 'secretvalue' => 'UK',
            // 'othervalue' => 'DE',
        ),
    );

    /**
     * Various paths.
     *
     * - logdir: directory where all logs will be written to (debug and audit logs)
     *
     * - openssl: absolute path to the openssl executable. If you just fill in "openssl" the one from the system $PATH will be taken.
     * - rad_eap_test: absolute path to the rad_eap_test executable. If you just fill in "rad_eap_test" the one from the system $PATH will be taken.
     *
     * @var string[]
     */
    public static $PATHS = array(
        'logdir'  => '/var/log/CAT/',
        'openssl' => 'openssl',
        'rad_eap_test' => 'rad_eap_test',
    );

    /**
     * Configuration for the simpleSAMLphp instance which authenticates CAT administrative users.
     *
     * - ssp-path-to-autoloader: points to the simpleSAMLphp autoloader location
     *
     * - ssp-authsource: which authsource should we point to?
     *
     * - attribute in which authsource transmits unique user identifier. Required. If multi-valued, first submitted value is taken.
     *
     * - attribute in which authsource transmits user's mail address. Receiving this attribute is optional.
     *
     * - attribute in which authsource transmits user's real name. Receiving this attribute is optional.
     *
     * @var string[]
     */
    public static $AUTHENTICATION = array(
        'ssp-path-to-autoloader' => '/srv/www/simplesamlphp/lib/_autoload.php',
        'ssp-authsource'         => 'default-sp',
        'ssp-attrib-identifier'  => 'eptid',
        'ssp-attrib-email'       => 'mail',
        'ssp-attrib-name'        => 'cn',
    );

    /**
     * List of all supported languages in CAT. Comment some if you want to disable them
     *
     * @var array[]
     */
    public static $LANGUAGES = array(
      'ca' => array('display' => 'Català',      'locale' => 'ca_ES.utf8'),
      'de' => array('display' => 'Deutsch',     'locale' => 'de_DE.utf8'),
      'en' => array('display' => 'English(GB)', 'locale' => 'en_GB.utf8'),
      'es' => array('display' => 'Español',     'locale' => 'es_ES.utf8'),
      'fr' => array('display' => 'Français',    'locale' => 'fr_FR.utf8'),
      'gl' => array('display' => 'Galego',      'locale' => 'gl_ES.utf8'),
      'hr' => array('display' => 'Hrvatski',    'locale' => 'hr_HR.utf8'),
      'it' => array('display' => 'Italiano',    'locale' => 'it_IT.utf8'),
      'nb' => array('display' => 'Norsk',       'locale' => 'nb_NO.utf8'),
      'pl' => array('display' => 'Polski',      'locale' => 'pl_PL.utf8'),
      'pt' => array('display' => 'Português',   'locale' => 'pt_PT.utf8'),
      'sk' => array('display' => 'Slovenčina',  'locale' => 'sk_SK.utf8'),
      'sl' => array('display' => 'Slovenščina', 'locale' => 'sl_SI.utf8'),
      'sr' => array('display' => 'Srpski',      'locale' => 'sr_RS@latin'),
      'fi' => array('display' => 'Suomi',       'locale' => 'fi_FI.utf8'),
// For the following languages, partial translations exist in Transifex, but
// they are not complete enough for display. Their Transifex content is not
// necessarily ported to SVN yet. Contact the authors if you want the current
// state of translation of these languages.
//
//      'el' => array('display' => 'Ελληνικά', 'locale' => 'el_GR.utf8'),
//      'nl' => array('display' => 'Nederlands', 'locale' => 'nl_NL.utf8'),
//      'sv' => array('display' => 'Svenska', 'locale' => 'sv_SE.utf8'),
//      'hu' => array('display' => 'Magyar', 'locale' => 'hu_HU.utf8'),
//      'cy' => array('display' => 'Cymraeg', 'locale' => 'cy_GB.utf8'),
    );

    /**
     * Configures the reachability tests, both for plain RADIUS/UDP and RADIUS/TLS.
     *
     * - UDP-hosts: an array of RADIUS servers to which login probes will be sent
     *
     * - TLS-discoverytag: the DNS NAPTR label that should be used for finding RADIUS/TLS servers
     *
     * - TLS-acceptableOIDs: defines which policy OID is expected from RADIUS/TLS servers and clients
     *
     * - TLS-clientcerts: for full two-way auth, the TLS handshake must have access to client certificates. You can specify known-good certificates (expected=pass) and known-bad ones (expected=fail)
     *
     * For each accredited CA you should 
     * 
     * @var array
     */
    public static $RADIUSTESTS = array(
        'UDP-hosts' => array(
            array('display_name' => 'Recon Viper 1',
                'ip' => '192.0.2.1',
                'secret' => 'somesecret',
                'timeout' => 5),
            array('display_name' => 'Recon Viper 2',
                'ip' => '198.51.100.17',
                'secret' => 'whatever',
                'timeout' => 5),
        ),
        'TLS-discoverytag' => 'aaa+auth:radius.tls',
        'TLS-acceptableOIDs' => array(
            'client' => '1.3.6.1.4.1.25178.3.1.1',
            'server' => '1.3.6.1.4.1.25178.3.1.2',
        ),

        'TLS-clientcerts' => array(
          'CA1' => array(
            'status' => 'ACCREDITED',
            'issuerCA' => '/DC=org/DC=pki1/CN=PKI 1',
            'certificates' => array(
              array(
                'status' => 'CORRECT',
                'public' => 'ca1-client-cert.pem',
                'private' => 'ca1-client-key.pem',
                'expected' => 'PASS'),
              array(
                'status' => 'WRONGPOLICY',
                'public' => 'ca1-nopolicy-cert.pem',
                'private' => 'ca1-nopolicy-key.key',
                'expected' => 'FAIL'),
              array(
                'status' => 'EXPIRED',
                'public' => 'ca1-exp.pem',
                'private' => 'ca1-exp.key',
                'expected' => 'FAIL'),
              array(
                'status' => 'REVOKED',
                'public' => 'ca1-revoked.pem',
                'private' => 'ca1-revoked.key',
                'expected' => 'FAIL'),
            )
          ),
          'CA-N' => array(
            'status' => 'NONACCREDITED',
            'issuerCA' => '/DC=org/DC=pkiN/CN=PKI N',
            'certificates' => array(
               array(
                'status' => 'CORRECT',
                'public' => 'caN-client-cert.pem',
                'private' => 'caN-client-cert.key',
                'expected' => 'FAIL'),
               )
          )
      ),
      'accreditedCAsURL' => '',
    );

    /**
     * Set of database connection details.
     *
     * The third entry is only needed if you set $ENFORCE_EXTERNAL_DB_SYNC to TRUE.
     *
     * See the extra notes on external sync enforcement below.
     * 
     * @var array
     */
    public static $DB = array(
        'INST' => array(
            'host' => 'db.host.example',
            'db' => 'cat',
            'user' => 'someuser',
            'pass' => 'somepass'),
        'USER' => array(
            'host' => 'db.host.example',
            'db' => 'cat',
            'user' => 'someuser',
            'pass' => 'somepass'),
        /*   If you use this tool in conjunction with an external customer management database, you can configure that every 
         * institution entry in CAT MUST correspond to a customer entry in an external database. If you want this, set this
         * config variable to TRUE.
         * ### BEWARE: You need to write custom code for the mapping of CAT IDs to the external DB YOURSELF. ###
         * ### The functions where you need to add custom code are:
         * 
         * Federation::listUnmappedExternalEntities();
         * Federation::getExternalDBEntityDetails($external_id);
         * IdP::getExternalDBSyncCandidates();
         * IdP::getExternalDBSyncState();
         * IdP::setExternalDBId($identifier);
         * 
         * The code for the consortium "eduroam" is already written and may serve as template. See the functions in question. */
        'EXTERNAL' => array(
            'host' => 'customerdb.otherhost.example',
            'db' => 'customer_db',
            'user' => 'customerservice',
            'pass' => '2lame4u'),
         'enforce-external-sync' => TRUE,
         /* if you feed your user database from a third-party source and do not want CAT to update it on its own, you can 
          * make it read-only
          */
         'userdb-readonly' => FALSE,
    );

    /**
     * Maximum size of files to be uploaded. Clever people can circumvent this; in the end, the hard limit is configured in php.ini
     *
     * @var int
     */
    public static $MAX_UPLOAD_SIZE = 10000000;

    /**
     * Verbosity of some of the core code. The following debug levels are supported:
     *
     *   1 = production (silence)
     *
     *   2 = normal debug
     *
     *   3 = more debug
     *
     *   4 = annoyingly much debug output
     *
     *   5 = way too much debug output (level 4 + SQL query dump)
     *
     * @var int
     *
     */
    public static $DEBUG_LEVEL = 5;

    /**
     * Who is allowed to access the installation check/local installation administration page on admin/112365365321.php ?
     *
     * Fill the array with the authorized user identifiers as produced by simpleSAMLphp login.
     *
     * The string 'I do not care about security!' is a backdoor which will give EVERYBODY access to the page. Remove this entry after finishing the installation.
     *
     * @var string[]
     */ 
    public static $SUPERADMINS = array (
        'eptid:someuser',
        'http://sommeopenid.example/anotheruser',
        'I do not care about security!',
    );
}

?>
