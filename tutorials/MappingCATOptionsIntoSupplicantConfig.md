Mapping CAT device options into Supplicant Configuration
========================================================

Many of the options that an IdP administrator can configure in CAT are
self-explanatory and directly map to configuration proprties in supplicants;
e.g. adding an EAP type "EAP-TTLS" in the admin interface means the EAP type
TTLS should be configured in the supplicant.

Some of the options are less clear in their semantics, and transpire into 
configuration files or executable installers in different ways. This document 
provides guidance to supplicant implementers on what is the intended behaviour 
described by a specific CAT option.

The document also describes these mappings in the existing device modules.

Verify user input to contain realm suffix (checkbox)
----------------------------------------------------
Setting this option should trigger a sanity check for usernames as they are 
entered by the user during configuration or first use.

The verification is an after-the-fact check. It should not trigger any 
user-visible artifact so long as the validation passes. A typical embodiment
would be: as soon as the user confirms the username in a corresponding input
field, verify the string. Do not allow to continue and inform the user that a
correction is needed.

It is always possible to set this box. The amount of checks that can be done and
the extent of the helpfulness this option can provide in case the input is
incorrect varies depending on another option: Realm.

  - if Realm is not set, verification is limited to check whether the user input
    contains 
    * exactly one '@' character, 
    * one of more '.' (dot) characters which are not immediately adjacent to one
      another and not immediately adjacent to the '@', 
    * that it does not end in a ' ' (space) or '.' (dot)
    as the violation of any of these rules results in this string not containing
    a well-formed realm.
  - if Realm is set, in addition to the above, the verification should check
    whether the configured Realm is the suffix of the part of the user input
    behind the '@' character. The check should be case-sensitive. This means 
    that additional sub-realms are allowed. Examples for Realm = 'foo.bar':
    * john@.foo.bar -> FAIL
    * john@foo.bar -> OK
    * john@accounting.foo.bar -> OK
    * john@accounting -> FAIL
    * john@accounting.bar -> FAIL
    * john@ACCOUNTING.foo.bar-> OK
    * john@ACCOUNTING.FOO.BAR -> FAIL

In case of a failed verification, the error message can contain hints on where
the verification failed for maximum user friendliness; e.g. 
  - "The username cannot end with a ' ' (space) character"
  - "There is no '@' in the username", 
  - "The username must end with 'foo.bar'"

Embodiments of this option in CAT device modules:

  - mobileconfig (Apple iOS / macOS): the mobileconfig configuration file format
    does not provide any hooks after the user input of the username, so this
    option has no effect in this module.
  - chromebook (ChromeOS): the ONC configuration file format does not provide 
    any hooks after the user input of the username, so this option has no effect
    in this module.
  - Windows: the installer implements the option as described above
  - Linux: the istaller implements the option as described above

Prefill user input with realm suffix
------------------------------------
Setting this option should fill the username input field with the Realm as
indicated in the Realm option before the user starts typing the username. For
maximum helpfulness, the cursor should be placed at the start of the input field
to allow the user to start typing right away.

This option should also limit the user input, so that the realm cannot be
changed even if the inteface allows editing.
 
It is only possible to select this checkbox if Realm has been populated by the
IdP administrator.

Embodiments of this option in CAT device modules:

  - mobileconfig (Apple iOS / macOS): the mobileconfig configuration file format
    does not provide any hooks after the user input of the username. If this
    option is set and there is no configured Acceptable Use Policy configured to
    be displayed, text reminding the user of the username format with realm
    suffix is taking the place of he AUP display (this text is displayed 
    immediately before the user input field is shown).
  - chromebook (ChromeOS): the ONC configuration file format does not provide 
    any hooks after the user input of the username, so this option has no effect
    in this module.
  - Windows: the installer implements the option as described above
  - Linux: the installer implements the option as described above
