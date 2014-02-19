; Used by Vista/W7/W8 modules
;---------------------------------------

!define DEBUG_CAT 5
Var HEADLINE
Var TEXT
Var IMAGECTL
Var IMAGE

Var HEADLINE_FONT
;-----------------------------------------------

;--------------------------------
;Include Modern UI

	!include "MUI.nsh"
	!include "Sections.nsh"
	!include "nsDialogs.nsh"
	!include "LogicLib.nsh"
	!include "NSISArray.nsh"
	!include "main.nsh"
        !include "WordFunc.nsh"
        !include "FileFunc.nsh"
!ifdef SW2
        !include "ZipDLL.nsh"
!endif

XPStyle on
SetCompressor bzip2


${Array} "Profile_names" 5 40
${ArrayFunc} Read
${ArrayFunc} Write
${ArrayFunc} Concat
${ArrayFunc} Debug

${Array} "Profile_states" 5 2
${ArrayFunc} Read
${ArrayFunc} Write

${Array} "Delete_files" 20 30
${ArrayFunc} ReadFirst
${ArrayFunc} ReadNext
${ArrayFunc} Push
${ArrayFunc} ReadClose

${Array} "Commands" 6 80
${ArrayFunc} ReadFirst
${ArrayFunc} ReadNext
${ArrayFunc} Shift
${ArrayFunc} ReadClose


!define PROFILE_STATE_DELETE 1
 
!define PROFILE_STATE_FAIL 2

Var Debug_cat
Var Debug_file
Var Dialog
Var profile_fail
Var profile_count
Var aes_count
Var tkip_count
Var welcome_message
Var VistaNoSP
Var W7
Var WindowsVer
Var Symantec_installed
!ifdef TLS
  Var Cert_file
  Var PFX_install
  Var Dialog1
  var Password
!endif
!ifdef PEAP
!define CRED
!endif
!ifdef CRED
  Var UNAME_F
  Var UNAME
  Var PASS1_F
  Var PASS1
  Var PASS2_F
  Var PASS2
!endif
!ifdef SW2
  Var Radio1
  Var Radio2
  Var Radioi
  Var Reboot
  Var SkipReboot
!endif

!include "base64.nsh"

;--------------------------------
;General

  ;Name and file
  Name "${APPLICATION} ${VERSION}"
  OutFile "${INSTALLER_NAME}"
!ifdef EXECLEVEL
  RequestExecutionLevel ${EXECLEVEL}
!endif

  ShowInstDetails show

;--------------------------------
;Interface Settings

!define MUI_ICON "eduroam32.ico";
!define MUI_UNICON "eduroam32.ico"
!define MUI_ABORTWARNING
!define MUI_HEADERIMAGE
!define MUI_HEADERIMAGE_RIGHT
!define MUI_HEADERIMAGE_BITMAP "eduroam_150.bmp"
!define MUI_COMPONENTSPAGE_SMALLDESC

;TRANSLATION
!define WELCOME_HEADER "<?php printf(sprint_nsi(_("Welcome to the %s installer")),Config::$CONSORTIUM['name'])?>"
!define FAREWELL_HEADER "<?php echo_nsi( _("Installation complete"))?>"
!define FAREWELL_TEXT	"<?php echo_nsi( _("Network profiles have been installed."))?>$\r$\n$\n <?php echo_nsi(  _("Your system is ready."))?>"
!define FAREWELL_FAIL	"<?php echo_nsi( _("Network installation had errors."))?>$\r$\n$\n <?php echo_nsi( _("Please contact \${SUPPORT}."))?>"
;--------------------------------
;Languages
!insertmacro MUI_LANGUAGE "${LANG}"

;--------------------------------
; License file
!ifdef LICENSE_FILE
;TRANSLATION
LicenseForceSelection checkbox "<?php echo_nsi( _("Accept"))?>"
LicenseText "<?php echo_nsi(_("If you accept the conditions then select Accept and then click Install to continue."))?>"
LicenseData ${LICENSE_FILE}
!endif

;-----------------------------------

MiscButtonText "" "" "" "<?php echo_nsi( _("Finish"))?>"
;----------------------------------
;  FUNCTIONS

!macro debug_cat deb_level message
!ifdef DEBUG_CAT
 ${If} $Debug_cat >= ${deb_level}
    FileWrite $Debug_file '${message}'
    FileWriteByte $Debug_file "13"
    FileWriteByte $Debug_file "10"
 ${EndIf}
!endif
!macroend


!macro define_wlan_profile prof enc
  ${Profile_names->Write} $profile_count "${prof}"
  IntOp $profile_count $profile_count + 1
  ${If} ${enc} == "TKIP"
    IntOp $tkip_count $tkip_count + 1
  ${EndIf}
  ${If} ${enc} == "AES"
    IntOp $aes_count $aes_count + 1
  ${EndIf}
!macroend

!macro install_ca_cert ca_file ca_fingerprint level
   !insertmacro debug_cat 2 "locating certificate  SHA=${ca_fingerprint} Level=${level}"
   DetailPrint "<?php echo_nsi( _("searching for certificate"))?> ${ca_file}"
   File "${ca_file}"
   ${Delete_files->Push} "${ca_file}"
;;TRANSLATION
   !insertmacro debug_cat 2 "Testing machine store root"
   !insertmacro debug_cat 3 "Execute: certutil -store root ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store root ${ca_fingerprint}'
   Pop $0
   !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "Testing machine store authroot"
   !insertmacro debug_cat 3 "Execute: certutil -store authroot ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store authroot ${ca_fingerprint}'
   Pop $0
   !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      !insertmacro debug_cat 2 "Found AUTHROOT"
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "Testing machine store ca"
   !insertmacro debug_cat 3 "Execute: certutil -store ca ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store ca ${ca_fingerprint}'
   Pop $0
   !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      !insertmacro debug_cat 2 "Found CA"
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "Testing user store root"
   !insertmacro debug_cat 3 "Execute: certutil -store -user root ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store -user root ${ca_fingerprint}'
   Pop $0
  !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      !insertmacro debug_cat 2 "Found USER ROOT"
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "Testing user store root"
   !insertmacro debug_cat 3 "Execute: certutil -store -user authroot ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store -user authroot ${ca_fingerprint}'
   Pop $0
  !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      !insertmacro debug_cat 2 "Found USER AUTHROOT"
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "Testing user store ca"
   !insertmacro debug_cat 3 "Execute: certutil -store -user ca ${ca_fingerprint}"
   nsExec::Exec '"certutil" -store -user ca ${ca_fingerprint}'
   Pop $0
  !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 == 0
      !insertmacro debug_cat 2 "Found USER CA"
      Goto no_install_${ca_file}
   ${EndIf}
   !insertmacro debug_cat 2 "installing certificate $TEMP\${ca_file}"
   !insertmacro debug_cat 3 "Execute: certutil -addstore -user ${level} $TEMP\${ca_file}"
   DetailPrint "<?php echo_nsi( _("installing certificate"))?> ${ca_file}"
   nsExec::Exec '"certutil" -addstore -user ${level} $TEMP\${ca_file}'
   Pop $0
   !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 != 0
     MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi( _("could not install certificate"))?>  ${ca_file}"
     !insertmacro debug_cat 1 "could not install certificate ${ca_file}"
   ${EndIf}
no_install_${ca_file}:
!macroend

Function .onInit

  ${GetParameters} $R0
  ${GetOptions} "$R0" "-DEBUG=" $R1
        IfErrors 0 +3
        Push 0
        Goto +2
        Push $R1
  Pop $Debug_cat

  !insertmacro MUI_LANGDLL_DISPLAY
  SetOutPath $TEMP 
  !ifdef DEBUG_CAT
    ${If} $Debug_cat > 0
      FileOpen $Debug_file CAT.log w
      MessageBox MB_OK|MB_ICONEXCLAMATION "Debug mode - see information in $TEMP\CAT.log"
    ${EndIf}
  !endif

  CreateFont $HEADLINE_FONT "$(^Font)" "13" "700"
  ${Profile_names->Init}
  ${Profile_states->Init}
  ${Delete_files->Init}
  ${Commands->Init}
  Push 0
  Pop $profile_count
  Push 0
  Pop $aes_count
  Push 0
  Pop $tkip_count
  !include "profiles.nsh"
  IntOp $profile_count $profile_count - 1
  File "wlan_test.exe"
  ${Delete_files->Push} "wlan_test.exe"
  !ifdef BACKGROUND_IMG
    File /oname=eduroam_background.bmp "${BACKGROUND_IMG}"
  !else
    File /oname=eduroam_background.bmp "cat_bg.bmp"
  !endif
  ${Delete_files->Push} "eduroam_background.bmp"
  !ifdef EXTERNAL_INFO
    File "${EXTERNAL_INFO}"
    ${Delete_files->Push} "${EXTERNAL_INFO}"
  !endif
  Push 0
  Pop $profile_fail


  Call wVersionCheck
  Call Wireless_Check
  Call CheckSymantec
FunctionEnd

;Function .onUserAbort
;    Call Cleanup
;FunctionEnd


; COMMON FUNCTIONS

Function HideControls
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 1028
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1256
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1035
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1037
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1038
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1039
  ShowWindow $0 ${SW_HIDE}

  GetDlgItem $0 $HWNDPARENT 1045
  ShowWindow $0 ${SW_NORMAL}
  LockWindow off
FunctionEnd

Function HideButtons
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 2
  ShowWindow $0 ${SW_HIDE}
  GetDlgItem $0 $HWNDPARENT 3
  ShowWindow $0 ${SW_HIDE}
  LockWindow off
FunctionEnd
    
Function ShowControls
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 1028
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1256
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1035
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1037
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1038
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1039
  ShowWindow $0 ${SW_NORMAL}

  GetDlgItem $0 $HWNDPARENT 1045
  ShowWindow $0 ${SW_HIDE}
  LockWindow off
FunctionEnd

Function PrepareWelcomeText  

${Profile_names->Concat} $R0 ", "

;TRANSLATION
${If} $tkip_count > 0
  ${If} $aes_count > 1
    StrCpy $R1 "$\r$\n$\n<?php echo_nsi( _("The non TKIP profiles are preferred. Always use them if you have a choice."))?>"
  ${Else}
      StrCpy $R1 "$\r$\n$\n<?php echo_nsi( _("The non TKIP profile is preferred. Always use it if you have a choice."))?>"
  ${EndIf}
${Else}
  StrCpy $R1 ""
${EndIf}

StrCpy $welcome_message "<?php echo_nsi( _("This installer has been prepared for \${ORGANISATION}"))?>.$\r$\n\
<?php echo_nsi( _("The installer will create the following wireless profiles:"))?>$\r$\n\
$R0.\
$R1$\r$\n$\n\
<?php echo_nsi( _("More information and comments:"))?>$\r$\n\
   EMAIL: ${SUPPORT}$\r$\n\
   WWW: ${URL}"
FunctionEnd


Function nsDialogsWelcome
  call PrepareWelcomeText  
  nsDialogs::Create 1044
  Pop $DIALOG

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS}|${SS_BITMAP} 0 0 0 109u 193u ""
  Pop $IMAGECTL

  StrCpy $0 "$TEMP\eduroam_background.bmp"
  System::Call 'user32::LoadImage(i 0, t r0, i ${IMAGE_BITMAP}, i 0, i 0, i ${LR_LOADFROMFILE}) i.s'
  Pop $IMAGE
	
  SendMessage $IMAGECTL ${STM_SETIMAGE} ${IMAGE_BITMAP} $IMAGE

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 10u -130u 36u "${WELCOME_HEADER}"
  Pop $HEADLINE
  SendMessage $HEADLINE ${WM_SETFONT} $HEADLINE_FONT 0
;TRANSLATION
  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 46u -130u -32u "$welcome_message\
$\r$\n$\r$\n<?php echo_nsi( ( Config::$CONSORTIUM['name'] == "eduroam" ? 
                                _("Installer created with software from the GEANT project.") : 
                                sprintf(_("This installer was brought to you by %s. See %s for details."),Config::$APPEARANCE['productname_long'],( isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))) ))?>"
  Pop $TEXT

  SetCtlColors $DIALOG "" 0xffffff
  SetCtlColors $HEADLINE "" 0xffffff
  SetCtlColors $TEXT "" 0xffffff
  Call HideControls
  nsDialogs::Show
  Call ShowControls
  System::Call gdi32::DeleteObject(i$IMAGE)
FunctionEnd



;  Check for Windows Version
;
Function wVersionCheck
  Push 0
  Pop $VistaNoSP
  GetVersion::WindowsVersion
  Pop $R0
  GetVersion::WindowsServicePackBuild
  Pop $R1
  ${VersionCompare} "$R0" "6.2" $R5
!ifdef W8
  ${If} $R5 == 2
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php echo_nsi( _("This installer works with Windows 8 only"))?>"
!ifndef ALLOW_LOW_VERSION
    Call Cleanup
!endif
  ${EndIf}
  push 8
  pop $WindowsVer
!else
  ${If} $R5 < 2
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php echo_nsi( _("This installer works with Windows Vista and Windows 7 only"))?>"
    Call Cleanup
  ${EndIf}
  ${VersionCompare} "$R0" "6.0" $R5
  ${If} $R5 < 2
    ${If} $R1 >= 7000
      Push 1
      Pop $W7
      push 7
      pop $WindowsVer
    ${ElseIf} $R1 >= 6001
      Push 0
      Pop $W7
      push 6
      pop $WindowsVer
    ${Else}
      Push 0
      Pop $W7
      Push 1
      Pop $VistaNoSP
      push 6
      pop $WindowsVer
    ${EndIf}
  ${EndIf}
  !insertmacro debug_cat 3 "VistaNoSP:$VistaNoSP; W7:$W7"

  System::Call kernel32::GetVersion()i.s
  Pop $R0
  !insertmacro debug_cat 3 "Version $R0"
  IntOp $R1 $R0 & 0xff
;TRANSLATION
  !insertmacro debug_cat 3 "Version $R1"
  ${If} $R1 < 6
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php echo_nsi( _("This installer works with Windows Vista and Windows 7 only"))?>"
!ifndef ALLOW_XP
    Call Cleanup
!endif
  ${EndIf}
!endif
   !insertmacro debug_cat 3 "WindowsVer:$WindowsVer"
FunctionEnd

; Check if wireless intefaces and service are running
Function Wireless_Check
  !insertmacro debug_cat 3 "Exec: $TEMP\wlan_test.exe"
  nsExec::Exec "$TEMP\wlan_test.exe"
  Pop $0
  !insertmacro debug_cat 3 "wlan_test.exe returned $0"
  ${If} $0 == 1
    !insertmacro debug_cat 1 "wlan_test.exe did not find wireless configuration service"
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi( _("Windows wireless configuration service is not enabled. The installer cannot continue."))?>"
    Call Cleanup
  ${EndIf}
  ${If} $0 == 2
    !insertmacro debug_cat 1 "wlan_test.exe did not find wireless interfaces"
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi( _("No wireless interfaces found. The installer cannot continue."))?>"
    Call Cleanup
  ${EndIf}
FunctionEnd

; Check for installed EAP metod
  Function CheckEAP
    pop $R0
    !insertmacro debug_cat 3 "testing for EAP: $R0"
    readRegStr $0 HKLM "SYSTEM\CurrentControlSet\services\RasMan\PPP\EAP\$R0" Path
    !insertmacro debug_cat 3 "EAP test returned: $0"
    push $0
  FunctionEnd

; Chect for Symantec problem
  Function CheckSymantec
    push 88
    call CheckEAP
    pop $Symantec_installed
    !insertmacro debug_cat 3 "Symantec test returned: $Symantec_installed"
  FunctionEnd

;================================

;TRANSLATION
Function ShowInstfiles
  !insertmacro MUI_HEADER_TEXT "<?php echo_nsi( _("Profiles installation"))?>" " "
FunctionEnd
Function ProfileCheck
  Pop $R0
  ${Profile_names->Read} $R1 $R0
  DetailPrint "<?php echo_nsi( _("Checking for profile \$R1"))?>"
  !insertmacro debug_cat 2 "Checking for profile $R1"
  !insertmacro debug_cat 3 "Exec: netsh wlan show profiles $R1"
  nsExec::Exec  '"netsh" wlan show profiles "$R1"'
  Pop $0
  !insertmacro debug_cat 3 "netsh returned $0"
  ${If} $0 == 0
    !insertmacro debug_cat 1 "found profile $R1"
    DetailPrint "<?php echo_nsi( _("found profile \$R1"))?>"
    ${Profile_states->Write} $R0 ${PROFILE_STATE_DELETE}
  ${Else}
    ${Profile_states->Write} $R0 0
  ${EndIf}
FunctionEnd
;==========================

Function CheckWirelessProfiles
  !ifdef CRED
  File setEAPCred.exe
  ${Delete_files->Push} "setEAPCred.exe"
  !endif
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call ProfileCheck
  ${Next}
FunctionEnd
;=============================

Function confirmUserGroup
;TRANSLATION
!ifdef USER_GROUP
MessageBox MB_OKCANCEL "<?php echo_nsi(_("This installer will only work properly if you are a member of \${ORGANISATION} and the user group: \${USER_GROUP}."))?>$\r$\n<?php echo_nsi(_("Click OK to continue with installation."))?>" IDOK cont
!else 
MessageBox MB_OKCANCEL "<?php echo_nsi(_("This installer will only work properly if you are a member of \${ORGANISATION}."))?>$\r$\n<?php echo_nsi(_("Click OK to continue with installation."))?>" IDOK cont
!endif
    Call Cleanup
cont:
FunctionEnd

Function displayExternalInfo
;TRANSLATION
  MessageBox MB_OK "<?php echo_nsi(_("Please read the additional information from your home institution which will be displayed in a moment."))?>"
  ExecShell "open" "${EXTERNAL_INFO}"
FunctionEnd

;=============================

Function Finish
;MessageBox MB_OK "Finish"
  
   ${If} $profile_fail == 1
     Goto WriteScript
   ${EndIf}
   !ifdef DEBUG_CAT
     ${If} $Debug_cat > 0
       Goto WriteScript
     ${EndIf}
   !endif
   Goto SkipScript
   WriteScript:
   !insertmacro debug_cat 2 "writing $EXEDIR\inst_eduroam.cmd"
   FileOpen $9 "$EXEDIR\inst_eduroam.cmd" w
   ClearErrors
   ${Commands->ReadFirst} $R0 $R1
   Loop1:
    IfErrors Done1
    FileWrite $9 "$R1$\r$\n"
    ClearErrors
    ${Commands->ReadNext} $R0 $R1
    Goto Loop1
    Done1:
    FileWrite $9 "@TIMEOUT 10$\r$\n"
;    FileWrite $9 "del $EXEDIR\inst_eduroam.cmd"
   ${Commands->ReadClose} $R0
   FileClose $9
  SkipScript:
  nsDialogs::Create 1044
  Pop $DIALOG
        
  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS}|${SS_BITMAP} 0 0 0 109u 193u ""
  Pop $IMAGECTL
        
  StrCpy $0 "$TEMP\eduroam_background.bmp"
  System::Call 'user32::LoadImage(i 0, t r0, i ${IMAGE_BITMAP}, i 0, i 0, i ${LR_LOADFROMFILE}) i.s'
  Pop $IMAGE
        
  SendMessage $IMAGECTL ${STM_SETIMAGE} ${IMAGE_BITMAP} $IMAGE

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 10u -130u 20u "${FAREWELL_HEADER}"
  Pop $HEADLINE

  SendMessage $HEADLINE ${WM_SETFONT} $HEADLINE_FONT 0
!ifdef SW2
  ${NSD_CreateRadioButton} 150u 80u 50% 12u "<?php echo_nsi(_("Restart system now"))?>"
  Pop $Radio1
  ${NSD_CreateRadioButton} 150u 120u 50% 12u "<?php echo_nsi(_("I will restart later"))?>"
  Pop $Radio2
  ${NSD_SetState} $Radio1 ${BST_CHECKED}
  SetCtlColors $Radio1 "" 0xffffff
  SetCtlColors $Radio2 "" 0xffffff
!else
  ${If} $profile_fail == 1
    !insertmacro debug_cat 1 "Profile installation failed"
    ${If} $Symantec_installed  != 0
     !insertmacro debug_cat 3 "Symantec problem"
     MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi(_("Please READ this message it is IMPORTANT.$\\r$\\nInstallation problems may be due to the fact that Symantec Endpoint Protection is installed on your machine.$\\r$\\nWhile this is a well-known bug of the Symantec product, about which the installer can not do anything, there is a workaround.$\\r$\\nWhen you close this window the installer will exit and an explorer window will be started (it could appear underneath already opened windows). In this window you should see a script named inst_eduroam. Start it by double-clicking, It will install the profiles. You will need to login to eduroam with your username and password.")) ?>"
    Exec '"explorer" /select,"$EXEDIR\inst_eduroam.cmd"'
    Quit

    ${EndIf}
     nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 32u -130u -32u "${FAREWELL_FAIL}"
  ${Else}
     nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 32u -130u -32u "${FAREWELL_TEXT}"
  ${EndIf}
     Pop $TEXT
     SetCtlColors $TEXT "" 0xffffff
!endif
  SetCtlColors $DIALOG "" 0xffffff
  SetCtlColors $HEADLINE "" 0xffffff

  Call HideControls
  Call HideButtons
  nsDialogs::Show

  System::Call gdi32::DeleteObject(i$IMAGE)

  nsDialogs::Create 1018
  Pop $Dialog
  ${If} $Dialog == error
        Abort
  ${EndIf}
FunctionEnd


!ifdef SW2
Function FinishLeave
   ${NSD_GetState} $Radio1 $Reboot
   ${NSD_GetState} $Radio2 $SkipReboot
   ${If} $Reboot == ${BST_CHECKED}
      Reboot
   ${EndIf}
FunctionEnd
!else

;=========================

;==========================
Function CreateWirelessProfiles
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call InstallProfile
  ${Next}

FunctionEnd
;=============================

Function InstallProfile
  Pop $R0
  ${Profile_names->Read} $R1 $R0
  ${Profile_states->Read} $R2 $R0
  ${If} $R2 == ${PROFILE_STATE_DELETE}
;TRANSLATION
    DetailPrint "<?php echo_nsi(_("deleting profile \$R1"))?>"
    !insertmacro debug_cat 1 'deleting profile "$R1"'
    !insertmacro debug_cat 3 'Execute: netsh wlan delete profile "$R1"'
    ${Commands->Shift} "netsh wlan delete profile $\"$R1$\""
    nsExec::Exec '"netsh" wlan delete profile "$R1"'
    Pop $0
    !insertmacro debug_cat 3 "netsh returned $0"
    !insertmacro debug_cat 1 "Profile $R1 deleted"
  ${EndIf}
;TRANSLATION
  DetailPrint "<?php echo_nsi(_("installing profile \$R1"))?>"
  !insertmacro debug_cat 3 "Execute: netsh wlan add profile $TEMP\wlan_prof-$R0.xml"
  ${Commands->Shift} "netsh wlan add profile $TEMP\wlan_prof-$R0.xml"
;  ${Commands->Shift} "del $TEMP\wlan_prof-$R0.xml"
  nsExec::Exec '"netsh" wlan add profile $TEMP\wlan_prof-$R0.xml'
  Pop $0
  !insertmacro debug_cat 3 "netsh returned $0"
;TRANSLATION
  ${If} $0 <> 0
    !insertmacro debug_cat 1 'Profile installation error for "$R1"'
    MessageBox MB_OK "<?php echo_nsi(_("Profile installation error for \$R1."))?>$\r$\n<?php echo_nsi(_("Please contact \${SUPPORT}."))?>"
    ${Profile_states->Write} $R0 ${PROFILE_STATE_FAIL}
    Push 1
    Pop $profile_fail
  ${Else}
  !ifndef DEBUG_CAT
     Delete "$TEMP\wlan_prof-$R0.xml"
  !endif
  !ifdef DEBUG_CAT
   ${If} $Debug_cat == 0
     Delete "$TEMP\wlan_prof-$R0.xml"
  ${EndIf}
  !endif
  !ifdef CRED
    !insertmacro debug_cat 1 "installing credentials for profile $R1"
    DetailPrint "<?php echo_nsi(_("installing credentials for profile \$R1"))?>"
    !insertmacro debug_cat 3 "Execute: $TEMP\setEAPCred.exe $\"$UNAME$\" $\"base_64_password_not_recorded$\" $\"$R1$\""
    nsExec::Exec "$TEMP\setEAPCred.exe $\"$UNAME$\" $\"$PASS1$\" $\"$R1$\""
    pop $0
    !insertmacro debug_cat 3 "setEAPCred.exe returned $0"
    StrCmp $0 0 Cont1
; Error Label, show error box
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi(_("Credentials installation problem"))?>"
    Cont1:
  !endif
  ${EndIf}
FunctionEnd
;=============================
!endif
!ifdef TLS
Function PFXCertificateSelect
;TRANSLATION
!insertmacro MUI_HEADER_TEXT "<?php sprintf(sprint_nsi(_("%s installer for")),Config::$CONSORTIUM['name'])?> " "<?php echo_nsi(_("Install personal certificate"))?>"
  ${If} $PFX_install == 0
    Return
  ${EndIf}
;TRANSLATION
  MessageBox MB_OK "<?php echo_nsi(_("Preparing to install personal certificate."))?>$\r$\n<?php echo_nsi(_("Click OK to continue"))?> "
  nsDialogs::Create 1018
  Pop $Dialog1
  ${If} $Dialog1 == error
    Abort
  ${EndIf}
  nsDialogs::SelectFileDialog open "" "<?php echo_nsi(_("Certificate files (*.p12)"))?>|*.p12|<?php echo_nsi(_("Certificate files (*pfx)"))?>|*.pfx|<?php echo_nsi(_("All files"))?>|*.*"
  Pop $Cert_file
;TRANSLATION
  ${NSD_CreateLabel} 0 0 100% 32u "<?php echo_nsi(_("Selected file: \$Cert_file"))?>"
  !insertmacro debug_cat 3 "Vista level (Vista without Service pack): $VistaNoSP";
  ${If} $VistaNoSP == 0
    ${NSD_CreateLabel} 0 35u 80u 12u "<?php echo_nsi(_("certificate password:"))?>"
    ${NSD_CreatePassword} 85u 34.5u 100u 12u ""
    Pop $Password
    ${NSD_SetFocus} $Password
  ${EndIf}
  nsDialogs::Show
FunctionEnd
;===========================

Function PFXCertificateSelectLeave
;TRANSLATION
   ${If} $Cert_file == ""
      MessageBox MB_OK  "<?php echo_nsi(_("No certificate file selected"))?>"
      Call Cleanup
   ${EndIf}

   ${If} $VistaNoSP == 1
     System::Call "cryptext::CryptExtAddPFX(i $HWNDPARENT, i 0, t '$Cert_file', i 1)"
   ${Else}
      ${NSD_GetText} $Password $0
      !insertmacro debug_cat 3 'Execute: "certutil" -importpfx -user -p "password_not_recorded" "$Cert_file"'
      nsExec::Exec '"certutil" -importpfx -user -p "$0" "$Cert_file"'
      Pop $1
      !insertmacro debug_cat 3 "certutil returned $1"
;TRANSLATION
    ${If} $1 <> 0
      MessageBox MB_OK  "<?php echo_nsi(_('Incorrect certificate password$\r$\nenter again'))?>"
      Abort
    ${EndIf}
   ${EndIf}
FunctionEnd
;===========================
!endif
!ifdef CRED
Function UserCredentials

  !insertmacro MUI_HEADER_TEXT "<?php echo_nsi(_("User credentials"))?>" " "
        nsDialogs::Create 1018
        Pop $0

        ${NSD_CreateLabel} 10 36u 60u 12u "<?php echo_nsi(_("Username:"))?>"
        ${NSD_CreateText} 67u 35u 80u 12u "$UNAME"
        Pop $UNAME_F
        ${NSD_CreateLabel} 10 51u 60u 12u "<?php echo_nsi(_("Password:"))?>"
        ${NSD_CreatePassword} 67u 50u 80u 12u "$PASS1"
        Pop $PASS1_F
        ${NSD_CreateLabel} 10 66u 60u 22u "<?php echo_nsi(_("Repeat password:"))?>"
        ${NSD_CreatePassword} 67u 65u 80u 12u "$PASS2"
        Pop $PASS2_F
        nsDialogs::Show
FunctionEnd

Function SaveCredentials
${NSD_GetText} $UNAME_F $UNAME
${NSD_GetText} $PASS1_F $PASS1
${NSD_GetText} $PASS2_F $PASS2
${If} "$UNAME" == ""
  MessageBox MB_OK "<?php echo_nsi(_("Missing username"))?>"
  Abort
${EndIf}

${If} "$PASS1" == ""
  MessageBox MB_OK "<?php echo_nsi(_("Missing password"))?>"
  Abort
${EndIf}
${If} "$PASS1" != "$PASS2"
${NSD_SetText} $PASS1_F ""
${NSD_SetText} $PASS2_F ""
MessageBox MB_OK "<?php echo_nsi(_("Password missmatch"))?>"
Abort
${EndIf}
${Base64_Encode} $PASS1
Pop $PASS1
FunctionEnd
!endif

Function Cleanup
${If} $Debug_cat > 0
  Quit
${EndIf}
ClearErrors
${Delete_files->ReadFirst} $R0 $R1
Loop:
IfErrors Done
 Delete $R1
 ClearErrors
 ${Delete_files->ReadNext} $R0 $R1
Goto Loop
Done:
${Delete_files->ReadClose} $R0
Quit
FunctionEnd

; ---- PAGES ----------------------------

Page custom nsDialogsWelcome
Page custom confirmUserGroup
;============================
!ifdef LICENSE_FILE
  Page license
!endif
;============================
!ifdef TLS
PageEx components
PageCallbacks showComponents "" ""
;TRANSLATION
ComponentText "<?php sprintf(sprint_nsi(_("In order to install/update %s access all selected steps need to be performed. Click Continue to proceed")),Config::$CONSORTIUM['name'])?>" "" \
      "<?php echo_nsi(_("If an option is not selected by default, then it is either not available or optional."))?>"
PageExEnd
;=============================
Page custom PFXCertificateSelect PFXCertificateSelectLeave
;=============================
!endif
!ifdef CRED
Page custom UserCredentials SaveCredentials
!endif
;=============================
Page instfiles ShowInstfiles
;=============================
!ifdef SW2
PageEx custom
;=============================
PageCallbacks Finish FinishLeave
PageExEnd
!else
Page custom Finish Cleanup
!endif

;----------------------------- Installer Sections ------------------------
Section "-start"
SectionIn RO

!insertmacro MUI_HEADER_TEXT "Installation" "Checking for existing wireless profiles"
!ifndef PWD
!ifdef SW2
File cert-*.crt
!else
!include "certs.nsh"
!endif
!endif
SectionEnd


; COMMON END
