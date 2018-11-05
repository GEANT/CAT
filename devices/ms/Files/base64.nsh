; the contents of this file has been copied from
; http://nsis.sourceforge.net/Base64 and
; http://nsis.sourceforge.net/CharToASCII
; under the http://nsis.sourceforge.net/License
;
; The authors of CAT did not write the original software.
;
; merging the two files above created an altered source; this is not the 
; original software
;
!define CharToASCII "!insertmacro CharToASCII" 
 
!macro CharToASCII AsciiCode Character
  Push "${Character}"
  Call CharToASCII
  Pop "${AsciiCode}"
!macroend
 
Function CharToASCII
  Exch $0 ; given character
  Push $1 ; current character
  Push $2 ; current Ascii Code   
 
  StrCpy $2 1 ; right from start
Loop:
  IntFmt $1 %c $2 ; Get character from current ASCII code
  ${If} $1 S== $0 ; case sensitive string comparison
     StrCpy $0 $2
     Goto Done
  ${EndIf}
  IntOp $2 $2 + 1
  StrCmp $2 255 0 Loop ; ascii from 1 to 255
  StrCpy $0 0 ; ASCII code wasn't found -> return 0
Done:         
  Pop $2
  Pop $1
  Exch $0
FunctionEnd


!ifndef BASE64_NSH
!define BASE64_NSH
 
!define BASE64_ENCODINGTABLE "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/"
 
!define BASE64_PADDING "="
 
VAR   OCTETVALUE
VAR  BASE64TEMP
 
!define Base64_Encode "!insertmacro Base64_Encode"
 
!macro  Base64_Encode _cleartext
  push $R0
  push $R1
  push $R2
  push $0
  push $1
  push $2
  push $3
  push $4
  push $5
  push $6
  push $7
  push `${_cleartext}`
  push `${BASE64_ENCODINGTABLE}`
  Call Base64_Encode
  Pop $BASE64TEMP
  Pop $7
  Pop $6
  Pop $5
  Pop $4
  Pop $3
  Pop $2
  Pop $1
  Pop $0
  pop $R2
  pop $R1
  pop $R0
  Push $BASE64TEMP
!macroend
 
Function Base64_Encode
  pop $R2 ; Encoding table
  pop $R0 ; Clear Text
  StrCpy "$R1" "" # The result
 
  StrLen $1 "$R0"
  StrCpy $0 0
 
  ${WHILE} $0 < $1
    # Copy 3 characters, and for each character push their value.
    StrCpy $OCTETVALUE 0
 
    StrCpy $5 $0
    StrCpy $4 "$R0" 1 $5
    ${CharToASCII} $4 "$4"
 
    IntOp $OCTETVALUE $4 << 16
 
    IntOp $5 $5 + 1
    ${IF} $5 < $1
      StrCpy $4 "$R0" 1 $5
      ${CharToASCII} $4 "$4"
 
      IntOp $4 $4 << 8
      IntOp $OCTETVALUE $OCTETVALUE + $4
 
      IntOp $5 $5 + 1
      ${IF} $5 < $1
        StrCpy $4 "$R0" 1 $5
        ${CharToASCII} $4 "$4"
 
        IntOp $OCTETVALUE $OCTETVALUE + $4
      ${ENDIF}
    ${ENDIF}
 
    # Now take the 4 indexes from the encoding table, based on 6bits each of the octet's value.
    IntOp $4 $OCTETVALUE >> 18
    IntOp $4 $4 & 63
    StrCpy $5   "$R2" 1 $4
    StrCpy $R1  "$R1$5"
 
    IntOp $4 $OCTETVALUE >> 12
    IntOp $4 $4 & 63
    StrCpy $5   "$R2" 1 $4
    StrCpy $R1  "$R1$5"
 
    StrCpy $6 $0
    StrCpy $7 2
 
    IntOp $6 $6 + 1
    ${IF} $6 < $1
      IntOp $4 $OCTETVALUE >> 6
      IntOp $4 $4 & 63
      StrCpy $5   "$R2" 1 $4
      StrCpy $R1  "$R1$5"
      IntOp $7 $7 - 1
    ${ENDIF}
 
    IntOp $6 $6 + 1
    ${IF} $6 < $1
      IntOp $4 $OCTETVALUE & 63
      StrCpy $5   "$R2" 1 $4
      StrCpy $R1  "$R1$5"
      IntOp $7 $7 - 1
    ${ENDIF}
 
    # If there is any padding required, we now write that here.
    ${IF} $7 > 0
      ${WHILE} $7 > 0
        StrCpy $R1 "$R1${BASE64_PADDING}"
        IntOp $7 $7 - 1
      ${ENDWHILE}
    ${ENDIF}
 
    IntOp $0 $0 + 3
  ${ENDWHILE}
 
  Push "$R1"
FunctionEnd
 
!endif ;BASE64_NSH

