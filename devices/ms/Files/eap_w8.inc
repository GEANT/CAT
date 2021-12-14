;/*
; * *****************************************************************************
; * Contributions to this work were made on behalf of the GÉANT project, a 
; * project that has received funding from the European Union’s Framework 
; * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
; * Horizon 2020 research and innovation programme under Grant Agreements No. 
; * 691567 (GN4-1) and No. 731122 (GN4-2).
; * On behalf of the aforementioned projects, GEANT Association is the sole owner
; * of the copyright in all material which was developed by a member of the GÉANT
; * project. GÉANT Vereniging (Association) is registered with the Chamber of 
; * Commerce in Amsterdam with registration number 40535155 and operates in the 
; * UK as a branch of GÉANT Vereniging.
; * 
; * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
; * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
; *
; * License: see the web/copyright.inc.php file in the file structure or
; *          <base_url>/copyright.php after deploying the software
; */
; Used for W8 and newer and EAP methods - TLS, PEAP and native Windows TTLS 

!include "common.nsh"
;===========================
;TRANSLATION
Section "<?php echo _("Installation of the personal certificate")?>" cert
!insertmacro MUI_HEADER_TEXT "<?php echo _("Components installation")?>" "<?php echo _("Personal certificate")?>"
SectionEnd
;==========================
;TRANSLATION
Section "<?php echo _("Installation of network profiles")?>" "<?php echo _("profiles")?>"
SectionIn RO
!insertmacro MUI_HEADER_TEXT "<?php echo _("Installation")?>" "<?php echo _("Network profiles update")?>"
File "profiles/*lan_prof*.xml"


${If} $wireless_result == 0
Call CheckWirelessProfiles
Call CreateWirelessProfiles 
${EndIf}
${If} $wired == 1
  Call CreateLanProfile
${EndIf}
!ifdef CRED
  IfSilent +2
  Call InstallUserCredentials
!endif
;Sleep 2000
IfSilent +2
SendMessage $HWNDPARENT "0x408" "1" ""
IfSilent +2
Sleep 2000
SectionEnd
;==========================
Section "-koncowka"
!ifdef CA_CERT
Delete "$TEMP\${CA_CERT}"
!endif
!ifdef EXTERNAL_INFO
  Call displayExternalInfo
!endif
SectionEnd
;==========================
