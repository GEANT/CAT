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
abstract class WindowsCommon extends \core\DeviceConfig
{

    /**
     * copies various common files into temp dir for inclusion into installers
     * 
     * @return void
     * @throws Exception
     */
    public function copyBasicFiles()
    {
        if (!($this->copyFile('wlan_test.exe') &&
                $this->copyFile('check_wired.cmd') &&
                $this->copyFile('install_wired.cmd') &&
                $this->copyFile('cat_bg.bmp') &&
                $this->copyFile('base64.nsh'))) {
            throw new Exception("Copying needed files (part 1) failed for at least one file!");
        }

        if (!($this->copyFile('cat32.ico') &&
                $this->copyFile('cat_150.bmp') &&
                $this->copyFile('WLANSetEAPUserData/WLANSetEAPUserData32.exe', 'WLANSetEAPUserDatax86.exe') &&
                $this->copyFile('WLANSetEAPUserData/WLANSetEAPUserData64.exe', 'WLANSetEAPUserDatax64.exe'))) {
            throw new Exception("Copying needed files (part 2) failed for at least one file!");
        }
        if (!$this->translateFile('common.inc', 'common.nsh')) {
            throw new Exception("Translating needed file common.inc failed!");
        }
        return;
    }

    /**
     *  Copy a file from the module location to the temporary directory aplying transcoding.
     *
     * Transcoding is only required for Windows installers, and no Unicode support
     * in NSIS (NSIS version below 3)
     * Trancoding is only applied if the third optional parameter is set and nonzero
     * If CONFIG['NSIS']_VERSION is set to 3 or more, no transcoding will be applied
     * regardless of the third parameter value.
     * If the second argument is provided and is not equal to 0, then the file will be
     * saved under the name taken from this argument.
     * If only one parameter is given or the second is equal to 0, source and destination
     * filenames are the same.
     * The third optional parameter, if nonzero, should be the character set understood by iconv
     * This is required by the Windows installer and is expected to go away in the future.
     * Source file can be located either in the Files subdirectory or in the sibdirectory of Files
     * named the same as device_id. The second option takes precedence.
     *
     * @param string $source_name The source file name
     * @param string $output_name The destination file name
     * @return boolean
     * @final not to be redefined
     */
    final protected function translateFile($source_name, $output_name = NULL)
    {
        // there is no explicit gettext() call in this function, but catalogues
        // and translations occur in the varios ".inc" files - so make sure we
        // operate in the correct catalogue
        \core\common\Entity::intoThePotatoes();
        if ($output_name === NULL) {
            $output_name = $source_name;
        }

        $this->loggerInstance->debug(5, "translateFile($source_name, $output_name)\n");
        ob_start();
        $this->loggerInstance->debug(5, $this->module_path . '/Files/' . $this->device_id . '/' . $source_name . "\n");
        $source = $this->findSourceFile($source_name);

        if ($source !== false) { // if there is no file found, don't attempt to include an uninitialised variable
            include $source;
        }
        $output = ob_get_clean();
        $fileHandle = fopen("$output_name", "w");
        if ($fileHandle === false) {
            $this->loggerInstance->debug(2, "translateFile($source, $output_name) failed\n");
            \core\common\Entity::outOfThePotatoes();
            return false;
        }
        fwrite($fileHandle, $output);
        fclose($fileHandle);
        $this->loggerInstance->debug(5, "translateFile($source, $output_name) end\n");
        \core\common\Entity::outOfThePotatoes();
        return true;
    }

    /**
     * Transcode a string adding double quotes escaping
     *
     * Transcoding is only required for Windows installers, and no Unicode support
     * in NSIS (NSIS version below 3)
     * Trancoding is only applied if the third optional parameter is set and nonzero
     * If CONFIG['NSIS']_VERSION is set to 3 or more, no transcoding will be applied
     * regardless of the second parameter value.
     * The second optional parameter, if nonzero, should be the character set understood by iconv
     * This is required by the Windows installer and is expected to go away in the future.
     *
     * @param string $source_string The source string
     * @param int    $encoding      Set Windows charset if non-zero
     * @return string
     * @final not to be redefined
     */
    final protected function translateString($source_string)
    {
        $this->loggerInstance->debug(5, "translateString input: \"$source_string\"\n");
        if (empty($source_string)) {
            return $source_string;
        }

        $output_c = $source_string;
        $source_string = str_replace('"', '$\\"', $output_c);

        return $source_string;
    }


    /**
     * copies GEANTlink files into temp dir for later inclusion into installers
     * 
     * @return void
     * @throws Exception
     */
    public function copyGeantLinkFiles()
    {
        if (!($this->copyFile('GEANTLink/GEANTLink-x86.msi', 'GEANTLink-x86.msi') &&
                $this->copyFile('GEANTLink/GEANTLink-x64.msi', 'GEANTLink-x64.msi') &&
                $this->copyFile('GEANTLink/GEANTLink-ARM64.msi', 'GEANTLink-ARM64.msi') &&
                $this->copyFile('GEANTLink/CredWrite.exe', 'CredWrite.exe') &&
                $this->copyFile('GEANTLink/MsiUseFeature.exe', 'MsiUseFeature.exe'))) {
            throw new Exception("Copying needed files (GEANTLink) failed for at least one file!");
        }
        if (!$this->translateFile('geant_link.inc', 'cat.NSI')) {
            throw new Exception("Translating needed file geant_link.inc failed!");
        }
    }

    /**
     * function to escape double quotes in a special NSI-compatible way
     * 
     * @param string $in input string
     * @return string
     */
    public static function echoNsis($in)
    {
        echo preg_replace('/"/', '$\"', $in);
    }

    /**
     * @param string $input input string
     * @return string
     */
    public static function sprintNsis($input)
    {
        return preg_replace('/"/', '$\"', $input);
    }

    /**
     * determine Windows language settings based on requested installer language
     * 
     * @return void
     */
    protected function prepareInstallerLang()
    {
        if (isset($this->LANGS[$this->languageInstance->getLang()])) {
            $this->lang = $this->LANGS[$this->languageInstance->getLang()];
        } else {
            $this->lang = 'English';
        }
    }

    /**
     * creates HTML code which will be displayed when the "info" button is pressed
     * 
     * @return string the HTML code
     */
    public function writeDeviceInfo()
    {
        $networkList = [];
        foreach (array_keys($this->getAttribute('internal:networks')) as $networkName) {
            $networkList[] = $networkName;
        }
        $configNetworkList = [];
        foreach (array_keys(\config\ConfAssistant::CONSORTIUM['networks']) as $networkName) {
            $configNetworkList[] = $networkName;
        }

        $configCount = count($configNetworkList);
        $networksCount = count($networkList);
        $out = "<p>";
        $out .= "$networksCount:";
        $out .= sprintf(_("%s installer will be in the form of an EXE file. It will configure %s on your device, by creating wireless network profiles.<p>When you click the download button, the installer will be saved by your browser. Copy it to the machine you want to configure and execute."), \config\ConfAssistant::CONSORTIUM['display_name'], \config\ConfAssistant::CONSORTIUM['display_name']);
        $out .= "<p>";
        if ($networksCount > $configCount) {
            $out .= sprintf(ngettext("In addition to <strong>%s</strong> the installer will also configure access to:", "In addition to <strong>%s</strong> the installer will also configure access to the following networks:", $networksCount - $configCount), implode(', ', $configNetworkList)) . " ";
            $out .= '<strong>' . join('</strong>, <strong>', array_diff($networkList, $configNetworkList)) . '</strong>';
            $out .= "<p>";
        }
// TODO - change this below
        if ($this->selectedEapObject->isClientCertRequired()) {
            $out .= _("In order to connect to the network you will need an a personal certificate in the form of a p12 file. You should obtain this certificate from your organisation. Consult the support page to find out how this certificate can be obtained. Such certificate files are password protected. You should have both the file and the password available during the installation process.");
            return $out;
        }
        // not EAP-TLS
        $out .= _("In order to connect to the network you will need an account from your organisation. You should consult the support page to find out how this account can be obtained. It is very likely that your account is already activated.");
        return $out;
    }

    /**
     * scales a logo to the desired size
     * @param string $imagePath path to the image
     * @param int    $maxSize   maximum size of output image (larger axis counts)
     * @return \Imagick|\GMagick *Magick image object
     */
    private function scaleLogo($imagePath, $maxSize)
    {
        if (class_exists('\\Gmagick')) { 
            $imageObject = new \Gmagick($imagePath); 
        } else {
            $imageObject = new \Imagick($imagePath);
        }
        $imageSize = $imageObject->getImageGeometry();
        $imageMax = max($imageSize);
        $this->loggerInstance->debug(5, "Logo size: ");
        $this->loggerInstance->debug(5, $imageSize);
        $this->loggerInstance->debug(5, "max=$imageMax\n");
// resize logo if necessary
        if ($imageMax > $maxSize) {
            if ($imageMax == $imageSize['width']) {
                $imageObject->scaleImage($maxSize, 0);
            } else {
                $imageObject->scaleImage(0, $maxSize);
            }
        }
        $imageSize = $imageObject->getImageGeometry();
        $this->background['freeHeight'] -= $imageSize['height'];
        return($imageObject);
    }

    /**
     * combines the inst and federation logo into one image and writes to file
     * 
     * @param array $logos   inst logo meta info
     * @param array $fedLogo fed logo meta info
     * @return void
     */
    protected function combineLogo($logos = NULL, $fedLogo = NULL)
    {
        // maximum size to which we want to resize the logos

        $maxSize = 120;
        // $freeTop is set to how much vertical space we need to leave at the top
        // this will depend on the design of the background
        $freeTop = 70;
        // $freeBottom is set to how much vertical space we need to leave at the bottom
        // this will depend on the design of the background
        // we are prefixig the paths with getcwd() wich migh appear unnecessary
        // but under some conditions appeared to be required
        $freeBottom = 30;
        if (class_exists('\\Gmagick')) { 
            $bgImage = new \Gmagick(getcwd().'/cat_bg.bmp');
        } else {
            $bgImage = new \Imagick(getcwd().'/cat_bg.bmp');
        }
        $bgImage->setFormat('BMP3');
        $bgImageSize = $bgImage->getImageGeometry();
        $logosToPlace = [];
        $this->background = [];
        $this->background['freeHeight'] = $bgImageSize['height'] - $freeTop - $freeBottom;

        if ($this->getAttribute('fed:include_logo_installers') === NULL) {
            $fedLogo = NULL;
        }
        if ($fedLogo != NULL) {
            $logosToPlace[] = $this->scaleLogo(getcwd()."/".$fedLogo[0]['name'], $maxSize);
        }
        if ($logos != NULL) {
            $logosToPlace[] = $this->scaleLogo(getcwd()."/".$logos[0]['name'], $maxSize);
        }

        $logoCount = count($logosToPlace);
        if ($logoCount > 0) {
            $voffset = $freeTop;
            $freeSpace = (int) round($this->background['freeHeight'] / ($logoCount + 1));
            foreach ($logosToPlace as $logo) {
                $voffset += $freeSpace;
                $logoSize = $logo->getImageGeometry();
                $hoffset = (int) round(($bgImageSize['width'] - $logoSize['width']) / 2);
                $bgImage->compositeImage($logo, $logo->getImageCompose(), $hoffset, $voffset);
                $voffset += $logoSize['height'];
            }
        }
//new image is saved as the background
        $bgImage->writeImage('BMP3:'.getcwd().'/cat_bg.bmp');
    }

    /**
     * adds a digital signature to the installer, and returns path to file
     * 
     * @return string path to signed installer
     */
    protected function signInstaller()
    {
        $fileName = $this->installerBasename . '.exe';
        if (!$this->sign) {
            rename("installer.exe", $fileName);
            return $fileName;
        }
        // are actually signing
        $outputFromSigning = system($this->sign . " installer.exe '$fileName' > /dev/null");
        if ($outputFromSigning === false) {
            $this->loggerInstance->debug(2, "Signing the WindowsCommon installer $fileName FAILED!\n");
        }
        return $fileName;
    }

    /**
     * creates one single installer .exe out of the NSH inputs and other files
     * 
     * @return void
     */
    protected function compileNSIS()
    {
        $makensis = \config\ConfAssistant::PATHS['makensis'] . " -INPUTCHARSET UTF8";
        $lcAll = getenv("LC_ALL");
        putenv("LC_ALL=en_US.UTF-8");
        $command = $makensis . ' -V4 cat.NSI > nsis.log 2>&1';
        system($command);
        putenv("LC_ALL=" . $lcAll);
        $this->loggerInstance->debug(4, "compileNSIS:$command\n");
    }

    /**
     * find out where the user can get support
     * 
     * @param array  $attr list of profile attributes
     * @param string $type which type of support resource to we want
     * @return string NSH line with the resulting !define
     */
    private function getSupport($attr, $type)
    {
        $supportString = [
            'email' => 'SUPPORT',
            'url' => 'URL',
        ];
        $s = "support_" . $type . "_substitute";
        $substitute = $this->translateString($this->$s);
        $returnValue = !empty($attr['support:' . $type][0]) ? $attr['support:' . $type][0] : $substitute;
        return '!define ' . $supportString[$type] . ' "' . $returnValue . '"' . "\n";
    }

    /**
     * returns various NSH !define statements for later inclusion into main file
     * 
     * @param array $attr profile attributes
     * @return string
     */
    protected function writeNsisDefines($attr)
    {
        $fcontents = '';
        if ($attr['internal:profile_count'][0] > 1) {
            $fcontents .= "\n" . '!define USER_GROUP "' . $this->translateString(str_replace('"', '$\\"', $attr['profile:name'][0])) . '"
';
        }
        $fcontents .= '
Caption "' . $this->translateString(sprintf(WindowsCommon::sprintNsis(_("%s installer for %s")), \config\ConfAssistant::CONSORTIUM['display_name'], $attr['general:instname'][0])) . '"
!define APPLICATION "' . $this->translateString(sprintf(WindowsCommon::sprintNsis(_("%s installer for %s")), \config\ConfAssistant::CONSORTIUM['display_name'], $attr['general:instname'][0])) . '"
!define VERSION "' . \core\CAT::VERSION_MAJOR . '.' . \core\CAT::VERSION_MINOR . '"
!define INSTALLER_NAME "installer.exe"
!define LANG "' . $this->lang . '"
!define LOCALE "' . preg_replace('/\..*$/', '', \config\Master::LANGUAGES[$this->languageInstance->getLang()]['locale']) . '"
;--------------------------------
!define ORGANISATION "' . $this->translateString($attr['general:instname'][0]) . '"
';
        $fcontents .= $this->getSupport($attr, 'email');
        $fcontents .= $this->getSupport($attr, 'url');
        if (\core\common\Entity::getAttributeValue($attr, 'media:wired', 0) == 'on') {
            $fcontents .= '!define WIRED
        ';
        }
        $fcontents .= '!define PROVIDERID "urn:UUID:' . $this->deviceUUID . '"
';
        if (!empty($attr['internal:realm'][0])) {
            $fcontents .= '!define REALM "' . $attr['internal:realm'][0] . '"
';
        }
        if (!empty($attr['internal:hint_userinput_suffix'][0]) && $attr['internal:hint_userinput_suffix'][0] == 1) {
            $fcontents .= '!define HINT_USER_INPUT "' . $attr['internal:hint_userinput_suffix'][0] . '"
';
        }
        if (!empty($attr['internal:verify_userinput_suffix'][0]) && $attr['internal:verify_userinput_suffix'][0] == 1) {
            $fcontents .= '!define VERIFY_USER_REALM_INPUT "' . $attr['internal:verify_userinput_suffix'][0] . '"
';
        }
        $fcontents .= $this->msInfoFile($attr);
        return $fcontents;
    }

    /**
     * includes NSH commands displaying terms of use file into installer, if any
     * 
     * @param array $attr profile attributes
     * @return string NSH commands
     * @throws Exception
     */
    protected function msInfoFile($attr)
    {
        $out = '';
        if (isset($attr['support:info_file'])) {
            $out .= '!define EXTERNAL_INFO "';
//  $this->loggerInstance->debug(4,"Info file type ".$attr['support:info_file'][0]['mime']."\n");
            if ($attr['internal:info_file'][0]['mime'] == 'rtf') {
                $out = '!define LICENSE_FILE "' . $attr['internal:info_file'][0]['name'];
            } elseif ($attr['internal:info_file'][0]['mime'] == 'txt') {
                $infoFile = file_get_contents($attr['internal:info_file'][0]['name']);
                if ($infoFile === false) {
                    throw new Exception("We were told this file exists. Failing to read it is not really possible.");
                }
                $infoFileConverted = $infoFile;

                if ($infoFileConverted !== false && strlen($infoFileConverted) > 0) {
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

    /**
     * writes commands to delete SSIDs, if any, into a file
     * 
     * @param array $profiles WLAN profiles to delete
     * @return void
     * @throws Exception
     */
    protected function writeAdditionalDeletes($profiles)
    {
        if (count($profiles) == 0) {
            return;
        }
        $fileHandle = fopen('profiles.nsh', 'a');
        if ($fileHandle === false) {
            throw new Exception("Unable to open possibly pre-existing profiles.nsh to append additional deletes.");
        }
        fwrite($fileHandle, "!define AdditionalDeletes\n");
        foreach ($profiles as $profile) {
            fwrite($fileHandle, "!insertmacro define_delete_profile \"$profile\"\n");
        }
        fclose($fileHandle);
    }

    /**
     * writes client certificate into file
     * 
     * @return void
     * @throws Exception
     */
    protected function writeClientP12File()
    {
        if (!is_array($this->clientCert)) {
            throw new Exception("the client block was called but there is no client certificate!");
        }
        file_put_contents('SB_cert.p12', $this->clientCert["certdata"]);
    }

    public $LANGS = [
        'fr' => "French",
        'de' => "German",
        'es' => "SpanishInternational",
        'it' => "Italian",
        'nl' => "Dutch",
        'sv' => "Swedish",
        'fi' => "Finnish",
        'pl' => "Polish",
        'ca' => "Catalan",
        'sr' => "SerbianLatin",
        'hr' => "Croatian",
        'sl' => "Slovenian",
        'da' => "Danish",
        'nb' => "Norwegian",
        'nn' => "NorwegianNynorsk",
        'el' => "Greek",
        'ru' => "Russian",
        'pt' => "Portuguese",
        'uk' => "Ukrainian",
        'cs' => "Czech",
        'sk' => "Slovak",
        'bg' => "Bulgarian",
        'hu' => "Hungarian",
        'ro' => "Romanian",
        'lv' => "Latvian",
        'mk' => "Macedonian",
        'et' => "Estonian",
        'tr' => "Turkish",
        'lt' => "Lithuanian",
        'ar' => "Arabic",
        'he' => "Hebrew",
        'id' => "Indonesian",
        'mn' => "Mongolian",
        'sq' => "Albanian",
        'br' => "Breton",
        'be' => "Belarusian",
        'is' => "Icelandic",
        'ms' => "Malay",
        'bs' => "Bosnian",
        'ga' => "Irish",
        'uz' => "Uzbek",
        'gl' => "Galician",
        'af' => "Afrikaans",
        'ast' => "Asturian",
    ];
    
    
    /**
     * this constant controls if the system generates sepaarate SSID and OI profiles
     * or puts all settings into a single profile
     */
    const separateHS20profiles = true;
    
    public $codePage;
    public $lang;
    public $useGeantLink = false;
    private $background;

}
