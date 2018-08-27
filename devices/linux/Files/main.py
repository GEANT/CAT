#!/usr/bin/env python
# -*- coding: utf-8 -*-
import subprocess 
import sys

def missing_dbus():
    print("Cannot import the dbus module")
    sys.exit(1)
    
try:
    import dbus
except:
    if sys.version_info.major == 3:
        missing_dbus()
    try:
        subprocess.call(['python3'] + sys.argv)
    except:
        missing_dbus()
    sys.exit(0)
import re
import os
import uuid
import getpass
import platform
from shutil import copyfile

debug_on = False

# the function below was partially copied from https://ubuntuforums.org/showthread.php?t=1139057
def detect_desktop_environment():
    desktop_environment = 'generic'
    if os.environ.get('KDE_FULL_SESSION') == 'true':
        desktop_environment = 'kde'
    elif os.environ.get('GNOME_DESKTOP_SESSION_ID'):
        desktop_environment = 'gnome'
    else:
        try:
            q = subprocess.Popen(['xprop', '-root', '_DT_SAVE_MODE'],stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            out, err = q.communicate()
            info = out.strip().decode('utf-8')
        except (OSError, RuntimeError):
            pass
        else:
            if ' = "xfce4"' in info:
                desktop_environment = 'xfce'
    return desktop_environment

def get_system():
    """
    Detect Linux platform. Not used at this stage.
    It is meant to enable password encryption in distos that can handle this well.
    """
    system = platform.linux_distribution()
    desktop = detect_desktop_environment()
    return([system[0], system[1], desktop])

def debug(msg):
    if debug_on == False:
        return
    print(msg)
    
def run_installer():
    global debug_on
    try:
        if sys.argv[1] == '-d':
            debug_on = True
            print("Runnng debug mode")
    except:
        pass
    debug(get_system())
    inst = InstallerData();
    inst.get_user_cred()
    ENMCT = CatNMConfigTool()
    if ENMCT.main(inst) == None:
        inst.save_wpa_conf()  
    inst.show_info(Messages.installation_finished)

class Messages:
    quit = "Really quit?"
    username_prompt = "enter your userid"
    enter_password = "enter password"
    enter_import_password = "enter your import password"
    incorrect_password = "incorrect password"
    repeat_password = "repeat your password"
    passwords_difffer = "passwords do not match"
    installation_finished = "Installation successful"
    cat_dir_exists = "Directory {} exists; some of its files may be overwritten."
    cont = "Continue?"
    nm_not_supported ="This NetworkManager version is not supported"
    cert_error = "Certificate file not found, looks like a CAT error"
    unknown_version = "Unknown version"
    dbus_error = "DBus connection problem, a sudo might help"
    yes = "Y"
    no = "N"
    p12_filter = "personal certificate file (p12 or pfx)"
    all_filter = "All files"
    p12_title = "personal certificate file (p12 or pfx)"
    save_wpa_conf = "NetworkManager configuration failed, but we may generate a wpa_supplicant configuration file if you wish. Be warned that your connection password will be saved in this file as clear text."
    save_wpa_confirm = "Write the file"
    wrongUsernameFormat = "Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"
    wrong_realm = "Error: your username must be in the form of 'xxx@{}'. Please enter the username in the correct format."
    wrong_realm_suffix = "Error: your username must be in the form of 'xxx@institutionID' and end with '{}'. Please enter the username in the correct format."
    user_cert_missing = "personal certificate file not found"
    
#    "File %s exists; it will be overwritten."
#    "Output written to %s"
    


class Config:
    instname = ""
    profilename = ""
    url = ""
    email = ""
    title = "eduroam CAT"
    servers = []
    ssids = []
    del_ssids = []
    eap_outer = ''
    eap_inner = ''
    use_other_tls_id = False
    server_match = ''
    anonymous_identity = ''
    CA = ""
    init_info = ""
    init_confirmation = ""
    tou = ""
    sb_user_file =  ""
    verify_user_realm_input = False
    user_realm =  ""
    hint_user_input = False



class InstallerData:
    graphics = ''
    def __init__(self):
        self.__get_graphics_support()
        self.show_info(Config.init_info.format(Config.instname, Config.email, Config.url))
        if self.ask(Config.init_confirmation.format(Config.instname, Config.profilename), Messages.cont, 1):
            sys.exit(1)
        if Config.tou != None:
            if self.ask(Config.tou, Messages.cont, 1):
                sys.exit(1)
        if os.path.exists(os.environ.get('HOME') + '/.cat_installer'):
            if self.ask(Messages.cat_dir_exists.format(os.environ.get('HOME') + '/.cat_installer'), Messages.cont, 1):
                sys.exit(1)
        else:
            os.mkdir(os.environ.get('HOME') + '/.cat_installer', 0o700)
        certfile = os.environ.get('HOME') + '/.cat_installer/ca.pem'
        with open(certfile, 'w') as f:
            f.write(Config.CA + "\n")
        f.closed

    def ask(self, question, prompt = '', default = None):
        if self.graphics == 'tty':
            yes = Messages.yes[:1].upper()
            no = Messages.no[:1].upper()
            print("\n-------\n" + question + "\n")
            while True:
                p = prompt + " (" + Messages.yes + "/" + Messages.no + ") "
                if default == 1:
                    p += "[" + yes + "]"
                elif default == 0:
                    p += "[" + no + "]"  
                try:
                    inp = raw_input(p)
                except:
                    inp = input(p)
                if inp == '':
                    if default == 1:
                        return 0
                    if default == 0:
                        return 1
                i = inp[:1].upper()
                if i == yes:
                    return 0
                if i == no:
                    return 1
        if self.graphics == "zenity":
            command = ['zenity',
            '--title=' + Config.title,
            '--width=500',
            '--question',
            '--text=' + question + "\n\n" + prompt]
        elif self.graphics == 'kdialog':
            command = ['kdialog',
            '--yesno',
            question + "\n\n" + prompt,
            '--title=',
            Config.title]
        returncode = subprocess.call(command)
 #       out, err = q.communicate()
        return returncode

    def show_info(self, data):
        if self.graphics == 'tty':
            print(data)
            return
        if self.graphics == "zenity":
            command = ['zenity', '--info', '--width=500', '--text=' + data]
        elif self.graphics == "kdialog":
            command = ['kdialog', '--msgbox', data]
        else:
            sys.exit(1)
        subprocess.call(command)
#        out, err = q.communicate()

    def confirm_exit(self):
        ret = self.ask(Messages.quit)
        if ret == 0:
            sys.exit(1)
            

    def alert(self,text):
        if self.graphics == 'tty':
            print(text)
            return
        if self.graphics == 'zenity':
            command = ['zenity','--warning', '--text=' + text]
        elif self.graphics == "kdialog":
            command = ['kdialog','--sorry', text]
        else:
            sys.exit(1)
        subprocess.call(command)
#        out, err = q.communicate()

    def prompt_nonempty_string(self, show, prompt, val = ''):
        if self.graphics == 'tty':
            if show == 0:
                while True:
                    inp = str(getpass.getpass(prompt + ": "))
                    output = inp.strip()
                    if output != '':
                        return output
            while True:
                try:
                    inp = str(raw_input(prompt + ": "))
                except:
                    inp = str(input(prompt + ": "))
                output = inp.strip().decode('utf-8')
                if output != '':
                    return output
                
        if self.graphics == 'zenity':
            if val == '':
                default_val = ''
            else:
                default_val = '--entry-text=' + val
            if show == 0:
                hide_text = '--hide-text'
            else:
                hide_text = ''
            command = ['zenity', 
            '--entry', hide_text,
            default_val,
            '--width=500',
            '--text=' + prompt]
        elif self.graphics == 'kdialog':
            if show == 0:
                hide_text = '--password'
            else:
                hide_text = '--inputbox'
            command = ['kdialog', hide_text, prompt]
            
        output = ''
        while not output:
            q = subprocess.Popen(command,stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            out, err = q.communicate()
            output = out.strip().decode('utf-8')
            if q.returncode == 1:
                self.confirm_exit()
        return output 
    
            
    def get_user_cred(self):
        if Config.eap_outer == 'PEAP' or Config.eap_outer == 'TTLS':
            self.__get_username_password()
        if Config.eap_outer == 'TLS':
            self.__get_p12_cred()
            
    def save_wpa_conf(self):
        if self.ask(Messages.save_wpa_conf, Messages.cont, 1):
            sys.exit(1)
        wpa = WpaConf()
        wpa.create_wpa_conf(Config.ssids,self)     
        
    def __get_username_password(self):
        PASSWORD="a"
        PASSWORD1="b"
        if Config.hint_user_input:
            user_prompt = '@' + Config.user_realm
        else:
            user_prompt = ''
        while True:
            self.USERNAME = self.prompt_nonempty_string(1, Messages.username_prompt, user_prompt)
            if self.__validate_user_name():
                break
        while PASSWORD != PASSWORD1:
            PASSWORD = self.prompt_nonempty_string(0, Messages.enter_password)
            PASSWORD1 = self.prompt_nonempty_string(0, Messages.repeat_password)
            if PASSWORD != PASSWORD1:
                self.alert(Messages.passwords_difffer)
        self.PASSWORD = PASSWORD
    
    def __get_graphics_support(self):
        if os.environ.get('DISPLAY') != None:
            q = subprocess.Popen(['which', 'zenity'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            q.wait()
            if q.returncode == 0:
                self.graphics = 'zenity'
            else:
                q = subprocess.Popen(['which', 'kdialog'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                out, err = q.communicate()
                if q.returncode == 0:
                    self.graphics = 'kdialog'
                else:
                    self.graphics = 'tty'
        else:
            self.graphics = 'tty'
    def __process_p12(self):
        debug('process_p12')
        pfx_file = os.environ['HOME'] + '/.cat_installer/user.p12'
        try:
            from OpenSSL import crypto
        except:
            debug("using openssl")
            command = ['openssl',
            'pkcs12',
            '-in', pfx_file,
            '-passin',
            'pass:' + self.PASSWORD,
            '-nokeys',
            '-clcerts']
            q = subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            out, err = q.communicate()
            if q.returncode != 0:
                return(False)
            else:
                if Config.use_other_tls_id == True:
                    return(True)
                out_str = out.decode('utf-8')
                subject = re.findall(r'subject=/?(.*)$', out_str, re.MULTILINE)[0].split('/')
                S = {}
                for field in subject:
                    if field:
                        vp = field.split('=')
                        S[vp[0].lower()] = vp[1]
                if S['cn'] and re.search(r'@', S['cn']):
                    debug('Using cn: ' + S['cn'])
                    self.USERNAME = S['cn']
                elif S['emailaddress'] and re.search(r'@', S['emailaddress']):
                    debug('Using email: ' + S['emailaddress'])
                    self.USERNAME = S['emailaddress']
                else:
                    self.USERNAME = ''
                    self.alert("Unable to extract username form the certificate")
                return(True)
        else:
            debug("using crypto")
            try:
                p12 = crypto.load_pkcs12(open(pfx_file, 'rb').read(), self.PASSWORD)
            except:
                debug("incorrect password")
                return(False)
            else:
                if Config.use_other_tls_id == True:
                    return(True)
                try:
                    self.USERNAME = p12.get_certificate().get_subject().commonName
                except:
                    self.USERNAME = p12.get_certificate().get_subject().emailAddress
                return(True)
            
            
    def __select_p12_file(self):
        if self.graphics == 'tty':
            dir = os.listdir(".")
            p_count = 0
            pfx_file = ''
            for file in dir:
                if file.endswith('.p12') or file.endswith('*.pfx') or file.endswith('.P12') or file.endswith('*.PFX'):
                    p_count += 1
                    pfx_file = file
            prompt="personal certificate file (p12 or pfx)"
            default = ''
            if p_count == 1:
                default = '[' + pfx_file + ']'

            while True:
                try:
                    inp = raw_input(prompt + default + ": ")
                except:
                    inp = input(prompt + default + ": ")
                output = inp.strip()
                
                if default != '' and output =='':
                    return(pfx_file)
                default = ''
                if os.path.isfile(output):
                    return(output)
                else:
                    print("file not found")

        if self.graphics == 'zenity':
            command = ['zenity', '--file-selection',  '--file-filter=' + Messages.p12_filter + ' | *.p12 *.P12 *.pfx *.PFX', '--file-filter=' + Messages.all_filter + ' | *', '--title=' + Messages.p12_title]
            q = subprocess.Popen(command,stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            cert, err = q.communicate()
        if self.graphics == 'kdialog':
            command = ['kdialog', '--getopenfilename', '.', '*.p12 *.P12 *.pfx *.PFX | ' + Messages.p12_filter, '--title', Messages.p12_title]
            q = subprocess.Popen(command,stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            cert, err = q.communicate()
        return(cert.strip().decode('utf-8'))
    


    def __save_sb_pfx(self):
        import base64
        certfile = os.environ.get('HOME') + '/.cat_installer/user.p12'
        with open(certfile, 'wb') as f:
            f.write(base64.b64decode(Config.sb_user_file))
        f.closed

    def __get_p12_cred(self):
        if Config.eap_inner == 'SILVERBULLET':
            self.__save_sb_pfx()
        else:
            pfx_file = self.__select_p12_file()
            try:
                copyfile(pfx_file, os.environ['HOME'] + '/.cat_installer/user.p12')
            except (OSError, RuntimeError):
                print(Messages.user_cert_missing)
                sys.exit()
        self.PASSWORD = ''
        self.USERNAME = ''
        while not self.PASSWORD:
            self.PASSWORD = self.prompt_nonempty_string(0, Messages.enter_import_password)
            if not self.__process_p12():
                self.alert(Messages.incorrect_password)
                self.PASSWORD = ''
        if not self.USERNAME:
            self.USERNAME = self.prompt_nonempty_string(1, Messages.username_prompt)
            
    def __validate_user_name(self):
        # locate the @ character in username
        pos = self.USERNAME.find('@')
        debug("@ position: " + str(pos))
        # trailing @
        if pos == len(self.USERNAME) - 1:
            debug("username ending with @")
            self.alert(Messages.wrongUsernameFormat)
            return(False)
        # no @ at all
        if pos == -1:
            if Config.verify_user_realm_input:
                debug("missing realm")
                self.alert(Messages.wrongUsernameFormat)
                return(False)
            else:
                debug("No realm, but possibly correct")
                return(True)
        # @ at the beginning
        if pos == 0:
            debug("missing user part")
            self.alert(Messages.wrongUsernameFormat)
            return(False)
        pos += 1
        if Config.verify_user_realm_input:
            if Config.hint_user_input:
                if self.USERNAME.endswith('@' + Config.user_realm,pos -1):
                    debug("realm equal to the expected value")
                    return(True)
                else:
                    debug("incorrect realm; expected:" + Config.user_realm)
                    self.alert(Messages.wrong_realm.format(Config.user_realm))
                    return(False)
            if self.USERNAME.endswith(Config.user_realm,pos):
                debug("real ends with expected suffix")
                return(True)
            else:
                debug("realm suffix error; expected: " + Config.user_realm)
                self.alert(Messages.wrong_realm_suffix.format(Config.user_realm))
                return(False)
        pos1 = self.USERNAME.find('@',pos)
        if pos1 > -1:
            debug("second @ character found")
            self.alert(Messages.wrongUsernameFormat)
            return(False)
        pos1 = self.USERNAME.find('.',pos)
        if pos1 == -1:
            debug("no dot in the realm")
            self.alert(Messages.wrongUsernameFormat)
            return(False)
        if pos1 == pos:
            debug("a dot immediately after the @ character")
            self.alert(Messages.wrongUsernameFormat)
            return(False)
        debug("all passed")
        return(True)

            


class WpaConf:
    def prepare_network_block(self,ssid,user_data):
        out = """network={
        ssid=""" + ssid + """
        key_mgmt=WPA-EAP 
        pairwise=CCMP  
        group=CCMP TKIP 
        eap=""" + Config.eap_outer + """
        ca_cert=\"""" + os.environ.get('HOME') + """/.cat_installer/ca.pem\"
        identity=\"""" + user_data.USERNAME + """\"
        domain_suffix_match=\"""" + Config.server_match + """\"
        phase2=\"auth=""" + Config.eap_inner + """\"
        password=\"""" + user_data.PASSWORD + """\"
        anonymous_identity=\"""" + Config.anonymous_identity + """\"
} 
    """
        return out
        
    def create_wpa_conf(self,ssids, user_data):
        wpa_conf = os.environ.get('HOME') + '/.cat_installer/cat_installer.conf'
        with open(wpa_conf, 'w') as f:
            for ssid in ssids:
                net = self.prepare_network_block(ssid,user_data)
                f.write(net)
            f.closed
            

class CatNMConfigTool:
    def connect_to_NM(self):
        #connect to DBus
        try:
            self.bus = dbus.SystemBus()
        except dbus.exceptions.DBusException:
            print("Can't connect to DBus")
            return(None)
        #main service name
        self.system_service_name = "org.freedesktop.NetworkManager"
        #check NM version
        self.check_nm_version()
        debug("NM version: " + self.nm_version)
        if self.nm_version == "0.9" or self.nm_version == "1.0":
            self.settings_service_name = self.system_service_name
            self.connection_interface_name = "org.freedesktop.NetworkManager.Settings.Connection"
            #settings proxy
            sysproxy = self.bus.get_object(self.settings_service_name, "/org/freedesktop/NetworkManager/Settings")
            #settings intrface
            self.settings = dbus.Interface(sysproxy, "org.freedesktop.NetworkManager.Settings")
        elif self.nm_version == "0.8":
            self.settings_service_name = "org.freedesktop.NetworkManager"
            self.connection_interface_name = "org.freedesktop.NetworkManagerSettings.Connection"
            #settings proxy
            sysproxy = self.bus.get_object(self.settings_service_name, "/org/freedesktop/NetworkManagerSettings")
            #settings intrface
            self.settings = dbus.Interface(sysproxy, "org.freedesktop.NetworkManagerSettings")
        else:
            print(Messages.nm_not_supported)
            return(None)
        debug("NM connection worked")
        return(True)
            

    def check_opts(self):
        self.cacert_file = os.environ['HOME'] + '/.cat_installer/ca.pem'
        self.pfx_file = os.environ['HOME'] + '/.cat_installer/user.p12'
        if not os.path.isfile(self.cacert_file):
            print(Messages.cert_error)
            sys.exit(2)

    def check_nm_version(self):
        try:
            proxy = self.bus.get_object(self.system_service_name, "/org/freedesktop/NetworkManager")
            props = dbus.Interface(proxy, "org.freedesktop.DBus.Properties")
            version = props.Get("org.freedesktop.NetworkManager", "Version")
        except dbus.exceptions.DBusException:
            version = "0.8"
        if re.match(r'^1\.', version):
            self.nm_version = "1.0"
            return
        if re.match(r'^0\.9', version):
            self.nm_version = "0.9"
            return
        if re.match(r'^0\.8', version):
            self.nm_version = "0.8"
            return
        else:
            self.nm_version = Messages.unknown_version
            return

    def byte_to_string(self, barray):
        return "".join([chr(x) for x in barray])


    def delete_existing_connections(self, ssid):
        #"checks and deletes earlier connections"
        try:
            conns = self.settings.ListConnections()
        except dbus.exceptions.DBusException:
            print(Messages.dbus_error)
            exit(3)
        for each in conns:
            con_proxy = self.bus.get_object(self.system_service_name, each)
            connection = dbus.Interface(con_proxy, "org.freedesktop.NetworkManager.Settings.Connection")
            try:
               connection_settings = connection.GetSettings()
               if connection_settings['connection']['type'] == '802-11-wireless':
                   conn_ssid = self.byte_to_string(connection_settings['802-11-wireless']['ssid'])
                   if conn_ssid == ssid:
                       debug("deleting connection: " + conn_ssid)
                       connection.Delete()
            except dbus.exceptions.DBusException:
               pass

    def add_connection(self,ssid,user_data):
        debug("Adding connection: " + ssid)
        server_alt_subject_name_list = dbus.Array(Config.servers)
        server_name = Config.server_match
        if self.nm_version == "0.9" or self.nm_version == "1.0":
             match_key = 'altsubject-matches'
             match_value = server_alt_subject_name_list
        else:
             match_key = 'subject-match'
             match_value = server_name
        s_8021x_data = {
            'eap': [Config.eap_outer.lower()],
            'identity': user_data.USERNAME,
            'ca-cert': dbus.ByteArray("file://{0}\0".format(self.cacert_file).encode('utf8')),
             match_key: match_value}
        if Config.eap_outer == 'PEAP' or Config.eap_outer == 'TTLS':
            s_8021x_data['password'] = user_data.PASSWORD
            s_8021x_data['phase2-auth'] = Config.eap_inner.lower()
            s_8021x_data['anonymous-identity'] = Config.anonymous_identity
            s_8021x_data['password-flags'] = 0
        if Config.eap_outer == 'TLS':
            s_8021x_data['client-cert'] = dbus.ByteArray("file://{0}\0".format(self.pfx_file).encode('utf8'))
            s_8021x_data['private-key'] = dbus.ByteArray("file://{0}\0".format(self.pfx_file).encode('utf8'))
            s_8021x_data['private-key-password'] = user_data.PASSWORD
            s_8021x_data['private-key-password-flags'] = 0
        s_con = dbus.Dictionary({
            'type': '802-11-wireless',
            'uuid': str(uuid.uuid4()),
            'permissions': ['user:' + os.environ.get('USER')],
            'id': ssid 
        })
        s_wifi = dbus.Dictionary({
            'ssid': dbus.ByteArray(ssid.encode('utf8')),
            'security': '802-11-wireless-security'
        })
        s_wsec = dbus.Dictionary({
            'key-mgmt': 'wpa-eap',
            'proto': ['rsn',],
            'pairwise': ['ccmp',],
            'group': ['ccmp', 'tkip']
        })
        s_8021x = dbus.Dictionary(s_8021x_data)
        s_ip4 = dbus.Dictionary({'method': 'auto'})
        s_ip6 = dbus.Dictionary({'method': 'auto'})
        con = dbus.Dictionary({
            'connection': s_con,
            '802-11-wireless': s_wifi,
            '802-11-wireless-security': s_wsec,
            '802-1x': s_8021x,
            'ipv4': s_ip4,
            'ipv6': s_ip6
        })
        self.settings.AddConnection(con)

    def main(self,user_data):
        self.check_opts()
        if self.connect_to_NM() == None:
            return(None)
        for ssid in Config.ssids:
            self.delete_existing_connections(ssid)
            self.add_connection(ssid,user_data)
        for ssid in Config.del_ssids:
            self.delete_existing_connections(ssid)
        debug("NM returning success")
        return(True)



