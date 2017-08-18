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
 * This classes' members hold the configuration for CAT
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Configuration
 */
const CONFIG_DIAGNOSTICS = [
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
     * certfilename, keyfilename, keypass: if you want to send S/MIME signed mails, just configure the signing cert
     *                                     with these parameters. All must be non-NULL for signing to happen. If you
     *                                     don't need a keypass, make it an empty string instead.
     * silverbullet options:
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
     * @var array
     */

        /**
     * Various paths.
     * eapol_test: absolute path to the eapol_test executable. If you just fill in "eapol_test" the one from the system $PATH will be taken.
     * c_rehash: absolute path to the c_rehash executable. If you just fill in "c_rehash" the one from the system $PATH will be taken.
     *   See also NSIS_VERSION further down
     * @var array
     */
    'PATHS' => [
        'c_rehash' => 'c_rehash',
        'eapol_test' => 'eapol_test',
    ],

    /**
     * Configures the reachability tests, both for plain RADIUS/UDP and RADIUS/TLS.
     * UDP-hosts: an array of RADIUS servers to which login probes will be sent
     * TLS-discoverytag: the DNS NAPTR label that should be used for finding RADIUS/TLS servers
     * TLS-acceptableOIDs: defines which policy OID is expected from RADIUS/TLS servers and clients
     * TLS-clientcerts: for full two-way auth, the TLS handshake must have access to client certificates.
     * You can specify known-good certificates (expected=pass) and known-bad ones (expected=fail)
     * For each accredited CA you should provide four server certificates: valid, expired, revoked, wrong policy
     * so that all corner cases can be tested. Be sure to set "expected" to match
     * your expectations regarding the outcome of the connection attempt.
     * 
     * @var array
     */
    'RADIUSTESTS' => [
        'UDP-hosts' => [
            ['display_name' => 'Recon Viper 1',
                'ip' => '192.0.2.1',
                'secret' => 'somesecret',
                'timeout' => 5],
            ['display_name' => 'Recon Viper 2',
                'ip' => '198.51.100.17',
                'secret' => 'whatever',
                'timeout' => 5],
        ],
        'TLS-discoverytag' => 'aaa+auth:radius.tls',
        'TLS-acceptableOIDs' => [
            'client' => '1.3.6.1.4.1.25178.3.1.1',
            'server' => '1.3.6.1.4.1.25178.3.1.2',
        ],

        'TLS-clientcerts' => [
          'CA1' => [
            'status' => 'ACCREDITED',
            'issuerCA' => '/DC=org/DC=pki1/CN=PKI 1',
            'certificates' => [
              [
                'status' => 'CORRECT',
                'public' => 'ca1-client-cert.pem',
                'private' => 'ca1-client-key.pem',
                'expected' => 'PASS'],
              [
                'status' => 'WRONGPOLICY',
                'public' => 'ca1-nopolicy-cert.pem',
                'private' => 'ca1-nopolicy-key.key',
                'expected' => 'FAIL'],
              [
                'status' => 'EXPIRED',
                'public' => 'ca1-exp.pem',
                'private' => 'ca1-exp.key',
                'expected' => 'FAIL'],
              [
                'status' => 'REVOKED',
                'public' => 'ca1-revoked.pem',
                'private' => 'ca1-revoked.key',
                'expected' => 'FAIL'],
            ]
          ],
          'CA-N' => [
            'status' => 'NONACCREDITED',
            'issuerCA' => '/DC=org/DC=pkiN/CN=PKI N',
            'certificates' => [
               [
                'status' => 'CORRECT',
                'public' => 'caN-client-cert.pem',
                'private' => 'caN-client-cert.key',
                'expected' => 'FAIL'],
               ]
          ]
      ],
      'accreditedCAsURL' => '',
    ],
];
