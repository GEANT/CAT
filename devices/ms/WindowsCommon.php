<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This file contains common functions needed by all Windows installers
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\ms;
use \Exception;

/**
 * This class defines common functions needed by all Windows installers
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */
class WindowsCommon extends \core\DeviceConfig {

    public function copyBasicFiles() {
        if (!($this->copyFile('wlan_test.exe') &&
                $this->copyFile('check_wired.cmd') &&
                $this->copyFile('install_wired.cmd') &&
                $this->copyFile('cat_bg.bmp') &&
                $this->copyFile('base64.nsh'))) {
            throw new Exception("Copying needed files (part 1) failed for at least one file!");
        }

        if (!($this->copyFile('cat32.ico') &&
                $this->copyFile('cat_150.bmp') &&
                $this->copyFile('WLANSetEAPUserData/WLANSetEAPUserData32.exe', 'WLANSetEAPUserData32.exe') &&
                $this->copyFile('WLANSetEAPUserData/WLANSetEAPUserData64.exe', 'WLANSetEAPUserData64.exe'))) {
            throw new Exception("Copying needed files (part 2) failed for at least one file!");
        }
        if (!$this->translateFile('common.inc', 'common.nsh', $this->codePage)) {
            throw new Exception("Translating needed file common.inc failed!");
        }
        return;
    }

    public function copyPwdFiles() {
        if (!($this->copyFile('Aruba_Networks_EAP-pwd_x32.msi') &&
                $this->copyFile('Aruba_Networks_EAP-pwd_x64.msi'))) {
            throw new Exception("Copying needed files (EAP-pwd) failed for at least one file!");
        }
        if (!$this->translateFile('pwd.inc', 'cat.NSI', $this->codePage)) {
            throw new Exception("Translating needed file pwd.inc failed!");
        }
    }

    public function copyGeantLinkFiles() {
        if (!($this->copyFile('GEANTLink/GEANTLink32.msi', 'GEANTLink32.msi') &&
                $this->copyFile('GEANTLink/GEANTLink64.msi', 'GEANTLink64.msi') &&
                $this->copyFile('GEANTLink/CredWrite.exe', 'CredWrite.exe') &&
                $this->copyFile('GEANTLink/MsiUseFeature.exe', 'MsiUseFeature.exe'))) {
            throw new Exception("Copying needed files (GEANTLink) failed for at least one file!");
        }
        if (!$this->translateFile('geant_link.inc', 'cat.NSI', $this->codePage)) {
            throw new Exception("Translating needed file geant_link.inc failed!");
        }
    }


    /**
     * function to escape double quotes in a special NSI-compatible way
     * 
     * @param string $in input string
     * @return string
     */
    public static function echo_nsi($in) {
        echo preg_replace('/"/', '$\"', $in);
    }

    /**
     * @param string $input input string
     * @return string
     */
    public static function sprint_nsi($input) {
        return preg_replace('/"/', '$\"', $input);
    }

    public function __construct() {
        parent::__construct();
        $this->useGeantLink = (isset($this->options['args']) && $this->options['args'] == 'gl' ) ? 1 : 0;
    }

    protected function prepareInstallerLang() {
        if (isset($this->LANGS[$this->languageInstance->getLang()])) {
            $language = $this->LANGS[$this->languageInstance->getLang()];
            $this->lang = $language['nsis'];
            $this->codePage = 'cp' . $language['cp'];
        } else {
            $this->lang = 'English';
            $this->codePage = 'cp1252';
        }
    }

    public function writeDeviceInfo() {
        $ssidCount = count($this->attributes['internal:SSID']);
        $out = "<p>";
        $out .= sprintf(_("%s installer will be in the form of an EXE file. It will configure %s on your device, by creating wireless network profiles.<p>When you click the download button, the installer will be saved by your browser. Copy it to the machine you want to configure and execute."), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $out .= "<p>";
        if ($ssidCount > 1) {
            if ($ssidCount > 2) {
                $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to the following networks:"), implode(', ', CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) . " ";
            } else {
                $out .= sprintf(_("In addition to <strong>%s</strong> the installer will also configure access to:"), implode(', ', CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) . " ";
            }
            $out .= '<strong>' . join ('</strong>, <strong>', array_diff(array_keys($this->attributes['internal:SSID']), CONFIG_CONFASSISTANT['CONSORTIUM']['ssid'])) . '</strong>';
            $out .= "<p>";
        }
// TODO - change this below
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TLS || $this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $out .= sprintf(_("In order to connect to the network you will need an a personal certificate in the form of a p12 file. You should obtain this certificate from your %s. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process."), $this->nomenclature_inst);
            return($out);
        }
        // not EAP-TLS
        $out .= sprintf(_("In order to connect to the network you will need an account from your %s. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated."), $this->nomenclature_inst);

        if (! $this->useGeantLink && ( $this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2 || $this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP )) {
            $out .= "<p>";
            $out .= _("When you are connecting to the network for the first time, Windows will pop up a login box, where you should enter your user name and password. This information will be saved so that you will reconnect to the network automatically each time you are in the range.");
            if ($ssidCount > 1) {
                $out .= "<p>";
                $out .= _("You will be required to enter the same credentials for each of the configured notworks:") . " ";
                $out .= '<strong>' . join ('</strong>, <strong>', array_keys($this->attributes['internal:SSID'])) . '</strong>';
            }
        }
        return($out);
    }    
    
    protected function combineLogo($logos, $fedLogo = NULL) {
        // maximum size to which we want to resize the logos
        $maxSize = 120;
        // $freeTop is set to how much vertical space we need to leave at the top
        // this will depend on the design of the background
        $freeTop = 70;
        // $freeBottom is set to how much vertical space we need to leave at the bottom
        // this will depend on the design of the background
        $freeBottom = 30;
        // $useFederationLogo controls if federation logos should be enabled
        $useFederationLogo = FALSE;
                
        $bgImage = new \Imagick('cat_bg.bmp');
        $bgImage->setFormat('BMP3');
        $bgImageSize = $bgImage->getImageGeometry();
        $freeHeight = $bgImageSize['height'] - $freeTop - $freeBottom;
        $idpLogoObject = new \Imagick($logos[0]['name']);
        $idpLogoSize = $idpLogoObject->getImageGeometry();
        $max = max($idpLogoSize);
        $this->loggerInstance->debug(5, "Logo size: ");
        $this->loggerInstance->debug(5, $idpLogoSize);
        $this->loggerInstance->debug(5, "max=$max\n");

// resize logo if necessary
        if ($max > $maxSize) {
            if ($max == $idpLogoSize['width']) {
                $idpLogoObject->scaleImage($maxSize, 0);
            } else {
                $idpLogoObject->scaleImage(0, $maxSize);
            }
            $idpLogoSize = $idpLogoObject->getImageGeometry();
            $this->loggerInstance->debug(5, "New logo size: ");
            $this->loggerInstance->debug(5, $idpLogoSize);
        }
        if ($useFederationLogo && $fedLogo != NULL) {
            $fedLogoObject = new \Imagick($fedLogo[0]['name']);
            $fedLogoSize = $fedLogoObject->getImageGeometry();
            $fedMax = max($fedLogoSize);
            $this->loggerInstance->debug(5, "Fed Logo size: ");
            $this->loggerInstance->debug(5, $fedLogoSize);
            $this->loggerInstance->debug(5, "max=$fedMax\n");
            if ($fedLogoSize['width'] > $maxSize) {
                $fedLogoObject->scaleimage($maxSize, 0);
                $fedLogoSize = $fedLogoObject->getImageGeometry();
            }
            $hoffset = round(($bgImageSize['width'] - $fedLogoSize['width']) / 2);
            $freeSpace = round(($freeHeight - $fedLogoSize['height'] - $idpLogoSize['height']) / 3);
            $voffset = $freeSpace + $freeTop;
            $bgImage->compositeImage($fedLogoObject, $fedLogoObject->getImageCompose(), $hoffset, $voffset);
            $voffset = 2 * $freeSpace + $fedLogoSize['height'] + $freeTop;
        } else {
            $voffset = round(($freeHeight - $idpLogoSize['height']) / 2) + $freeTop;
        }
        $hoffset = round(($bgImageSize['width'] - $idpLogoSize['width']) / 2);
        $bgImage->compositeImage($idpLogoObject, $idpLogoObject->getImageCompose(), $hoffset, $voffset);    

//new image is saved as the background
        $bgImage->writeImage('BMP3:cat_bg.bmp');
    }

    protected function signInstaller() {
        $fileName = $this->installerBasename . '.exe';
        if (!$this->sign) {
            rename("installer.exe", $fileName);
            return $fileName;
        }
        // are actually signing
        $outputFromSigning = system($this->sign . " installer.exe '$fileName' > /dev/null");
        if ($outputFromSigning === FALSE) {
            $this->loggerInstance->debug(2, "Signing the WindowsCommon installer $fileName FAILED!\n");
        }
        return $fileName;
    }

    protected function compileNSIS() {
        if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
            $makensis = CONFIG_CONFASSISTANT['PATHS']['makensis'] . " -INPUTCHARSET UTF8";
        } else {
            $makensis = CONFIG_CONFASSISTANT['PATHS']['makensis'];
        }
        $command = $makensis . ' -V4 cat.NSI > nsis.log';
        system($command);
        $this->loggerInstance->debug(4, "compileNSIS:$command\n");
    }

    protected function msInfoFile($attr) {
        $out = '';
        if (isset($attr['support:info_file'])) {
            $out .= '!define EXTERNAL_INFO "';
//  $this->loggerInstance->debug(4,"Info file type ".$attr['support:info_file'][0]['mime']."\n");
            if ($attr['internal:info_file'][0]['mime'] == 'rtf') {
                $out = '!define LICENSE_FILE "' . $attr['internal:info_file'][0]['name'];
            } elseif ($attr['internal:info_file'][0]['mime'] == 'txt') {
                $infoFile = file_get_contents($attr['internal:info_file'][0]['name']);
                if (CONFIG_CONFASSISTANT['NSIS_VERSION'] >= 3) {
                    $infoFileConverted = $infoFile;
                } else {
                    $infoFileConverted = iconv('UTF-8', $this->codePage . '//TRANSLIT', $infoFile);
                }
                if ($infoFileConverted) {
                    file_put_contents('info_f.txt', $infoFileConverted);
                    $out = '!define LICENSE_FILE " info_f.txt';
                }
            } else {
                $out = '!define EXTERNAL_INFO "' . $attr['internal:info_file'][0]['name'];
            }

            $out .= "\"\n";
        }
        $this->loggerInstance->debug(4, "Info file returned: $out");
        return $out;
    }

    protected function writeAdditionalDeletes($profiles) {
        if (count($profiles) == 0) {
            return;
        }
        $fileHandle = fopen('profiles.nsh', 'a');
        fwrite($fileHandle, "!define AdditionalDeletes\n");
        foreach ($profiles as $profile) {
            fwrite($fileHandle, "!insertmacro define_delete_profile \"$profile\"\n");
        }
        fclose($fileHandle);
    }

    protected function writeClientP12File() {
        if (!is_array($this->clientCert)) {
            throw new Exception("the client block was called but there is no client certificate!");
        }
        $fileHandle = fopen('SB_cert.p12', 'w');
        fwrite($fileHandle, $this->clientCert["certdata"]);
        fclose($fileHandle);
    }

    protected function writeTlsUserProfile() {
        
    }

    public $LANGS = [
        'fr' => ['nsis' => "French", 'cp' => '1252'],
        'de' => ['nsis' => "German", 'cp' => '1252'],
        'es' => ['nsis' => "SpanishInternational", 'cp' => '1252'],
        'it' => ['nsis' => "Italian", 'cp' => '1252'],
        'nl' => ['nsis' => "Dutch", 'cp' => '1252'],
        'sv' => ['nsis' => "Swedish", 'cp' => '1252'],
        'fi' => ['nsis' => "Finnish", 'cp' => '1252'],
        'pl' => ['nsis' => "Polish", 'cp' => '1250'],
        'ca' => ['nsis' => "Catalan", 'cp' => '1252'],
        'sr' => ['nsis' => "SerbianLatin", 'cp' => '1250'],
        'hr' => ['nsis' => "Croatian", 'cp' => '1250'],
        'sl' => ['nsis' => "Slovenian", 'cp' => '1250'],
        'da' => ['nsis' => "Danish", 'cp' => '1252'],
        'nb' => ['nsis' => "Norwegian", 'cp' => '1252'],
        'nn' => ['nsis' => "NorwegianNynorsk", 'cp' => '1252'],
        'el' => ['nsis' => "Greek", 'cp' => '1253'],
        'ru' => ['nsis' => "Russian", 'cp' => '1251'],
        'pt' => ['nsis' => "Portuguese", 'cp' => '1252'],
        'uk' => ['nsis' => "Ukrainian", 'cp' => '1251'],
        'cs' => ['nsis' => "Czech", 'cp' => '1250'],
        'sk' => ['nsis' => "Slovak", 'cp' => '1250'],
        'bg' => ['nsis' => "Bulgarian", 'cp' => '1251'],
        'hu' => ['nsis' => "Hungarian", 'cp' => '1250'],
        'ro' => ['nsis' => "Romanian", 'cp' => '1250'],
        'lv' => ['nsis' => "Latvian", 'cp' => '1257'],
        'mk' => ['nsis' => "Macedonian", 'cp' => '1251'],
        'et' => ['nsis' => "Estonian", 'cp' => '1257'],
        'tr' => ['nsis' => "Turkish", 'cp' => '1254'],
        'lt' => ['nsis' => "Lithuanian", 'cp' => '1257'],
        'ar' => ['nsis' => "Arabic", 'cp' => '1256'],
        'he' => ['nsis' => "Hebrew", 'cp' => '1255'],
        'id' => ['nsis' => "Indonesian", 'cp' => '1252'],
        'mn' => ['nsis' => "Mongolian", 'cp' => '1251'],
        'sq' => ['nsis' => "Albanian", 'cp' => '1252'],
        'br' => ['nsis' => "Breton", 'cp' => '1252'],
        'be' => ['nsis' => "Belarusian", 'cp' => '1251'],
        'is' => ['nsis' => "Icelandic", 'cp' => '1252'],
        'ms' => ['nsis' => "Malay", 'cp' => '1252'],
        'bs' => ['nsis' => "Bosnian", 'cp' => '1250'],
        'ga' => ['nsis' => "Irish", 'cp' => '1250'],
        'uz' => ['nsis' => "Uzbek", 'cp' => '1251'],
        'gl' => ['nsis' => "Galician", 'cp' => '1252'],
        'af' => ['nsis' => "Afrikaans", 'cp' => '1252'],
        'ast' => ['nsis' => "Asturian", 'cp' => '1252'],
    ];
    public $codePage;
    public $lang;
    public $useGeantLink;

}
