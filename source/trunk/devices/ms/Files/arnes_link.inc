; used by Vista/W7/W8 modules
!include "common.nsh"
;==========================
;TRANSLATION
Section "<?php echo _("user profile")?>" arnes_link
SectionIn RO
SectionEnd

Section "<?php echo _("install program")?>" eap_arnes_link
SectionIn RO
!insertmacro MUI_HEADER_TEXT  "<?php echo _("Installation")?>" "ArnesLink"
File "GPL3.rtf"
File "ArnesLinkEn32.msi"
File "ArnesLinkEn64.msi"


DetailPrint "Run ArnesLink installer"
!insertmacro debug_cat 1 "Run ArnesLink installer"
!insertmacro debug_cat 3 'Execute: msiexec.exe /i "$OUTDIR\ArnesLinkEn$Platform.msi" /qb'
ClearErrors
ExecWait 'msiexec.exe /i "$OUTDIR\ArnesLinkEn$Platform.msi" /qb' $0
!insertmacro debug_cat 4 "ArnesLink returned $0"
IfErrors 0 +3
MessageBox MB_OK "Error installing ArnesLink.$\nEmbedded installer didn't complete succesfully.$\nReport: $0"
Quit

Delete "$TEMP\ArnesLinkEn32.msi"
Delete "$TEMP\ArnesLinkEn64.msi"
Delete "$TEMP\GPL3.rtf"
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

;DetailPrint "Fire up profile import"
;!insertmacro debug_cat 1 "Fire up profile import"
;!insertmacro debug_cat 3 '"$INSTDIR\al_import.exe" "$OUTDIR\wlan_prof.xml"'
;ClearErrors
;ExecWait '"$INSTDIR\al_import.exe" "$OUTDIR\wlan_prof-0.xml"'
;;ExecWait '"$INSTDIR\al_import.exe"'
;!insertmacro debug_cat 4 "profile import returned $0"
;IfErrors 0 +2
;MessageBox MB_OK "Wlan profile import failed! Continuing anyway!$\nMake sure to import wlan profile manually!"

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


