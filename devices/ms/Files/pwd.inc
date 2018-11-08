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
; used by Vista/W7/W8 modules
!include "common.nsh"
;==========================
;TRANSLATION
Section "<?php echo _("user profile")?>" pwd_prof
SectionIn RO
SectionEnd

Section "<?php echo _("install program")?>" eap_pwd
SectionIn RO
!insertmacro MUI_HEADER_TEXT  "<?php echo _("Installation")?>" "EAP-pwd"
ClearErrors
ReadRegStr $0 HKLM SYSTEM\CurrentControlSet\Services\Rasman\PPP\EAP\52 "FriendlyName"
IfErrors no_key
Goto no_install
no_key:
IfSilent +2
   MessageBox MB_OKCANCEL "We need to install additional software provided by Aruba Networks, Inc." IDOK cont
   Call Cleanup
cont:
File Aruba_Networks_EAP-pwd_x32.msi
File Aruba_Networks_EAP-pwd_x64.msi
ExecWait "$Msiexec /i $TEMP\Aruba_Networks_EAP-pwd_x$Platform.msi" $0
StrCmp $0 0 Koniec
        ;
        ; Error Label, show error box and then quit
        ;

        MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo _("EAP-pwd installation error. Please try again or contact Support.")?>"
        Delete Aruba_Networks_EAP-pwd_x32.msi
        Delete Aruba_Networks_EAP-pwd_x64.msi
        Call Cleanup
Koniec:
Delete Aruba_Networks_EAP-pwd_x32.msi
Delete Aruba_Networks_EAP-pwd_x64.msi
no_install:
SectionEnd
;TRANSLATION
Section "<?php echo _("Installation of network profiles")?>" "<?php echo _("profiles")?>"
SectionIn RO
!insertmacro MUI_HEADER_TEXT "<?php echo _("Installation")?>" "<?php echo _("Network profiles update")?>"
!ifdef W8
File "w8/*lan_prof*.xml"
!else
${If} $WindowsVer == 6
File "vista/*lan_prof*.xml"
${EndIf}
${If} $WindowsVer == 7
File "w7/*lan_prof*.xml"
${EndIf}
!endif

${If} $wireless_result == 0
Call CheckWirelessProfiles
Call CreateWirelessProfiles
${EndIf}

${If} $wired == 1
  Call CreateLanProfile
${EndIf}
SendMessage $HWNDPARENT "0x408" "1" ""
Sleep 2000
SectionEnd


;==========================
Section "-koncowka"
!ifdef EXTERNAL_INFO
  Call displayExternalInfo
!endif
SectionEnd
;==========================


