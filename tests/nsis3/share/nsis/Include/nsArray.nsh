/*
  nsArray NSIS plug-in by Stuart Welch <afrowuk@afrowsoft.co.uk>
  v1.1.1.4 - 3rd September 2012
*/

!ifndef __NsArray_H__
!define __NsArray_H__

!include LogicLib.nsh
!include Util.nsh

!macro __nsArray_Copy
Exch $R0
Exch
Exch $R1
Push $R2
Push $R3

  ${Do}
    ClearErrors
    nsArray::Iterate $R1
    ${If} ${Errors}
      ${Break}
    ${EndIf}
    Pop $R2
    Pop $R3
    nsArray::Set $R0 /key=$R2 $R3
  ${Loop}

Pop $R3
Pop $R2
Pop $R1
Pop $R0
!macroend

!macro _nsArray_Copy Src Dest
  Push `${Src}`
  Push `${Dest}`
  ${CallArtificialFunction} __nsArray_Copy
!macroend
!define nsArray_Copy `!insertmacro _nsArray_Copy`

!macro __nsArray_CopyKeys
Exch $R0
Exch
Exch $R1
Push $R2
Push $R3

  ${Do}
    ClearErrors
    nsArray::Iterate $R1
    ${If} ${Errors}
      ${Break}
    ${EndIf}
    Pop $R2
    Pop $R3
    nsArray::Set $R0 $R2
  ${Loop}

Pop $R3
Pop $R2
Pop $R1
Pop $R0
!macroend

!macro _nsArray_CopyKeys Src Dest
  Push `${Src}`
  Push `${Dest}`
  ${CallArtificialFunction} __nsArray_CopyKeys
!macroend
!define nsArray_CopyKeys `!insertmacro _nsArray_CopyKeys`

!macro __nsArray_ToString
Exch $R0
Push $R1
Push $R2
Push $R3

  StrCpy $R3 ``
  ${Do}
    ClearErrors
    nsArray::Iterate $R0
    ${If} ${Errors}
      ${Break}
    ${EndIf}
    Pop $R1
    Pop $R2
    ${If} $R3 != ``
      StrCpy $R3 `$R3, `
    ${EndIf}
    StrCpy $R3 `$R3$R1 => $R2`
  ${Loop}

  StrCpy $R0 `$R0 = {$R3}`

Pop $R3
Pop $R2
Pop $R1
Exch $R0
!macroend

!macro _nsArray_ToString Array OutVar
  Push `${Array}`
  ${CallArtificialFunction} __nsArray_ToString
  Pop `${OutVar}`
!macroend
!define nsArray_ToString `!insertmacro _nsArray_ToString`

!macro _ForEachIn _s _a _k _v
  !verbose push
  !verbose ${LOGICLIB_VERBOSITY}
  !insertmacro _PushLogic
  !define ${_Logic}For _LogicLib_Label_${LOGICLIB_COUNTER}
  !insertmacro _IncreaseCounter
  !insertmacro _PushScope ExitFor _LogicLib_Label_${LOGICLIB_COUNTER}
  nsArray::Iterate `${_a}` /reset
  ${${_Logic}For}:
  !insertmacro _IncreaseCounter
  !insertmacro _PushScope Break ${_ExitFor}
  ClearErrors
  !if `${_s}` == next
  nsArray::Iterate `${_a}`
  !else
  nsArray::Iterate `${_a}` /prev
  !endif
  IfErrors ${_ExitFor}
  Pop `${_k}`
  Pop `${_v}`
  !insertmacro _PushScope Continue ${${_Logic}For}
  !define ${_Logic}Condition _
  !verbose pop
!macroend
!define ForEachIn `!insertmacro _ForEachIn next`
!define ForEachInReverse `!insertmacro _ForEachIn prev`

!endif