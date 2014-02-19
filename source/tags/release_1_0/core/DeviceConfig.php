<?php
/* *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
  * This file defines the abstract Device class
  *
  * @package ModuleWriting
  */

/**
 * 
 */
require_once('Helper.php');
require_once('CAT.php');
require_once('Profile.php');
require_once('X509.php');
require_once('EAP.php');
include_once("devices/devices.php");


/**
 * This class defines the API for CAT module writers.
 *
 * A device is a fairly abstract notion. In most cases it represents
 * a particular operating system or a set of operationg systems
 * like MS Windows Vista and newer.
 *
 * The purpose of this class is to preapare a setup for the device configurator,
 * collect all necessary information from the database, taking into account
 * limitations, that a given device may present (like a set of supported EAP methods).
 *
 * All that is required from the device module is to produce a conigurator
 * file and pass its name back to the API.
 *
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 * 
 * @package ModuleWriting
 * @abstract
 */

abstract class DeviceConfig {
   /**
    * stores the path to the temporary working directory for a module instance
    * @var string $FPATH
    */
    public $FPATH;

    /**
     * array of specialities - will be displayed on the admin download as "footnote"
     * @var array specialities
     */
    public $specialities;

   /**
     * device module constructor should be defined by each module, but if it is not, then here is a default one
     */

      public function __construct() {
      $this->supportedEapMethods  = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP);
      debug(4,"This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
    }


   /**
    * Set up working environment for a device module
    *
    * Sets up the device module environment taking into account the actual profile
    * selected by the user in the GUI. The selected profile is passed as the
    * Profile $profile argumant.
    *
    * This method needs to be called after the device instance has been created (the GUI class does that)
    *
    * setup performs the following tasks:
    * - collect profile attributes and pass them as the attributes property;
    * - create the temporary working directory
    * - process CA certificates and store them as 'internal:CAs' attribute
    * - process and save optional info files and store references to them in
    *   'internal:info_file' attribute
    * @param Profile $profile the profile object which will be passed by the caller
    * @final not to be redefined
    */
    final public function setup(Profile $profile) {
       debug(4,"module setup start\n");
       if(! $profile instanceof Profile) {
          debug(2,"No profile has been set\n");
          error("No profile has been set");
          exit;
       }
       $this->attributes = $this->getProfileAttributes($profile);
debug(4,"INFO_FILE:\n");
debug(4,$this->attributes);
       if(! $this->selected_eap) {
          error("No EAP type specified");
          exit;
       }
       // create temporary directory, its path will be saved in $this->FPATH;
       $this->createTemporaryDirectory();
          $CAs = array();
       if(isset($this->attributes['eap:ca_file'])) {
       foreach ($this->attributes['eap:ca_file'] as $ca) {
          if($c = X509::processCertificate($ca))
             $CAs[] = $c;
          }
          $this->attributes['internal:CAs'][0]=$CAs;
       }
       if(isset($this->attributes['support:info_file'])) {
debug(4,"INFO_FILE:\n");
debug(4,$this->attributes['support:info_file']);
          $this->attributes['internal:info_file'][0] = 
             $this->saveInfoFile($this->attributes['support:info_file'][0]);
       }
       if(isset($this->attributes['general:logo_file']))
          $this->attributes['internal:logo_file'] = 
             $this->saveLogoFile($this->attributes['general:logo_file']);
       $this->attributes['internal:SSID'] = $this->getSSIDs();
       $this->lang_index = CAT::$lang_index;
       // phpMD says the below is not needed. Wow.
       // $idp = new IdP($profile->institution);
       $olddomain = CAT::set_locale("core");
       $this->support_email_substitute = sprintf(_("your local %s support"),Config::$CONSORTIUM['name']);
       $this->support_url_substitute = sprintf(_("your local %s support page"),Config::$CONSORTIUM['name']);
       CAT::set_locale($olddomain);

       if($this->signer && $this->options['sign'])
         $this->sign = CAT::$root . '/signer/'. $this->signer;

    }

  /**
    * Selects the preferred eap method based on profile EAP configuration and device EAP capabilities
    *
    * @param array eap_array an array of eap methods supported by a given device
    * @return the best matching EAP type for the profile; or 0 if no match was found
    */   
   public function getPreferredEapType($eap_array) {
     foreach ($eap_array as $eap) {
         if(in_array($eap,$this->supportedEapMethods)) {
            $this->selected_eap = $eap;
            debug(4,"Selected EAP:");
            debug(4,$eap);
            return($eap);
         }
     }
     return(0);
   }
  /**
    * prepare usage information for the installer
    * every device module should override this method
    *
    * @return String HTML text to be displayed
    */ 
   public function writeDeviceInfo() {
     return _("Sorry, this should not happen - no additional information is available");
   }

  /**
    *  Copy a file from the module location to the temporary directory.
    *
    * If the second argument is provided then the file will be saved under the name 
    * taken form this argument. If only one parameter is given, source and destination
    * filenames are the same
    * Source file can be located either in the Files subdirectory or in the sibdirectory of Files
    * named the same as device_id. The second option takes precedence.
    *
    * @param string $source_name The source file name
    * @param string $output_name The destination file name
    *
    * @return bool result of the copy operation
    * @final not to be redefined
    */
   final protected function copyFile($source_name, $output_name = 0) {
      if  ( $output_name === 0)
        $output_name = $source_name;

      debug(4,"fileCopy($source_name, $output_name)\n");
      if(is_file($this->module_path.'/Files/'.$this->device_id.'/'.$source_name))
         $source = $this->module_path.'/Files/'.$this->device_id.'/'.$source_name;
      elseif(is_file($this->module_path.'/Files/'.$source_name))
         $source = $this->module_path.'/Files/'.$source_name;
      else {
        debug(2,"fileCopy:reqested file $source_name does not exist\n");
        return(FALSE);
      }
      debug(4,"Copying $source to $output_name\n");
      $result = copy($source,"$output_name");
      if(! $result )
        debug(2,"fileCopy($source_name, $output_name) failed\n");
      return($result); 
   }


  /**
    *  Copy a file from the module location to the temporary directory aplying translation.
    *
    * If the second argument is provided and is not equal to 0, then the file will be
    * saved under the name taken form this argument.
    * If only one parameter is given or the second is equal to 0, source and destination
    * filenames are the same.
    * The third optional parameter, if nonzero, should be the character set understood by iconv
    * This is required by the Windows installer and is
    * expected to go away in the future.
    * Source file can be located either in the Files subdirectory or in the sibdirectory of Files
    * named the same as device_id. The second option takes precedence.
    *
    * @param string $source_name The source file name
    * @param string $output_name The destination file name
    * @param int $use_win_cp Set Windows charset if non-zero
    *
    * @final not to be redefined
    */

   final protected function translateFile($source_name, $output_name = 0, $encoding = 0) {
      if  ( $output_name === 0)
        $output_name = $source_name;

      debug(4,"translateFile($source_name, $output_name, $encoding)\n");
      ob_start();
      if(is_file($this->module_path.'/Files/'.$this->device_id.'/'.$source_name))
         $source = $this->module_path.'/Files/'.$this->device_id.'/'.$source_name;
      elseif(is_file($this->module_path.'/Files/'.$source_name))
         $source = $this->module_path.'/Files/'.$source_name;
      include($source);
      $output = ob_get_clean();
      if($encoding) {
        $output_c = iconv('UTF-8',$encoding.'//IGNORE',$output);
        if($output_c)
           $output = $output_c;
      }
      $f = fopen("$output_name","w");
      if(! $f)
         debug(2,"translateFile($source, $output_name, $encoding) failed\n");
      fwrite($f,$output);
      fclose($f);
      debug(4,"translateFile($source, $output_name, $encoding) end\n");
   }

   final protected function translateString($source_string,$encoding) {
        $output_c = iconv('UTF-8',$encoding.'//IGNORE',$source_string);
        if($output_c)
           $source_string = $output_c;
        else
           debug(2,"Failed to convert string $source_string\n");
        return $source_string;
   }


  /**
   * Save certificate files in either DER or PEM format
   *
   * Certificate files will be saved in the module working directory.
   * @param string $format  only "der" and "pem" are currently allowed
   * @return array an array of arrays or FALSE on error
   * saved certificate file names are avalable under the 'file' index
   * additional array entries are indexed as 'sha1', 'md5', and 'root'.
   * sha1 and md5 are correcponding certificate hashes
   * root is set to 1 for the CA roor certicicate and 0 otherwise
  */ 
   final protected function saveCertificateFiles($format) {
     if($format == 'der' || $format == 'pam') {
       $i = 0;
       $CA_files = array();
       $ca_array = $this->attributes['internal:CAs'][0];
       if(! $ca_array)
         return(FALSE);
       foreach ($ca_array as $CA) {
         $f = fopen("cert-$i.crt","w");
         if(! $f) die("problem opening the file\n");
         if($format == "pem")
            fwrite($f,$CA['pem']);
         else
            fwrite($f,$CA['der']);
         fclose($f);
         $C = array();
         $C['file'] = "cert-$i.crt";
         $C['sha1'] = $CA['sha1'];
         $C['md5'] = $CA['md5'];
         $C['root'] = $CA['root'];
         $CA_files[] = $C;
         $i++;
       }
       return($CA_files);
     } else {
       debug(2, 'incorrect format value specified');
       return(FALSE);
     }
      
   }


  private function getSSIDs() {
    $S=array();
    if (isset(Config::$CONSORTIUM['ssid'])) {
       foreach (Config::$CONSORTIUM['ssid'] as $ssid) {
        if(isset(Config::$CONSORTIUM['tkipsupport']) && Config::$CONSORTIUM['tkipsupport'] == TRUE)
          $S[$ssid] = 'TKIP';
        else
          $S[$ssid] = 'AES';
       }
    }
    if(isset($this->attributes['general:SSID'])) {
      $SSID = $this->attributes['general:SSID'];

      foreach($SSID as $ssid)
         $S[$ssid] = 'AES';
      }
    if(isset($this->attributes['general:SSID_with_legacy'])) {
      $SSID = $this->attributes['general:SSID_with_legacy'];
      foreach($SSID as $ssid)
         $S[$ssid] = 'TKIP';
    }
    return $S;
  }

  /**
   * An array with shorthand definitions for MIME types
   * @var array
   */
  private $mime_extensions = array(
     'text/plain' => 'txt',
     'text/rtf' => 'rtf',
     'application/pdf' =>'pdf',
  );

  private function saveLogoFile($Logos) {
    $i=0;
    $returnarray= array();
    foreach ($Logos as $blob) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->buffer($blob);
      if(preg_match('/^image\/(.*)/',$mime,$m))
        $ext = $m[1];
      else
        $ext = 'unsupported';
      debug(4,"saveLogoFile: $mime : $ext\n");
      $f_name = 'logo-'.$i.'.'.$ext;
      $f = fopen($f_name,"w");
      if(! $f) {
          debug(2,"saveLogoFile failed for: $f_name\n");
          die("problem opening the file\n");
      }
      fwrite($f,$blob);
      fclose($f);
      $returnarray[]= array('name'=>$f_name,'mime'=>$ext);
      $i++;
    }
    return($returnarray);
  }


  private function saveInfoFile($blob) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($blob);
    $ext = isset($this->mime_extensions[$mime]) ? $this->mime_extensions[$mime] : 'usupported';
    debug(4,"saveInfoFile: $mime : $ext\n");
    $f = fopen('local-info.'.$ext,"w");
    if(! $f) die("problem opening the file\n");
    fwrite($f,$blob);
    fclose($f);
    return(array('name'=>'local-info.'.$ext,'mime'=>$ext));
  }

  private function getProfileAttributes(Profile $profile) {
     if($eap = $this->getPreferredEapType($profile->getEapMethodsinOrderOfPreference(1))) {
          $a = $profile->getCollapsedAttributes($eap);
          $a['eap'] = $eap;
          return($a);
     } else {
       error("no supported eap types found for this profile");
       return(FALSE);
  }
  }
/**
  * dumps attributes for debugging purposes
  *
  * dumpAttibutes method is supplied for debuging purposes, it simply dumps the attribute array
  * to a file with name passed in the attribute.
  * @param string $file the output file name
  */
  protected function dumpAttibutes($file) {
        ob_start();
        print_r($this->attributes);
        $output = ob_get_clean();
        $f = fopen($file,"w");
        fwrite($f,$output);
        fclose($f);
  }
/** 
 * placeholder for the main device method
 *
 */

  protected function writeInstaller() {
     return("download path");
  }

// this creates the temporary directory so that new files can be written into it
// and changes the working directory to it.
   private function createTemporaryDirectory() {
        $name = 'downloads'.'/'.md5(time().rand());
        $tmp_dir = dirname(dirname(__FILE__)).'/web/'.$name;
        debug(4,"temp dir: $tmp_dir\n");
        if(! mkdir($tmp_dir,0700, true)) {
          error("unable to create temporary directory: $tmp_dir\n");
          exit;
       }
     chdir($tmp_dir);
     $this->FPATH = $name;
   }
/**
 * Array passing all options to the device module.
 *
 * $attrbutes array contains option values defined for the institution and a particular
 * profile (possibly overriding one another) ready for the device module to consume.
 * 
 * For each of the options the value is another array of vales (even if only one value is present).
 * Some attributes may be missing if they have not been configured for a viven institution or profile.
 *
 * The following attributes are meant to be used by device modules:
 * - <b>general:geo_coordinates</b> -  geographical coordinates of the institution or a campus
 * - <b>support:info_file</b>  -  consent file displayed to the users                                                         
 * - <b>general:logo_file</b>  -  file data containing institution logo                                                      
 * - <b>support:eap_types</b>  -  URL to a local support page for a specific eap methiod, not to be confused with general:url 
 * - <b>support:email</b>      -  email for users to contact for local instructions                                           
 * - <b>support:phone</b>      -  telephone number for users to contact for local instructions                                
 * - <b>support:url</b>        -  URL where the user will find local instructions       
 * - <b>internal:info_file</b> -  the pathname of the info_file saved in the working directory
 * - <b>internal:logo_file</b>  -  array of pathnames of logo_files saved in the working directory
 * - <b>internal:CAs</b> - the value is an array produced by X509::processCertificate() with the following filds
 * - <b>internal:SSID</b> - an array indexed by SSID strings with values either TKIP or AES; if TKIP is set the both WPA/TKIP and WPA2/AES should be set if AES is set the this is a WPA2/AES only SSID; the consortium's defined SSIDs are always set as the first array elements.
 * -<b>internal:profile_count</b> - the number of profiles for the associated IdP
 *
 *
 * these attributes are available and can be used, but the "internal" attributes are better suited for modules
 * -  eap:ca_file    -      certificate of the CA signing the RADIUS server key                                         
 * - <b>general:SSID</b>       -  additional SSID to configure, WPA2/AES only (device modules should use internal:SSID)
 * - <b>general:SSID_with_legacy</b> -  additional SSID to configure, WPA2/AES and WPA/TKIP (device modules should use internal:SSID)
 *
 * @see X509::processCertificate()
 * @var array $attributes
 */
  public $attributes;
/**
  * stores the path to the module source location and is used 
  * by copyFile and translateFile
  * the only reason for it to be a public variable ies that it is set by the DeviceFactory class
  * module_path should not be used by module drivers.
  * @var string 
  */
  public $module_path;

/**
 * The optimal EAP type
 *
 */ 
/**
  * optimal EAP method selected given profile and device
  * @var EAP::constant
  */
  public $selected_eap;
/**
  * the path to the profile signing program
  * device modules which require signing should use this property to exec the signer
  * the signer program must accept two arguments - input and output file names
  * the signer program mus operate in the local directory and filenames are relative to this
  * directory
  *
  *@var string
  */
  public $sign;
  public $signer;
/**
 * the string referencing the language (index ot the Config::$LANGUAGES array).
 * It is set to the current language and may be used by the device module to
 * set its language
 *
 *@var string
 */
  public $lang_index;
  /**
   * The string identifier of the device (don't show this to users)
   * @var string
   */
  public $device_id;

  /**
   * See devices-template.php for a list of available options
   * @var array
   */
  public $options;

  /**
   * This string will be shown if no support email was configured by the admin
   * 
   * @var string 
   */
  public static $support_email_substitute;

  /**
   * This string will be shown if no support URL was configured by the admin
   * 
   * @var string 
   */
  public static $support_url_substitute;
}
?>
