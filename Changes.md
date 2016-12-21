Changes in 1.2
==============

Upgrade path notice: it is not possible to upgrade directly from 1.0 to 1.2

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
- [FEATURE #10] eduroam-as-a-Service (working title): basic user IdM system and
                automatic issuance of EAP-TLS based user credentials
                this feature is complemented by a RADIUS server for validation
                of these credentials. Currently supported target platforms:
                Win7+, MacOS X, macOS, iOS, ChromeOS [missing Linux, Android]

Configuration parameter changes
-------------------------------

- [ADDED] CONSORTIUM['silverbullet_default_maxusers']
- [ADDED] CONSORTIUM['silverbullet_realm_suffix']
- [ADDED] APPEARANCE['skins']

Changes in previous versions
============================
Can be found in their respective .tar.gz distribution in the "Changes" file
