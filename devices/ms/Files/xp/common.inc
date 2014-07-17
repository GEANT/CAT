!define HKEY_CURRENT_USER        0x80000001
!define HKEY_LOCAL_MACHINE       0x80000002
 
!define KEY_QUERY_VALUE          0x0001
!define KEY_ENUMERATE_SUB_KEYS   0x0008
 
 
!define RegOpenKeyEx     "Advapi32::RegOpenKeyExA(i, t, i, i, *i) i"


;---------------------------------------


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
        !include "WinVer.nsh"
        !include "WordFunc.nsh"
!ifdef TTLS
        !include "ZipDLL.nsh"
!endif

XPStyle on


${Array} "Radios" 1 20
${ArrayFunc} Pop
${ArrayFunc} SizeOf
${ArrayFunc} Read
${ArrayFunc} Write

${Array} "Profile_names" 5 40
${ArrayFunc} Read
${ArrayFunc} Write
${ArrayFunc} Concat
${ArrayFunc} Debug


${Array} "Profile_states" 5 2
${ArrayFunc} Read
${ArrayFunc} Write

 
!define PROFILE_STATE_DELETE 1
 
!define PROFILE_STATE_FAIL 2


Var Dialog
Var profile_fail
Var VistaNoSP 
Var W7
Var XPSP3
Var WindowsName
Var profile_count
Var aes_count
Var welcome_message
Var wired

!ifdef TLS
  Var Cert_file
  Var PFX_install
  Var Dialog1
  var Password
!endif
!ifdef TTLS
Var Radio1
Var Radio2
Var Radioi
Var Reboot
Var SkipReboot
!endif



;--------------------------------
;General

  ;Name and file
  Name "${APPLICATION} ${VERSION}"
  OutFile "${INSTALLER_NAME}"
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

;TRANSLATION
!define WELCOME_HEADER "<?php printf(sprint_nsi(_("Welcome to the %s installer")),Config::$CONSORTIUM['name'])?>"
!define FAREWELL_HEADER "<?php echo_nsi( _("Installation complete"))?>"
!define FAREWELL_TEXT	"<?php echo_nsi( _("Network profiles have been installed."))?>$\r$\n$\n <?php echo_nsi( _("Your system is ready."))?>"
!define FAREWELL_FAIL	"<?php echo_nsi( _("Network installation had errors."))?>$\r$\n$\n <?php echo_nsi( _('Please contact \${SUPPORT}.'))?>"
;--------------------------------
;Languages
!insertmacro MUI_LANGUAGE "${LANG}"

;--------------------------------
; License file
!ifdef LICENSE_FILE
;TRANSLATION
LicenseForceSelection checkbox "<?php echo_nsi( _("Accept"))?>"
LicenseText "<?php echo_nsi( _("If you accept the conditions then select Accept and then click Install to continue."))?>"
LicenseData ${LICENSE_FILE}
!endif

;-----------------------------------

MiscButtonText "" "" "" "<?php echo_nsi( _("Finish"))?>"
;----------------------------------
;  FUNCTIONS

!macro define_wlan_profile prof enc
  ${Profile_names->Write} $profile_count "${prof}"
  IntOp $profile_count $profile_count + 1
  ${If} ${enc} == "AES"
    IntOp $aes_count $aes_count + 1
  ${EndIf}
!macroend

!macro install_ca_cert ca_file
   File "${ca_file}"
;TRANSLATION
   DetailPrint "<?php echo_nsi( _("installing certificate"))?> ${ca_file}"
   nsExec::Exec '"certutil" -addstore -user root $TEMP\${ca_file}'
!macroend

Function .onInit
  
  !insertmacro MUI_LANGDLL_DISPLAY
  SetOutPath $TEMP 
  CreateFont $HEADLINE_FONT "$(^Font)" "13" "700"
  ${Profile_names->Init}
  ${Profile_states->Init}
  Push 0
  Pop $profile_count
  Push 0
  Pop $aes_count
 !ifdef WIRED
    Push 1
  !else
    Push 0
  !endif
  Pop $wired

  !include "profiles.nsh"
  IntOp $profile_count $profile_count - 1
  File "wlan_test.exe";
  !ifdef BACKGROUND_IMG
    File /oname=cat_background.bmp "${BACKGROUND_IMG}"
  !else
    File /oname=cat_background.bmp "cat_bg.bmp"
  !endif
  !ifdef EXTERNAL_INFO
    File "${EXTERNAL_INFO}"
  !endif
  Push 0
  Pop $profile_fail


  Call Vista_Check
 ${If} $wired == 1
    Call CheckWired
  ${Else}
    Call Wireless_Check
  ${EndIf}
FunctionEnd


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

StrCpy $welcome_message "<?php echo_nsi( _('This installer has been prepared for \${ORGANISATION}'))?>.$\r$\n\
<?php echo_nsi( _("The installer will create network profile:"))?> \
$R0.$\r$\n$\n\
<?php sprintf(sprint_nsi( _("You will be able to use %s in most institutions however due to Windows XP limitations and differences in network settings at different institutions, you may be sometimes required to repeat the connection for the system to reconfigure.")),Config::$CONSORTIUM['name'])?>$\r$\n$\n\
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

  StrCpy $0 "$TEMP\cat_background.bmp"
  System::Call 'user32::LoadImage(i 0, t r0, i ${IMAGE_BITMAP}, i 0, i 0, i ${LR_LOADFROMFILE}) i.s'
  Pop $IMAGE
	
  SendMessage $IMAGECTL ${STM_SETIMAGE} ${IMAGE_BITMAP} $IMAGE

  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 10u -130u 30u "${WELCOME_HEADER}"
  Pop $HEADLINE
  SendMessage $HEADLINE ${WM_SETFONT} $HEADLINE_FONT 0
;TRANSLATION
  nsDialogs::CreateControl STATIC ${WS_VISIBLE}|${WS_CHILD}|${WS_CLIPSIBLINGS} 0 120u 42u -130u -32u "$welcome_message\
$\r$\n$\r$\n<?php echo_nsi( _("Installer created with software from the GEANT project."))?>"
  Pop $TEXT

  SetCtlColors $DIALOG "" 0xffffff
  SetCtlColors $HEADLINE "" 0xffffff
  SetCtlColors $TEXT "" 0xffffff
  Call HideControls
  nsDialogs::Show
  Call ShowControls
  System::Call gdi32::DeleteObject(i$IMAGE)
FunctionEnd



;  Check for Vista and W7
;
Function Vista_Check
;   ${If} ${AtLeastWinXP}
;   ${AndIf} ${AtLeastServicePack} 3
;     MessageBox MB_OK "Running XP SP3 or above"
;   ${Else}
;     MessageBox MB_OK "Running below XP SP3"   
;   ${EndIf}
  GetVersion::WindowsName
  Pop $WindowsName
  GetVersion::WindowsVersion
  Pop $R0
  GetVersion::WindowsServicePackBuild
  Pop $R1
;    MessageBox MB_OK "Windows version $R0 $R1"
  
     Push 0
     Pop $XPSP3
  ${VersionCompare} "$R0" "6.0" $R5
  ${If} $R5 < 2
;MessageBox MB_OK "H0"
    ${If} $R1 >= 7000
      Push 1
    ${ElseIf} $R1 >= 6001
      Push 0
      Pop $VistaNoSP
      Push 0
      Pop $W7
    ${Else}
     Push 0
     Pop $W7
     Push 1
     Pop $VistaNoSP 
    ${EndIf}
  ${Else}
    ${VersionCompare} "$R0" "5.1" $R5
    ${If} $R5 == 0
      ${If} ${AtLeastServicePack} 3
       Push 1
       Pop $XPSP3
      ${EndIf}
    ${EndIf}
  ${EndIf}
;    MessageBox MB_OK "VistaNoSP:$VistaNoSP; W7:$W7"

;TRANSLATION
  ${If} $XPSP3 == 0
    MessageBox MB_OK|MB_ICONEXCLAMATION  "<?php echo_nsi(_("You are running Windows \$WindowsName but this installer is only for Windows XP. Please download the correct installer and try again."))?>"
!ifndef ALLOW_VISTA
    Quit
!endif
  ${EndIf}
FunctionEnd

Function CreateLanProfile
 DetailPrint "<?php echo_nsi(_("installing wired profile"))?>"
  nsExec::Exec '"netsh" lan add profile $TEMP\lan_prof.xml'
  Pop $0
;  Delete "$TEMP\lan_prof.xml"
FunctionEnd


; Check if wireless intefaces and service are running
Function Wireless_Check
  nsExec::Exec "$TEMP\wlan_test.exe"
  Pop $0
  ${If} $0 == 1
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi( _("Windows wireless configuration service is not enabled. The installer cannot continue."))?>"
    Quit
  ${EndIf}
  ${If} $0 == 2
    MessageBox MB_OK|MB_ICONEXCLAMATION "<?php echo_nsi( _("No wireless interfaces found. The installer cannot continue."))?>"
    Quit
  ${EndIf}
FunctionEnd

; Check for running dot3svc service required for 802.1x on wired interfaces
  Function CheckWired
    IfSilent wired
    MessageBox MB_YESNO "<?php sprintf(sprint_nsi( _("Do you want to enable %s on wired interfaces?")),Config::$CONSORTIUM['name'])?>" IDYES wired
    push 0
    pop $wired
  wired:
  ${If} $wired == 1
    nsExec::Exec  '"sc" start dot3svc'
    nsExec::Exec  '"sc" config dot3svc start= auto'
  ${EndIf}
  FunctionEnd


;================================

;TRANSLATION
Function ShowInstfiles
  !insertmacro MUI_HEADER_TEXT "<?php echo_nsi( _("Profiles installation"))?>" " "
FunctionEnd
Function ProfileCheck
  Pop $R0
  ${Profile_names->Read} $R1 $R0
  DetailPrint "<?php echo_nsi( _("Checking for profile $R1"))?>"
  nsExec::Exec  '"netsh" wlan show profiles $R1'
  Pop $0
  ${If} $0 == 0
    DetailPrint "<?php echo_nsi( _("found profile $R1"))?>"
    ${Profile_states->Write} $R0 ${PROFILE_STATE_DELETE}
  ${EndIf}
FunctionEnd
;==========================

Function CheckWirelessProfiles
  ${ForEach} $R9 0 $profile_count + 1
    Push $R9
    Call ProfileCheck
  ${Next}
FunctionEnd
;=============================

Function confirmUserGroup
;TRANSLATION
!ifdef USER_GROUP
MessageBox MB_OKCANCEL "<?php echo_nsi( _("This installer will only work properly if you are a member of \${ORGANISATION} and the user group: \${USER_GROUP}."))?>$\r$\n<?php echo_nsi( _("Click OK to continue with installation."))?>" IDOK cont
!else
MessageBox MB_OKCANCEL "<?php echo_nsi( _("This installer will only work properly if you are a member of \${ORGANISATION}."))?>$\r$\n<?php echo_nsi( _("Click OK to continue with installation."))?>" IDOK cont
!endif
Quit
cont:
FunctionEnd

Function displayExternalInfo
;TRANSLATION
  MessageBox MB_OK "<?php echo_nsi( _("Please read the additional information from your home institution which will be displayed in a moment."))?>"
  ExecShell "open" "${EXTERNAL_INFO}"
FunctionEnd

;=============================

Function Finish
;MessageBox MB_OK "Finish"
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
!ifdef TTLS
  ${NSD_CreateRadioButton} 150u 80u 50% 12u "<?php echo_nsi( _("Restart system now"))?>"
  Pop $Radio1
  ${NSD_CreateRadioButton} 150u 120u 50% 12u "<?php echo_nsi( _("I will restart later"))?>"
  Pop $Radio2
  ${NSD_SetState} $Radio1 ${BST_CHECKED}
  SetCtlColors $Radio1 "" 0xffffff
  SetCtlColors $Radio2 "" 0xffffff
!else
  ${If} $profile_fail == 1
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


!ifdef TTLS
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
;    MessageBox MB_OK "deleting profile $R1"
;TRANSLATION
    DetailPrint "<?php echo_nsi( _('deleting profile $R1'))?>"
    nsExec::Exec '"netsh" wlan delete profile $R1'
  ${EndIf}
;TRANSLATION
  DetailPrint "<?php echo_nsi( _('installing profile $R1'))?>"
  nsExec::Exec '"netsh" wlan add profile $TEMP\wlan_prof-$R0.xml'
  Pop $0
;TRANSLATION
  ${If} $0 <> 0
    MessageBox MB_OK "<?php echo_nsi( _('Profile installation error for $R1.'))?>$\r$\n<?php echo_nsi( _('Please contact \${SUPPORT}.'))?>"
    ${Profile_states->Write} $R0 ${PROFILE_STATE_FAIL}
    Push 1
    Pop $profile_fail
  ${EndIf}
  Delete "$TEMP\wlan_prof-$R0.xml"
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
  MessageBox MB_OK "<?php echo_nsi( _("Preparing to install personal certificate."))?>$\r$\n<?php echo_nsi( _("Click OK to continue"))?> "
  nsDialogs::Create 1018
  Pop $Dialog1
  ${If} $Dialog1 == error
    Abort
  ${EndIf}
  nsDialogs::SelectFileDialog open "" "<?php echo_nsi( _('Certificate files (*.p12)'))?>|*.p12|<?php echo_nsi( _('Certificate files (*pfx)'))?>|*.pfx|All files |*.*"
  Pop $Cert_file
;TRANSLATION
  ${NSD_CreateLabel} 0 0 100% 32u "<?php echo_nsi( _('Selected file: $Cert_file'))?>"
  ${If} $VistaNoSP == 0
    ${NSD_CreateLabel} 0 35u 80u 12u "<?php echo_nsi( _("certificate password:"))?>"
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
      MessageBox MB_OK  "<?php echo_nsi( _("No certificate file selected"))?>"
      Quit
   ${EndIf}

   ${If} $VistaNoSP == 1
     System::Call "cryptext::CryptExtAddPFX(i $HWNDPARENT, i 0, t '$Cert_file', i 1)"
   ${Else}
      ${NSD_GetText} $Password $0
      nsExec::Exec '"certutil" -importpfx -user -p $0 $Cert_file'
      Pop $1
;TRANSLATION
      ${If} $1 <> 0
        MessageBox MB_OK  "<?php echo_nsi( _('Incorrect certificate password$\r$\nenter again'))?>"
        Abort
      ${EndIf}
    ${EndIf}
FunctionEnd
;===========================
!endif

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
      "<?php echo_nsi( _("If an option is not selected by default, then it is either not available or optional."))?>"
PageExEnd
;=============================
Page custom PFXCertificateSelect PFXCertificateSelectLeave
;=============================
!endif
;=============================
Page instfiles ShowInstfiles
;=============================
!ifdef TTLS
PageEx custom
PageCallbacks Finish FinishLeave
PageExEnd
!else
Page custom Finish
!endif

;----------------------------- Installer Sections ------------------------
Section "-start"
SectionIn RO

!insertmacro MUI_HEADER_TEXT "Installation" "Checking for existing wireless profiles"
!ifdef TTSL
File cert-*.crt
!else
!include "certs.nsh"
!endif
SectionEnd


; COMMON END
