<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file creates Linux installers
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Michał Gasewicz <genn@umk.pl> (Network Manager support)
 *
 * @package ModuleWriting
 */

/**
 * necessary includes
 */
require_once('DeviceConfig.php');

/**
 * This class creates Linux installers. It supports NetworkManager and raw
 * wpa_supplicant files.
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Michał Gasewicz <genn@umk.pl> (Network Manager support)
 *
 * @package ModuleWriting
 */
class Device_Linux extends DeviceConfig {

      final public function __construct() {
//      $this->supportedEapMethods  = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP);
      $this->supportedEapMethods  = array(EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_MSCHAP2, EAP::$TLS);
      $this->local_dir = '.eduroam';
      $this->conf_file = '$HOME/'.$this->local_dir.'/eduroam.conf';
      debug(4,"LINUX: This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
    }

   public function writeInstaller() {
      $out_string = '#!/bin/bash

';
      $out_string .= $this->printFunctions();
      $out_string .= $this->printStart();
      $out_string .= $this->printProfileConfirmation();
      $out_string .= $this->printUserConsent();
      $out_string .= $this->printCheckDirectory();
      $CAs = $this->attributes['internal:CAs'][0];
      $this->server_name = $this->glueServerNames($this->attributes['eap:server_name']);
      $out_string .= "# save certificates\n";
      $out_string .= 'echo "';
      foreach ($CAs as $ca) {
        $out_string .= $ca['pem']."\n";
      }
      $out_string .= '"'." > \$HOME/$this->local_dir/ca.pem\n";

     $SSIDs = $this->attributes['internal:SSID'];

     $out_string .= $this->printNMScript($SSIDs);
     $out_string .= $this->writeWpaConf($SSIDs);
     if($this->selected_eap == EAP::$TLS) 
       $out_string .= $this->printP12Dialog();
     else
       $out_string .= $this->printPasswordDialog();
     $out_string .= $this->checkNMResultAndCont();
     $installer_path = $this->installerBasename.'.sh';
      file_put_contents($installer_path, $out_string);
      return($installer_path);
   }

    public function writeDeviceInfo() {
    $ssid_ct=count($this->attributes['internal:SSID']);
    $out = '';

   $out .= _("The installer is in the form of a bash script. It will try to configure eduroam under Network Manager and if this is either not appropriate for your system or your version of Network Manager is too old, a wpa_supplicant config file will be created instead.");
   $out .= "<p>";
    if($ssid_ct > 1) {
        if($ssid_ct > 2) {
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to the following networks:"),implode(', ',Config::$CONSORTIUM['ssid']))." ";
        } else
            $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to:"),implode(', ',Config::$CONSORTIUM['ssid']))." ";
        $i = 0;
        foreach ($this->attributes['internal:SSID'] as $ssid=>$v) {
           if(! in_array($ssid, Config::$CONSORTIUM['ssid'])) {
             if($i > 0)
           $out .= ", ";
         $i++;
         $out .= "<strong>$ssid</strong>";
       }
    }
    $out .= "<p>";
    }
   $out .= _("The installer will create .eduroam sub-directory in your home directory and will copy your server certificates there.");
if($this->eap == EAP::$TLS)
   $out .= _("In order to connect to the network you will need an a personal certificate in the form of a p12 file. You should obtain this certificate from your home institution. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process. Your p12 file will also be copied to the .eduroam directory.");
else {
   $out .= _("In order to connect to the network you will need an account from your home institution. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
   $out .= "<p>";
   $out .= _("You will be requested to enter your account credentials during the installation. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
}
    $out .= "<p>";
    return $out;
  }


  private function printCheckDirectory() {
$out = 'if [ -d $HOME/'.$this->local_dir.' ] ; then
   if ! ask "'.sprintf(_("Directory %s exists; some of its files may be overwritten."),'$HOME/'.$this->local_dir).'" "'._("Continue").'" 1 ; then exit; fi
else
  mkdir $HOME/'.$this->local_dir.'
fi
';
 return $out;
  }

  private function checkNMResultAndCont() {
    $out = 'if run_python_script ; then
   show_info "'._("Installation successful").'"
else
   show_info "'._("Network Manager configuration failed, generating wpa_supplicant.conf").'"
if [ -f '.$this->conf_file.' ] ; then
  if ! ask "'.sprintf(_("File %s exists; it will be overwritten."),$this->conf_file).'" "'._("Continue").'" 1 ; then confirm_exit; fi
  rm '.$this->conf_file.'
  fi
   create_wpa_conf
   show_info "'.sprintf(_("Output written to %s"),$this->conf_file).'"
fi
';
  return $out;
  }

private function printStart() {
     $out = "setup_environment\n";
     $out .= 'show_info "'._("This installer has been prepared for \${ORGANISATION}").'\n\n'._("More information and comments:").'\n\nEMAIL: ${SUPPORT}\nWWW: ${URL}\n\n'.
_("Installer created with software from the GEANT project.").'"
';
  return $out;
}


private function printProfileConfirmation() {
 if($this->attributes['internal:profile_count'][0] > 1)
       $out = 'if ! ask "'.sprintf(_("This installer will only work properly if you are a member of %s and the user group: %s."),'${bf}'.$this->attributes['general:instname'][0].'${n}','${bf}'.$this->attributes['profile:name'][0]).'${n}"';
    else
       $out = 'if ! ask "'.sprintf(_("This installer will only work properly if you are a member of %s."),'${bf}'.$this->attributes['general:instname'][0]).'${n}"';
    $out .= ' "'._("Continue").'" 1 ; then exit; fi
';
  return $out;

}


  private function printUserConsent() {
    $out = '';
    if(isset($this->attributes['support:info_file'])) {
      if( $this->attributes['internal:info_file'][0]['mime'] == 'txt') {
         $handle = fopen($this->attributes['internal:info_file'][0]['name'],"r");
         $consent = '';
         while (($buffer = fgets($handle, 4096)) !== false) {
           $consent .= rtrim($buffer) . '\n';
         }
         $out = 'if ! ask "'.$consent.'${n}" "'._("Continue").'" 1 ; then exit; fi
';
      }
    }
    return $out;
  }
# ask user for confirmation
# the first argument is the user prompt
# if the second argument is 0 then the first element of yes_no array
# will be the default value prompted to the user
  private function printFunctions() {
$url = (isset($this->attributes['support:url'][0]) && $this->attributes['support:url'][0] ) ? $this->attributes['support:url'][0] : $this->support_url_substitute;
$support=(isset($this->attributes['support:email'][0]) && $this->attributes['support:email'][0] ) ? $this->attributes['support:email'][0] : $this->support_email_substitute;
$out ='
my_name=$0


function setup_environment {
  bf=""
  n=""
  ORGANISATION="'.$this->attributes['general:instname'][0].'"
  URL="'.$url.'"
  SUPPORT="'.$support.'"
if [ ! -z "$DISPLAY" ] ; then
  if which zenity 1>/dev/null 2>&1 ; then
    ZENITY=`which zenity`
  elif which kdialog 1>/dev/null 2>&1 ; then
    KDIALOG=`which kdialog`
  else
    if tty > /dev/null 2>&1 ; then
      if  echo $TERM | grep -E -q "xterm|gnome-terminal|lxterminal"  ; then
        bf="[1m";
        n="[0m";
      fi
    else
      find_xterm
      if [ -n "$XT" ] ; then
        $XT -e $my_name
      fi
    fi
  fi
fi
}

function split_line {
echo $1 | awk  -F \'\\\\\\\\n\' \'END {  for(i=1; i <= NF; i++) print $i }\'
}

function find_xterm {
terms="xterm aterm wterm lxterminal rxvt gnome-terminal konsole"
for t in $terms
do
  if which $t > /dev/null 2>&1 ; then
  XT=$t
  break
  fi
done
}


function ask {
     T="'.Config::$APPEARANCE['productname'].'"
#  if ! [ -z "$3" ] ; then
#     T="$T: $3"
#  fi
  if [ ! -z $KDIALOG ] ; then
     if $KDIALOG --yesno "${1}\n${2}?" --title "$T" ; then
       return 0
     else
       return 1
     fi
  fi
  if [ ! -z $ZENITY ] ; then
     text=`echo "${1}" | fmt -w60`
     if $ZENITY --no-wrap --question --text="${text}\n${2}?" --title="$T" ; then
       return 0
     else
       return 1
     fi
  fi

  yes='._("Y").'
  no='._("N").'
  yes1=`echo $yes | awk \'{ print toupper($0) }\'`
  no1=`echo $no | awk \'{ print toupper($0) }\'`

  if [ $3 == "0" ]; then
    def=$yes
  else
    def=$no
  fi

  echo "";
  while true
  do
  split_line "$1"
  read -p "${bf}$2 ${yes}/${no}? [${def}]:$n " answer
  if [ -z "$answer" ] ; then
    answer=${def}
  fi
  answer=`echo $answer | awk \'{ print toupper($0) }\'`
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
  if [ ! -z $KDIALOG ] ; then
     $KDIALOG --sorry "${1}"
     return
  fi
  if [ ! -z $ZENITY ] ; then
     $ZENITY --warning --text="$1"
     return
  fi
  echo "$1"

}

function show_info {
  if [ ! -z $KDIALOG ] ; then
     $KDIALOG --msgbox "${1}"
     return
  fi
  if [ ! -z $ZENITY ] ; then
     $ZENITY --info --width=500 --text="$1"
     return
  fi
  echo "$1"
}

function confirm_exit {
  if [ ! -z $KDIALOG ] ; then
     if $KDIALOG --yesno \"'._("Really quit?").'\"  ; then
     exit 1
     fi
  fi
  if [ ! -z $ZENITY ] ; then
     if $ZENITY --question --text=\"'._("Really quit?").'\" ; then
        exit 1
     fi
  fi
}



function prompt_nonempty_string {
  prompt=$2
  if [ ! -z $ZENITY ] ; then
    if [ $1 -eq 0 ] ; then
     H="--hide-text "
    fi
    if ! [ -z "$3" ] ; then
     D="--entry-text=$3"
    fi
  elif [ ! -z $KDIALOG ] ; then
    if [ $1 -eq 0 ] ; then
     H="--password"
    else
     H="--inputbox"
    fi
  fi


  out_s="";
  if [ ! -z $ZENITY ] ; then
    while [ ! "$out_s" ] ; do
      out_s=`$ZENITY --entry --width=300 $H $D --text "$prompt"`
      if [ $? -ne 0 ] ; then
        confirm_exit
      fi
    done
  elif [ ! -z $KDIALOG ] ; then
    while [ ! "$out_s" ] ; do
      out_s=`$KDIALOG $H "$prompt" "$3"`
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

function user_cred {
  PASSWORD="a"
  PASSWORD1="b"

  if ! USER_NAME=`prompt_nonempty_string 1 "'._("enter your userid").'"` ; then
    exit 1
  fi

  while [ "$PASSWORD" != "$PASSWORD1" ]
  do
    if ! PASSWORD=`prompt_nonempty_string 0 "'._("enter your password").'"` ; then
      exit 1
    fi
    if ! PASSWORD1=`prompt_nonempty_string 0 "'._("repeat your password").'"` ; then
      exit 1
    fi
    if [ "$PASSWORD" != "$PASSWORD1" ] ; then
      alert "'._("passwords do not match").'"
    fi
  done
}
';
return $out;

}


  private function writeWpaConf($SSIDs) {
     $e = EAP::eapDisplayName($this->selected_eap);
$out = 'function create_wpa_conf {
cat << EOFW >> '.$this->conf_file."\n";
     foreach (array_keys($SSIDs) as $ssid) {
    $out .= '
network={
  ssid="'.$ssid.'"
  key_mgmt=WPA-EAP
  eap='.$e['OUTER'].'
  ca_cert="${HOME}/'.$this->local_dir.'/ca.pem"
  identity="${USER_NAME}"';
  if($this->server_name)
    $out .= '
  subject_match="'.$this->server_name.'"';
  if($this->selected_eap == EAP::$TLS) {
    $out .= '
  private_key="${HOME}/'.$this->local_dir.'/user.p12"
  private_key_passwd="${PASSWORD}"';
  } else {
    $out .= '
  phase2="auth='.$e['INNER'].'"
  password="${PASSWORD}"';
  if($this->attributes['internal:use_anon_outer'][0] == 1) 
    $out .= '
  anonymous_identity="'.$this->attributes['internal:anon_local_value'][0].'@'.$this->attributes['internal:realm'][0].'"';
  }
    $out .= '
}';
}
  $out .= '
EOFW
chmod 600 '.$this->conf_file.'
}
';
  return $out;
}



  private function printPasswordDialog() {
  $out = '#prompt user for credentials
  user_cred
  ';
  return $out;
}
  private function printP12Dialog() {
  $out ='function p12dialog {
  if [ ! -z $ZENITY ] ; then
    if ! cert=`$ZENITY --file-selection --file-filter="'._("personal certificate file (p12 or pfx)").' | *.p12 *.P12 *.pfx *.PFX" --file-filter="All files | *" --title="'._("personal certificate file (p12 or pfx)").'"` ; then
       exit
    fi
  elif [ ! -z $KDIALOG ] ; then
    if ! cert=`$KDIALOG --getopenfilename . "*.p12 *.P12 *.pfx *.PFX | '._("personal certificate file (p12 or pfx)").'" --title "'._("personal certificate file (p12 or pfx)").'"` ; then
       exit
    fi
  
  else
    cert=""
    fl_ct=`ls *.p12 *.P12 *.pfx *.PFX  2>/dev/null | wc -l`
    if [ "$fl_ct" = "1" ]; then
      cert=`ls *.p12 *.P12 *.pfx *.PFX 2>/dev/null `
    fi

    while true ; do
      prompt="'._("personal certificate file (p12 or pfx)").'"
      read -p "${prompt} [$bf$cert${n}]" cert_f
      if [ "$cert" -a -z "$cert_f" ] ; then
         break
      else
        if [ -f "$cert_f" ] ; then
          cert=$cert_f
          break
        else
          echo "'._("file not found").'"
          cert=""
        fi
      fi
    done
fi
    cp "$cert" $HOME/'.$this->local_dir.'/user.p12
    cert=$HOME/'.$this->local_dir.'/user.p12

    PASSWORD=""
    prompt="'._("enter the password for the certificate file").'"
    while [ ! "$PASSWORD" ]
    do
      if ! PASSWORD=`prompt_nonempty_string 0 "'._("enter the password for the certificate file").'"` ; then
        exit 1
      fi
      if openssl pkcs12 -in $cert -passin pass:"$PASSWORD" -noout 2>/dev/null; then
        USER_NAME=`openssl pkcs12 -in $cert -passin pass:"$PASSWORD" -nokeys -clcerts 2>/dev/null | awk -F/ \'/subject=/ {for(i=1 ; i <= NF; i++) { if(match($i,\'/[cC][nN]=/\')) { print substr($i,RSTART+RLENGTH)}}}\' | grep \'@\'`
        if [ -z "$USER_NAME" ] ; then
        USER_NAME=`openssl pkcs12 -in $cert -passin pass:"$PASSWORD" -nokeys -clcerts 2>/dev/null | awk -F/ \'/subject=/ {for(i=1 ; i <= NF; i++) { if(match($i,\'/email[^=]*=/\')) { print substr($i,RSTART+RLENGTH)}}}\' | grep  \'@\'`
        fi
      else
        alert "incorrect password"
        PASSWORD=""
      fi
    done
      if ! USERNAME=`prompt_nonempty_string 1 "enter your userid" "$USER_NAME"` ; then
       exit 1
    fi
}  
p12dialog
';
 return $out;
}


private function glueServerNames($server_list) {
  if(! $server_list)
    return FALSE;
 $A0 =  array_reverse(explode('.',array_shift($server_list)));
 $B = $A0;
     foreach($server_list as $a) {
     $A= array_reverse(explode('.',$a));
     $B = array_intersect_assoc($A0,$A);
     $A0 = $B;
   }
  return(implode('.',array_reverse($B)));
}

private function printNMScript($SSIDs) {
   $e = EAP::eapDisplayName($this->selected_eap);
$out = 'function run_python_script {
python << EOF > /dev/null 2>&1
#-*- coding: utf-8 -*-
import dbus
import re
import sys
import uuid
import os

class EduroamNMConfigTool:

    def connect_to_NM(self):
        #connect to DBus
        try:
            self.bus = dbus.SystemBus()
        except dbus.exceptions.DBusException:
            print "Can\'t connect to DBus"
            sys.exit(2)
        #main service name
        self.system_service_name = "org.freedesktop.NetworkManager"
        #check NM version
        nm_version = self.check_nm_version()
        if nm_version == "0.9":
            self.settings_service_name = self.system_service_name
            self.connection_interface_name = "org.freedesktop.NetworkManager.Settings.Connection"
            #settings proxy
            sysproxy = self.bus.get_object(self.settings_service_name, "/org/freedesktop/NetworkManager/Settings")
            #settings intrface
            self.settings = dbus.Interface(sysproxy, "org.freedesktop.NetworkManager.Settings")
        elif nm_version == "0.8":
            #self.settings_service_name = "org.freedesktop.NetworkManagerUserSettings"
            self.settings_service_name = "org.freedesktop.NetworkManager"
            self.connection_interface_name = "org.freedesktop.NetworkManagerSettings.Connection"
            #settings proxy
            sysproxy = self.bus.get_object(self.settings_service_name, "/org/freedesktop/NetworkManagerSettings")
            #settings intrface
            self.settings = dbus.Interface(sysproxy, "org.freedesktop.NetworkManagerSettings")
        else:
            print "This Network Manager version is not supported"
            sys.exit(2)

    def check_opts(self):
        self.cacert_file = \'${HOME}/'.$this->local_dir.'/ca.pem\'
        self.pfx_file = \'${HOME}/'.$this->local_dir.'/user.p12\'
        if not os.path.isfile(self.cacert_file):
            print "Certificate file not found, looks like a CAT error"
            sys.exit(2)

    def check_nm_version(self):
        try:
            proxy = self.bus.get_object(self.system_service_name, "/org/freedesktop/NetworkManager")
            props = dbus.Interface(proxy, "org.freedesktop.DBus.Properties")
            version = props.Get("org.freedesktop.NetworkManager", "Version")
        except dbus.exceptions.DBusException:
            version = "0.8"
        if re.match(r\'^0\.9\', version):
            return "0.9"
        if re.match(r\'^0\.8\', version):
            return "0.8"
        else:
            return "Unknown version"

    def byte_to_string(self, barray):
        return "".join([chr(x) for x in barray])


    def delete_existing_connections(self, ssid):
        "checks and deletes earlier connections"
        try:
            conns = self.settings.ListConnections()
        except dbus.exceptions.DBusException:
            print "DBus connection problem, a sudo might help"
            exit(3)
        for each in conns:
            con_proxy = self.bus.get_object(self.system_service_name, each)
            connection = dbus.Interface(con_proxy, "org.freedesktop.NetworkManager.Settings.Connection")
            try:
               connection_settings = connection.GetSettings()
               if connection_settings[\'connection\'][\'type\'] == \'802-11-wireless\':
                   conn_ssid = self.byte_to_string(connection_settings[\'802-11-wireless\'][\'ssid\'])
                   if conn_ssid == ssid:
                       connection.Delete()
            except dbus.exceptions.DBusException:
               pass

    def add_connection(self,ssid):
        s_con = dbus.Dictionary({
            \'type\': \'802-11-wireless\',
            \'uuid\': str(uuid.uuid4()),
            \'permissions\': [\'user:$USER\'],
            \'id\': ssid 
        })
        s_wifi = dbus.Dictionary({
            \'ssid\': dbus.ByteArray(ssid),
            \'security\': \'802-11-wireless-security\'
        })
        s_wsec = dbus.Dictionary({
            \'key-mgmt\': \'wpa-eap\',
            \'proto\': [\'rsn\',],
            \'pairwise\': [\'ccmp\',],
            \'group\': [\'ccmp\',]
        })
        s_8021x = dbus.Dictionary({
            \'eap\': [\''.strtolower($e['OUTER']).'\'],
            \'identity\': \'$USER_NAME\',
            \'ca-cert\': dbus.ByteArray("file://" + self.cacert_file + "\0"),';
    
  if($this->server_name)
    $out .= '
            \'subject-match\': \''.$this->server_name.'\',';
    if($this->selected_eap == EAP::$TLS) {
       $out .= '
            \'client-cert\':  dbus.ByteArray("file://" + self.pfx_file + "\0"),
            \'private-key\':  dbus.ByteArray("file://" + self.pfx_file + "\0"),
            \'private-key-password\':  \'$PASSWORD\',';
    } else {
       $out .= '
            \'password\': \'$PASSWORD\',
            \'phase2-auth\': \''.strtolower($e['INNER']).'\',';
         if($this->attributes['internal:use_anon_outer'][0] == 1) 
              $out .= '
            \'anonymous-identity\': \''.$this->attributes['internal:anon_local_value'][0].'@'.$this->attributes['internal:realm'][0].'\',';
    }
    $out .= '
        })
        s_ip4 = dbus.Dictionary({\'method\': \'auto\'})
        s_ip6 = dbus.Dictionary({\'method\': \'auto\'})
        con = dbus.Dictionary({
            \'connection\': s_con,
            \'802-11-wireless\': s_wifi,
            \'802-11-wireless-security\': s_wsec,
            \'802-1x\': s_8021x,
            \'ipv4\': s_ip4,
            \'ipv6\': s_ip6
        })
        self.settings.AddConnection(con)

    def main(self):
        self.check_opts()
        ver = self.connect_to_NM()';
     foreach (array_keys($SSIDs) as $ssid) {
           $out .='
        self.delete_existing_connections(\''.$ssid.'\')
        self.add_connection(\''.$ssid.'\')';
     }
$out .='

if __name__ == "__main__":
    ENMCT = EduroamNMConfigTool()
    ENMCT.main()
EOF
}
';
return $out;
}


private $local_dir;
private $conf_file;
private $server_name;
}

?>
