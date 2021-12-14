<?php 
namespace devices\ms; 
?>
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
File "profiles/*lan_prof*.xml"
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
  File "GEANTLink-x86.msi"
  nsArray::Set Delete_files "GEANTLink-x86.msi"
  File "GEANTLink-x64.msi"
  nsArray::Set Delete_files "GEANTLink-x64.msi"
  DetailPrint "Install GEANTLink installer"
  File "GEANTLink-x86.msi"
  nsArray::Set Delete_files "GEANTLink-ARM64.msi"
  File "GEANTLink-ARM64.msi"
  IfSilent +2
  MessageBox MB_OK  "<?php WindowsCommon::echoNsis( _("An additional piece of software 'GEANTlink' needs to be installed. This installation requires Administrator rights; you will be prompted to give permission for that action."))?>"
  !insertmacro debug_cat 1 "Run GEANTLink installer"
  !insertmacro debug_cat 3 'Execute: msiexec.exe /i "$OUTDIR\GEANTLink-$Platform.msi" REBOOT=Supress'
  ClearErrors
  ExecWait '$Msiexec.exe /i "$TEMP\GEANTLink-$Platform.msi" /qb REBOOT=Supress' $0
  !insertmacro debug_cat 4 "GEANTLink returned $0"
  StrCmp $0 0 Cont2
  ${If} $0 == 3010
  Push 1
  Pop $rebootRequired
  ${Else}
  IfSilent +2
  MessageBox MB_OK "<?php WindowsCommon::echoNsis( _("Error installing GEANTLink.\$\\nEmbedded installer didn't complete succesfully."))?>"
  Quit
  ${EndIf}
  Cont2:
  !insertmacro debug_cat 3 'Insert key "SOFTWARE\GÉANT\GÉANTLink" "Language" "${LOCALE}"'
  WriteRegStr HKCU "SOFTWARE\GÉANT\GÉANTLink" "Language" "${LOCALE}"
FunctionEnd

; Install credentials
Function InstallGEANTLinkCred
  DetailPrint "<?php WindowsCommon::echoNsis(_("installing credentials for \${PROVIDERID}"))?>"
  !insertmacro debug_cat 3 "Execute: $TEMP\CredWrite.exe $\"$UNAME_B64$\" $\"base_64_password_not_recorded$\" $\"${PROVIDERID}$\" 1"
  nsExec::Exec "$TEMP\CredWrite.exe $\"$UNAME_B64$\" $\"$PASS_B64$\" $\"${PROVIDERID}$\" 1"
  Pop $0
  !insertmacro debug_cat 3 "CredWrite.exe returned $0"
  StrCmp $0 0 Cont1
; Error Label, show error box
  IfSilent +2
  MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis(_("Credentials installation problem"))?>"
  Cont1:
FunctionEnd
;=============================

