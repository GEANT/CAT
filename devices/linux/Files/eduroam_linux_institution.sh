#!/usr/bin/env bash

set -euo pipefail

if [ -z "$BASH" ] ; then
   bash  "$0"
   exit
fi

file_name=$0


main() {
  setup_environment
  show_info "$INIT_INFO"
  if ! ask "$INIT_CONFIRMATION" "$CONTINUE" 1 ; then exit; fi

  if [ -z "${XDG_CONFIG_HOME:-}" ] ; then
    CAT_PATH="$HOME/.config"
  else
    CAT_PATH="$XDG_CONFIG_HOME"
  fi

  printf -v CAT_DIR_EXISTS "$CAT_DIR_EXISTS" "$CAT_PATH"

  if [ -d "$CAT_PATH/cat_installer" ] ; then
    if ! ask "$CAT_DIR_EXISTS" "$CONTINUE" 1 ; then exit; fi
  else
    mkdir "$CAT_PATH/cat_installer"
    log "Directory $CAT_PATH/cat_installer created."
  fi

  echo "$CA_CERTIFICATE" > "$CAT_PATH/cat_installer/ca.pem"
  log "Write $CAT_PATH/cat_installer/ca.pem"


  if [ -z "$USERNAME" -a -z "$PASSWORD" ] ; then
    get_user_credentials
  fi
  if nmcli_add_connection ; then
    # nmcli --ask connection up eduroam
    show_info "$INSTALLATION_FINISHED"
  else
    show_info "$SAVE_WPA_CONF"
    if ! ask "$SAVE_WPA_CONF" 1 ; then exit ; fi

  if [ -f "$CAT_PATH/cat_installer/cat_installer.conf" ] ; then
    printf -v CONF_FILE_EXITS "$CONF_FILE_EXITS" "$CAT_PATH"
    if ! ask "$CONF_FILE_EXITS" "$CONTINUE" 1 ; then confirm_exit; fi
    rm "$CAT_PATH/cat_installer/cat_installer.conf"
    log "$CAT_PATH/cat_installer/cat_installer.conf removed."
  fi
    create_wpa_conf
    show_info "$INSTALLATION_FINISHED"
    log "Installation successful."
  fi
}

function setup_environment {
  bf=""
  n=""
  ZENITY=""
  KDIALOG=""
  if [ ! -z "${DISPLAY:-}" ] ; then
    if which zenity 1>/dev/null 2>&1 ; then
      ZENITY=$(which zenity)
      log "$ZENITY detected."
    elif which kdialog 1>/dev/null 2>&1 ; then
      KDIALOG=$(which kdialog)
      log "$KDIALOG detected."
    else
      if tty > /dev/null 2>&1 ; then
        if  echo "$TERM" | grep -E -q "xterm|gnome-terminal|lxterminal"  ; then
          bf=" [1m";
          n=" [0m";
        fi
      else
        find_xterm
        if [ -n "$XT" ] ; then
          $XT -e "$file_name"
        fi
      fi
    fi
  fi
}

function split_line {
  echo "$1" | awk  -F '\\\\n' 'END {  for(i=1; i <= NF; i++) print $i }'
}

function find_xterm {
  terms="xterm aterm wterm lxterminal rxvt gnome-terminal konsole"
  for terminal in $terms; do
    if which "$terminal" > /dev/null 2>&1 ; then
      XT="$terminal"
      log "$XT detected."
      break
    fi
  done
}

function ask {
  if [ ! -z "$silent" ] ; then
    return 0
  fi
  if [ ! -z "$KDIALOG" ] ; then
     if "$KDIALOG" --yesno "${1}\n${2}" --title "$TITLE" ; then
       return 0
     else
       return 1
     fi
  fi
  if [ ! -z "$ZENITY" ] ; then
     text=$(echo "${1}" | fmt -w60)
     if "$ZENITY" --no-wrap --question --text="${text}\n${2}" --title="$TITLE" 2>/dev/null ; then
       return 0
     else
       return 1
     fi
  fi

  yes=J
  no=N
  yes1=$(echo $yes | awk '{ print toupper($0) }')
  no1=$(echo $no | awk '{ print toupper($0) }')

  if [ "$3" == "0" ]; then
    def="$yes"
  else
    def="$no"
  fi

  echo "";
  while true
  do
  split_line "$1"
  read -p -r "${bf}$2 ${yes}/${no}? [${def}]:$n " answer
  if [ -z "$answer" ] ; then
    answer=${def}
  fi
  answer=$(echo $answer | awk '{ print toupper($0) }')
  case "$answer" in
    ${yes1})
       return 0
       ;;
    ${no1})
       return 1
       ;;
  esac
  done
}

function alert {
  if [ ! -z "$silent" ] ; then
    echo "$1"
    return
  fi
  if [ ! -z "$KDIALOG" ] ; then
     "$KDIALOG" --sorry "${1}"
     return
  fi
  if [ ! -z "$ZENITY" ] ; then
     "$ZENITY" --warning --text="$1" 2>/dev/null
     return
  fi
  echo "$1"

}

function show_info {
  if [ ! -z "$silent" ] ; then
    echo "$1"
    return
  fi
  if [ ! -z "$KDIALOG" ] ; then
     "$KDIALOG" --msgbox "${1}"
     return
  fi
  if [ ! -z "$ZENITY" ] ; then
     "$ZENITY" --info --width=500 --text="$1" 2>/dev/null
     return
  fi
  split_line "$1"
}

function confirm_exit {
  if [ ! -z "$silent" ] ; then
    echo "$QUIT"
    exit 1
  fi
  if [ ! -z "$KDIALOG" ] ; then
     if "$KDIALOG" --yesno "$QUIT"  ; then
     exit 1
     fi
  fi
  if [ ! -z "$ZENITY" ] ; then
     if "$ZENITY" --question --text="$QUIT" 2>/dev/null ; then
        exit 1
     fi
  fi
}

function prompt_nonempty_string {
  prompt=$2
  H=""
  D=""
  if [ ! -z "$ZENITY" ] ; then
    if [ "$1" -eq 0 ] ; then
     H="--hide-text "
    fi
    if ! [ -z "${3:-}" ] ; then
     D="--entry-text=$3"
    fi
  elif [ ! -z "$KDIALOG" ] ; then
    if [ "$1" -eq 0 ] ; then
     H="--password"
    else
     H="--inputbox"
    fi
  fi

  out_s="";
  if [ ! -z "$silent" ] ; then
    if [ "$1" -eq 0 ] ; then
      out_s="$USERNAME"
    elif [ "$1" -eq 1 ] ; then
      out_s="$PASSWORD"
    fi
    confirm_exit
  fi

  if [ ! -z "$ZENITY" ] ; then
    while [ ! "$out_s" ] ; do
      out_s=$($ZENITY --entry --width=300 $H "$D" --text "$prompt" 2>/dev/null)
      if [ $? -ne 0 ] ; then
        confirm_exit
      fi
    done
  elif [ ! -z "$KDIALOG" ] ; then
    while [ ! "$out_s" ] ; do
      out_s=$($KDIALOG $H "$prompt" "$3")
      if [ $? -ne 0 ] ; then
        confirm_exit
      fi
    done
  else
    while [ ! "$out_s" ] ; do
      read -p "${prompt}: " out_s
    done
  fi
  echo "$out_s";
}

function get_user_credentials {
  if [ "$EAP_OUTER" = "PEAP" -o "$EAP_OUTER" = "TTLS" ] ; then
    get_username_password
  fi
  if [ "$EAP_OUTER" = "TLS" ] ; then
    get_p12_credentials
  fi
}

function get_username_password {
  PASSWORD="a"
  PASSWORD1="b"

  if ! USERNAME=$(prompt_nonempty_string 1 "$USERNAME_PROMPT") ; then
    exit 1
  else
    while validate_username ; do
      USERNAME=$(prompt_nonempty_string 1 "$USERNAME_PROMPT")
    done
    log "Username entered."
  fi

  while [ "$PASSWORD" != "$PASSWORD1" ] ; do
    if ! PASSWORD=$(prompt_nonempty_string 0 "$ENTER_PASSWORD") ; then
      exit 1
    fi
    if ! PASSWORD1=$(prompt_nonempty_string 0 "$ENTER_PASSWORD") ; then
      exit 1
    fi
    if [ "$PASSWORD" != "$PASSWORD1" ] ; then
      alert "$PASSWORD_DIFFER"
    fi
  done
  log "Password entered."
}

function validate_username {
  log "validate username"
  if [[ "$USERNAME" =~ "@" ]] ; then
    log "\$USERNAME contains character '@' ($USERNAME)."
    username_length="${#USERNAME}"
    t="${USERNAME%%@*}"
    t="${#t}"
    at_position="$((t + 1))"
    if [ "$at_position" -le "$username_length" ] ; then
      log "Username length: $username_length, @ position: $at_position ($USERNAME)"
      EMAIL_REGEX="([0-9a-zA-Z]+)@([0-9a-zA-Z]+)"
      if [[ "$USERNAME" =~ $EMAIL_REGEX ]] ; then
        log "\$USERNAME match regex ($USERNAME)."
        realm="${BASH_REMATCH[2]}"
        if [ "$VERIFY_USER_REALM_INPUT" = true ] ; then
          if [ "$realm" = "$USER_REALM" ] ; then
            log "User realm input is equal to \$USER_REALM ($USERNAME)."
            return 1
          else
            log "User realm input is uneqal to \$USER_REALM ($USERNAME)."
            alert "$WRONG_REALM_SUFFIX"
            return 0
          fi
        else
          log "\$VERIFY_USER_REAM_INPUT is false. Realm possibly correct."
        fi
      else
        log "Username not valid ($USERNAME)."
        alert "$WRONG_REALM_SUFFIX"
        return 0
      fi
    else
      if [ "$VERIFY_USER_REALM_INPUT" = true ] ; then
        log "No realm exists, but $USER_REALM expected."
        alert "$WRONG_REALM"
        return 0
      fi
    fi
  else
    if [ "$VERIFY_USER_REALM_INPUT" = true ] ; then
      log "The realm is missing ($USERNAME)."
      alert "$WRONG_USERNAME_FORMAT"
      return 0
    else
      log "No realm exists, but possibly correct."
      return 1
    fi
  fi
  return 1
}

function get_p12_credentials {
  if [ "$EAP_INNER" = "SILVERBULLET" ] ; then
    save_sb_pfx
  else
    if [ ! -z "$silent" ] ; then
      if [ ! -z "PFX_FILE" ] ; then
        alert "PFX is missning." # TODO
        exit 1
      fi
    else
      PFX_FILE_PATH=select_p12_file
      if cp "$PFX_FILE_PATH" "$CAT_PATH/cat_installer/user.p12" ; then
        log "File user.p12 is written."
      else
        log "Couldn't write p12 file."
        exit 1
      fi
    fi
  fi

  if [ ! -z "$silent" ] ; then
    if process_p12 ; then
      exit 1
    fi
  else
    while [ ! -z "$PASSWORD" -o ! -z "$USERNAME" ] ; do
    if ! USERNAME=$(prompt_nonempty_string 0 "$USERNAME_PROMPT") ; then
        alert "$ENTER_IMPORT_PASSWORD"
      fi
      if ! PASSWORD=$(prompt_nonempty_string 0 "$ENTER_PASSWORD") ; then
        alert "$ENTER_IMPORT_PASSWORD"
      fi
      if process_p12 ; then
        alert "$INCORRECT_PASSWORD"
      fi
    done
  fi
}

function process_p12 {
  if [ -z "$USE_OUTER_TLS_ID" ] ; then
    exit 0
  fi

  if which libressl 1>/dev/null 2>&1 ; then
      SSL_LIBRARY=libressl
  elif which openssl 1>/dev/null 2>&1 ; then
      SSL_LIBRARY=openssl
  else
    log "No ssl library found."
  fi
  log "Found $SSL_LIBRARY."
  output="$($SSL_LIBRARY pkcs12 -in "$PFX_FILE" -passin pass:$PASSWORD -nokeys -clcerts)"
  if [ "$?" != 0 ] ; then
    log "SSL library command failed (Error code $?)."
    exit 1
  fi
  certificate_property=()
  readarray -t lines <<< "$output"
  for line in "${lines[@]}" ; do
    if [ "$line" =~ "subject=*" ] ; then
      IFS="="
      read -ra subject <<< "$line"
      key=echo "${subject[0],,}"
      value=echo "${subject[1],,}"
      certificate_property["$key"]="$value"
    fi
  done

  if [ -z "$certificate_property['cn']" -a "$certificate_property['cn']" =~ "*@*" ] ; then
    USERNAME="$certificate_property['cn']"
    log "Using cn: $certificate_property['cn']"
  elif [ -z "$certificate_property['emailaddress']" -a "$certificate_property['emailaddress']" =~ "*@*" ] ; then
    log "Using emailaddress: $certificate_property['emailaddress']"
    USERNAME="$certificate_property['emailaddress']"
  else
    USERNAME=""
    alert "Unable to extract username from the certificate." # TODO
  fi
  return 0
}

function select_p12_file {
  if [ ! -z "$ZENITY" ] ; then
    certificate_output="$($ZENITY --file-selection --file-filter=$P12_FILTER | *.p12 *.P12 *.pfx *.PFX --file-filter=$ALL_FILTER --title=$P12_TITLE)"
    if [ "$?" != 0 ] ; then
      log " Choose pfx file failed (Error code: $?)."
      exit 1
    fi
  elif [ ! -z "$KDIALOG" ] ; then
    certificate_output="$($KDIALOG --getopenfilename . *.p12 *.P12 *.pfx *.PFX | $P12_FILTER --title=$P12_TITLE)"
    if [ "$?" != 0 ] ; then
      log " Choose pfx file failed (Error code: $?)."
      exit 1
    fi
  else
    certificate_output="$(find . -type f \( -name '*.p12' -or -name '*.P12' -or -name '*.pfx' -or -name '*.PFX' \))"
    if [ "$?" != 0 ] ; then
      log " Choose pfx file failed (Error code: $?)."
      exit 1
    fi
    PFX_FILE="$certificate_output"
    # TODO
  fi

  return "$(echo $certificate_output | xargs | iconv -t utf8)"
}

function save_sb_fx {
  CERTIFICATE_FILE="CAT_PATH/cat_installer/user.p12"
  echo "$SB_USER_FILE" > "$CERTIFICATE_FILE"
}

function nmcli_add_connection {
  interface=$(get_wlan_interface)
  log "WLAN device $interface found."

  for ssid in "${SSIDS[@]}"; do
    log "Try to add connection for $ssid."
    nmcli connection add type wifi con-name "$ssid" ifname "$interface" ssid "$ssid" -- \
    wifi-sec.key-mgmt wpa-eap 802-1x.eap "$EAP_OUTER" 802-1x.phase2-auth "$EAP_INNER" \
    802-1x.altsubject-matches "$ALTSUBJECT_MATCHES" 802-1x.anonymous-identity "$ANONYMOUS_IDENTITY" \
    802-1x.ca-cert "$CAT_PATH/cat_installer/ca.pem" 802-1x.identity "$USERNAME" connection.permissions "$USER" \
    802-11-wireless-security.proto rsn 802-11-wireless-security.group "ccmp,tkip" 802-11-wireless-security.pairwise ccmp
    log "Add $ssid connection with nmcli successful."
  done
}

function get_wlan_interface {
  device=$(echo /sys/class/net/*/wireless | awk -F"/" "{ print \$5 }")
  echo "$device"
  return 0
}

function create_wpa_conf {
  if [ "$EAP_INNER" == "MSCHAPV2" ] ; then
    if which openssl 1>/dev/null 2>&1 ; then
      PASSWORD=$(echo -n "$PASSWORD" | iconv -t utf16le | openssl md4)
      PASSWORD=hash:${PASSWORD#*= }
    fi
  fi

  cat << EOFW >> $HOME/.config/cat_installer/cat_installer.conf

network={
  ssid="eduroam"
  key_mgmt=WPA-EAP
  pairwise=CCMP
  group=CCMP TKIP
  eap="${EAP_OUTER}"
  ca_cert="$CAT_PATH/cat_installer/ca.pem"
  identity="${USERNAME}"
  domain_suffix_match="${ALTSUBJECT_MATCHES}"
  phase2="auth=${EAP_INNER}"
  password="${PASSWORD}"
  anonymous_identity="${ANONYMOUS_IDENTITY}"
}
EOFW
  log "Write $HOME/.config/cat_installer/cat_installer.conf."
  chmod 600 "$CAT_PATH/cat_installer/cat_installer.conf"
}

function log {
  if ! [ -z "$debug" ] ; then
    echo "[${USER}][$(date)] - ${*}"
  fi
}

function usage() {
    echo "usage: eduroam_linux installer [[[--debug]] | [--help]]"
}

debug=
silent=
username=
password=
verbose=
USERNAME=""
PASSWORD=""
PFX_FILE=""
while (( "$#" )); do
    case $1 in
        -d | --debug )          debug=1
                                ;;
        -s | --silent )         silent=1
                                ;;
        -u | --username )       shift
                                USERNAME=$1
                                ;;
        -p | --password )       shift
                                PASSWORD=$1
                                ;;
        -f | --pfxfile )        shift
                                PFX_FILE=$1
                                ;;
        -v | --verbose )        verbose=1
                                ;;
        -h | --help )           usage
                                exit
                                ;;
        * )                     usage
                                exit 1
    esac
    shift
done

if [ ! -z "$silent" ] ; then
  missing_parameter=false
  if [ -z "${USERNAME+x}" ] ; then
    echo "Parameter --username is missing."
    missing_parameter=true
  fi
  if [ -z "${PASSWORD+x}" ] ; then
    echo "Parameter --password is missing."
    missing_parameter=true
  fi
  if [ "$missing_parameter" = true ] ; then
    exit 1
  fi
fi

if ! [ -z "$verbose" ] ; then
  set -x
fi

ORGANISATION="Institution"
#PROFILE_NAME="eduroam"
URL="https://cat.eduroam.org/"
E_MAIL="it-helpdesk@eduroam.org"
TITLE="DFN eduroam CAT"
SSIDS=("eduroam")
ALTSUBJECT_MATCHES="'DNS:radius.eduroam.org'"
EAP_OUTER="TTLS"
EAP_INNER="PAP"
ANONYMOUS_IDENTITY="anonymous@eduroam.org"
VERIFY_USER_REALM_INPUT=true
USER_REALM="eduroam"
TOU=""
CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
MIIDwzCCAqugAwIBAgIBATANBgkqhkiG9w0BAQsFADCBgjELMAkGA1UEBhMCREUx
KzApBgNVBAoMIlQtU3lzdGVtcyBFbnRlcnByaXNlIFNlcnZpY2VzIEdtYkgxHzAd
BgNVBAsMFlQtU3lzdGVtcyBUcnVzdCBDZW50ZXIxJTAjBgNVBAMMHFQtVGVsZVNl
YyBHbG9iYWxSb290IENsYXNzIDIwHhcNMDgxMDAxMTA0MDE0WhcNMzMxMDAxMjM1
OTU5WjCBgjELMAkGA1UEBhMCREUxKzApBgNVBAoMIlQtU3lzdGVtcyBFbnRlcnBy
aXNlIFNlcnZpY2VzIEdtYkgxHzAdBgNVBAsMFlQtU3lzdGVtcyBUcnVzdCBDZW50
ZXIxJTAjBgNVBAMMHFQtVGVsZVNlYyBHbG9iYWxSb290IENsYXNzIDIwggEiMA0G
CSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCqX9obX+hzkeXaXPSi5kfl82hVYAUd
AqSzm1nzHoqvNK38DcLZSBnuaY/JIPwhqgcZ7bBcrGXHX+0CfHt8LRvWurmAwhiC
FoT6ZrAIxlQjgeTNuUk/9k9uN0goOA/FvudocP05l03Sx5iRUKrERLMjfTlH6VJi
1hKTXrcxlkIF+3anHqP1wvzpesVsqXFP6st4vGCvx9702cu+fjOlbpSD8DT6Iavq
jnKgP6TeMFvvhk1qlVtDRKgQFRzlAVfFmPHmBiiRqiDFt1MmUUOyCxGVWOHAD3bZ
wI18gfNycJ5v/hqO2V81xrJvNHy+SE/iWjnX2J14np+GPgNeGYtEotXHAgMBAAGj
QjBAMA8GA1UdEwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgEGMB0GA1UdDgQWBBS/
WSA2AHmgoCJrjNXyYdK4LMuCSjANBgkqhkiG9w0BAQsFAAOCAQEAMQOiYQsfdOhy
NsZt+U2e+iKo4YFWz827n+qrkRk4r6p8FU3ztqONpfSO9kSpp+ghla0+AGIWiPAC
uvxhI+YzmzB6azZie60EI4RYZeLbK4rnJVM3YlNfvNoBYimipidx5joifsFvHZVw
IEoHNN/q/xWA5brXethbdXwFeilHfkCoMRN3zUA7tFFHei4R40cR3p1m0IvVVGb6
g1XqfMIpiRvpb7PO4gWEyS8+eIVibslfwXhjdFjASBgMmTnrpMwatXlajRWc2BQN
9noHV8cigwUtPJslJj0Ys6lDfMjIq2SPDqO/nBudMNva0Bkuqjzx+zOAduTNrRlP
BSeOE6Fuwg==
-----END CERTIFICATE-----
"
USERNAME_PROMPT="Geben Sie ihre Benutzerkennung ein."
ENTER_PASSWORD="Geben Sie Ihr Passwort ein."
PASSWORD_DIFFER="Die Passwörter stimmen nicht Überein."
INSTALLATION_FINISHED="Installation erfolgreich."
SAVE_WPA_CONF="Konfiguration von NetworkManager fehlgeschlagen, erzeuge nun wpa_supplicant.conf Datei."
QUIT="Wirklich beenden?"
CONTINUE="Weiter"
WRONG_USERNAME_FORMAT="Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"
WRONG_REALM="Error: your username must be in the form of 'xxx@%s'. Please enter the username in the correct format."
WRONG_REALM_SUFFIX="Error: your username must be in the form of 'xxx@institutionID' and end with '%s'. Please enter the username in the correct format."
ENTER_IMPORT_PASSWORD="Enter your import password."

INIT_INFO_TMP="Dieses Installationsprogramm wurde für %s hergestellt.\n\nMehr Informationen und Kommentare:\n\nEMAIL: %s\nWWW: %s\n\nDas Installationsprogramm wurde mit Software vom GEANT Projekt erstellt."
INIT_CONFIRMATION_TMP="Dieses Installationsprogramm funktioniert nur für Anwender von %s."
CAT_DIR_EXISTS="Das Verzeichnis %s/cat_installer existiert bereits; einige Dateien darin könnten überschrieben werden."
CONF_FILE_EXITS="Datei %s/cat_installer/cat_installer.conf existiert bereits, sie wird überschrieben."
SAVE_WPA_CONF="Die Konfiguration des Network-Manager ist fehlgeschlagen, aber es könnte stattdessen eine Konfigurationsdatei für das Programm wpa_supplicant erstellt werden. Beachten Sie bitte, dass Ihr Passwort im Klartext in dieser Datei steht."

printf -v INIT_INFO "$INIT_INFO_TMP" "$ORGANISATION" "$E_MAIL" "$URL"
printf -v INIT_CONFIRMATION "$INIT_CONFIRMATION_TMP" "$ORGANISATION"

main "$@"; exit