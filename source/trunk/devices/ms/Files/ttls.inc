; used by XP/Vista/W7 module
!include "common.nsh"
;==========================
;TRANSLATION
Section "<?php echo _("Installation of network profiles")?>" "<?php echo _("profiles")?>"
SectionIn RO
!insertmacro MUI_HEADER_TEXT "<?php echo _("Installation")?>" "<?php echo _("Network profiles update")?>"

Call CheckWirelessProfiles
;Sleep 2000
;SendMessage $HWNDPARENT "0x408" "1" ""
;Sleep 2000
SectionEnd

;TRANSLATION
Section "<?php echo _("user profile")?>" sw2_prof
SectionIn RO
SectionEnd

;TRANSLATION
Section "<?php echo _("install program")?>" sw2_inst
SectionIn RO
!insertmacro MUI_HEADER_TEXT "SecureW2" "SecureW2"
IfSilent +3
File SecureW2.INF
Goto +2
File /oname=SecureW2.INF SecureW2S.INF
File SecureW2_EAP_Suite_113.zip
!insertmacro ZIPDLL_EXTRACT $TEMP\SecureW2_EAP_Suite_113.zip $TEMP SecureW2_EAP_Suite_113.exe
Delete $TEMP\SecureW2_EAP_Suite_113.zip
ExecWait "$TEMP\SecureW2_EAP_Suite_113.exe /S" $0
StrCmp $0 0 Koniec
        ;
        ; Error Label, show error box and then quit
        ;

        MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo _('SecureW2 installation error. Please try again or contact ${SUPPORT}.')?>"
        Quit
Koniec:
Delete "$TEMP\SecureW2_EAP_Suite_113.exe"
Delete "$TEMP\SecureW2.INF"
Delete "$TEMP\SecureW2S.INF"
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


