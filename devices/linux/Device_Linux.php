<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */

/**
 * This file creates Linux installers
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Michał Gasewicz <genn@umk.pl> (Network Manager support)
 *
 * @package ModuleWriting
 */
namespace devices\linux;
use Exception;
/**
 * This class creates Linux installers. It supports NetworkManager and raw
 * wpa_supplicant files.
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @author Michał Gasewicz <genn@umk.pl> (Network Manager support)
 *
 * @package ModuleWriting
 */
class Device_Linux extends \core\DeviceConfig {

    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_SILVERBULLET]);
    }

    public function writeInstaller() {
        $installerPath = $this->installerBasename . ".py";
        $this->copyFile("main.py", $installerPath);
        $installer = fopen($installerPath,"a");
        if ($installer === FALSE) {
            throw new Exception("Unable to open installer file for writing!");
        }
        fwrite($installer,$this->writeMessages());
        fwrite($installer,$this->writeConfigVars());
        fwrite($installer, "run_installer()\n");
        fclose($installer);
        return($installerPath);
    }

    public function writeDeviceInfo() {
        $ssidCount = count($this->attributes['internal:SSID']);
        $out = '';

        $out .= _("The installer is in the form of a Python script. It will try to configure eduroam under Network Manager and if this is either not appropriate for your system or your version of Network Manager is too old, a wpa_supplicant config file will be created instead.");
        $out .= "<p>";
        if ($ssidCount > 1) {
            if ($ssidCount > 2) {
                $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to the following networks:"), implode(', ', CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) . " ";
            } else {
                $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to:"), implode(', ', CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) . " ";
            }
            $iterator = 0;
            foreach ($this->attributes['internal:SSID'] as $ssid => $v) {
                if (!in_array($ssid, CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) {
                    if ($iterator > 0) {
                        $out .= ", ";
                    }
                    $iterator++;
                    $out .= "<strong>$ssid</strong>";
                }
            }
            $out .= "<p>";
        }
        $out .= _("The installer will create .cat_installer sub-directory in your home directory and will copy your server certificates there.");
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS) {
            $out .= _("In order to connect to the network you will need a personal certificate in the form of a p12 file. You should obtain this certificate from your organisation. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process. Your p12 file will also be copied to the .cat_installer directory.");
        } elseif ($this->selectedEap != \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $out .= _("In order to connect to the network you will need an account from your organisation. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
            $out .= "<p>";
            $out .= _("You will be requested to enter your account credentials during the installation. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
        }
        // nothing to say if we are doing silverbullet.
        $out .= "<p>";
        return $out;
    }
    
    private function writeMessages() {
        $out = '';
        $out .= 'Messages.quit = "' . _("Really quit?") . "\"\n";
        $out .= 'Messages.username_prompt = "' . _("enter your userid") . "\"\n";
        $out .= 'Messages.enter_password = "' . _("enter password") . "\"\n";
        $out .= 'Messages.enter_import_password = "' . _("enter your import password") . "\"\n";
        $out .= 'Messages.incorrect_password = "' . _("incorrect password") . "\"\n";
        $out .= 'Messages.repeat_password = "' . _("repeat your password") . "\"\n";
        $out .= 'Messages.passwords_difffer = "' . _("passwords do not match") . "\"\n";
        $out .= 'Messages.installation_finished = "' . _("Installation successful") . "\"\n";
        $out .= 'Messages.cat_dir_exisits = "' . _("Directory {} exists; some of its files may be overwritten.") . "\"\n";
        $out .= 'Messages.cont = "' . _("Continue?") . "\"\n";
        $out .= 'Messages.nm_not_supported = "' . _("This Network Manager version is not supported") . "\"\n";
        $out .= 'Messages.cert_error = "' . _("Certificate file not found, looks like a CAT error") . "\"\n";
        $out .= 'Messages.unknown_version = "' . _("Unknown version") . "\"\n";
        $out .= 'Messages.dbus_error = "' . _("DBus connection problem, a sudo might help") . "\"\n";
        $out .= 'Messages.yes = "' . _("Y") . "\"\n";
        $out .= 'Messages.no = "' . _("N") . "\"\n";
        $out .= 'Messages.p12_filter = "' . _("personal certificate file (p12 or pfx)") . "\"\n";
        $out .= 'Messages.all_filter = "' . _("All files") . "\"\n";
        $out .= 'Messages.p12_title = "' . _("personal certificate file (p12 or pfx)") . "\"\n";
        $out .= 'Messages.save_wpa_conf = "' . _("Network Manager configuration failed, but we may generate a wpa_supplicant configuration file if you wish. Be warned that your connection password will be saved in this file as clear text.") . "\"\n";
        $out .= 'Messages.save_wpa_confirm = "' . _("Write the file") . "\"\n";
        $out .= 'Messages.wrongUsernameFormat = "' ._("Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!") . "\"\n";
        $out .= 'Messages.wrong_realm = "' . _("Error: your username must be in the form of 'xxx@{}'. Please enter the username in the correct format.") . "\"\n";
        $out .= 'Messages.wrong_realm_suffix = "' . _("Error: your username must be in the form of 'xxx@institutionID' and end with '{}'. Please enter the username in the correct format.") . "\"\n";
        $out .= 'Messages.user_cert_missing = "' . _("personal certificate file not found") . "\"\n";
    
        return $out;
    }
    
    private function writeConfigVars() {
        $eapMethod = \core\common\EAP::eapDisplayName($this->selectedEap);
        $out = '';
        $out .= 'Config.instname = "' . $this->attributes['general:instname'][0] . '"' . "\n";
        $out .= 'Config.profilename = "' . $this->attributes['profile:name'][0] . '"' . "\n";
        $contacts = $this->mkSupportContacts();
        $out .= 'Config.url = "' . $contacts['url'] . '"' . "\n";
        $out .= 'Config.email = "' . $contacts['email'] . '"' . "\n";
        $out .= 'Config.title = "' . "eduroam CAT" . "\"\n";
        $out .= 'Config.servers = ' . $this->mkSubjectAltNameList() . "\n";
        $out .= 'Config.ssids = ' . $this->mkSsidList() . "\n";
        $out .= 'Config.del_ssids = ' . $this->mkDelSsidList() . "\n";
        $out .= "Config.server_match = '" . $this->glueServerNames() . "'\n";
        $out .= "Config.eap_outer = '" . $eapMethod['OUTER'] . "'\n";
        $out .= "Config.eap_inner = '" . $eapMethod['INNER'] . "'\n";
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS && isset($this->attributes['eap-specific:tls_use_other_id']) && $this->attributes['eap-specific:tls_use_other_id'][0] == 'on') {
            $out .= "Config.use_other_tls_id = True\n";
        }
        else {
            $out .= "Config.use_other_tls_id = False\n";
        }
        $tou = $this->mkUserConsent();
        $out .= 'Config.tou = ' . ( $tou ? '"""' . $tou . '"""' : 'None' ) . "\n"; 
        $out .= 'Config.CA = """' . $this->mkCAfile()  . '"""' . "\n";
        $outerId = $this->determineOuterIdString();
        if ($outerId !== NULL) {
            $out .= "Config.anonymous_identity = '$outerId'\n";
        }
        $out .= 'Config.init_info = """' . $this->mkIntro() . '"""' . "\n";
        $out .= 'Config.init_confirmation = "' . $this->mkProfileConfirmation() . "\"\n";
        
        $out .= 'Config.sb_user_file = """' . $this->mkSbUserFile() . '"""' . "\n";
        if (!empty($this->attributes['internal:realm'][0])) {
           $out .= 'Config.user_realm = "' . $this->attributes['internal:realm'][0] . "\"\n";
        }
        if(!empty($this->attributes['internal:hint_userinput_suffix'][0]) && $this->attributes['internal:hint_userinput_suffix'][0] == 1) {
            $out .= "Config.hint_user_input = True\n";
        }
        if(!empty($this->attributes['internal:verify_userinput_suffix'][0]) && $this->attributes['internal:verify_userinput_suffix'][0] == 1) {
            $out .= "Config.verify_user_realm_input = True\n";
        }        
        return $out;
    }

    
    private function glueServerNames() {
        $serverList = $this->attributes['eap:server_name'];        
        if (!$serverList) {
            return '';
        }
        $A0 = array_reverse(explode('.', array_shift($serverList)));
        $B = $A0;
        foreach ($serverList as $oneServer) {
            $A = array_reverse(explode('.', $oneServer));
            $B = array_intersect_assoc($A0, $A);
            $A0 = $B;
        }
        return(implode('.', array_reverse($B)));
    }

    private function mkSupportContacts() {
        $url = (!empty($this->attributes['support:url'][0])) ? $this->attributes['support:url'][0] : $this->support_url_substitute;
        $email = (!empty($this->attributes['support:email'][0])) ? $this->attributes['support:email'][0] : $this->support_email_substitute;
        return(['url'=>$url, 'email'=>$email]);
    }   
    
    private function mkSubjectAltNameList() {
        $serverList = $this->attributes['eap:server_name'];
        if (!$serverList) {
            return '';
        }
        $out = '';
        foreach ($serverList as $oneServer) {
            if ($out) {
                $out .= ',';
            }
            $out .= "'DNS:$oneServer'";
        }
        return "[" . $out. "]";
    }

    
    private function mkSsidList() {
        $ssids = $this->attributes['internal:SSID'];
        $outArray = [];
        foreach ($ssids as $ssid => $cipher) {
            $outArray[] = "'$ssid'";
        }
        return '[' . implode(', ', $outArray) . ']';
    }
    
    private function mkDelSsidList() {
        $outArray = [];
        $delSSIDs = $this->attributes['internal:remove_SSID'];
        foreach ($delSSIDs as $ssid => $cipher) {
            if ($cipher == 'DEL') {
                $outArray[] = "'$ssid'";
            }
        }
        return '[' . implode(', ', $outArray) . ']';
    }
    
    private function mkCAfile(){
        $out = '';
        $cAlist = $this->attributes['internal:CAs'][0];
        foreach ($cAlist as $oneCa) {
            $out .= $oneCa['pem'] . "\n";
        }
        return $out;
    }
    
    private function mkIntro() {
        $out = _("This installer has been prepared for {0}") . '\n\n' . _("More information and comments:") . '\n\nEMAIL: {1}\nWWW: {2}\n\n' .
            _("Installer created with software from the GEANT project.") . "\"\n";
        return $out;
    }
    
    private function mkUserConsent() {
        $out = '';
        if (isset($this->attributes['support:info_file'])) {
            if ($this->attributes['internal:info_file'][0]['mime'] == 'txt') {
                $out = $this->attributes['support:info_file'][0];
            }
        }
        return $out;
    }
    
    private function mkProfileConfirmation() {
        if ($this->attributes['internal:profile_count'][0] > 1) {
            $out = _("This installer will only work properly if you are a member of {0} and the user group: {1}.");
        } else {
            $out = _("This installer will only work properly if you are a member of {0}.");
        }
        return $out;
    }
    

    
    private function mkSbUserFile() {
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            return chunk_split(base64_encode($this->clientCert["certdata"]), 64, "\n");
        }
        return "";
    }
    
}
