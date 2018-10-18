Changes in 2.0-beta3
====================
- [BUGFIX  #1]  restore ability for admins to download non-published installers
                from their fine-tuning page
- [BUGFIX  #2]  for Apple installers, check is a CA was duplicate and if so do
                not include CA twice in installer
- [BUGFIX  #3]  fix various translation errors (wrong quotation marks) which led
                to incorrect installers in those languages
- [BUGFIX  #4]  make the "test" device work again
- [BUGFIX  #5]  various typos
- [BUGFIX  #6]  display admin user's real name as we get it from SAML. Not
                stored persistently anywhere yet.
- [BUGFIX  #7]  invalidate all cached installers federation-wide if a federation
                has changed one of its properties
- [BUGFIX  #8]  for Apple installers, the flag "verify user input has suffix" is
                now honoured (the warning was erroneously always displayed 
                before)
- [BUGFIX  #9]  various bugs in the handling of device-specific and eap-specific
                attributes in the "fine-tuning" pages (e.g. deletion of 
                attribute not possible; editing general profile properties
                erroneously also deletes fine-tuning attributes
                

Changes in 2.0-beta2
====================
- [FEATURE #1]  warn and reject support URLs if they are not properly prefixed
                with the protocol (http:// and https:// are the only allowed
                protocols
- [FEATURE #2]  allow inclusion of a privacy notice URL. If set, is displayed
                on the front page footer and immediately adjacent to the end
                user download buttons
- [BUGFIX  #1]  when using built-in user management, the fedadmin privilege got
                lost when changing other user attributes
- [BUGFIX  #2]  add a shebang to the Linux installer so that it gets executed
                with the system's Python interpreter
- [BUGFIX  #3]  improve whitespace in Linux installer so that its syntax is more
                correct

Configuration parameter changes
-------------------------------
- CONFIG['APPEARANCE']['privacy_notice_url'] link to the privacy notice

Changes in 2.0-beta1
====================
- [FEATURE #1]  admin API implemented
- [FEATURE #2]  allow configuration of map provider (currently "Google" (Maps),
                "OpenStreetMaps", and a text-only "None")
- [FEATURE #3]  enhance Android config format to allow supplying alternative SSIDs
                and the "prefill/validate realm suffix" config items
- [FEATURE #4]  add Hotspot 2.0 support to Windows 10 installers
- [FEATURE #5]  set reply-to for admin invitations to the mail address of the
                federation administrators, not the mailing list
- [BUGFIX #1 ]  Symantec protection warning message was unnecessarily popping up in some cases
- [BUGFIX #2 ]  remove Windows EAP-pwd installers due to non-technical bug
                
Changes in 2.0-alpha2
=====================
- [FEATURE #1]  add a button to UNlink an institution from the external DB
- [FEATURE #2]  all databases can be marked as readonly; the code will never
                execute anything else than SELECTs on those databases then. All
                buttons which usually let users edit or delete anything are not
                displayed.
- [FEATURE #3]  allow fed admins to upload a "minted" CA which will be auto-added
                to new IdPs when they sign up. Good for federations where IdP
                certificates come from one well-known CA.
- [FEATURE #4]  add options to force HTTP/HTTPS proxies in the installers

Configuration parameter changes
-------------------------------
- CONFIG['DB']['userdb-readonly'] is replaced by CONFIG['DB']['USER']['readonly']

Changes in 2.0-alpha1
=====================

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
