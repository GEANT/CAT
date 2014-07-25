<?php
/***********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This is the collection of methods dedicated for the user GUI
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserAPI
 *
 * Parts of this code are based on simpleSAMLPhp discojuice module.
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
 */

/**
 * includes required by this class
 */
require_once("Helper.php");
require_once("Options.php");
require_once("CAT.php");
require_once("Profile.php");
require_once("Federation.php");
require_once("DeviceFactory.php");
require_once("devices/devices.php");

/**
 * The basic methoods for the user GUI
 * @package UserAPI
 *
 */
class UserAPI extends CAT {

/**
 * Prepare the device module environment and send back the link
 * This method creates a device module instance via the {@link DeviceFactory} call, 
 * then sets up the device module environment for the specific profile by calling 
 * {@link DeviceConfig::setup()} method and finally, called the devide writeInstaller meethod
 * passing the returned path name.
 * 
 * @param string $device identifier as in {@link devices.php}
 * @param int $prof_id profile identifier
 *
 * @return array 
 *  array with the following fields: 
 *  profile - the profile identifier; 
 *  device - the device identifier; 
 *  link - the path name of the resulting installer
 *  mime - the mimetype of the installer
 */
  public function generateInstaller($device,$prof_id, $generated_for = "user") {
    $this->set_locale("devices");
    $Dev = Devices::listDevices();
    $Config = $Dev[$device];
    debug(4,"installer:$device:$prof_id\n");
    $profile = new Profile($prof_id);
    $a = array();
    $a['profile'] = $prof_id;
    if( (isset(Devices::$Options['no_cache']) && Devices::$Options['no_cache'] ) || ( isset($Config['options']['no_cache']) && $Config['options']['no_cache'] ))
      $i_path = FALSE;
    else
      $i_path = $profile->testCache($device);
    if($i_path && is_file($this->root.'/web/'.$i_path)) { 
      $installer_file = $i_path;
      debug(4,"Using cached installer for: $device\n");
      $a['link'] = $i_path;
    } else {
      $factory = new DeviceFactory($device);
      $dev = $factory->device;
      if(isset($dev)) {
         $dev->setup($profile);
         $installer_file = $dev->FPATH.'/'.$dev->writeInstaller();
         if($installer_file && is_file($this->root.'/web/'.$installer_file)) {
         $profile->updateCache($device,$installer_file);
         $a['device'] = $device;
         if(isset($dev->options['mime']))
               $a['mime'] = $dev->options['mime'];
         else {
           $info = new finfo();
           $a['mime'] = $info->file($this->root.'/web/'.$installer_file, FILEINFO_MIME_TYPE);
         }
         debug(4,"Generated installer :".$this->root.'/web/'.$installer_file.": for: $device\n");
         $a['link'] = $installer_file;
         } else {
         debug(2,"Installer generation failed for: $prof_id:$device\n");
         $a['link'] = 0;
         }
      } 
    }
    $profile->incrementDownloadStats($device, $generated_for);
    $this->set_locale("web_user");
    return($a);
 }

 /**
  * interface to Devices::listDevices() 
  */
 public function listDevices($show_hidden = 0) {
    $Dev = Devices::listDevices();
    $R = array();
    $ct = 0;
    if($show_hidden !== 0 && $show_hidden != 1)
      return;
    foreach ($Dev as $device => $D) {
      if(isset($D['options']['hidden']) && $D['options']['hidden'] && $show_hidden == 0)
         continue;
      $ct ++;
      $D['id'] = $device;
      $group = isset($D['group']) ? $D['group'] : 'other';
      if (! isset($R[$group]))
         $R[$group] = array();
      $R[$group][$device] = $D;
    }
   return $R;
 }

 public function deviceInfo($device,$prof_id) {
    $this->set_locale("devices");
    $out = 0;
    $profile = new Profile($prof_id);
    $factory = new DeviceFactory($device);
    $dev = $factory->device;
    if(isset($dev)) {
//       $dev->setup($profile);
       $out = $dev->writeDeviceInfo();
   }
    $this->set_locale("web_user");
    echo $out;
 }

/**
 * Prepare the support data for a given profile
 *
 * @param int $prof_id profile identifier
 * @return array
 * array with the following fields:
 * - local_email
 * - local_phone
 * - local_url
 * - description
 * - devices - an array of device names and their statuses (for a given profile)
 */
 public function profileAttributes($prof_id) {
    $this->set_locale("devices");
      $profile = new Profile($prof_id);
      $attr = $profile->getCollapsedAttributes();
      $a = array();
debug(4,"profileAttributes\n");
debug(4,$attr);
      if(isset($attr['support:email']))
         $a['local_email'] = $attr['support:email'][0];
      if(isset($attr['support:phone']))
         $a['local_phone'] = $attr['support:phone'][0];
      if(isset($attr['support:url']))
         $a['local_url'] = $attr['support:url'][0];
      if(isset($attr['profile:description']))
         $a['description'] = $attr['profile:description'][0];
      $a['devices'] = $profile->listDevices();
      $this->set_locale("web_user");
      return($a);
 }

/*
   this method needs to be used with care, it could give wrong results in some
   cicumstances
*/
private function GetRootURL() {
    $backtrace =  debug_backtrace();
    $F = array_pop($backtrace);
    $file= $F['file'];
    $file = substr($file,strlen(dirname(__DIR__)));
    while(substr($file,0,1) == '/')
       $file = substr($file,1);
    $n = count(explode('/',$file));
    $out = $_SERVER['SCRIPT_NAME'];
    for ($i= 0; $i < $n; $i++)
      $out = dirname($out);
    if ($out == '/')
      $out = '';
    $s = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http';
    $s .= '//'.$_SERVER['HTTP_HOST'] . $out;
    return $s;
}

/* JSON functions */

  public function return_json($data,$status=1) {
     $return_array = array();
     $return_array['status'] = $status;
     $return_array['data'] = $data;
     $return_array['tou'] =  "Please consult Terms of Use at: ".$this->GetRootURL()."/tou.php";
     return(json_encode($return_array));
  }

/**
  * Return the list of supported languages.
  *
  * 
  */
  public function JSON_listLanguages() {
     $return_array = array();
     foreach(Config::$LANGUAGES as $id => $val)
       $return_array[] = array('id'=>$id,'display'=>$val['display'],'locale'=>$val['locale']);
     echo $this->return_json($return_array);
  }

/**
 * Return the list of countiers with configured IdPs
 *
 * @return string JSON encoded data
 */

  public function JSON_listCountries() {
     $FED = $this->printCountryList(1);
     $return_array = array();
     foreach ($FED as $id => $val)
       $return_array[] = array('id'=>$id,'display'=>$val);
     echo $this->return_json($return_array);
  }

/**
 * Return the list of IdPs in a given country
 *
 * @param int $idp_id the IdP identifier
 * @return string JSON encoded data
 */

  public function JSON_listIdentityProviders($country) {
     $idps = Federation::listAllIdentityProviders(1,$country);
     $return_array = array();
     foreach ($idps as $idp) {
        $return_array[] = array('id'=>$idp['entityID'],'display'=>$idp['title']);
     }
     echo $this->return_json($return_array);
  }

/**
 * return the list of all active IdPs
 *
 * The IdP list is formatted for DiscoJuice
 * @return string JSON encoded data
 */

  public function JSON_listIdentityProvidersForDisco() {
     $idps = Federation::listAllIdentityProviders(1);
     $return_array = array();
     foreach ($idps as $idp) {
        $idp['id'] = $idp['entityID'];
        $return_array[] = $idp;
      }
     echo json_encode($return_array);
  }



/**
 * Return the list of IdPs in a given country ordered with respect to the user location
 *
 * @param int $idp_id the IdP identifier
 * @return string JSON encoded data
 */


  public function JSON_orderIdentityProviders($country) {
     $idps = $this->orderIdentityProviders($country);
     $return_array = array();
     foreach ($idps as $idp) {
        $return_array[] = array('id'=>$idp['id'],'display'=>$idp['title']);
     }
     echo $this->return_json($return_array);
  }

/**
 * Produce a list of profiles available for a given IdP
 *
 * @param int $idp_id the IdP identifier
 * @return string JSON encoded data
 */
  public function JSON_listProfiles($idp_id,$sort = 0) {
     $this->set_locale("web_user");
     $return_array = array();
     try {     
         $idp = new IdP($idp_id);
     }
     catch (Exception $fail) {
        echo $this->return_json($return_array,0);
        return;
     }
     $l = 0;
     $logo = $idp->getAttributes('general:logo_file');
     if($logo)
       $l = 1;
     $profiles = $idp->listProfiles(1);
     if($sort == 1)
        usort($profiles,"profile_sort");
     foreach ($profiles as $P) {
       $return_array[] = array('id'=>$P->identifier,'display'=>$P->name, 'idp_name'=>$P->inst_name,'logo'=>$l); 
     }
     echo $this->return_json($return_array);
  }

/**
 * Return the list of devices available for the given profile
 *
 * @param int $profile_id the Profile identifier
 * @return string JSON encoded data
 */
  public function JSON_listDevices($profile_id) {
     $this->set_locale("web_user");
     $return_array = array();
     $a = $this->profileAttributes($profile_id);
     $thedevices = $a['devices'];
     if(!isset($profile_redirect) || ! $profile_redirect) {
         $profile_redirect = 0;
         foreach ($thedevices as $D) {
              if(isset($D['options']) && isset($D['options']['hidden']) &&  $D['options']['hidden'])
                   continue;
              $disp = $D['display'];
              if($D['id'] === '0') {
                  $profile_redirect = 1;
                  $disp = $c;
              }
             $return_array[] = array('id'=>$D['id'], 'display'=>$disp, 'status'=>$D['status'], 'redirect'=>$D['redirect']);
         }

  }
  echo $this->return_json($return_array);
}
  
/**
 * Call installer generation and return the link
 *
 * @param string $device identifier as in {@link devices.php}
 * @param int $prof_id profile identifier
 * @return string JSON encoded data
 */
  public function JSON_generateInstaller($device,$prof_id) {
    debug(4,"JSON::generateInstaller arguments: $device,$prof_id\n");
    $o = $this->generateInstaller($device,$prof_id);
    debug(4,"output from GUI::generateInstaller:");
    debug(4,$o);
    debug(4,json_encode($o));
//    header('Content-type: application/json; utf-8');
    echo $this->return_json($o);
 }

/**
 * Generate and send the installer
 *
 * @param string $device identifier as in {@link devices.php}
 * @param int $prof_id profile identifier
 * @return binary installerFile
 */

 public function downloadInstaller($device,$prof_id,$generatedfor='user') {
    debug(4,"downloadInstaller arguments: $device,$prof_id,$generatedfor\n");
    $o = $this->generateInstaller($device,$prof_id);
    debug(4,"output from GUI::generateInstaller:");
    debug(4,$o);
    if(! $o['link']) {
       header("HTTP/1.0 404 Not Found");
       return;
    }
    $file = $this->root.'/web/'.$o['link'];
    $filetype = $o['mime'];
    debug(4,"installer MIME type:$filetype\n");
    header("Content-type: ".$filetype);
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Content-Length: ' . filesize($file));
    ob_clean();
    flush();
    readfile($file);
 }
/**
 * Get and prepare logo file 
 *
 * When called for DiscoJuice, first check if file cache exists
 * If not then generate the file and save it in the cache
 * @param int $idp_id IdP identifier
 * @param int $disco flag turning on image generation for DiscoJuice
 *
 */

 public function sendLogo($idp_id, $disco=FALSE) {
   $ExpStr = '';
   if($disco && is_file($this->root.'/web/downloads/logos/'.$idp_id.'.png')) {
      debug(4,"Using cached logo for: $idp_id\n");
      $blob = file_get_contents($this->root.'/web/downloads/logos/'.$idp_id.'.png');
      $filetype = 'image/png';
   }
   else {
      $idp = new IdP($idp_id);
      $at = $idp->getAttributes('general:logo_file');
      $blob =  $at[0]['value'];
      $info = new finfo();
      $filetype = $info->buffer($blob, FILEINFO_MIME_TYPE);
      $offset = 60 * 60 * 24 * 30;
      $ExpStr = "Expires: " . gmdate( "D, d M Y H:i:s", time() + $offset ) . " GMT";
      if($disco) {
         $filetype = 'image/png';
         $image = new Imagick();
         $image->readImageBlob($blob);
         if( $image->setImageFormat('PNG')) {
           $image->thumbnailImage(120,40,1);
           $blob = $image->getImageBlob();
           debug(4,"Writing cached logo for: $idp_id\n");
           file_put_contents($this->root.'/web/downloads/logos/'.$idp_id.'.png',$blob);
         }
         else
           $blob = "XXXXXX";
      }
   }
   header( "Content-type: ".$filetype );
   header( "Cache-Control:max-age=36000, must-revalidate" );
   header( $ExpStr );
   echo $blob;
 }

 public function locateUser() {
   $host = $_SERVER['REMOTE_ADDR'];
   $record = geoip_record_by_name($host);
   if($record) {
     $result = array('status' => 'ok');
     $result['country'] = $record['country_code'];
     $result['region'] = $record['region'];
     $result['geo'] = array('lat' => (float)$record['latitude'] , 'lon' => (float)$record['longitude']);
   } else {
     $result = array('status' => 'error', 'error' =>'Problem listing countries'); 
   }
   return($result);
 }

public function JSON_locateUser() {
    header('Content-type: application/json; utf-8');
    echo json_encode($this->locateUser());
}

/**
 * Produce support data prepared within {@link GUI::profileAttributes()}
 * @return string JSON encoded data
 */
  public function JSON_profileAttributes($prof_id) {
//    header('Content-type: application/json; utf-8');
    echo $this->return_json($this->profileAttributes($prof_id));
  }

/**
  * Calculate the distence in km between two points given their
  * geo coordinates.
  * @param array $P1 - first point as an 'lat', 'lon' array 
  * @param array $P2 - second point as an 'lat', 'lon' array 
  * @return float distance in km
  */
private function geoDistance($P1,$P2) {

  $dist = sin(deg2rad($P1['lat'])) * sin(deg2rad($P2['lat'])) +  
         cos(deg2rad($P1['lat'])) * cos(deg2rad($P2['lat'])) * cos(deg2rad($P1['lon'] - $P2['lon']));
  $dist = rad2deg(acos($dist)) * 60 * 1.1852 ;
  return(round($dist));
}

/**
  * Order active identity providers according to their distance and name
  * @param array $L - current location
  * @return array $IdPs -  list of arrays ('id', 'name');
  */

public function orderIdentityProviders($country) {
     $idps = Federation::listAllIdentityProviders(1,$country);

  $U = $this->locateUser();
  if($U['status'] == 'ok') {
  $L = $U['geo'];
  } else {
    $L = array('lat'=>"90",'lon'=>"0");
  }
  $T=array();
  $R=array();
     foreach ($idps as $idp) {
        $T[$idp['entityID']] = $idp['title'];
        $dist = 10000;
        if(isset($idp['geo'])) {
          $G=$idp['geo'];
          if(isset($G['lon'])) {
             $d1 = $this->geoDistance($L,$G); 
             if( $d1 < $dist)
                $dist = $d1;
          } else {
            foreach ($G as $g) {
             $d1 = $this->geoDistance($L,$g); 
             if( $d1 < $dist)
                $dist = $d1;
            }
          }
        }
       if($dist > 100)
         $dist=10000;
      $d = sprintf("%06d",$dist);
      $R[$idp['entityID']] = $d." ".$idp['title'];
     }
     asort($R);
     foreach (array_keys($R) as $r)
      $outarray[] = array('id'=>$r, 'title'=>$T[$r]);
     return($outarray);
}

/**
  * Detect the best device driver form the browser
  *
  * Detects the operating system and returns its id 
  * display name and group membership (as in devices.php)
  * @return array indexed by 'id', 'display', 'group'
  */

public function detectOS() {
   $Dev = Devices::listDevices();
   if( isset($_REQUEST['device']) && isset($Dev[$_REQUEST['device']]) && (!!isset($device['options']['hidden']) || $device['options']['hidden'] == 0)) {
      $dev_id = $_REQUEST['device'];
      $device = $Dev[$dev_id];
      return(array('id'=>$dev_id,'display'=>$device['display'], 'group'=>$device['group']));
   }
   $browser = $_SERVER['HTTP_USER_AGENT'];
   debug(4,"HTTP_USER_AGENT=$browser\n");
   foreach ($Dev as $dev_id => $device) {
     if(!isset($device['match']))
        continue;
     if(preg_match('/'.$device['match'].'/',$browser)) {
       if(!isset($device['options']['hidden']) || $device['options']['hidden'] == 0) {
          debug(4,"Browser_id: $dev_id\n");
          return(array('id'=>$dev_id,'display'=>$device['display'], 'group'=>$device['group']));
       }
       else {
         debug(2, "Unrecognised system: ".$_SERVER['HTTP_USER_AGENT']."\n");
         return(false);
       }
     }
   }
   debug(2, "Unrecognised system: ".$_SERVER['HTTP_USER_AGENT']."\n");
   return(false);
}


public $device;
  
}
function profile_sort($P1,$P2) {
   return strcasecmp($P1->name, $P2->name);
} 
?>
