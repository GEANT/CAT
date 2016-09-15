<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains common functions needed by all Windows installers
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

/**
 * function to escape double quotes in a special NSI-compatible way
 * 
 * @param string $in input string
 * @return string
 */
function echo_nsi($in) {
    echo preg_replace('/"/', '$\"', $in);
}

/**
 * @param string $in input string
 * @return string
 */
function sprint_nsi($in) {
    return preg_replace('/"/', '$\"', $in);
}

/**
 * This class defines common functions needed by all Windows installers
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */
class WindowsCommon extends DeviceConfig {

    public function __construct() {
        parent::__construct();
    }

    protected function prepareInstallerLang() {
        if (isset($this->LANGS[$this->langIndex])) {
            $L = $this->LANGS[$this->langIndex];
            $this->lang = $L['nsis'];
            $this->code_page = 'cp' . $L['cp'];
        } else {
            $this->lang = 'English';
            $this->code_page = 'cp1252';
        }
    }

    protected function combineLogo($Logos) {
        // maximum size to which we want to resize
        $max_size = 120;
// logo wull be shited up by this much
        $vshift = 20;
        $bg_image = new Imagick('cat_bg.bmp');
        $bg_image->setFormat('BMP3');
        $bg_image_size = $bg_image->getImageGeometry();
        $logo = new Imagick($Logos[0]['name']);
        $logo_size = $logo->getImageGeometry();
        $max = max($logo_size);
        $this->loggerInstance->debug(4, "Logo size: ");
        $this->loggerInstance->debug(4, print_r($logo_size, true));
        $this->loggerInstance->debug(4, "max=$max\n");
// resize logo if necessary
        if ($max > $max_size) {
            if ($max == $logo_size['width']) {
                $logo->scaleImage($max_size, 0);
            } else {
                $logo->scaleImage(0, $max_size);
            }
        }
        $logo_size = $logo->getImageGeometry();
        $this->loggerInstance->debug(4, "New logo size: ");
        $this->loggerInstance->debug(4, print_r($logo_size, true));
// calculate logo offsets for composition with the background
        $hoffset = round(($bg_image_size['width'] - $logo_size['width']) / 2);
        $voffset = round(($bg_image_size['height'] - $logo_size['height']) / 2) - $vshift;

//logo image is put on top of the background
        $bg_image->compositeImage($logo, $logo->getImageCompose(), $hoffset, $voffset);

//new image is saved as the background
        $bg_image->writeImage('BMP3:cat_bg.bmp');
    }

    protected function signInstaller($attr) {
        $e = $this->installerBasename . '.exe';
        if ($this->sign) {
            $o = system($this->sign . " installer.exe '$e' > /dev/null");
        } else {
            rename("installer.exe", $e);
        }
        return $e;
    }

    protected function compileNSIS() {
        if (CONFIG['NSIS_VERSION'] >= 3) {
            $makensis = CONFIG['PATHS']['makensis'] . " -INPUTCHARSET UTF8";
        } else {
            $makensis = CONFIG['PATHS']['makensis'];
        }
        $o = $makensis . ' -V4 cat.NSI > nsis.log';
        system($o);
        $this->loggerInstance->debug(4, "compileNSIS:$o\n");
    }

    protected function msInfoFile($attr) {
        $out = '';
        if (isset($attr['support:info_file'])) {
            $out .= '!define EXTERNAL_INFO "';
//  $this->loggerInstance->debug(4,"Info file type ".$attr['support:info_file'][0]['mime']."\n");
            if ($attr['internal:info_file'][0]['mime'] == 'rtf') {
                $out = '!define LICENSE_FILE "' . $attr['internal:info_file'][0]['name'];
            } elseif ($attr['internal:info_file'][0]['mime'] == 'txt') {
                $in_txt = file_get_contents($attr['internal:info_file'][0]['name']);
                if (CONFIG['NSIS_VERSION'] >= 3)
                    $out_txt = $in_txt;
                else
                    $out_txt = iconv('UTF-8', $this->code_page . '//TRANSLIT', $in_txt);
                if ($out_txt) {
                    file_put_contents('info_f.txt', $out_txt);
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
    public $code_page;
    public $lang;

}
