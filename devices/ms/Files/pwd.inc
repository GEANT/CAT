; ******************************************************************************
; * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
; * and GN4-2 consortia
; *
; * License: see the web/copyright.php file in the file structure
; ******************************************************************************
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

        MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo _("EAP-pwd installation error. Please try again or contact ${SUPPORT}.")?>"
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


