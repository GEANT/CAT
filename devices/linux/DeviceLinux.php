<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * This file creates Linux installers
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
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
class DeviceLinux extends \core\DeviceConfig {

    /**
     * constructor. Sets supported EAP methods.
     */
    final public function __construct() {
        parent::__construct();
        $this->setSupportedEapMethods([\core\common\EAP::EAPTYPE_PEAP_MSCHAP2, \core\common\EAP::EAPTYPE_TTLS_PAP, \core\common\EAP::EAPTYPE_TTLS_MSCHAP2, \core\common\EAP::EAPTYPE_TLS, \core\common\EAP::EAPTYPE_SILVERBULLET]);
        $this->specialities['media:openroaming'] = _("This device does not support provisioning of OpenRoaming.");
        $this->specialities['media:consortium_OI'] = _("This device does not support provisioning of Passpoint networks.");

    }

    /**
     * create the actual installer script
     * 
     * @return string filename of the generated installer
     * @throws Exception
     *
     */
    public function writeInstaller() {
        $installerPath = $this->installerBasename.".py";
        $this->copyFile("main.py", $installerPath);
        $installer = fopen($installerPath,"a");
        if ($installer === FALSE) {
            throw new Exception("Unable to open installer file for writing!");
        }
        fwrite($installer, "\n\n");
        $this->writeMessages($installer);
        $this->writeConfigVars($installer);
        fwrite($installer, "\n\n");
        fwrite($installer, "if __name__ == '__main__':\n");
        fwrite($installer, "    run_installer()\n");
        fclose($installer);
        return($installerPath);
    }
    
    /**
     * produces the HTML text to be displayed when clicking on the "help" button
     * besides the download button.
     * 
     * @return string
     */
    public function writeDeviceInfo() {
        \core\common\Entity::intoThePotatoes();
        $out = sprintf(_("The installer is in the form of a Python script. It will try to configure %s under NetworkManager and if this is either not appropriate for your system or your version of NetworkManager is too old, a wpa_supplicant config file will be created instead."), \config\ConfAssistant::CONSORTIUM['display_name']);
        $out .= "<p>"._("The installer will configure access to:")." <strong>";
        $out .= implode('</strong>, <strong>', array_keys($this->attributes['internal:networks']));
        $out .= '</strong><p>';

        $out .= _("The installer will create cat_installer sub-directory in your config directory (possibly the .config in your home directory) and will copy your server certificates there.");
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS) {
            $out .= _("In order to connect to the network you will need a personal certificate in the form of a p12 file. You should obtain this certificate from your organisation. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process. Your p12 file will also be copied to the cat_installer directory.");
        } elseif ($this->selectedEap != \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $out .= _("In order to connect to the network you will need an account from your organisation. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
            $out .= "<p>";
            $out .= _("You will be requested to enter your account credentials during the installation. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
        }
        // nothing to say if we are doing silverbullet.
        $out .= "<p>";
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }
    
    /**
     * writes a line of Python code into the installer script
     * 
     * @param resource $file   the file handle
     * @param string   $prefix prefix to write
     * @param string   $name   config item to write
     * @param string   $text   text to write
     * @return void
     */
    private function writeConfigLine($file, $prefix, $name, $text) {
        $out = $prefix.$name.' = "'.$text;
        fwrite($file, wordwrap($out, 70, " \" \\\n    \"")."\n");
    }
    
    /**
     * localises the user messages and writes them into the file
     * 
     * @param resource $file the file resource of the installer script
     * @return void
     */
    private function writeMessages($file) {
        \core\common\Entity::intoThePotatoes();
        $messages = [
        'quit'=> _("Really quit?"),
        'username_prompt'=> _("enter your userid"),
        'enter_password' => _("enter password"),
        'enter_import_password' => _("enter your import password"),
        'incorrect_password' => _("incorrect password"),
        'repeat_password' => _("repeat your password"),
        'passwords_differ'=> _("passwords do not match"),
        'installation_finished' => _("Installation successful"),
        'cat_dir_exisits' => _("Directory {} exists; some of its files may be overwritten."),
        'cont' => _("Continue?"),
        'nm_not_supported' => _("This NetworkManager version is not supported"),
        'cert_error' => _("Certificate file not found, looks like a CAT error"),
        'unknown_version' => _("Unknown version"),
        'dbus_error' => _("DBus connection problem, a sudo might help"),
        'yes' => _("Y"),
        'no' => _("N"),
        'p12_filter' => _("personal certificate file (p12 or pfx)"),
        'all_filter' => _("All files"),
        'p12_title' => _("personal certificate file (p12 or pfx)"),
        'save_wpa_conf' => _("NetworkManager configuration failed, but we may generate a wpa_supplicant configuration file if you wish. Be warned that your connection password will be saved in this file as clear text."),
        'save_wpa_confirm' => _("Write the file"),
        'wrongUsernameFormat' =>_("Error: Your username must be of the form 'xxx@institutionID' e.g. 'john@example.net'!"),
        'wrong_realm' => _("Error: your username must be in the form of 'xxx@{}'. Please enter the username in the correct format."),
        'wrong_realm_suffix' => _("Error: your username must be in the form of 'xxx@institutionID' and end with '{}'. Please enter the username in the correct format."),
        'user_cert_missing' => _("personal certificate file not found"),
        ];
        foreach ($messages as $name => $value) {
            $this->writeConfigLine($file, 'Messages.', $name, $value.'"');
        }
        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * writes configuration variables into the installer script
     * 
     * @param resource $file the file handle
     * @return void
     */
    private function writeConfigVars($file) {
        $eapMethod = \core\common\EAP::eapDisplayName($this->selectedEap);
        $contacts = $this->mkSupportContacts();
        $tou = $this->mkUserConsent();
        $outerId = $this->determineOuterIdString();
        $config = [
            'instname' => $this->attributes['general:instname'][0],
            'profilename' => $this->attributes['profile:name'][0],
            'url' => $contacts['url'],
            'email' => $contacts['email'],
            'title' => "eduroam CAT",
            'server_match' => $this->glueServerNames(),
            'eap_outer' => $eapMethod['OUTER'],
            'eap_inner' => $eapMethod['INNER'],
            'init_info' => $this->mkIntro(),
            'init_confirmation' => $this->mkProfileConfirmation(),
//            'sb_user_file' => $this->mkSbUserFile(),
        ];
        
        $configRaw = [
            'ssids' => $this->mkSsidList(),
            'del_ssids' => $this->mkDelSsidList(),
            'servers' => $this->mkSubjectAltNameList(),
        ];
            
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS && isset($this->attributes['eap-specific:tls_use_other_id']) && $this->attributes['eap-specific:tls_use_other_id'][0] == 'on') {
            $configRaw['use_other_tls_id'] = "True";
        }
        else {
            $configRaw['use_other_tls_id'] = "False";
        }

        if ($outerId !== NULL) {
            $configRaw['anonymous_identity'] = '"'.$outerId.'"';
        }

        if (!empty($this->attributes['internal:realm'][0])) {
           $config['user_realm'] = $this->attributes['internal:realm'][0];
        }
        
        if(!empty($this->attributes['internal:hint_userinput_suffix'][0]) && $this->attributes['internal:hint_userinput_suffix'][0] == 1) {
            $configRaw['hint_user_input'] = "True";
        }
        
        if(!empty($this->attributes['internal:verify_userinput_suffix'][0]) && $this->attributes['internal:verify_userinput_suffix'][0] == 1) {
            $configRaw['verify_user_realm_input'] = "True";
        }
        
        foreach ($config as $name => $value) {
            $this->writeConfigLine($file, 'Config.', $name, $value.'"');
        }
        
        foreach ($configRaw as $name => $value) {
            fwrite($file, 'Config.'.$name.' = '.$value."\n");
        }
        
        if ($tou === '') {
            fwrite($file, 'Config.tou = ""'."\n");
        } else {
            fwrite($file, 'Config.tou = """'.$tou.'"""'."\n");
        }
        
        fwrite($file, 'Config.CA = """'.$this->mkCAfile().'"""'."\n");
        $sbUserFile = $this->mkSbUserFile();
        if ($sbUserFile !== '') {
            fwrite($file, 'Config.sb_user_file = """'.$sbUserFile.'"""'."\n");
        }
    }

    /**
     * coerces the list of EAP server names into a single string
     * 
     * @return string
     */
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
        return implode('.', array_reverse($B));
    }

    /**
     * generates the list of support contacts
     * 
     * @return array
     */
    private function mkSupportContacts() {
        $url = (!empty($this->attributes['support:url'][0])) ? $this->attributes['support:url'][0] : $this->support_url_substitute;
        $email = (!empty($this->attributes['support:email'][0])) ? $this->attributes['support:email'][0] : $this->support_email_substitute;
        return ['url'=>$url, 'email'=>$email];
    }   
    
    /**
     * generates the list of subjectAltNames to configure
     * 
     * @return string
     */
    private function mkSubjectAltNameList() {
        $serverList = $this->attributes['eap:server_name'];
        if (!$serverList) {
            return '';
        }
        $out = '';
        foreach ($serverList as $oneServer) {
            if ($out) {
                $out .= ', ';
            }
            $out .= "'DNS:$oneServer'";
        }
        return "[".$out. "]";
    }

    /**
     * generates the list of SSIDs to configure
     * 
     * @return string
     */
    private function mkSsidList() {
        $networks = $this->attributes['internal:networks'];
        $outArray = [];
        foreach ($networks as $network => $networkDetails) {
            if (!empty($networkDetails['ssid'])) {
                $outArray = array_merge($outArray, $networkDetails['ssid']);
            }
        }
        return "['".implode("', '", $outArray)."']";
    }
    
    /**
     * generates the list of SSIDs to delete from the system
     * 
     * @return string
     */
    private function mkDelSsidList() {
        $outArray = [];
        $delSSIDs = $this->attributes['internal:remove_SSID'];
        foreach ($delSSIDs as $ssid => $cipher) {
            if ($cipher == 'DEL') {
                $outArray[] = "'$ssid'";
            }
        }
        return '['.implode(', ', $outArray).']';
    }
    
    /**
     * creates a blob containing all CA certificates
     * 
     * @return string
     */
    private function mkCAfile(){
        $out = '';
        $cAlist = $this->attributes['internal:CAs'][0];
        foreach ($cAlist as $oneCa) {
            $out .= $oneCa['pem'];
        }
        return $out;
    }
    
    /**
     * generates the welcome text
     * 
     * @return string
     */
    private function mkIntro() {
        \core\common\Entity::intoThePotatoes();
        $out = _("This installer has been prepared for {0}").'\n\n'._("More information and comments:").'\n\nEMAIL: {1}\nWWW: {2}\n\n' .
            _("Installer created with software from the GEANT project.");
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }
    
    /**
     * generates text for the user consent dialog box, if any
     * 
     * @return string
     */
    private function mkUserConsent() {
        $out = '';
        if (isset($this->attributes['support:info_file'])) {
            if ($this->attributes['internal:info_file'][0]['mime'] == 'txt') {
                $out = $this->attributes['support:info_file'][0];
            }
        }
        return $out;
    }
    
    /**
     * generates the warning that the account will only work for inst members
     * 
     * @return string
     */
    private function mkProfileConfirmation() {
        \core\common\Entity::intoThePotatoes();
        if ($this->attributes['internal:profile_count'][0] > 1) {
            $out = _("This installer will only work properly if you are a member of {0} and the user group: {1}.");
        } else {
            $out = _("This installer will only work properly if you are a member of {0}.");
        }
        \core\common\Entity::outOfThePotatoes();
        return $out;
    }
    

    /**
     * generates the client certificate data for Silberbullet installers
     * 
     * @return string
     */
    private function mkSbUserFile() {
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            return chunk_split(base64_encode($this->clientCert["certdata"]), 64, "\n");
        }
        return "";
    }
    
}
