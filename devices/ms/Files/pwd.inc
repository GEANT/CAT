; used by Vista/W7/W8 modules
!include "common.nsh"
;==========================
;TRANSLATION
Section "<?php echo _("user profile")?>" pwd_prof
SectionIn RO
SectionEnd

;TRANSLATION
Section "<?php echo _("install program")?>" eap_pwd
SectionIn RO
!insertmacro MUI_HEADER_TEXT  "<?php echo _("Installation")?>" "EAP-pwd"
ClearErrors
ReadRegStr $0 HKLM SYSTEM\CurrentControlSet\Services\Rasman\PPP\EAP\52 "FriendlyName"
IfErrors no_key
Goto no_install
no_key:
File Aruba_EAP_pwd.exe
ExecWait "$TEMP\Aruba_EAP_pwd.exe" $0
StrCmp $0 0 Koniec
        ;
        ; Error Label, show error box and then quit
        ;

;        MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo _('EAP-pwd installation error. Please try again or contact ${SUPPORT}.')?>"
;        Quit
Koniec:
Delete "$TEMP\Aruba_EAP_pwd.exe"
no_install:
SectionEnd
;TRANSLATION
Section "<?php echo _("Installation of network profiles")?>" "<?php echo _("profiles")?>"
SectionIn RO
!insertmacro MUI_HEADER_TEXT "<?php echo _("Installation")?>" "<?php echo _("Network profiles update")?>"
!ifdef W8
File "w8/wlan_prof-*.xml"
!else
${If} $WindowsVer == 6
File "vista/wlan_prof-*.xml"
${EndIf}
${If} $WindowsVer == 7
File "w7/wlan_prof-*.xml"
${EndIf}
!endif

Call CheckWirelessProfiles
Call CreateWirelessProfiles
SectionEnd


;==========================
Section "-koncowka"
!ifdef EXTERNAL_INFO
  Call displayExternalInfo
!endif
SectionEnd
;==========================


