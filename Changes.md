Changes in 1.2
================
- [FEATURE #1] UserAPI redone. Instead of the "id" as a common argument we now use
               meaningful names, like idp, profile, device etc.
               To get the new behaviour you need to set api_version argument to 2.
- [FEATURE #2] added createTemporaryDirectory to the Helper to avoind using the came code in several places
- [FEATURE #3] configuration tests rebuilt and extended
- [FEATURE #4] realm checks are saved in DB and results shown on federation 
               overview page
- [FEATURE #5] federation customisation: name, logo, custom invitation texts and
               more
- [FEATURE #6] deprecated NSISArray has been replaced with nsArry
- [FEATURE #7] Support for UTF-8 installer has been added (this requires nsis v3)

Configuration parameter changes
-------------------------------


Changes in previous versions
============================
Can be found in their respective .tar.gz distribution in the "Changes" file
