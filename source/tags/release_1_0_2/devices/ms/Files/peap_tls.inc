; used by Vista/W7 module
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
;Sleep 2000
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


;----------------------------
; Functions which need to defined after sections
!ifdef TLS
Function .onSelChange
  SectionGetFlags ${cert} $PFX_install
FunctionEnd

Function showComponents
  !ifdef TLS_CERT_STRING
  FileOpen $9 check_pfx.cmd w
  FileWrite $9 '@certutil -user -store my | find "${TLS_CERT_STRING}"'
  FileClose $9
  nsExec::Exec "$TEMP\check_pfx.cmd"
  !else
  Push 1
  !endif
  Pop $PFX_install
  ${If} $PFX_install == 0
    SectionSetFlags ${cert} 0
  ${Else}
    SectionSetFlags ${cert} 17
  ${EndIf}

;TRANSLATION
  !insertmacro MUI_HEADER_TEXT "<?php echo _("Information about components")?>" " "
FunctionEnd
!endif
