<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This is the collection of methods dedicated for the user GUI
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 *
 * Parts of this code are based on simpleSAMLPhp discojuice module.
 * This product includes GeoLite data created by MaxMind, available from
 * http://www.maxmind.com
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
 * @package UserGUI
 *
 */
class GUI extends CAT {
/**
 * Prepare the device module environment and send back the link
 * This method creates a device module instance fia the {@link DeviceFactory} call, 
 * then sets up the device module environment for the specific profile by calling 
 * {@link DeviceConfig::setup()} method and finally, called the devide writeInstaller meethod
 * passing the returned path name.
 * {@source}
 * 
 * @param string $device identifier as in {@link devices.php}
 * @param int $prof_id profile identifier
 *
 * @return array 
 * array with the following fields:
 * - profile - the profile identifier
 * - system - the device identifier
 * - link - the path name of the resulting installer
 *
 */
  public function generateInstaller($device,$prof_id, $generated_for = "user") {
    $this->set_locale("devices");
    $Dev = Devices::listDevices();
    $Config = $Dev[$device];
    debug(4,"installer:$device:$prof_id\n");
    $profile = new Profile($prof_id);
    $a = array();
    $a['profile'] = $prof_id;
    $a['system'] = $device;
    if(isset($Config['options']['no_cache']) && $Config['options']['no_cache'])
      $i_path = FALSE;
    else
      $i_path = $profile->testCache($device);
    if($i_path && is_file($this->root.'/web/'.$i_path)) { 
      debug(4,"Using cached installer for: $device\n");
      $a['link'] = $i_path;
    } else {
      $factory = new DeviceFactory($device);
      $dev = $factory->device;
      if(isset($dev)) {
         $dev->setup($profile);
         $a['link'] = $dev->FPATH.'/'.$dev->writeInstaller();
         if(is_file($this->root.'/web/'.$a['link'])) {
         $profile->updateCache($device,$a['link']);
         debug(4,"Generated installer for: $device\n");
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

 public function listDevices() {
    $Dev = Devices::listDevices();
    $R = array();
    $ct = 0;
    foreach ($Dev as $device => $D) {
      if(isset($D['options']['hidden']) && $D['options']['hidden'])
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
       $dev->setup($profile);
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

  public function JSON_listIdentityProvidersForDisco() {
     $idps = Federation::listAllIdentityProviders(1);
//     header('Content-type: application/json; utf-8');
     echo json_encode($idps);
  }

/**
 * Produce a list of profiles available for a given IdP
 *
 * @param int $idp_id the IdP identifier
 * @return string JSON encoded data
 */
  public function JSON_listProfiles($idp_id) {
     $this->set_locale("web_user");
     $return_array = array();
     try {     
         $idp = new IdP($idp_id);
     }
     catch (Exception $fail) {
        $return_array[0] = 0;  
        echo json_encode($return_array);
        return;
     }
     $return_array[0] = 1;
     $l = 0;
     $logo = $idp->getAttributes('general:logo_file');
     if($logo)
       $l = 1;
     $profiles = $idp->listProfiles(1);
     foreach ($profiles as $P) {
       $return_array[] = array('identifier'=>$P->identifier,'name'=>$P->name, 'inst_name'=>$P->inst_name,'logo'=>$l); 
     }
     echo json_encode($return_array);
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
    echo json_encode($o);
 }
/**
 * Get and prepare logo file 
 *
 */

 public function sendLogo($idp_id, $disco=FALSE) {
   $idp = new IdP($idp_id);
   $at = $idp->getAttributes('general:logo_file');
   $blob =  $at[0]['value'];
        $info = new finfo();
        $filetype = $info->buffer($blob, FILEINFO_MIME_TYPE);

        if($disco) 
          header( "Content-type: image/png");
        else
          header( "Content-type: ".$filetype );
        header( "Cache-Control: must-revalidate" );
        $offset = 60 * 60 * 24 * 30;
        $ExpStr = "Expires: " . gmdate( "D, d M Y H:i:s", time() + $offset ) . " GMT";
        header( $ExpStr );

        if($disco) {
        $image = new Imagick();
        $image->readImageBlob($blob);
        if( $image->setImageFormat('PNG')) {
        $image->thumbnailImage(120,40,1);
        echo $image->getImageBlob();
        } else
          echo "XXXXXX";
        } else
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
     $result['keywords'] = array('tralala','XXXX');
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
    echo json_encode($this->profileAttributes($prof_id));
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
  * @return array $IdPs -  list of arrays ('entityID', 'title');
  */

public function orderIdentityProviders($L,$country) {
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
      $outarray[] = array('entityID'=>$r, 'title'=>$T[$r]);
     return($outarray);
}



public $device;
  
}
?>
