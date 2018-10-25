#!/usr/bin/env python
# -*- coding: utf-8 -*-
import argparse
import base64
import getpass
import os
import re
import subprocess
import sys
import uuid
from shutil import copyfile

NM_AVAILABLE = True
DEBUG_ON = False
DEV_NULL = open("/dev/null", "w")
STDERR_REDIR = DEV_NULL


def debug(msg):
    """
    Print debbuging messages to stdout
    """
    if not DEBUG_ON:
        return
    print("DEBUG:" + str(msg))


def missing_dbus():
    """
    Handle missing dbus module
    """
    global NM_AVAILABLE
    debug("Cannot import the dbus module")
    NM_AVAILABLE = False

try:
    import dbus
except:
    if sys.version_info.major == 3:
        missing_dbus()
    if sys.version_info.major < 3:
        try:
            subprocess.call(['python3'] + sys.argv)
        except:
            missing_dbus()

if sys.version_info.major == 3 and sys.version_info.minor >= 8:
    import distro
else:
    import platform


# the function below was partially copied
# from https://ubuntuforums.org/showthread.php?t=1139057
def detect_desktop_environment():
    """
    Detect what desktop type is used. This method is prepared for
    possible future use with password encryption on supported distrs
    """
    desktop_environment = 'generic'
    if os.environ.get('KDE_FULL_SESSION') == 'true':
        desktop_environment = 'kde'
    elif os.environ.get('GNOME_DESKTOP_SESSION_ID'):
        desktop_environment = 'gnome'
    else:
        try:
            shell_command = subprocess.Popen(['xprop', '-root',
                                              '_DT_SAVE_MODE'],
                                             stdout=subprocess.PIPE,
                                             stderr=subprocess.PIPE)
            out, err = shell_command.communicate()
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
    It is meant to enable password encryption in distos
    that can handle this well.
    """
    if sys.version_info.major == 3 and sys.version_info.minor >= 8:
        system = distro.linux_distribution()
    else:
        system = platform.linux_distribution()
    desktop = detect_desktop_environment()
    return [system[0], system[1], desktop]


def run_installer():
    """
    This is the main installer part. It tests for MN availability
    gets user credentials and starts a proper installer.
    """
    global DEBUG_ON
    global NM_AVAILABLE
    username = ''
    password = ''
    silent = False
    parser = argparse.ArgumentParser(description='eduroam linux installer.')
    parser.add_argument('--debug', '-d', action='store_true', dest='debug',
                        default=False, help='set debug flag')
    parser.add_argument('--username', '-u', action='store', dest='username',
                        help='set username')
    parser.add_argument('--password', '-p', action='store', dest='password',
                        help='set text_mode flag')
    parser.add_argument('--silent', '-s', action='store_true', dest='silent',
                        help='set silent flag')
    args = parser.parse_args()
    if args.debug:
        DEBUG_ON = True
        print("Runnng debug mode")

    if args.username:
        username = args.username
    if args.password:
        password = args.password
    if args.silent:
        silent = args.silent
    debug(get_system())
    debug("Calling InstallerData")
    installer_data = InstallerData(silent=silent, username=username,
                                   password=password)

    # test dbus connection
    if NM_AVAILABLE:
        config_tool = CatNMConfigTool()
        if config_tool.connect_to_nm() is None:
            NM_AVAILABLE = False
    if not NM_AVAILABLE:
        # no dbus so ask if the user will want wpa_supplicant config

        dbus_package = {
            'ubuntu': 'python3-dbus',
            'debian': 'python3-dbus',
            'fedora': 'python3-dbus',
            'opensuse': 'python3-dbus-python',
            'arch': 'python-dbus',
            'centos': 'dbus-python'
            # I could extend this
        }
        package = dbus_package.get(get_system()[0], '')

        if package:
            if installer_data.ask(Messages.dbus_missing, Messages.cont, 1):
                installer_data.show_info(Messages.install_package.format(package))
                sys.exit(1)
        if installer_data.ask(Messages.save_wpa_conf, Messages.cont, 1):
            sys.exit(1)
    installer_data.get_user_cred()
    installer_data.save_ca()
    if NM_AVAILABLE:
        config_tool.add_connections(installer_data)
    else:
        wpa_config = WpaConf()
        wpa_config.save_wpa_conf(installer_data)
    installer_data.show_info(Messages.installation_finished)


class Messages(object):
    """
    These are innitial definitions of messages, but they will be
    overriden with translated strings.
    """
    quit = "Really quit?"
    username_prompt = "enter your userid"
    enter_password = "enter password"
    enter_import_password = "enter your import password"
    incorrect_password = "incorrect password"
    repeat_password = "repeat your password"
    passwords_difffer = "passwords do not match"
    installation_finished = "Installation successful"
    cat_dir_exists = "Directory {} exists; some of its files may be " \
        "overwritten."
    cont = "Continue?"
    nm_not_supported = "This NetworkManager version is not supported"
    cert_error = "Certificate file not found, looks like a CAT error"
    unknown_version = "Unknown version"
    dbus_error = "DBus connection problem, a sudo might help"
    dbus_missing = "Do you want to install the dbus package to fix the error?"
    yes = "Y"
    nay = "N"
    p12_filter = "personal certificate file (p12 or pfx)"
    all_filter = "All files"
    p12_title = "personal certificate file (p12 or pfx)"
    save_wpa_conf = "NetworkManager configuration failed, " \
        "but we may generate a wpa_supplicant configuration file " \
        "if you wish. Be warned that your connection password will be saved " \
        "in this file as clear text."
    save_wpa_confirm = "Write the file"
    wrongUsernameFormat = "Error: Your username must be of the form " \
        "'xxx@institutionID' e.g. 'john@example.net'!"
    wrong_realm = "Error: your username must be in the form of 'xxx@{}'. " \
        "Please enter the username in the correct format."
    wrong_realm_suffix = "Error: your username must be in the form of " \
        "'xxx@institutionID' and end with '{}'. Please enter the username " \
        "in the correct format."
    user_cert_missing = "personal certificate file not found"
    # "File %s exists; it will be overwritten."
    # "Output written to %s"
    install_package = 'Please install the package {}. Then start the eduroam ' \
                      'linux installer again'

class Config(object):
    """
    This is used to prepare settings durig installer generation.
    """
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
    sb_user_file = ""
    verify_user_realm_input = False
    user_realm = ""
    hint_user_input = False


class InstallerData(object):
    """
    General user intercation handling, supports zenity, kdialog and
    standard command-line interface
    """

    def __init__(self, silent=False, username='', password=''):
        self.graphics = ''
        self.username = username
        self.password = password
        self.silent = silent
        debug("starting constructor")
        if silent:
            self.graphics = 'tty'
        else:
            self.__get_graphics_support()
        self.show_info(Config.init_info.format(Config.instname,
                       Config.email, Config.url))
        if self.ask(Config.init_confirmation.format(Config.instname,
                                                    Config.profilename),
                    Messages.cont, 1):
            sys.exit(1)
        if Config.tou is not None:
            if self.ask(Config.tou, Messages.cont, 1):
                sys.exit(1)

    def save_ca(self):
        """
        Save CA certificate to .cat_installer directory
        (create directory if needed)
        """
        if os.path.exists(os.environ.get('HOME') + '/.cat_installer'):
            if self.ask(Messages.cat_dir_exists.format(
                        os.environ.get('HOME') + '/.cat_installer'),
                        Messages.cont, 1):
                sys.exit(1)
        else:
            os.mkdir(os.environ.get('HOME') + '/.cat_installer', 0o700)
        certfile = os.environ.get('HOME') + '/.cat_installer/ca.pem'
        debug("saving cert")
        with open(certfile, 'w') as cert:
            cert.write(Config.CA + "\n")

    def ask(self, question, prompt='', default=None):
        """
        Propmpt user for a Y/N reply, possibly supplying a default answer
        """
        if self.silent:
            return 0
        if self.graphics == 'tty':
            yes = Messages.yes[:1].upper()
            nay = Messages.nay[:1].upper()
            print("\n-------\n" + question + "\n")
            while True:
                tmp = prompt + " (" + Messages.yes + "/" + Messages.nay + ") "
                if default == 1:
                    tmp += "[" + yes + "]"
                elif default == 0:
                    tmp += "[" + nay + "]"
                try:
                    inp = raw_input(tmp)
                except:
                    inp = input(tmp)
                if inp == '':
                    if default == 1:
                        return 0
                    if default == 0:
                        return 1
                i = inp[:1].upper()
                if i == yes:
                    return 0
                if i == nay:
                    return 1
        if self.graphics == "zenity":
            command = ['zenity', '--title=' + Config.title, '--width=500',
                       '--question', '--text=' + question + "\n\n" + prompt]
        elif self.graphics == 'kdialog':
            command = ['kdialog', '--yesno', question + "\n\n" + prompt,
                       '--title=', Config.title]
        returncode = subprocess.call(command, stderr=STDERR_REDIR)
        return returncode

    def show_info(self, data):
        """
        Show a piece of information
        """
        if self.silent:
            return
        if self.graphics == 'tty':
            print(data)
            return
        if self.graphics == "zenity":
            command = ['zenity', '--info', '--width=500', '--text=' + data]
        elif self.graphics == "kdialog":
            command = ['kdialog', '--msgbox', data]
        else:
            sys.exit(1)
        subprocess.call(command, stderr=STDERR_REDIR)

    def confirm_exit(self):
        """
        Confirm exit from installer
        """
        ret = self.ask(Messages.quit)
        if ret == 0:
            sys.exit(1)

    def alert(self, text):
        """
        Generate alert message
        """
        if self.silent:
            return
        if self.graphics == 'tty':
            print(text)
            return
        if self.graphics == 'zenity':
            command = ['zenity', '--warning', '--text=' + text]
        elif self.graphics == "kdialog":
            command = ['kdialog', '--sorry', text]
        else:
            sys.exit(1)
        subprocess.call(command, stderr=STDERR_REDIR)

    def prompt_nonempty_string(self, show, prompt, val=''):
        """
        Prompt user for input
        """
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
                output = inp.strip()
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
            command = ['zenity', '--entry', hide_text, default_val,
                       '--width=500', '--text=' + prompt]
        elif self.graphics == 'kdialog':
            if show == 0:
                hide_text = '--password'
            else:
                hide_text = '--inputbox'
            command = ['kdialog', hide_text, prompt]

        output = ''
        while not output:
            shell_command = subprocess.Popen(command, stdout=subprocess.PIPE,
                                             stderr=subprocess.PIPE)
            out, err = shell_command.communicate()
            output = out.strip().decode('utf-8')
            if shell_command.returncode == 1:
                self.confirm_exit()
        return output

    def get_user_cred(self):
        """
        Get user credentials both username/password and personame certificate
        based
        """
        if Config.eap_outer == 'PEAP' or Config.eap_outer == 'TTLS':
            self.__get_username_password()
        if Config.eap_outer == 'TLS':
            self.__get_p12_cred()

    def __get_username_password(self):
        """
        read user password and set the password property
        do nothing if silent mode is set
        """
        password = "a"
        password1 = "b"
        if self.silent:
            return
        if self.username:
            user_prompt = self.username
        elif Config.hint_user_input:
            user_prompt = '@' + Config.user_realm
        else:
            user_prompt = ''
        while True:
            self.username = self.prompt_nonempty_string(
                1, Messages.username_prompt, user_prompt)
            if self.__validate_user_name():
                break
        while password != password1:
            password = self.prompt_nonempty_string(
                0, Messages.enter_password)
            password1 = self.prompt_nonempty_string(
                0, Messages.repeat_password)
            if password != password1:
                self.alert(Messages.passwords_difffer)
        self.password = password

    def __get_graphics_support(self):
        if os.environ.get('DISPLAY') is not None:
            shell_command = subprocess.Popen(['which', 'zenity'],
                                             stdout=subprocess.PIPE,
                                             stderr=subprocess.PIPE)
            shell_command.wait()
            if shell_command.returncode == 0:
                self.graphics = 'zenity'
            else:
                shell_command = subprocess.Popen(['which', 'kdialog'],
                                                 stdout=subprocess.PIPE,
                                                 stderr=subprocess.PIPE)
                shell_command.wait()
                # out, err = shell_command.communicate()
                if shell_command.returncode == 0:
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
            command = ['openssl', 'pkcs12', '-in', pfx_file, '-passin',
                       'pass:' + self.password, '-nokeys', '-clcerts']
            shell_command = subprocess.Popen(command, stdout=subprocess.PIPE,
                                             stderr=subprocess.PIPE)
            out, err = shell_command.communicate()
            if shell_command.returncode != 0:
                return False
            else:
                if Config.use_other_tls_id:
                    return True
                out_str = out.decode('utf-8')
                subject = re.findall(r'subject=/?(.*)$',
                                     out_str, re.MULTILINE)[0].split('/')
                cert_prop = {}
                for field in subject:
                    if field:
                        vp = field.split('=')
                        cert_prop[vp[0].lower()] = vp[1]
                if cert_prop['cn'] and re.search(r'@', cert_prop['cn']):
                    debug('Using cn: ' + cert_prop['cn'])
                    self.username = cert_prop['cn']
                elif cert_prop['emailaddress'] and \
                        re.search(r'@', cert_prop['emailaddress']):
                    debug('Using email: ' + cert_prop['emailaddress'])
                    self.username = cert_prop['emailaddress']
                else:
                    self.username = ''
                    self.alert("Unable to extract username "
                               "from the certificate")
                return True
        else:
            debug("using crypto")
            try:
                p12 = crypto.load_pkcs12(open(pfx_file, 'rb').read(),
                                         self.password)
            except:
                debug("incorrect password")
                return False
            else:
                if Config.use_other_tls_id:
                    return True
                try:
                    self.username = p12.get_certificate().\
                        get_subject().commonName
                except:
                    self.username = p12.get_certificate().\
                        get_subject().emailAddress
                return True

    def __select_p12_file(self):
        """
        prompt user for the PFX file selection
        """
        if self.graphics == 'tty':
            my_dir = os.listdir(".")
            p_count = 0
            pfx_file = ''
            for my_file in my_dir:
                if my_file.endswith('.p12') or my_file.endswith('*.pfx') or \
                        my_file.endswith('.P12') or my_file.endswith('*.PFX'):
                    p_count += 1
                    pfx_file = my_file
            prompt = "personal certificate file (p12 or pfx)"
            default = ''
            if p_count == 1:
                default = '[' + pfx_file + ']'

            while True:
                try:
                    inp = raw_input(prompt + default + ": ")
                except:
                    inp = input(prompt + default + ": ")
                output = inp.strip()

                if default != '' and output == '':
                    return pfx_file
                default = ''
                if os.path.isfile(output):
                    return output
                else:
                    print("file not found")

        if self.graphics == 'zenity':
            command = ['zenity', '--file-selection',
                       '--file-filter=' + Messages.p12_filter +
                       ' | *.p12 *.P12 *.pfx *.PFX', '--file-filter=' +
                       Messages.all_filter + ' | *',
                       '--title=' + Messages.p12_title]
            shell_command = subprocess.Popen(command, stdout=subprocess.PIPE,
                                             stderr=subprocess.PIPE)
            cert, err = shell_command.communicate()
        if self.graphics == 'kdialog':
            command = ['kdialog', '--getopenfilename',
                       '.', '*.p12 *.P12 *.pfx *.PFX | ' +
                       Messages.p12_filter, '--title', Messages.p12_title]
            shell_command = subprocess.Popen(command, stdout=subprocess.PIPE,
                                             stderr=STDERR_REDIR)
            cert, err = shell_command.communicate()
        return cert.strip().decode('utf-8')

    def __save_sb_pfx(self):
        """
        write the user PFX file
        """
        certfile = os.environ.get('HOME') + '/.cat_installer/user.p12'
        with open(certfile, 'wb') as cert:
            cert.write(base64.b64decode(Config.sb_user_file))

    def __get_p12_cred(self):
        """
        get the password for the PFX file
        """
        if Config.eap_inner == 'SILVERBULLET':
            self.__save_sb_pfx()
        else:
            pfx_file = self.__select_p12_file()
            try:
                copyfile(pfx_file, os.environ['HOME'] +
                         '/.cat_installer/user.p12')
            except (OSError, RuntimeError):
                print(Messages.user_cert_missing)
                sys.exit()
        self.password = ''
        self.username = ''
        while not self.password:
            self.password = self.prompt_nonempty_string(
                0, Messages.enter_import_password)
            if not self.__process_p12():
                self.alert(Messages.incorrect_password)
                self.password = ''
        if not self.username:
            self.username = self.prompt_nonempty_string(
                1, Messages.username_prompt)

    def __validate_user_name(self):
        # locate the @ character in username
        pos = self.username.find('@')
        debug("@ position: " + str(pos))
        # trailing @
        if pos == len(self.username) - 1:
            debug("username ending with @")
            self.alert(Messages.wrongUsernameFormat)
            return False
        # no @ at all
        if pos == -1:
            if Config.verify_user_realm_input:
                debug("missing realm")
                self.alert(Messages.wrongUsernameFormat)
                return False
            else:
                debug("No realm, but possibly correct")
                return True
        # @ at the beginning
        if pos == 0:
            debug("missing user part")
            self.alert(Messages.wrongUsernameFormat)
            return False
        pos += 1
        if Config.verify_user_realm_input:
            if Config.hint_user_input:
                if self.username.endswith('@' + Config.user_realm, pos-1):
                    debug("realm equal to the expected value")
                    return True
                else:
                    debug("incorrect realm; expected:" + Config.user_realm)
                    self.alert(Messages.wrong_realm.format(Config.user_realm))
                    return False
            if self.username.endswith(Config.user_realm, pos):
                debug("real ends with expected suffix")
                return True
            else:
                debug("realm suffix error; expected: " + Config.user_realm)
                self.alert(Messages.wrong_realm_suffix.format(
                    Config.user_realm))
                return False
        pos1 = self.username.find('@', pos)
        if pos1 > -1:
            debug("second @ character found")
            self.alert(Messages.wrongUsernameFormat)
            return False
        pos1 = self.username.find('.', pos)
        if pos1 == pos:
            debug("a dot immediately after the @ character")
            self.alert(Messages.wrongUsernameFormat)
            return False
        debug("all passed")
        return True


class WpaConf(object):
    """
    Preapre and save wpa_supplicant config file
    """
    def __prepare_network_block(self, ssid, user_data):
        altsubj_match = "altsubject_match=\"%s\"" % ";".join(Config.servers)
        out = """network={
        ssid=""" + ssid + """
        key_mgmt=WPA-EAP
        pairwise=CCMP
        group=CCMP TKIP
        eap=""" + Config.eap_outer + """
        ca_cert=\"""" + os.environ.get('HOME') + """/.cat_installer/ca.pem\"
        identity=\"""" + user_data.username + """\"
        altsubject_match=\"""" + altsubj_match + """\"
        phase2=\"auth=""" + Config.eap_inner + """\"
        password=\"""" + user_data.password + """\"
        anonymous_identity=\"""" + Config.anonymous_identity + """\"
}
    """
        return out

    def __create_wpa_conf(self, ssids, user_data):
        wpa_conf = os.environ.get('HOME') + \
            '/.cat_installer/cat_installer.conf'
        with open(wpa_conf, 'w') as conf:
            for ssid in ssids:
                net = self.__prepare_network_block(ssid, user_data)
                conf.write(net)

    def save_wpa_conf(self, user_data):
        self.__create_wpa_conf(Config.ssids, user_data)


class CatNMConfigTool(object):
    """
    Prepare and save NetworkManager configuration
    """
    def __init__(self):
        self.cacert_file = None
        self.settings_service_name = None
        self.connection_interface_name = None
        self.system_service_name = None
        self.nm_version = None
        self.pfx_file = None
        self.settings = None
        self.user_data = None
        self.bus = None

    def connect_to_nm(self):
        """
        connect to DBus
        """
        try:
            self.bus = dbus.SystemBus()
        except dbus.exceptions.DBusException:
            print("Can't connect to DBus")
            return None
        # main service name
        self.system_service_name = "org.freedesktop.NetworkManager"
        # check NM version
        self.__check_nm_version()
        debug("NM version: " + self.nm_version)
        if self.nm_version == "0.9" or self.nm_version == "1.0":
            self.settings_service_name = self.system_service_name
            self.connection_interface_name = \
                "org.freedesktop.NetworkManager.Settings.Connection"
            # settings proxy
            sysproxy = self.bus.get_object(
                self.settings_service_name,
                "/org/freedesktop/NetworkManager/Settings")
            # settings intrface
            self.settings = dbus.Interface(sysproxy, "org.freedesktop."
                                           "NetworkManager.Settings")
        elif self.nm_version == "0.8":
            self.settings_service_name = "org.freedesktop.NetworkManager"
            self.connection_interface_name = "org.freedesktop.NetworkMana" \
                                             "gerSettings.Connection"
            # settings proxy
            sysproxy = self.bus.get_object(
                self.settings_service_name,
                "/org/freedesktop/NetworkManagerSettings")
            # settings intrface
            self.settings = dbus.Interface(
                sysproxy, "org.freedesktop.NetworkManagerSettings")
        else:
            print(Messages.nm_not_supported)
            return None
        debug("NM connection worked")
        return True

    def __check_opts(self):
        """
        set certificate files paths and test for existence of the CA cert
        """
        self.cacert_file = os.environ['HOME'] + '/.cat_installer/ca.pem'
        self.pfx_file = os.environ['HOME'] + '/.cat_installer/user.p12'
        if not os.path.isfile(self.cacert_file):
            print(Messages.cert_error)
            sys.exit(2)

    def __check_nm_version(self):
        """
        Get the NetworkManager version
        """
        try:
            proxy = self.bus.get_object(
                self.system_service_name, "/org/freedesktop/NetworkManager")
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

    def __byte_to_string(self, barray):
        return "".join([chr(x) for x in barray])

    def __delete_existing_connection(self, ssid):
        """
        checks and deletes earlier connection
        """
        try:
            conns = self.settings.ListConnections()
        except dbus.exceptions.DBusException:
            print(Messages.dbus_error)
            exit(3)
        for each in conns:
            con_proxy = self.bus.get_object(self.system_service_name, each)
            connection = dbus.Interface(
                con_proxy,
                "org.freedesktop.NetworkManager.Settings.Connection")
            try:
                connection_settings = connection.GetSettings()
                if connection_settings['connection']['type'] == '802-11-' \
                                                                'wireless':
                    conn_ssid = self.__byte_to_string(
                        connection_settings['802-11-wireless']['ssid'])
                    if conn_ssid == ssid:
                        debug("deleting connection: " + conn_ssid)
                        connection.Delete()
            except dbus.exceptions.DBusException:
                pass

    def __add_connection(self, ssid):
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
            'identity': self.user_data.username,
            'ca-cert': dbus.ByteArray(
                "file://{0}\0".format(self.cacert_file).encode('utf8')),
            match_key: match_value}
        if Config.eap_outer == 'PEAP' or Config.eap_outer == 'TTLS':
            s_8021x_data['password'] = self.user_data.password
            s_8021x_data['phase2-auth'] = Config.eap_inner.lower()
            s_8021x_data['anonymous-identity'] = Config.anonymous_identity
            s_8021x_data['password-flags'] = 0
        if Config.eap_outer == 'TLS':
            s_8021x_data['client-cert'] = dbus.ByteArray(
                "file://{0}\0".format(self.pfx_file).encode('utf8'))
            s_8021x_data['private-key'] = dbus.ByteArray(
                "file://{0}\0".format(self.pfx_file).encode('utf8'))
            s_8021x_data['private-key-password'] = self.user_data.password
            s_8021x_data['private-key-password-flags'] = 0
        s_con = dbus.Dictionary({
                                'type': '802-11-wireless',
                                'uuid': str(uuid.uuid4()),
                                'permissions': ['user:' +
                                                os.environ.get('USER')],
                                'id': ssid
                                })
        s_wifi = dbus.Dictionary({
                                 'ssid': dbus.ByteArray(ssid.encode('utf8')),
                                 'security': '802-11-wireless-security'
                                 })
        s_wsec = dbus.Dictionary({
                                 'key-mgmt': 'wpa-eap',
                                 'proto': ['rsn'],
                                 'pairwise': ['ccmp'],
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

    def add_connections(self, user_data):
        self.__check_opts()
        self.user_data = user_data
        for ssid in Config.ssids:
            self.__delete_existing_connection(ssid)
            self.__add_connection(ssid)
        for ssid in Config.del_ssids:
            self.__delete_existing_connection(ssid)
