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

namespace config;

/**
 * This classes' members hold the configuration for CAT
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Configuration
 */
class ConfAssistant
{

    /**
     * Defines various general parameters of the roaming consortium.
     * name: the display name of the consortium
     * ssid: an array of default SSIDs for this consortium; they are automatically added to all installers.
     * interworking-consortium-oi: Organisation Identifier of the roaming consortium for Interworking/Hotspot 2.0; 
     *                             a profile with these OIs will be added to all installers
     * interworking-domainname-fallback: This will be used in Windows installers for the DomainName setting if
     *                             the IdP configuration does not suppy its own realm
     * homepage: URL of the consortium's general homepage.
     * signer_name: if installers are configured for digital signature, this parameter should contain the "O" name
     *           in the certificate. If left empty, signatures are not advertised even if configured and working
     * allow_self_service_registration: if set to NULL, federation admins need to invite new inst admins manually
     *                                  if set to a federation ID string, e.g. "DE" for Germany, new admins can
     *                                  self-register and will be put into that federation.
     * registration_API_keys: allows select federations to make bulk registrations for new IdPs (e.g. if they have
     *                        an own, opaque, customer management system. The API will be documented at a later stage
     * LOGOS: there are several variants of the consortium logo scattered in the
     *        source. Please change them at the appropriate places:
     *        - web/resources/images/consortium_logo.png
     *        - web/favicon.ico
     *        - devices/ms/Files/eduroam_150.bmp
     *        - devices/ms/Files/eduroam32.ico
     * 
     * @var array
     */
    public const CONSORTIUM = [
        // for technical usages inside the product and things in installers not 
        // reaching the human eye. Please keep this ASCII only. There are some
        // code paths in the product which are only taken when the value is "eduroam"
        'name' => 'eduroam',
        // pretty-print version of the consortium name, for places where this is
        // presented to actual humans.
        'display_name' => 'eduroam®',
        'ssid' => ['eduroam'],
        'homepage' => 'https://www.eduroam.org',
        'signer_name' => 'GÉANT Association',
        'selfservice_registration' => NULL,
#        'deployment-voodoo'         => "Operations Team",
        'ssid' => ['eduroam'],
        'interworking-consortium-oi' => ['001bc50460'],
        'interworking-domainname-fallback' => 'eduroam.org',
        'networks' => [
            'eduroam®'     => [
                'ssid' => ['eduroam'], 
                'oi' => [
                    '001bc50460' /* eduroam RCOI */ 
                    ], 
                'condition' => TRUE],
            'OpenRoaming® (%REALM%)' => [
                'ssid' => [],     /* OpenRoaming has left SSIDs behind */
                'oi' => [
                    '5A03BA0000', /* OpenRoaming/AllIdentities/SettlementFree/NoPersonalData/BaselineQoS */
                    '5A03BA0800', /* OpenRoaming/EduIdentities/SettlementFree/NoPersonalData/BaselineQoS */
                    ],
                'condition' => 'internal:openroaming',
                ],
        ],
        'registration_API_keys' => [
        // 'secretvalue' => 'UK',
        // 'othervalue' => 'DE',
        ],
        /*  Please note that many languages that CAT is translated to distinguish
          grammatical gender and if you change this phrase it might get a wrong
          article in some translated strings or look odd. This only affects the
          administrative interface and not end users.
          Since this product has a flagship use for the eduroam consortium
          (which uses the term "Identity Provider"), at least the German
          translation is geared towards *male* declination to match that term.
         */
        'nomenclature_federation' => 'National Roaming Operator',
        'nomenclature_idp' => 'Identity Provider',
        'nomenclature_hotspot' => 'Service Provider',
        'nomenclature_participant' => 'Organisation',
    ];

    /** silverbullet options:
     *         default_maxusers: an institution is not allowed to create more than that amount of users
     *             the value can be overriden as a per-federation option in fed-operator UI
     *         realm_suffix: user credentials have a realm which always includes the inst ID and profile ID and the name
     *             of the federation; for routing aggregation purposes /all/ realms should end with a common suffix though
     *             if left empty, realms would end in the federation name only
     *         server_suffix: the suffix of the auth server's name. It will be auth.<fedname> followed by this suffix
     *         gracetime: admins need to re-login and verify that accounts are still valid. This prevents lazy admins
     *             who forget deletion of people who have lost their eligibility. The number is an integer value in days
     *         CA: the code can either act as its own CA ("embedded") or use API calls to an external CA. This config
     *             value steers where to get certificates from 
     * 
     */
    const SILVERBULLET = [
        'product_name' => 'Hosted Services',
        'subproduct_sp_name' => 'Managed SP',
        'subproduct_idp_name' => 'Managed IdP',
        'documentation' => 'https://wiki.geant.org/pages/viewpage.action?pageId=66650390',
        'default_maxusers' => 200,
        'realm_suffix' => '.hosted.eduroam.org',
        'server_suffix' => '.hosted.eduroam.org',
        'gracetime' => 90,
        'CA' => ["type" => "embedded"], # OCSP URL needs to be configured in openssl.cnf
            # 'CA' => ["type" => "DFN", "SOAP_API_ENDPOINT" => "http://no.idea.where/"],
    ];

    /**
     * Various paths.
     * makensis: path to the makensis executable. If you just fill in "makensis" the one from the system $PATH will be taken.
     * zip: path to the zip executable. If you just fill in "zip" the one from the system $PATH will be taken.
     *   See also NSIS_VERSION further down
     * trust-store-*: if an IdP wants to auto-detect his root CA rather than specifying it properly, we need to have repositories
     *                of "known-good" CAs. Mozilla's trust store is usually good, plus ones we can ship ourselves
     * @var array
     */
    const PATHS = [
        'makensis' => 'makensis',
        'zip' => 'zip',
        'trust-store-mozilla' => '/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem',
        'trust-store-custom' => __DIR__ . "/known-roots.pem",
    ];

    /**
     * NSIS version - with version 3 UTF installers will be created
     * see also $PATHS['makensis']
     * 
     * @var integer
     */
    const NSIS_VERSION = 3;
    const MAPPROVIDER = [
        'PROVIDER' => 'OpenLayers', // recognised values: Google, Bing, OpenLayers, None
        'USERNAME' => '' // or equivalent; for Google, this is the APIKEY
    ];

    /**
     * Configures SMS gateway settings
     */
    const SMSSETTINGS = [
        'provider' => 'Nexmo',
        'username' => '...',
        'password' => '...',
    ];
    
    /**
     * Lists the RADIUS servers. They have a built-in DB to log auth requests.
     * We need to query those to get auth stats for silverbullet admins
     *
     * @var array
     */
    const DB = [
        // names don't matter - the source code will iterate through
        // all entries
        'RADIUS_1' => [
            'host' => 'auth-1.hosted.eduroam.org',
            'db' => 'radacct',
            'user' => 'someuser',
            'pass' => 'somepass',
            'readonly' => TRUE,],
        'RADIUS_2' => [
            'host' => 'auth-2.hosted.eduroam.org',
            'db' => 'radacct',
            'user' => 'someuser',
            'pass' => 'somepass',
            'readonly' => TRUE,],
    ];

    /**
     * Determines if DiscoJuice keywords should be used in the discovery service
     * The keywords contain other language variants of the IdP name making it
     * easier to kollow keyboard serach. Turning this oprion on will add
     * about 40% size to the IdP list
     */
    const USE_KEYWORDS = true;
    /**
     * Determines if the IdP list for DiscoJouce shuld be preloaded in the background
     * at the main page load
     */
    const PRELOAD_IDPS = true;

}
