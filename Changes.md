Changes in 2.1
=====================
- [FEATURE #1]  display hotspot usage statistics

Changes in 2.1-alpha1
=====================
- [FEATURE #1]  institutions are now an SP, IdP, or both. Creation of unlinked
                insts now has a selection to that end; linked insts extract the
                corresponding info from the external DB; API creations now have
                to specify the type of inst in AUXATTRIB_INSTTYPE for the API
                action ACTION_NEWINST
- [FEATURE #2]  WPA/TKIP is dead. It cannot be configured as a "legacy" SSID any
                more. Existing configurations will be converted into a normal
                additional-SSID as a normal WPA2/AES network
- [FEATURE #3]  use IMagick unconditionally again. CentOS 8 added that with 8.1
- [FEATURE #4]  Integrate OpenRoaming Opt-In possibilities
                * NROs can allow their IdPs to enable OpenRoaming installers
                * NROs can specify where their custom RADIUS/TLS endpoint is, 
                  if any (else a consortium-wide default is shown)
                * IdPs can choose to have OpenRoaming Free RCOIs included in
                  their end-user installers either unconditionally or only on
                  explicit user request
- [FEATURE #5]  add possibility to add OpenRoaming ANP uplinks independently
                from eduroam Managed SP uplinks. Fed op needs to enable the
                OpenRoaming feature set for this to be exposed to IdPs
- [BUGFIX  #1]  don't use the bash "which" to find executables. Does not work
                with php-fpm
- [BUGFGIX #2]  do not use the hard-coded term "eduroam" in Apple installers

Changes in 2.0.4
================
- [FEATURE #1]  The system now sends out notification/alert mails if a
                significantly security relevant parameter was changed. The mails
                go to the NRO admin. Significant changes are:
                - change of institution name
                - addition of a new root CA (with more prominent WARNING if the
                  new CA has the same DN as an existing one)
                - addition of a new acceptable server name
- [FEATURE #2]  support negotiation of TLS versions higher than 1.0 while still
                rejecting SSL2 and SSL3
- [FEATURE #3]  realm reachability checks now produce a WARNING level message if
                the EAP server does not support TLS1.2 or higher
- [FEATURE #4]  check whether SRV-discovered hostname and certificate hostname
                match

Changes in 2.0.3
================
- [FEATURE #1]  Be compatible with RHEL/CentOS 8 (use GMagick instead of IMagick
                as this is what these distributions are moving towards)
- [FEATURE #2]  make it less dangerous to configure Passpoint settings by
                excluding known-problematic combinations (namely Apple products
                and username/password based EAP types)
- [FEATURE #3]  config now allows to set display names for Passpoint RCOIs 
                for RCOIs added manually by the IdP admin, use a fixed string
                not related to the consortium instead ("<IdP> Roaming Partner")
- [BUGFIX  #1]  using "which" is not yielding expected results to find 
                executables under php-fpm, so use a more direct method to find
                out whether configured executables exist and are executable
- [BUGFIX  #2]  some compatibility fixes for CentOS 8

Configuration parameter changes
-------------------------------
- CONFIG_CONFASSISTANT['CONSORTIUM']['interworking_consorium_oi'] now uses the
                array indexes as names for the consortium DisplayName (string)
                

                
Changes in 2.0.2
================
- [FEATURE #1]  hide expired and revoked silverbullet client certs behind a
                click to unclutter view
- [FEATURE #2]  add button to show auth logs for a given user in silverbullet
- [FEATURE #3]  show the realm of silverbullet profiles in the NRO overview
- [FEATURE #4]  add API action: change silverbullet end user expiry date
- [FEATURE #5]  show timestamp of last change of profile information on main
                download page
- [FEATURE #6]  separate silverbullet users into "current" and "previous" ones;
                hide the latter behind a non-default tab to reduce clutter
- [FEATURE #7]  allow actual *deletion* of a silverbullet user if he has expired
                and we do not have any authentication records of him (any more)
- [FEATURE #8]  ChromeOS installers can now also pin the server name, not just
                the CA (one string only though, not a list of names; lists will
                be condensed into a common suffix)
- [BUGFIX  #1]  language was not correctly applied in parts of the admin area
                and Windows installers
- [BUGFIX  #2]  provide Roaming Consortium OI in uppercase hex letters for the
                Apple installer, only then do they actually work
- [BUGFIX  #3]  the admin API action ENDUSER-IDENTIFY now only returns the 
                correct result set, not additional garbage afterwards
- [BUGFIX  #4]  mailto: links are now created correctly on main download page
- [BUGFIX  #5]  importing silverbullet users with CSV now operational again

- BEHAVIOUR CHANGE: GEANTlink becomes the non-default on every platform (except
                    W7 where it is required for TTLS support). Those who have
                    explicitly enabled GEANTLink in W8 will also get it enabled 
                    on W10 during release DB conversion. It is still possible to
                    steer the inclusion per-platform with the fine-tuning 
                    settings later on.

Configuration parameter changes
-------------------------------
- CONFIG_CONFASSISTANT['DB'] list with DB access details to silverbullet RADIUS
                servers (to retrieve their auth logs)

Changes in 2.0-beta3
====================
- [FEATURE #1]  allow to invite more than one admin for a new institution.
                Contrary to previous CAT 1.x, every invitation is now unique
                per destination mail address, so there is no "race condition"
                any more on who is the first one to consume an invitation
- [FEATURE #2]  fine-tuning options to allow admin steering of whether GEANTlink
                or the native supplicant is preferred on Windows 10 and 8
- [FEATURE #3]  always check username input for trailing spaces and warn user
                if found
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
- \config\Master::APPEARANCE['privacy_notice_url'] link to the privacy notice

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
- \config\Master::DB['userdb-readonly'] is replaced by \config\Master::DB['USER']['readonly']

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
- [ADDED]     CONSORTIUM['nomenclature_idp']
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
