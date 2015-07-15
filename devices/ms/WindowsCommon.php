<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
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
 */
function echo_nsi($in) {
  echo preg_replace('/"/','$\"',$in);
}

function sprint_nsi($in) {
  return preg_replace('/"/','$\"',$in);
}

/**
 * This class defines common functions needed by all Windows installers
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */
class WindowsCommon extends DeviceConfig {

protected function prepareInstallerLang() {
    if(isset($this->LANGS[$this->lang_index])) {
      $L = $this->LANGS[$this->lang_index];
      $this->lang = $L['nsis'];
      $this->code_page = 'cp'.$L['cp'];
    } else {
      $this->lang = 'English';
      $this->code_page = 'cp1252';
    }
}

protected function combineLogo($Logos) {
    // maximum size to which we want to resize
 $max_size= 120;
// logo wull be shited up by this much
 $vshift = 20;
 $bg_image = new Imagick('cat_bg.bmp');
 $bg_image->setFormat('BMP3');
 $bg_image_size = $bg_image->getImageGeometry();
 $logo = new Imagick($Logos[0]['name']);
 $logo_size = $logo->getImageGeometry();
 $max = max($logo_size);
 debug(4,"Logo size: "); debug(4,$logo_size); debug(4,"max=$max\n");
// resize logo if necessary
 if($max > $max_size) {
   if($max == $logo_size['width'])
      $logo->scaleImage($max_size,0);
   else
      $logo->scaleImage(0,$max_size);
 }
 $logo_size = $logo->getImageGeometry();
 debug(4,"New logo size: "); debug(4,$logo_size);
// calculate logo offsets for composition with the background
 $hoffset = round(($bg_image_size['width'] - $logo_size['width'])/2);
 $voffset = round(($bg_image_size['height'] - $logo_size['height'])/2) - $vshift;

//logo image is put on top of the background
$bg_image->compositeImage($logo, $logo->getImageCompose(), $hoffset, $voffset);

//new image is saved as the background
$bg_image->writeImage('BMP3:cat_bg.bmp');
}

protected function signInstaller($attr) {
   $e = $this->installerBasename.'.exe';
   if($this->sign) {
      $o = system($this->sign." installer.exe '$e' > /dev/null");
   }
   else
      rename("installer.exe",$e);
   return $e;
}

protected function compileNSIS() {
  $o = system(Config::$PATHS['makensis'].' -V4 cat.NSI > nsis.log');
  debug(4,"compileNSIS:$o\n");
}

protected function msInfoFile($attr) {
 $out = '';
if(isset($attr['support:info_file'])) {
    $out .= '!define EXTERNAL_INFO "';
//  debug(4,"Info file type ".$attr['support:info_file'][0]['mime']."\n");
  if ($attr['internal:info_file'][0]['mime'] == 'rtf')
     $out = '!define LICENSE_FILE "'. $attr['internal:info_file'][0]['name'];
  elseif( $attr['internal:info_file'][0]['mime'] == 'txt') {
     $in_txt = file_get_contents($attr['internal:info_file'][0]['name']);
     $out_txt = iconv('UTF-8',$this->code_page.'//TRANSLIT',$in_txt);
     file_put_contents('info_f.txt',$out_txt);
     $out = '!define LICENSE_FILE " info_f.txt';
  }
  else
     $out = '!define EXTERNAL_INFO "'. $attr['internal:info_file'][0]['name'];

  $out .= "\"\n";
}
 debug(4,"Info file returned: $out");
  return $out;
}


protected function writeAdditionalDeletes($P) {
  if(count($P) == 0 )
    return;
  $f = fopen('profiles.nsh','a');
  fwrite($f,"!define AdditionalDeletes\n");
  foreach ($P as $p)
    fwrite($f,"!insertmacro define_delete_profile \"$p\"\n");
  fclose($f);
}


public $LANGS=array(
'fr'=>array('nsis'=>"French",'cp'=>'1252'),
'de'=>array('nsis'=>"German",'cp'=>'1252'),
'es'=>array('nsis'=>"SpanishInternational",'cp'=>'1252'),
'it'=>array('nsis'=>"Italian",'cp'=>'1252'),
'nl'=>array('nsis'=>"Dutch",'cp'=>'1252'),
'sv'=>array('nsis'=>"Swedish",'cp'=>'1252'),
'fi'=>array('nsis'=>"Finnish",'cp'=>'1252'),
'pl'=>array('nsis'=>"Polish",'cp'=>'1250'),
'ca'=>array('nsis'=>"Catalan",'cp'=>'1252'),
'sr'=>array('nsis'=>"SerbianLatin",'cp'=>'1250'),
'hr'=>array('nsis'=>"Croatian",'cp'=>'1250'),
'sl'=>array('nsis'=>"Slovenian",'cp'=>'1250'),
'da'=>array('nsis'=>"Danish",'cp'=>'1252'),
'nb'=>array('nsis'=>"Norwegian",'cp'=>'1252'),
'nn'=>array('nsis'=>"NorwegianNynorsk",'cp'=>'1252'),
'el'=>array('nsis'=>"Greek",'cp'=>'1253'),
'ru'=>array('nsis'=>"Russian",'cp'=>'1251'),
'pt'=>array('nsis'=>"Portuguese",'cp'=>'1252'),
'uk'=>array('nsis'=>"Ukrainian",'cp'=>'1251'),
'cs'=>array('nsis'=>"Czech",'cp'=>'1250'),
'sk'=>array('nsis'=>"Slovak",'cp'=>'1250'),
'bg'=>array('nsis'=>"Bulgarian",'cp'=>'1251'),
'hu'=>array('nsis'=>"Hungarian",'cp'=>'1250'),
'ro'=>array('nsis'=>"Romanian",'cp'=>'1250'),
'lv'=>array('nsis'=>"Latvian",'cp'=>'1257'),
'mk'=>array('nsis'=>"Macedonian",'cp'=>'1251'),
'et'=>array('nsis'=>"Estonian",'cp'=>'1257'),
'tr'=>array('nsis'=>"Turkish",'cp'=>'1254'),
'lt'=>array('nsis'=>"Lithuanian",'cp'=>'1257'),
'ar'=>array('nsis'=>"Arabic",'cp'=>'1256'),
'he'=>array('nsis'=>"Hebrew",'cp'=>'1255'),
'id'=>array('nsis'=>"Indonesian",'cp'=>'1252'),
'mn'=>array('nsis'=>"Mongolian",'cp'=>'1251'),
'sq'=>array('nsis'=>"Albanian",'cp'=>'1252'),
'br'=>array('nsis'=>"Breton",'cp'=>'1252'),
'be'=>array('nsis'=>"Belarusian",'cp'=>'1251'),
'is'=>array('nsis'=>"Icelandic",'cp'=>'1252'),
'ms'=>array('nsis'=>"Malay",'cp'=>'1252'),
'bs'=>array('nsis'=>"Bosnian",'cp'=>'1250'),
'ga'=>array('nsis'=>"Irish",'cp'=>'1250'),
'uz'=>array('nsis'=>"Uzbek",'cp'=>'1251'),
'gl'=>array('nsis'=>"Galician",'cp'=>'1252'),
'af'=>array('nsis'=>"Afrikaans",'cp'=>'1252'),
'ast'=>array('nsis'=>"Asturian",'cp'=>'1252'),

  );

public $code_page;
public $lang;

}
?>
