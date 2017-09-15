Changes in 1.2
==============

Upgrade path notice: it is not possible to upgrade directly from 1.0 to 1.2

- [BUGFIX #1 ]  Google Maps JavaScript API needs an API key (again). Without it,
                things seem to work, but the conditions are unclear and it
                generates ugly JS error console warnings. Added a config
                parameter APPEARANCE['google_maps_api_key] to make things proper
- [BUGFIX #2 ]  In UserAPI deviceInfo was not calling a device setup for the selected
                profile, as a result parts of the info was not shown.
- [FEATURE #1]  UserAPI redone. Instead of the "id" as a common argument we now use
                meaningful names, like idp, profile, device etc.
                To get the new behaviour you need to set api_version argument to 2.
- [FEATURE #2]  added createTemporaryDirectory to the Helper to avoind using the came code in several places
- [FEATURE #3]  configuration tests rebuilt and extended
- [FEATURE #4]  realm checks are saved in DB and results shown on federation 
                overview page
- [FEATURE #5]  federation customisation: name, logo, custom invitation texts and
                more
- [FEATURE #6]  deprecated NSISArray has been replaced with nsArry
- [FEATURE #7]  Support for UTF-8 installer has been added (this requires nsis v3)
- [FEATURE #8]  also check for SHA-1 signatures and warn if found
- [FEATURE #9]  implement skin selection system. For details read 
                https://wiki.geant.org/display/H2eduroam/Changing+the+end-user+UI%3A+programming+your+own+skin
- [FEATURE #10] Managed IdP: basic user IdM system and
                automatic issuance of EAP-TLS based user credentials
                this feature is complemented by a RADIUS server for validation
                of these credentials. Currently supported target platforms:
                Win7+, MacOS X, macOS, iOS, ChromeOS, Linux [missing Android]
- [FEATURE #11] provide a link to the ChangeLog on the front page (click on
                version number in footer)
- [FEATURE #12] use API.php consistently for all installer downloads (the already
                previously declared obsolete download.php is gone)
- [FEATURE #13] TLS support in Windows has been reworked, now it always requires personal cert
                installation then then sets this cert as user credentials, no more problems with
                multiple user certificates
- [FEATURE #14] PEAP credenials setting has been changed to use the new WLANSetEAPUserData utility
- [FEATURE #15] allow separate deployments of the diagnostics vs. config assistant
                functionality (split config into three parts)
- [FEATURE #16] allow to configure a separate database user for end-user
                frontend things. Usually the same as "INST" but on deployments
                where end-user frontend and admin areas are on separate hosts
                this can be useful for privilege separation
- [FEATURE #17] Allow to specify custom installer name suffixes on per-profile
                level
- [FEATURE #18] Added support for displaying federation logo in Windows installers

- [FEATURE #19] Deleted the cat_back.php files which were only there for backwards compatibility


Configuration parameter changes
-------------------------------

- [ADDED]     CONSORTIUM['silverbullet_default_maxusers']
- [ADDED]     CONSORTIUM['silverbullet_realm_suffix']
- [ADDED]     CONSORTIUM['silverbullet_server_suffix']
- [ADDED]     CONSORTIUM['silverbullet_gracetime']
- [ADDED]     CONSORTIUM['nomenclature_federation']
- [ADDED]     CONSORTIUM['nomenclature_institution']
- [ADDED]     CONSORTIUM['display_name']
- [ADDED]     APPEARANCE['skins']
- [ADDED]     APPEARANCE['google_maps_api_key']
- [ADDED]     APPEARANCE['FUNCTIONALITY_LOCATIONS']
- [ADDED]     SMSSETTINGS['provider'] (only supported value: Nexmo)
- [ADDED]     SMSSETTINGS['username']
- [ADDED]     SMSSETTINGS['password']
- [ADDED]     DB['FRONTEND']
- [EXTERNAL]  for Managed IdP client cert auth for the accountstatus page:
              Apache: SSLCACertificateFile ... file with PEMs of client cert issuers ...
              Apache: SSLOptions StdEnvVars
              Apache: AllowOverride AuthConfig (for directory web/accountstatus/ )
- [ADMIN API] coordinates are now to be sent as a json_encode("lon" => x, "lat" => y)
              (previously PHP serialize() style)
- [USER API]  version 1 of the API is discontinued effective immediately


Changes in previous versions
============================
Can be found in their respective .tar.gz distribution in the "Changes" file
