<?php 
namespace devices\ms; 
?>
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
Section "<?php echo _("user profile")?>" geant_link
SectionIn RO
SectionEnd

Section "<?php echo _("install program")?>" eap_geant_link
SectionIn RO
!insertmacro MUI_HEADER_TEXT  "<?php echo _("Installation")?>" "GÉANTLink"
File "CredWrite.exe"
nsArray::Set Delete_files "CredWrite.exe"
File "MsiUseFeature.exe"
nsArray::Set Delete_files "MsiUseFeature.exe"
Call InstallGEANTLink
SectionEnd
;TRANSLATION
Section "<?php echo _("Installation of network profiles")?>" "<?php echo _("profiles")?>"
SectionIn RO
!insertmacro MUI_HEADER_TEXT "<?php echo _("Installation")?>" "<?php echo _("Network profiles update")?>"
!ifdef W8
!insertmacro debug_cat 4 "unpacking profiles for W8"
File "w8/*lan_prof*.xml"
!else
${If} $WindowsVer == 6
!insertmacro debug_cat 4 "unpacking profiles for Vista"
File "vista/*lan_prof*.xml"
${EndIf}
${If} $WindowsVer == 7
!insertmacro debug_cat 4 "unpacking profiles for W7"
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
Call InstallGEANTLinkCred
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

; check if GEANTLink needs to be installed
Function InstallGEANTLink
  !insertmacro debug_cat 3 "Execute: $TEMP\MsiUseFeature.exe featEAPTTLS"
  nsExec::Exec  '$TEMP\MsiUseFeature.exe featEAPTTLS'
  Pop $0
  !insertmacro debug_cat 4 "MsiUseFeature returned $0"
  StrCmp $0 0 Cont2
  File "GEANTLink32.msi"
  nsArray::Set Delete_files "GEANTLink32.msi"
  File "GEANTLink64.msi"
  nsArray::Set Delete_files "GEANTLink64.msi"
  DetailPrint "Install GEANTLink installer"
  MessageBox MB_OK  "<?php WindowsCommon::echo_nsi( _("An additional piece of software 'GEANTlink' needs to be installed. This installation requires Administrator rights; you will be prompted to give permission for that action."))?>"
  !insertmacro debug_cat 1 "Run GEANTLink installer"
  !insertmacro debug_cat 3 'Execute: msiexec.exe /i "$OUTDIR\GEANTLink$Platform.msi" REBOOT=Supress'
  ClearErrors
  ExecWait '$Msiexec.exe /i "$TEMP\GEANTLink$Platform.msi" /qb REBOOT=Supress' $0
  !insertmacro debug_cat 4 "GEANTLink returned $0"
  StrCmp $0 0 Cont2
  ${If} $0 == 3010
  Push 1
  Pop $rebootRequired
  ${Else}
  MessageBox MB_OK "<?php WindowsCommon::echo_nsi( _("Error installing GEANTLink.\$\\nEmbedded installer didn't complete succesfully."))?>"
  Quit
  ${EndIf}
  Cont2:
  !insertmacro debug_cat 3 'Insert key "SOFTWARE\GÉANT\GÉANTLink" "Language" "${LOCALE}"'
  WriteRegStr HKCU "SOFTWARE\GÉANT\GÉANTLink" "Language" "${LOCALE}"
FunctionEnd

; Install credentials
Function InstallGEANTLinkCred
  DetailPrint "<?php WindowsCommon::echo_nsi(_("installing credentials for \${PROVIDERID}"))?>"
  !insertmacro debug_cat 3 "Execute: $TEMP\CredWrite.exe $\"$UNAME_B64$\" $\"base_64_password_not_recorded$\" $\"${PROVIDERID}$\" 1"
  nsExec::Exec "$TEMP\CredWrite.exe $\"$UNAME_B64$\" $\"$PASS_B64$\" $\"${PROVIDERID}$\" 1"
  Pop $0
  !insertmacro debug_cat 3 "CredWrite.exe returned $0"
  StrCmp $0 0 Cont1
; Error Label, show error box
  IfSilent +2
  MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echo_nsi(_("Credentials installation problem"))?>"
  Cont1:
FunctionEnd
;=============================

