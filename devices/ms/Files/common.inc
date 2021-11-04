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
; Used by Vista/W7/W8/W10 module
;---------------------------------------

; Start

;SetCompress auto
SetCompressor bzip2
CRCCheck on
;ManifestDPIAware true


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
        !include "nsArray.nsh"
	!include "main.nsh"
        !include "WordFunc.nsh"
        !include "FileFunc.nsh"
        !include "x64.nsh"
        !include "WinVer.nsh"
!ifdef WIRED
   !ifdef EXECLEVEL
      !undef EXECLEVEL
   !endif
      !define EXECLEVEL "admin"
!endif

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
Var install_wireless_credentials
Var install_wired_credentials
Var VistaNoSP
Var W7
Var WindowsVer
Var WindowsName
Var WindowsBuild
Var NoFCupdate
Var Symantec_installed
Var wireless_result
Var wired
Var force_wired
Var Platform
Var Netsh
Var Msiexec
Var main_ssid
Var rebootRequired
Var spacesRemoved
!ifdef GEANTLink
  !define CRED
  !define PASSWORD_BASED_CRED
!endif
!ifdef PEAP
  !define CRED
  !define PASSWORD_BASED_CRED
!endif
!ifdef TTLS
  !define CRED
  !define PASSWORD_BASED_CRED
!endif
!ifdef TLS
  !define CRED
  Var Cert_file
  Var Dialog1
  Var tlsFingerprint
!endif
!ifdef CRED
  Var UNAME_F
  Var UNAME
  Var PASS1_F
  Var PASS1
  Var PASS_B64
  Var UNAME_B64
  Var PASS2_F
  Var PASS2
  Var inputErrorMsg
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

!define MUI_ICON "cat32.ico";
!define MUI_UNICON "cat32.ico"
!define MUI_ABORTWARNING
!define MUI_HEADERIMAGE
!define MUI_HEADERIMAGE_RIGHT
!define MUI_HEADERIMAGE_BITMAP "cat_150.bmp"
!define MUI_COMPONENTSPAGE_SMALLDESC

!define WELCOME_HEADER "<?php printf(WindowsCommon::sprintNsis(_("Welcome to the %s installer")),\config\ConfAssistant::CONSORTIUM['display_name'])?>"
!define FAREWELL_HEADER "<?php WindowsCommon::echoNsis( _("Installation complete"))?>"
!define FAREWELL_TEXT	"<?php WindowsCommon::echoNsis( _("Network profiles have been installed."))?>$\r$\n$\n <?php WindowsCommon::echoNsis(  _("Your system is ready."))?>"
!define FAREWELL_FAIL	"<?php WindowsCommon::echoNsis( _("Network installation had errors."))?>$\r$\n$\n <?php WindowsCommon::echoNsis( _("Please contact \${SUPPORT}."))?>"
;--------------------------------
;Languages
!insertmacro MUI_LANGUAGE "${LANG}"

;--------------------------------
; License file
!ifdef LICENSE_FILE
LicenseForceSelection checkbox "<?php WindowsCommon::echoNsis( _("Accept"))?>"
LicenseText "<?php WindowsCommon::echoNsis(_("If you accept the conditions then select Accept and then click Install to continue."))?>"
LicenseData ${LICENSE_FILE}
!endif

;-----------------------------------

MiscButtonText "" "" "" "<?php WindowsCommon::echoNsis( _("Finish"))?>"
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

!macro define_delete_profile prof
  nsArray::Set Additional_deletes "${prof}"
!macroend


!macro define_wlan_profile prof enc hs20
  nsArray::Set Profile_names /key=$profile_count "${prof}"
  nsArray::Set Profile_hs20 /key=$profile_count ${hs20}  
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
   DetailPrint "<?php WindowsCommon::echoNsis( _("searching for certificate"))?> ${ca_file}"
   File "${ca_file}"
   nsArray::Set Delete_files "${ca_file}"
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
   ${If} ${level} == "ca"
      !insertmacro debug_cat 2 "Testing machine store ca"
      !insertmacro debug_cat 3 "Execute: certutil -store ca ${ca_fingerprint}"
      nsExec::Exec '"certutil" -store ca ${ca_fingerprint}'
      Pop $0
      !insertmacro debug_cat 3 "certutil returned $0"
      ${If} $0 == 0
         !insertmacro debug_cat 2 "Found CA"
         Goto no_install_${ca_file}
      ${EndIf}
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
   ${If} ${level} == "ca"
      !insertmacro debug_cat 2 "Testing user store ca"
      !insertmacro debug_cat 3 "Execute: certutil -store -user ca ${ca_fingerprint}"
      nsExec::Exec '"certutil" -store -user ca ${ca_fingerprint}'
      Pop $0
     !insertmacro debug_cat 3 "certutil returned $0"
      ${If} $0 == 0
         !insertmacro debug_cat 2 "Found USER CA"
         Goto no_install_${ca_file}
      ${EndIf}
   ${EndIf}
   !insertmacro debug_cat 2 "installing certificate $TEMP\${ca_file}"
   !insertmacro debug_cat 3 "Execute: certutil -addstore -user ${level} $TEMP\${ca_file}"
   DetailPrint "<?php WindowsCommon::echoNsis( _("installing certificate"))?> ${ca_file}"
   nsExec::Exec '"certutil" -addstore -user ${level} "$TEMP"\${ca_file}'
   Pop $0
   !insertmacro debug_cat 3 "certutil returned $0"
   ${If} $0 != 0
     IfSilent +2
     MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis( _("could not install certificate"))?>  ${ca_file}"
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
      FileOpen $Debug_file "$DOCUMENTS\CAT.log" w
      IfSilent +2
      MessageBox MB_OK|MB_ICONEXCLAMATION "Debug mode - see information in $DOCUMENTS\CAT.log"
    ${EndIf}
  !endif

  CreateFont $HEADLINE_FONT "$(^Font)" "13" "700"
  Push 0
  Pop $profile_count
  Push 0
  Pop $aes_count
  Push 0
  Pop $tkip_count
  Push 0
  Pop $install_wired_credentials
  Push 0
  Pop $install_wireless_credentials
  !ifdef WIRED 
    Push 1
  !else
    Push 0
  !endif
  Pop $wired
  Push 0
  Pop $force_wired
  Push 0
  Pop $rebootRequired
  !include "profiles.nsh"
  IntOp $profile_count $profile_count - 1
  File "wlan_test.exe"
  nsArray::Set Delete_files "wlan_test.exe"
  !ifdef BACKGROUND_IMG
    File /oname=cat_background.bmp "${BACKGROUND_IMG}"
  !else
    File /oname=cat_background.bmp "cat_bg.bmp"
  !endif
  nsArray::Set Delete_files "cat_background.bmp"
  !ifdef EXTERNAL_INFO
    File "${EXTERNAL_INFO}"
    nsArray::Set Delete_files "${EXTERNAL_INFO}"
  !endif
  !ifdef WIRED
    File "install_wired.cmd"
    nsArray::Set Delete_files "install_wired.cmd"
  !endif
  Push 0
  Pop $profile_fail

  # switch between nsis 3.03 and 3.04
  !if "${NSIS_PACKEDVERSION}" > 0x3003000 ;
    ${If} ${IsNativeAMD64}
      StrCpy $Platform "x64"
      StrCpy $Netsh "$WINDIR\sysnative\netsh"
      StrCpy $Msiexec "$WINDIR\sysnative\msiexec"
    ${ElseIf} ${IsNativeARM64}
      StrCpy $Platform "ARM64"
      StrCpy $Netsh "$WINDIR\sysnative\netsh"
      StrCpy $Msiexec "$WINDIR\sysnative\msiexec"
    ${ElseIf} ${IsNativeIA32}
      StrCpy $Platform "x86"
      StrCpy $Netsh "netsh"
      StrCpy $Msiexec "msiexec"
    ${Else}
       Abort "Unsupported CPU architecture!"
    ${EndIf}
  !else
    ${If} ${RunningX64}
      StrCpy $Platform "x64"
      StrCpy $Netsh "$WINDIR\sysnative\netsh"
      StrCpy $Msiexec "$WINDIR\sysnative\msiexec"
      ${Else}
      StrCpy $Platform "x86"
      StrCpy $Netsh "netsh"
      StrCpy $Msiexec "msiexec"
    ${EndIf}
  !endif
  !insertmacro debug_cat 3 "Platfrom:$Platform"

  Call wVersionCheck
  Call Wireless_Check
  Call NoWirelessConfirm
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

Function HideAllButtons
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 1
  ShowWindow $0 ${SW_HIDE}
  GetDlgItem $0 $HWNDPARENT 2
  ShowWindow $0 ${SW_HIDE}
  GetDlgItem $0 $HWNDPARENT 3
  ShowWindow $0 ${SW_HIDE}
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

Function ShowNextCancelButtons
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 1
  ShowWindow $0 ${SW_NORMAL}
  GetDlgItem $0 $HWNDPARENT 2
  ShowWindow $0 ${SW_NORMAL}
  GetDlgItem $0 $HWNDPARENT 3
  ShowWindow $0 ${SW_HIDE}
  LockWindow off
FunctionEnd

Function ShowAllButtons
  LockWindow on
  GetDlgItem $0 $HWNDPARENT 1
  ShowWindow $0 ${SW_NORMAL}
  GetDlgItem $0 $HWNDPARENT 2
  ShowWindow $0 ${SW_NORMAL}
  GetDlgItem $0 $HWNDPARENT 3
  ShowWindow $0 ${SW_NORMAL}
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
  nsArray::Join Profile_names ", " /noempty
  pop $R0
  ${If} $tkip_count > 0
    ${If} $aes_count > 1
      StrCpy $R1 "$\r$\n$\n<?php WindowsCommon::echoNsis( _("The non TKIP profiles are preferred. Always use them if you have a choice."))?>"
    ${Else}
     StrCpy $R1 "$\r$\n$\n<?php WindowsCommon::echoNsis( _("The non TKIP profile is preferred. Always use it if you have a choice."))?>"
    ${EndIf}
  ${Else}
    StrCpy $R1 ""
  ${EndIf}
  ${If} $profile_count > 0
    StrCpy $welcome_message "<?php WindowsCommon::echoNsis( _("This installer has been prepared for \${ORGANISATION}"))?>.$\r$\n\
<?php WindowsCommon::echoNsis( _("The installer will create the following wireless profiles:"))?>$\r$\n\
$R0.\
$R1$\r$\n$\n\
<?php WindowsCommon::echoNsis( _("More information and comments:"))?>$\r$\n\
   EMAIL: ${SUPPORT}$\r$\n\
   WWW: ${URL}"
${Else}
StrCpy $welcome_message "<?php WindowsCommon::echoNsis( _("This installer has been prepared for \${ORGANISATION}"))?>.$\r$\n\
<?php WindowsCommon::echoNsis( _("The installer will create the wireless profile:"))?> $R0.\
$R1$\r$\n$\n\
<?php WindowsCommon::echoNsis( _("More information and comments:"))?>$\r$\n\
   EMAIL: ${SUPPORT}$\r$\n\
   WWW: ${URL}"
${EndIf}
FunctionEnd


Function nsDialogsWelcome
  call PrepareWelcomeText  
  nsDialogs::Create 1044
  Pop $DIALOG

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS}|${SS_BITMAP} 0 0 0 109u 193u ""
  Pop $IMAGECTL

  StrCpy $0 "$TEMP\cat_background.bmp"
  System::Call 'user32::LoadImage(i 0, t r0, i ${IMAGE_BITMAP}, i 0, i 0, i ${LR_LOADFROMFILE}) i.s'
  Pop $IMAGE
	
  SendMessage $IMAGECTL ${STM_SETIMAGE} ${IMAGE_BITMAP} $IMAGE

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 10u -130u 36u "${WELCOME_HEADER}"
  Pop $HEADLINE
  SendMessage $HEADLINE ${WM_SETFONT} $HEADLINE_FONT 0
;TRANSLATION
  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 46u -130u -32u "$welcome_message\
$\r$\n$\r$\n<?php WindowsCommon::echoNsis( _("Installer created with software from the GEANT project."))?>"
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
  ${If} ${AtLeastWin10}
     Push 8
     Push "Windows 10"
  ${ElseIf} ${IsWin8.1}
     Push 8
     Push "Windows 8.1"
  ${ElseIf} ${IsWin8}
     Push 8
     Push "Windows 8"
  ${ElseIf} ${IsWin7}
     Push 7
     Push "Windows 7"
  ${ElseIf} ${IsWinVista}
     Push 6
     Push "Windows Vista"
  ${Else}
     Push 5
     Push "Windows XP" ; this is not really nice, we actually assume that everything below Vista is XP
  ${EndIf}
  Pop $WindowsName
  Pop $WindowsVer
  ${WinVerGetBuild} $WindowsBuild
  !insertmacro debug_cat 3 "Detected $WindowsName:$WindowsVer:$WindowsBuild"
  Push 1
  Pop $NoFCupdate
  ${If} ${AtLeastWin10}
    !if "${NSIS_PACKEDVERSION}" > 0x3003000
        ${If} ${AtLeastWaaS} "Fall Creators Update"
           Push 0
           Pop $NoFCupdate
        ${EndIf}
    !endif
  ${EndIf}
  ${If} ${IsWinVista}
     ${If} ${AtLeastServicePack} 1
        Push 0
     ${Else}
        Push 1
     ${EndIf}
  ${Else}
     Push 0
  ${EndIf}
  Pop $VistaNoSP

!ifdef W10
; for native TTLS only - if the system has not been updated to have the working native supplicant - warn the user
; This will only work correctly for NSIS 3.04 and newer.
    !ifdef TTLS
        ${If} $NoFCupdate == 1
            IfSilent +3
            MessageBox MB_OKCANCEL|MB_ICONEXCLAMATION  "<?php WindowsCommon::echoNsis(_("Your Windows 10 has not been updated which means that you are likely to have problems accessing network. We suggest that you quit this installation, update your Windows and try again"))?>" IDOK cont1
            Call Cleanup
            ${EndIf}
            cont1:
    !endif
;  ${If} ${AtMostWin8.1}
;    IfSilent +2
;    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php WindowsCommon::echoNsis(_("You are running Windows \$WindowsName but this installer is only for Windows 10 and newer. Please download the correct installer and try again."))?>"
;    !ifndef ALLOW_LOW_VERSION
;        Call Cleanup
;    !endif
;  ${EndIf}
!else ifdef W8
;${If} ${AtLeastWin10}
;    IfSilent +2
;    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php WindowsCommon::echoNsis(_("You are running Windows \$WindowsName but this installer is only for Windows 8 and 8.1. Please download the correct installer and try again."))?>"
;    !ifndef ALLOW_LOW_VERSION
;        Call Cleanup
;    !endif
;  ${EndIf}
  ${If} ${AtMostWin7}
    IfSilent +2
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php WindowsCommon::echoNsis(_("You are running Windows \$WindowsName but this installer is only for Windows 8 and 8.1. Please download the correct installer and try again."))?>"
    !ifndef ALLOW_LOW_VERSION
        Call Cleanup
    !endif
  ${EndIf}
  !else
  ${If} ${AtMostWinXP}
  ${OrIf} ${AtLeastWin8}
    IfSilent +2
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php WindowsCommon::echoNsis(_("You are running Windows \$WindowsName but this installer is only for Windows Vista and Windows 7. Please download the correct installer and try again."))?>"
    Call Cleanup
  ${EndIf}
  !endif
FunctionEnd

; Check if wireless intefaces and service are running
Function Wireless_Check
  !insertmacro debug_cat 2 "Checking for wireless interfaces"
  !insertmacro debug_cat 3 "Exec: $TEMP\wlan_test.exe"
  nsExec::Exec "$TEMP\wlan_test.exe"
  Pop $wireless_result
  !insertmacro debug_cat 3 "wlan_test.exe returned $wireless_result"
  ${If} $wireless_result > 0
     !insertmacro debug_cat 2 "Wireless problem: $wireless_result"
  ${Else}
     !insertmacro debug_cat 2 "Wireless check OK"
  ${EndIf}
FunctionEnd

; If a wireless problem has been found, decide what to do
Function NoWirelessConfirm
  ${If} $wired == 1 
     File "check_wired.cmd"
     nsExec::Exec "$TEMP\check_wired.cmd"
     Pop $0
     Delete "$TEMP\check_wired.cmd"
  ${If} $0 > 0
    Push 0
    Pop $wired
  ${EndIf}
  ${EndIf}
  ${If} $wired == 0
    ${If} $wireless_result == 1
      !insertmacro debug_cat 1 "wlan_test.exe did not find wireless configuration service"
      IfSilent +2
      MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis( _("Windows wireless configuration service is not enabled. The installer cannot continue."))?>"
      Call Cleanup
    ${EndIf}
    ${If} $wireless_result == 2
      IfSilent +2
      MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis( _("No wireless interfaces found. The installer cannot continue."))?>"
      Call Cleanup
    ${EndIf}
  ${Else}
    ${If} $wireless_result > 0
       IfSilent +3
       MessageBox MB_YESNO "<?php WindowsCommon::echoNsis( _("No wireless interfaces found. Wireless access will not be configured. Would you like to continue and configure access on the wired interface?"))?>" IDYES wired_yes
       Call Cleanup
       wired_yes:
       Push 1
       Pop $force_wired
    ${EndIf}
  ${EndIf}
FunctionEnd

; ask if wired interfaces should be installed
Function WiredConfirm
  !insertmacro debug_cat 4 "Entering WiredConfirm with wireless_result=$wireless_result; wired=$wired"
  ${If} $wireless_result == 0
    ${If} $wired == 1
      ${If} $force_wired == 0
        IfSilent wired
        MessageBox MB_YESNO "<?php WindowsCommon::echoNsis( _("Do you want to enable access on wired interfaces?"))?>" IDYES wired
        Push 0
        Pop $wired
        wired:
      ${EndIf}
    ${EndIf}
  ${EndIf}
  ${If} $wired == 1
      !insertmacro debug_cat 2 "Setting up dot3svc"
      nsExec::Exec  '"sc" start dot3svc'
      nsExec::Exec  '"sc" config dot3svc start= auto'
  ${EndIf}
FunctionEnd


; Check for installed EAP metod
  Function CheckEAP
    Pop $R0
    !insertmacro debug_cat 3 "testing for EAP: $R0"
    readRegStr $0 HKLM "SYSTEM\CurrentControlSet\services\RasMan\PPP\EAP\$R0" Path
    !insertmacro debug_cat 3 "EAP test returned: $0"
    Push $0
  FunctionEnd

; Check for Symantec problem
  Function CheckSymantec
    Push 88
    call CheckEAP
    Pop $0
    ${If} $0 == ""
       Push 0
    ${Else}
       Push 1
    ${EndIf}
    Pop $Symantec_installed
    !insertmacro debug_cat 3 "Symantec test returned: $Symantec_installed"
  FunctionEnd

;================================

Function ShowInstfiles
  !insertmacro MUI_HEADER_TEXT "<?php WindowsCommon::echoNsis( _("Profiles installation"))?>" " "
FunctionEnd
;================================
;  Check if a wireless profile exist and put it on delete list
Function ProfileCheck
  Pop $R0
  nsArray::Get Profile_names $R0
  call FindProfile
  Pop $0
  ${If} $0 == 0
    nsArray::Set Profile_states /key=$R0 ${PROFILE_STATE_DELETE}
  ${Else}
    nsArray::Set Profile_states /key=$R0 0
  ${EndIf}
FunctionEnd
;==========================

Function FindProfile
  Pop $R8
  DetailPrint "<?php WindowsCommon::echoNsis( _("Checking for profile \$R8"))?>"
  !insertmacro debug_cat 2 "Checking for profile $R8"
  !insertmacro debug_cat 3 "Exec: $Netsh wlan show profiles $R8"
  nsExec::Exec  '"$Netsh" wlan show profiles "$R8"'
  Pop $0
  !insertmacro debug_cat 4 "netsh returned $0"
  ${If} $0 == 0
    !insertmacro debug_cat 1 "found profile $R8"
    DetailPrint "<?php WindowsCommon::echoNsis( _("found profile \$R8"))?>"
    Push 0
  ${Else}
    !insertmacro debug_cat 1 "profile $R8 not found"
    Push 1
  ${EndIf}
FunctionEnd


; Check which wireless profiles already exist and should be deleted
Function CheckWirelessProfiles
  !ifdef CRED
  !insertmacro debug_cat 4 "Unpacking WLANSetEAPUserData"
  File "WLANSetEAPUserDatax86.exe"
  File "WLANSetEAPUserDatax64.exe"
  nsArray::Set Delete_files "WLANSetEAPUserDatax86.exe"
  nsArray::Set Delete_files "WLANSetEAPUserDatax64.exe"
  !endif
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call ProfileCheck
  ${Next}
FunctionEnd
;=============================

; make sure that the user understand that this is a customised installer
Function confirmUserGroup
!ifdef USER_GROUP
IfSilent +2
MessageBox MB_OKCANCEL "<?php WindowsCommon::echoNsis(_("This installer will only work properly if you are a member of \${ORGANISATION} and the user group: \${USER_GROUP}."))?>$\r$\n<?php WindowsCommon::echoNsis(_("Click OK to continue with installation."))?>" IDOK cont
!else 
IfSilent +2
MessageBox MB_OKCANCEL "<?php WindowsCommon::echoNsis(_("This installer will only work properly if you are a member of \${ORGANISATION}."))?>$\r$\n<?php WindowsCommon::echoNsis(_("Click OK to continue with installation."))?>" IDOK cont
!endif
    Call Cleanup
cont:
FunctionEnd

Function displayExternalInfo
;TRANSLATION
  MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Please read the additional information from \${ORGANISATION} which will be displayed in a moment."))?>"
  ExecShell "open" "${EXTERNAL_INFO}"
FunctionEnd

;=============================

Function Finish
Call ShowAllButtons
  
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
   !insertmacro debug_cat 2 "writing $EXEDIR\inst_cat.cmd"
   FileOpen $9 "$EXEDIR\inst_cat.cmd" w
   ClearErrors
   Loop1:
   nsArray::Iterate Commands
    IfErrors Done1
    Pop $R0
    Pop $R1
    FileWrite $9 "$R1$\r$\n"
    Goto Loop1
    Done1:
    FileWrite $9 "@TIMEOUT 10$\r$\n"
;    FileWrite $9 "del $EXEDIR\inst_cat.cmd"
   FileClose $9
  SkipScript:
  nsDialogs::Create 1044
  Pop $DIALOG
        
  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS}|${SS_BITMAP} 0 0 0 109u 193u ""
  Pop $IMAGECTL
        
  StrCpy $0 "$TEMP\cat_background.bmp"
  System::Call 'user32::LoadImage(i 0, t r0, i ${IMAGE_BITMAP}, i 0, i 0, i ${LR_LOADFROMFILE}) i.s'
  Pop $IMAGE
        
  SendMessage $IMAGECTL ${STM_SETIMAGE} ${IMAGE_BITMAP} $IMAGE

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 10u -130u 20u "${FAREWELL_HEADER}"
  Pop $HEADLINE

  SendMessage $HEADLINE ${WM_SETFONT} $HEADLINE_FONT 0
  ${If} $profile_fail == 1
    !insertmacro debug_cat 1 "Profile installation failed"
    ${If} $Symantec_installed  != 0
     !insertmacro debug_cat 3 "Symantec problem"
     IfSilent +2
     MessageBox MB_OK|MB_ICONEXCLAMATION "<?php printf(WindowsCommon::sprintNsis(_("Please READ this message it is IMPORTANT.$\\r$\\nInstallation problems may be due to the fact that Symantec Endpoint Protection is installed on your machine.$\\r$\\nWhile this is a well-known bug of the Symantec product, about which the installer can not do anything, there is a workaround.$\\r$\\nWhen you close this window the installer will exit and an explorer window will be started (it could appear underneath already opened windows). In this window you should see a script named inst_cat. Start it by double-clicking, It will install the profiles. You will need to login to %s with your username and password.")),\config\ConfAssistant::CONSORTIUM['display_name']) ?>"
    Exec '"explorer" /select,"$EXEDIR\inst_cat.cmd"'
    Quit

    ${EndIf}
     nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 32u -130u -32u "${FAREWELL_FAIL}"
  ${Else}
     nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 32u -130u -32u "${FAREWELL_TEXT}"
  ${EndIf}
  ${If} $rebootRequired == 1
     MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis(_("Please restart your system after installation finishes."))?>"
  ${EndIf}

  Pop $TEXT
  SetCtlColors $TEXT "" 0xffffff
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

;=============================
Function CreateLanProfile
 !insertmacro debug_cat 2 "installing wired profile"
 DetailPrint "<?php WindowsCommon::echoNsis(_("installing wired profile"))?>"
  !insertmacro debug_cat 3 "execute: $Netsh lan add profile $TEMP\lan_prof.xml"
  nsArray::Set Commands "netsh lan add profile $TEMP\lan_prof.xml" 
  nsArray::Set Commands "del $TEMP\lan_prof.xml"
  nsExec::Exec '"$Netsh" lan add profile "$TEMP\lan_prof.xml"'
  Pop $0
  !insertmacro debug_cat 4 "netsh returned $0"
     !insertmacro debug_cat 1 "Wired profile created"
    Push 1
    Pop $install_wired_credentials
;  ${EndIf}
  !ifndef DEBUG_CAT
     Delete "$TEMP\lan_prof.xml"
  !endif
  !ifdef DEBUG_CAT
   ${If} $Debug_cat == 0
     Delete "$TEMP\lan_prof.xml"
  ${EndIf}
  !endif
FunctionEnd
;=============================

;==========================
; Install all required wireless profiles
Function CreateWirelessProfiles
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call InstallProfile
  ${Next}
  !ifdef AdditionalDeletes
     !insertmacro debug_cat 1 'Additional Deletes'
     ClearErrors
     Loop2:
     nsArray::Iterate Additional_deletes
     IfErrors Done2
     Pop $0
     Pop $R1
     Push $R1
     call FindProfile
     Pop $0
     ${If} $0 == 0
       DetailPrint "<?php WindowsCommon::echoNsis(_("deleting profile \$R1"))?>"
       !insertmacro debug_cat 1 'deleting profile "$R1"'
       !insertmacro debug_cat 3 'Execute: $Netsh wlan delete profile "$R1"'
       nsArray::Set Commands "netsh wlan delete profile $\"$R1$\"" 
       nsExec::Exec '"$Netsh" wlan delete profile "$R1"'
     ${EndIf}
     Goto Loop2
     Done2:
  !endif
FunctionEnd

; re-install a wireless profile and set the credentials flag if succeded
; Profile counter is passed on stack and popped into $R0
Function InstallProfile
    Pop $R0
    nsArray::Get Profile_names $R0
    Pop $R1
    nsArray::Get Profile_states $R0
    Pop $R2
    ${If} $R2 == ${PROFILE_STATE_DELETE}
        DetailPrint "<?php WindowsCommon::echoNsis(_("deleting profile \$R1"))?>"
        !insertmacro debug_cat 3 'Execute: $Netsh wlan delete profile "$R1"'
        nsArray::Set Commands "netsh wlan delete profile $\"$R1$\""
        nsExec::Exec '"$Netsh" wlan delete profile "$R1"'
        Pop $0
        !insertmacro debug_cat 4 "netsh returned $0"
        !insertmacro debug_cat 1 "Profile $R1 deleted"
    ${EndIf}
; profile installation
    DetailPrint "<?php WindowsCommon::echoNsis(_("installing profile \$R1"))?>"
    !insertmacro debug_cat 3 "Execute: $Netsh wlan add profile $TEMP\wlan_prof-$R0.xml"
    nsArray::Set Commands "netsh wlan add profile $TEMP\wlan_prof-$R0.xml" 
    nsArray::Set Commands "del $TEMP\wlan_prof-$R0.xml"
    nsExec::Exec '"$Netsh" wlan add profile "$TEMP\wlan_prof-$R0.xml"'
    Pop $0
    !insertmacro debug_cat 4 "netsh returned $0"
    ${If} $0 <> 0
        Push 1
        Pop $profile_fail
        !insertmacro debug_cat 1 'Profile installation error for "$R1"'
; we need to see if the profile is HS 2.0 if so then try the nohs profile
        nsArray::Get Profile_hs20 $R0
        Pop $R3
        ${If} $R3 == 1
            IfFileExists "$TEMP\nohs_wlan_prof-$R0.xml" 0 noInstall
            !insertmacro debug_cat 1 "Retrying installtion with the non-hs profile"
            !insertmacro debug_cat 3 "Execute: $Netsh wlan add profile $TEMP\nohs_wlan_prof-$R0.xml"
            nsArray::Set Commands "netsh wlan add profile $TEMP\nohs_wlan_prof-$R0.xml" 
            nsArray::Set Commands "del $TEMP\nohs_wlan_prof-$R0.xml"
            nsExec::Exec '"$Netsh" wlan add profile "$TEMP\nohs_wlan_prof-$R0.xml"'
            Pop $R4
            !insertmacro debug_cat 4 "netsh returned $R4"
            ${If} $R4 == 0
                !insertmacro debug_cat 1 "The non-hs profile suceeded"
                    Push 0
                    Pop $profile_fail
            ${Else}
                !insertmacro debug_cat 1 "The non-hs profile failed as well"
                IfSilent +2
                MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Profile installation error for \$R1."))?>$\r$\n<?php WindowsCommon::echoNsis(_("Please contact \${SUPPORT}."))?>"
            ${EndIf}
            noInstall:
        ${EndIf}
    ${EndIf}
    ${If} $profile_fail == 0
        !insertmacro debug_cat 1 "Profile $R1 created"
        Push 1
        Pop $install_wireless_credentials
        !insertmacro debug_cat 4 "pushing install_wireless_credentials $install_wireless_credentials"
        !ifndef DEBUG_CAT
            Delete "$TEMP\wlan_prof-$R0.xml"
        !endif
        !ifdef DEBUG_CAT
            ${If} $Debug_cat == 0
                Delete "$TEMP\wlan_prof-$R0.xml"
            ${EndIf}
        !endif
    ${Else}
        nsArray::Set Profile_states /key=$R0 ${PROFILE_STATE_FAIL}
    ${EndIf}
FunctionEnd



; Install credentials for wired
Function InstallWiredCred
  !insertmacro debug_cat 1 "installing credentials for wired"
  DetailPrint "<?php WindowsCommon::echoNsis(_("installing credentials for wired"))?>"
  !insertmacro debug_cat 3 "Execute: $TEMP\install_wired.cmd"
  nsExec::Exec "$TEMP\install_wired.cmd"
FunctionEnd

Function writeTlsUserProfile
    DetailPrint "<?php WindowsCommon::echoNsis(_("installing credentials TLS"))?>"
    !insertmacro debug_cat 3 "Installing TLS credentials profile"
    FileOpen $9 "$TEMP\user_cred.xml" w
       FileWriteUTF16LE $9 '<?xml version=$\"1.0$\" encoding=$\"UTF-16$\"?>$\r$\n\
<EapHostUserCredentials xmlns=$\"http://www.microsoft.com/provisioning/EapHostUserCredentials$\" xmlns:eapCommon=$\"http://www.microsoft.com/provisioning/EapCommon$\" xmlns:baseEap=$\"http://www.microsoft.com/provisioning/BaseEapMethodUserCredentials$\">$\r$\n\
  <EapMethod>$\r$\n\
    <eapCommon:Type>13</eapCommon:Type>$\r$\n\
    <eapCommon:AuthorId>0</eapCommon:AuthorId>$\r$\n\
  </EapMethod>$\r$\n\
  <Credentials xmlns:eapUser=$\"http://www.microsoft.com/provisioning/EapUserPropertiesV1$\" xmlns:xsi=$\"http://www.w3.org/2001/XMLSchema-instance$\" xmlns:baseEap=$\"http://www.microsoft.com/provisioning/BaseEapUserPropertiesV1$\" xmlns:eapTls=$\"http://www.microsoft.com/provisioning/EapTlsUserPropertiesV1$\">$\r$\n\
    <baseEap:Eap>$\r$\n\
      <baseEap:Type>13</baseEap:Type>$\r$\n\
      <eapTls:EapType>$\r$\n'

!ifdef PFX_USERNAME
    FileWriteUTF16LE $9 "<eapTls:Username>$UNAME</eapTls:Username>$\r$\n"

!endif
FileWriteUTF16LE $9 "<eapTls:UserCert>$tlsFingerprint</eapTls:UserCert>$\r$\n\
      </eapTls:EapType>$\r$\n\
    </baseEap:Eap>$\r$\n\
  </Credentials>$\r$\n\
</EapHostUserCredentials>$\r$\n"

   FileClose $9
   nsArray::Set Delete_files "user_cred.xml"
FunctionEnd


Function writePeapUserProfle
    FileOpen $9 "$TEMP\user_cred.xml" w
    FileWriteUTF16LE $9 '<?xml version=$\"1.0$\" encoding=$\"UTF-16$\"?>$\r$\n\
<EapHostUserCredentials xmlns=$\"http://www.microsoft.com/provisioning/EapHostUserCredentials$\" xmlns:eapCommon=$\"http://www.microsoft.com/provisioning/EapCommon$\" xmlns:baseEap=$\"http://www.microsoft.com/provisioning/BaseEapMethodUserCredentials$\">$\r$\n\
<EapMethod>$\r$\n\
<eapCommon:Type>25</eapCommon:Type>$\r$\n\
<eapCommon:AuthorId>0</eapCommon:AuthorId>$\r$\n\
</EapMethod>$\r$\n\
<Credentials xmlns:eapUser=$\"http://www.microsoft.com/provisioning/EapUserPropertiesV1$\" xmlns:xsi=$\"http://www.w3.org/2001/XMLSchema-instance$\" xmlns:baseEap=$\"http://www.microsoft.com/provisioning/BaseEapUserPropertiesV1$\" xmlns:MsPeap=$\"http://www.microsoft.com/provisioning/MsPeapUserPropertiesV1$\" xmlns:MsChapV2=$\"http://www.microsoft.com/provisioning/MsChapV2UserPropertiesV1$\">$\r$\n\
<baseEap:Eap>$\r$\n\
<baseEap:Type>25</baseEap:Type>$\r$\n\
<MsPeap:EapType>$\r$\n\
<baseEap:Eap>$\r$\n\
<baseEap:Type>26</baseEap:Type>$\r$\n\
<MsChapV2:EapType>$\r$\n'

FileWriteUTF16LE $9 "<MsChapV2:Username>$UNAME</MsChapV2:Username>$\r$\n\
<MsChapV2:Password>$PASS1</MsChapV2:Password>$\r$\n\
</MsChapV2:EapType>$\r$\n\
</baseEap:Eap>$\r$\n\
</MsPeap:EapType>$\r$\n\
</baseEap:Eap>$\r$\n\
</Credentials>$\r$\n\
</EapHostUserCredentials>$\r$\n"

   FileClose $9
   nsArray::Set Delete_files "user_cred.xml"
FunctionEnd

Function writeTtlsUserProfile
    !insertmacro debug_cat 4 "Creating TTLS user profile"
    FileOpen $9 "$TEMP\user_cred.xml" w
    FileWriteUTF16LE $9 '<?xml version=$\"1.0$\" encoding="UTF-16"?>$\r$\n\
<EapHostUserCredentials xmlns=$\"http://www.microsoft.com/provisioning/EapHostUserCredentials$\" xmlns:eapCommon=$\"http://www.microsoft.com/provisioning/EapCommon$\" xmlns:baseEap=$\"http://www.microsoft.com/provisioning/BaseEapMethodUserCredentials$\">$\r$\n\
<EapMethod>$\r$\n\
<eapCommon:Type>21</eapCommon:Type>$\r$\n\
<eapCommon:AuthorId>311</eapCommon:AuthorId>$\r$\n\
</EapMethod>$\r$\n\
<Credentials xmlns=$\"http://www.microsoft.com/provisioning/EapHostUserCredentials$\">$\r$\n\
<EapTtls xmlns=$\"http://www.microsoft.com/provisioning/EapTtlsUserPropertiesV1$\">$\r$\n'
    
FileWriteUTF16LE $9 "<Username>$UNAME</Username>$\r$\n\
<Password>$PASS1</Password>$\r$\n\
</EapTtls>$\r$\n\
</Credentials>$\r$\n\
</EapHostUserCredentials>$\r$\n"
   FileClose $9
   nsArray::Set Delete_files "user_cred.xml"
FunctionEnd


;=============================
; Install credentials for a wireless profile
Function InstallWirelessCred
  Pop $R0
  nsArray::Get Profile_names $R0
  Pop $R1
  nsArray::Get Profile_states $R0
  Pop $R2
  ${If} $R2 <> ${PROFILE_STATE_FAIL}
    !insertmacro debug_cat 1 "installing credentials for profile $R1"
    DetailPrint "<?php WindowsCommon::echoNsis(_("installing credentials for profile \$R1"))?>"
    !ifdef TLS
      Call writeTlsUserProfile
    !endif
    !ifdef PEAP
      Call writePeapUserProfle
    !endif
    !ifdef TTLS
      Call writeTtlsUserProfile
    !endif
    !insertmacro debug_cat 3 'Execute: "$TEMP\WLANSetEAPUserData$Platform.exe" "$R1" 0 "$TEMP\user_cred.xml" /i'
    nsExec::Exec '"$TEMP\WLANSetEAPUserData$Platform.exe" "$R1" 0 "$TEMP\user_cred.xml" /i'
    Pop $0
    ${If} $Debug_cat == 0
       Delete "$TEMP\user_cred.xml"
    ${EndIf}
    !insertmacro debug_cat 3 "credentials setting returned $0"
    StrCmp $0 0 Cont1
; Error Label, show error box
    IfSilent +2
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php WindowsCommon::echoNsis(_("Credentials installation problem"))?>"
    Cont1:
  ${EndIf}
FunctionEnd
;=============================
!ifdef TLS
Function PFXCertificateSelect
  !ifndef SILVERBULLET
;TRANSLATION
!insertmacro MUI_HEADER_TEXT "<?php printf(WindowsCommon::sprintNsis(_("%s installer for")),\config\ConfAssistant::CONSORTIUM['display_name'])?> " "<?php WindowsCommon::echoNsis(_("Install personal certificate"))?>"
;TRANSLATION
    IfSilent +2
  MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Preparing to install personal certificate."))?>$\r$\n<?php WindowsCommon::echoNsis(_("Click OK to continue"))?> "
  nsDialogs::Create 1018
  Pop $Dialog1
  ${If} $Dialog1 == error
    Abort
  ${EndIf}
  nsDialogs::SelectFileDialog open "" "<?php WindowsCommon::echoNsis(_("Certificate files (*.p12)"))?>|*.p12|<?php WindowsCommon::echoNsis(_("Certificate files (*pfx)"))?>|*.pfx|<?php WindowsCommon::echoNsis(_("All files"))?>|*.*"
  Pop $Cert_file
;TRANSLATION
  <?php $certPasswordLabel = WindowsCommon::sprintNsis(_("certificate password:"));
        $certPasswordLength = strlen($certPasswordLabel);
        $certUsernameLabel = WindowsCommon::sprintNsis(_("Username:"));
        $certUsernameLength = strlen($certUsernameLabel);
        $labelLength = max($certPasswordLength,$certUsernameLength);
  ?>
  ${NSD_CreateLabel} 0 0 100% 32u "<?php WindowsCommon::echoNsis(_("Selected file: \$Cert_file"))?>"
  !else
  <?php $certPasswordLabel = WindowsCommon::sprintNsis(_("import password:"));
        $certPasswordLength = strlen($certPasswordLabel);
  ?>
  !insertmacro debug_cat 4 "Unpacking SB_cert.p12"
  File "SB_cert.p12"
  nsArray::Set Delete_files "SB_cert.p12"
  Push "$TEMP\SB_cert.p12"
  Pop $Cert_file
  nsDialogs::Create 1018
  !endif
  !insertmacro debug_cat 3 "Vista level (Vista without Service pack): $VistaNoSP";
  ${If} $VistaNoSP == 0
    !ifdef PFX_USERNAME
       !define LABEL_LENGTH "<?php echo($labelLength*3.5)?>u"
       !define TEXT_START "<?php echo($labelLength*3.5 +5)?>u"
    !else
       !define LABEL_LENGTH "<?php echo($certPasswordLength*3.5)?>u"
       !define TEXT_START "<?php echo($certPasswordLength*3.5 +5)?>u"
    !endif
    ${NSD_CreateLabel} 0 35u ${LABEL_LENGTH} 12u "<?php echo $certPasswordLabel?>"
    ${NSD_CreatePassword} ${TEXT_START} 34.5u 120u 12u ""
    Pop $PASS1_F
    ${NSD_SetFocus} $PASS1_F
    !ifdef PFX_USERNAME
       ${NSD_CreateLabel} 0 60u ${LABEL_LENGTH} 12u "<?php echo $certUsernameLabel?>"
       ${NSD_CreateText} ${TEXT_START} 60u 120u 12u ""
       Pop $UNAME_F
    !endif
  ${EndIf}
  nsDialogs::Show
FunctionEnd
;===========================

Function PFXCertificateSelectLeave
   ${If} $Cert_file == ""
      MessageBox MB_OK  "<?php WindowsCommon::echoNsis(_("No certificate file selected"))?>"
      Call Cleanup
   ${EndIf}
   !ifdef SILVERBULLET
     Push "<?php WindowsCommon::echoNsis(_("Incorrect import password$\\r$\\nenter again"))?>"
   !else
     Push "<?php WindowsCommon::echoNsis(_("Incorrect certificate password$\\r$\\nenter again"))?>"
   !endif
   Pop $R0 

   ${If} $VistaNoSP == 1
     System::Call "cryptext::CryptExtAddPFX(i $HWNDPARENT, i 0, t '$Cert_file', i 1)"
   ${Else}
      ${NSD_GetText} $PASS1_F $0
      !ifdef W8
      !insertmacro debug_cat 3 'Execute: powershell -Command "&{$$securePassword = ConvertTo-SecureString -String $\"password_not_recorded$\" -Force -AsPlainText ; try { $$cert = Import-PfxCertificate -File .\SB_cert.p12 cert:\CurrentUser\My -Password $$securePassword} catch { exit 1 } ; $$cert.Thumbprint}  "'
      nsExec::ExecToStack 'powershell -Command "&{$$securePassword = ConvertTo-SecureString -String \"$0\" -Force -AsPlainText ; try { $$cert = Import-PfxCertificate -File \"$Cert_file\" cert:\CurrentUser\My -Password $$securePassword} catch { exit 1 } ; $$cert.Thumbprint}"'
      Pop $1
      Pop $tlsFingerprint
      !insertmacro debug_cat 3 "PFX install returned $1; fingerprint: $tlsFingerprint"
      !else
      !insertmacro debug_cat 3 'Execute: powershell -InputFormat None -ExecutionPolicy Bypass -Command "&{$cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2; try { $cert.Import(\"$Cert_file\", \"$0\", \"Exportable\") } catch { exit 1 }; $cert.Thumbprint}"'
      nsExec::ExecToStack 'powershell -InputFormat None -ExecutionPolicy Bypass -Command "&{$cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2; try { $cert.Import(\"$Cert_file\", \"$0\", \"Exportable\") } catch { exit 1 }; $cert.Thumbprint}"'
      Pop $1
      Pop $tlsFingerprint
      !insertmacro debug_cat 3 "PFX test returned $1; fingerprint: $tlsFingerprint"
      !insertmacro debug_cat 3 'Execute: "certutil" -importpfx -user -p "password_not_recorded" "$Cert_file" NoRoot'
      nsExec::Exec '"certutil" -importpfx -user -p "$0" "$Cert_file" NoRoot'
      Pop $1
      !insertmacro debug_cat 3 "certutil returned $1"
      !endif
;TRANSLATION
    ${If} $1 <> 0
      MessageBox MB_OK  $R0
      Abort
    ${EndIf}
   ${EndIf}
FunctionEnd
;===========================
!endif


; User credentials dialog
!ifdef PASSWORD_BASED_CRED
; Function copied from http://nsis.sourceforge.net/IndexOf_%26_RIndexOf:_Find_index_of_character_in_string
Function IndexOf
Exch $R0
Exch
Exch $R1
Push $R2
Push $R3
 
 StrCpy $R3 $R0
 StrCpy $R0 -1
 IntOp $R0 $R0 + 1
  StrCpy $R2 $R3 1 $R0
  StrCmp $R2 "" +2
  StrCmp $R2 $R1 +2 -3
  
 StrCpy $R0 -1
 
Pop $R3
Pop $R2
Pop $R1
Exch $R0
FunctionEnd

Function trimTrailingSpaces
   Pop $R5
   Push 0
   Pop $spacesRemoved
Loop3:
   StrCpy $R7 $R5 "" -1
   ${If} $R7 == ' '
      Push 1
      Pop $spacesRemoved
      StrCpy $R5 $R5 -1
      GoTo Loop3
   ${EndIf}
   ${If} $spacesRemoved == 1
      MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Username contained some trailing spaces which were removed."))?>"
   ${EndIf}
   Push $R5
FunctionEnd

; validateUsername returns 0 on success
; -1 on missing user part
;  1 if realm required but not present
;  2 on more then one @
;  3 on no dot or one immediately after @
;  4 in realm not matching the EALM value

Function validateUsername
   Pop $R5
   Push "@"
   Push $R5
   Call IndexOf
   Pop $R6
   ${If} $R6 = -1
   ; No @ character
   StrCpy $inputErrorMsg  "<?php WindowsCommon::echoNsis(_("Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"))?>"
   !ifdef VERIFY_USER_REALM_INPUT
   ; the realm should be there, hence error
      Push $inputErrorMsg
      Push 1
      Return
   !else
   ; no realm can be OK if verification is not set
      Push "All fine"
      Push 0
      Return
   !endif
   ${EndIf}
   !ifdef VERIFY_USER_REALM_INPUT
      !ifdef HINT_USER_INPUT
         StrCpy $inputErrorMsg  "<?php WindowsCommon::echoNsis(_("Error: your username must be in the form of 'xxx@\${REALM}'. Please enter the username in the correct format."))?>"
      !else
         StrCpy $inputErrorMsg "<?php WindowsCommon::echoNsis(_("Error: your username must be in the form of 'xxx@institutionID' and end with '\${REALM}'. Please enter the username in the correct format."))?>"
      !endif
   !else
      StrCpy $inputErrorMsg "<?php WindowsCommon::echoNsis(_("Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"))?>"
   !endif
   ${If} $R6 = 0
   ; missing username part
      Push $inputErrorMsg
      Push -1
      Return
   ${EndIf}
   IntOp $R6 $R6 + 1
   StrCpy $R7 "$R5" 256 $R6
   ; R7 contains the realm part
   !ifdef VERIFY_USER_REALM_INPUT
      !ifdef HINT_USER_INPUT
      ;we use exact match here
      ${If} "$R7" == "${REALM}"
         Push "All fine"
         Push 0
         Return
      ${Else}
         Push $inputErrorMsg
         Push 4
         Return
      ${EndIf}
      !else
      ; we just check the suffix and then run other tests
         StrLen $R8 "${REALM}"
         IntOp $R8 0 - $R8
         StrCpy $R9 "$R7" 256 $R8
         ${If} "$R9" != "${REALM}"
            Push $inputErrorMsg
            Push 4
            Return
         ${EndIf}
      !endif
   !endif
   Push "@"
   Push $R7
   Call IndexOf
   Pop $R6
   ${If} $R6 >= 0
   ; There is a second @ 
      Push $inputErrorMsg
      Push 2
      Return
   ${EndIf}
   Push "."
   Push $R7
   Call IndexOf
   Pop $R6
   ${If} $R6 = 0
   ; there is a dot immediately after @
      Push "<?php WindowsCommon::echoNsis(_("Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"))?>"
      Push 3
      Return
   ${EndIf}
   Push "All fine"
   Push 0
FunctionEnd

Function EncodeXmlChars
   ;$R0 will contain the current string location
   Push $R0
   Push $R1
   Push $R2
   Push $R9
   Push 0
   Pop $R0
   ;$R9 will contain the output string
   StrCpy $R9 ""
   StrLen $R2 $PASS1
   ${Do}
      StrCpy $R1 $PASS1 1 $R0
      ${Switch} $R1
         ${Case} "&"
           StrCpy $R1 "&amp;"
           ${Break}
         ${Case} "<"
           StrCpy $R1 "&lt;"
           ${Break}
           ${Case} ">"
           StrCpy $R1 "&gt;"
           ${Break}
      ${EndSwitch}
      StrCpy $R9 "$R9$R1"
      IntOp $R0 $R0 + 1
   ${LoopUntil} $R0 = $R2
   StrCpy $PASS1 $R9
   Pop $R9
   Pop $R2
   Pop $R1
   Pop $R0
FunctionEnd

Function GetPasswordBasedCredentials
  !ifdef HINT_USER_INPUT
    StrCpy $UNAME "@${REALM}"
  !endif
  call ShowNextCancelButtons
  !insertmacro MUI_HEADER_TEXT "<?php WindowsCommon::echoNsis(_("User credentials"))?>" " "
        nsDialogs::Create 1018
        Pop $0

        ${NSD_CreateLabel} 10 36u 60u 12u "<?php WindowsCommon::echoNsis(_("Username:"))?>"
        ${NSD_CreateText} 67u 35u 80u 12u "$UNAME"
        Pop $UNAME_F
        ${NSD_CreateLabel} 10 51u 60u 12u "<?php WindowsCommon::echoNsis(_("Password:"))?>"
        ${NSD_CreatePassword} 67u 50u 80u 12u "$PASS1"
        Pop $PASS1_F
        ${NSD_CreateLabel} 10 66u 60u 22u "<?php WindowsCommon::echoNsis(_("Repeat password:"))?>"
        ${NSD_CreatePassword} 67u 65u 80u 12u "$PASS2"
        Pop $PASS2_F
        nsDialogs::Show
FunctionEnd

; Handle user input
Function SavePasswordBasedCredentials
   ${NSD_GetText} $UNAME_F $UNAME
   ${NSD_GetText} $PASS1_F $PASS1
   ${NSD_GetText} $PASS2_F $PASS2
   Push $UNAME
   call trimTrailingSpaces
   Pop $UNAME
   ${If} "$UNAME" == ""
     MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Missing username"))?>"
     Abort
   ${EndIf}
   Push $UNAME
   call validateUsername
   Pop $0
   ${If} $0 != 0
     Pop $R1
     MessageBox MB_OK "$R1"
     Abort
   ${EndIf}
   ${If} "$PASS1" == ""
     MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Missing password"))?>"
     Abort
   ${EndIf}
   ${If} "$PASS1" != "$PASS2"
   ${NSD_SetText} $PASS1_F ""
   ${NSD_SetText} $PASS2_F ""
   MessageBox MB_OK "<?php WindowsCommon::echoNsis(_("Password mismatch"))?>"
   Abort
   ${EndIf}
   ${Base64_Encode} $PASS1
   Pop $PASS_B64
   ${Base64_Encode} $UNAME
   Pop $UNAME_B64
   Call ShowAllButtons
FunctionEnd
!endif ; PASSWORD_BASED_CRED
;=============================
!ifdef CRED
Function InstallUserCredentials
  !ifdef PASSWORD_BASED_CRED
     Call EncodeXmlChars
  !endif
  Call InstallWirelessUserCredentials
  Call InstallWiredUserCredentials
FunctionEnd
;=============================
Function InstallWirelessUserCredentials
  !insertmacro debug_cat 4 "Installing wireless credentials $install_wireless_credentials"
  ${If} $install_wireless_credentials == 0
    Goto WirelessEnd
  ${EndIf}
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call InstallWirelessCred
  ${Next}
WirelessEnd:
FunctionEnd
;=============================

Function InstallWiredUserCredentials
  ${If} $install_wired_credentials == 0
    Goto WiredEnd
  ${EndIf}
   Call writePeapUserProfle
   Call InstallWiredCred
WiredEnd:
FunctionEnd

!endif  ; CRED

;==============================

Function Cleanup
${If} $Debug_cat > 0
  Quit
${EndIf}
ClearErrors
Loop:
nsArray::Iterate Delete_files
IfErrors Done
Pop $R0
Pop $R1
Delete $R1
Goto Loop
Done:
Quit
FunctionEnd

; ---- PAGES ----------------------------

Page custom nsDialogsWelcome
!ifndef SILVERBULLET
Page custom confirmUserGroup
!endif
;============================
Page custom WiredConfirm
;============================
!ifdef LICENSE_FILE
  Page license
!endif
;============================
!ifdef TLS
;=============================
Page custom PFXCertificateSelect PFXCertificateSelectLeave
;=============================
!endif
;=============================
!ifdef PASSWORD_BASED_CRED
Page custom GetPasswordBasedCredentials SavePasswordBasedCredentials
!endif
;=============================
Page instfiles ShowInstfiles
;=============================
Page custom Finish Cleanup

;----------------------------- Installer Sections ------------------------
Section "-start"
SectionIn RO

!insertmacro MUI_HEADER_TEXT "<?php WindowsCommon::echoNsis( _("Installation"))?>" "<?php WindowsCommon::echoNsis( _("Checking for existing wireless profiles"))?>"
!ifndef PWD
!include "certs.nsh"
!endif
SectionEnd


; COMMON END
