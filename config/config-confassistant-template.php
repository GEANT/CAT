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
const CONFIG_CONFASSISTANT = [

    /**
     * Defines various general parameters of the roaming consortium.
     * name: the display name of the consortium
     * ssid: an array of default SSIDs for this consortium; they are automatically added to all installers.
     * interworking-consortium-oi: Organisation Identifier of the roaming consortium for Interworking/Hotspot 2.0; 
     *                             a profile with these OIs will be added to all installers
     * tkipsupport: whether the default SSIDs should be configured for WPA/TKIP and WPA2/AES (TRUE) or only for WPA2/AES (FALSE)
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
    'CONSORTIUM' => [
        // for technical usages inside the product and things in installers not 
        // reaching the human eye. Please keep this ASCII only. There are some
        // code paths in the product which are only taken when the value is "eduroam"
        'name' => 'eduroam',
        // pretty-print version of the consortium name, for places where this is
        // presented to actual humans.
        'display_name' => 'eduroam®',
        'ssid' => ['eduroam'],
        'tkipsupport' => FALSE,
        'homepage' => 'http://www.eduroam.org',
        'signer_name' => 'GÉANT Association',
        'selfservice_registration'   => NULL,
#        'deployment-voodoo'         => "Operations Team",
        'ssid'                       => ['eduroam'],
        'interworking-consortium-oi' => ['001bc50460'],
        'registration_API_keys'      =>  [
            // 'secretvalue' => 'UK',
            // 'othervalue' => 'DE',
        ],
        'nomenclature_federation' => 'National Roaming Operator',
        'nomenclature_institution' => 'Identity Provider',
    ],

    /* silverbullet options:
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
     */

    'SILVERBULLET' => [
        'default_maxusers' => 200,
        'realm_suffix' => '.hosted.eduroam.org',
        'server_suffix' => '.hosted.eduroam.org',
        'gracetime' => 90,
        'CA' => ["type" => "embedded"], # OCSP URL needs to be configured in openssl.cnf
      # 'CA' => ["type" => "DFN", "SOAP_API_ENDPOINT" => "http://no.idea.where/"],

    ],
    /**
     * Various paths.
     * makensis: absolute path to the makensis executable. If you just fill in "makensis" the one from the system $PATH will be taken.
     *   See also NSIS_VERSION further down
     * @var array
     */
    'PATHS' => [
        'makensis' => 'makensis',
    ],

    /**
     * NSIS version - with version 3 UTF installers will be created
     * see also $PATHS['makensis']
     */
    'NSIS_VERSION' => 2,
    
    /**
     * Configures SMS gateway settings
     */
    'SMSSETTINGS' => [
        'provider' => 'Nexmo',
        'username' => '...',
        'password' => '...',
    ],
];
