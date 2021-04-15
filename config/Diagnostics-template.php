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

namespace config;

/**
 * This classes' members hold the configuration for CAT
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Configuration
 */
class Diagnostics
{
         /**
         * Various paths.
         * eapol_test: absolute path to the eapol_test executable. If you just fill in "eapol_test" the one from the system $PATH will be taken.
         * c_rehash: absolute path to the c_rehash executable. If you just fill in "c_rehash" the one from the system $PATH will be taken.
         *   See also NSIS_VERSION further down
         * @var array
         */
        const PATHS = [
            'c_rehash' => 'c_rehash',
            'eapol_test' => 'eapol_test',
        ];
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
        const RADIUSTESTS = [
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
        ];
        const EDUGAINRESOLVER = [
            'url' => 'https://technical.edugain.org/api.php',
            'timeout' => 2,
        ];
        const RADIUSSPTEST = [
            'port' => '1999',
            'secret' => '1q2w3e4r5t0O9I8U7Y6TaZ',
        ];
}
