<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file contains the installer for Mac OS X 10.6 (Snow Leopard)
 *
 *
 * @author José Manuel Macías Luna
 * @package Developer
 */

/**
 * 
 */
require_once('DeviceConfig.php');
require_once('Helper.php');

// set_locale("devices");

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
class Device_macosx extends DeviceConfig {

  /**
   * this array holds the list of EAP methods supported by this device
   */
    final public function __construct() {
//      debug(4,"got device: $device\n");
      //$this->supportedEapMethods  = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP);
      // Restrict the list of supported EAP methods to EAP-TTLS+PAP... for now...
      $this->supportedEapMethods  = array(EAP::$TTLS_PAP);
      debug(4,"This device supports the following EAP methods: ");
      debug(4,$this->supportedEapMethods);
    }


   public static $my_eap_methods = array(array("OUTER" => TLS, "INNER" => NONE), array("OUTER" => PEAP, "INNER" => MSCHAPv2), array("OUTER" => TTLS, "INNER" => NONE));

   private $massaged_inst;
   private $massaged_profile;
   private $massaged_country;
   private $lang;

   /**
   * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
   *
   * {@source}
   */
   /*public function writeInstaller(Profile $profile) { */
   public function writeInstaller() {
   /** run innitial setup
      this will:
       - create the temporary directory and save its path as $this->FPATH
       - process the CA certificates and store results in $this->attributes['internal:CAs'][0]
            $this->attributes['internal:CAs'][0] is an array of processed CA certificates
            a processed certifincate is an array 
               'pem' points to pem feromat certificate
               'der' points to der format certificate
               'md5' points to md5 fingerprint
               'sha1' points to sha1 fingerprint
               'name' points to the certificate subject
       - save the info_file (if exists) and put the name in $this->attributes['internal:info_file_name'][0]
   */
       $dom = textdomain(NULL);
      textdomain("devices");
    $this->supportedEapMethods = Device_macosx::$my_eap_methods;

    debug(4,"macosx Module Installer start\n");
//    $this->setup($profile);
    $this->massaged_inst = preg_replace('/ +/','_',$this->attributes['general:instname'][0]);
    $this->massaged_profile = preg_replace('/ +/','_',$this->attributes['profile:name'][0]);
    $this->massaged_country = strtolower($this->attributes['internal:country'][0]);
    $this->lang = preg_replace('/\..+/','',setlocale(LC_ALL,"0"));

    if (isset($this->attributes['internal:use_anon_outer']) && $this->attributes['internal:use_anon_outer'][0] == "1" && isset($this->attributes['internal:realm']))
            $use_realm = $this->attributes['internal:realm'][0];
    else
            $use_realm = 0;

    $filename = $this->attributes['general:instname'][0]."-".$this->attributes['profile:name'][0].".networkConnect";
    $filename = preg_replace('/ +/','_',$filename);
    debug(4,"filename is going to be".$filename."\n");
    $xml_f = fopen($filename,'w');
    $raw_document = "";


    $ssid_list = $this->attributes['general:SSID'];
    $server_names = $this->attributes['eap:server_name'];
    $uuid_list = $this->list_ca_uuids($this->attributes['internal:CAs'][0]);
    $ca_list = $this->attributes['internal:CAs'][0];
    $eap_type = $this->selected_eap;

    $raw_document .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\"
\"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
   <dict>
    <key>8021X</key>
    <dict>
      <key>UserProfiles</key>
         <array>";

    $raw_document .= $this->all_wlan($ssid_list,$uuid_list,$eap_type, $use_realm, $ca_list, $server_names);

    //$raw_document .= $this->all_ca($this->attributes['internal:CAs'][0]);

    //$raw_document .= $this->all_server_names($server_names);

    $raw_document .= "
   </dict>
 </dict>
</plist>";
  
	$tidy_config = array(
	       'input-xml'		=> true,
           'indent'         => true,
           'output-xml'   => true,
           'tabsize'	=> 4,
           'wrap'           => 83);

	// Tidy
	$tidy = new tidy;
	$tidy->parseString($raw_document, $tidy_config, 'utf8');
	$tidy->cleanRepair();
	
	fwrite($xml_f,$tidy);

    fclose($xml_f);
   if($this->sign) {
      $e = 'signed-'.$filename;
      $o = system($this->sign." $filename $e > /dev/null");
   }
   else
      $e = $filename;

   textdomain($dom);
    return $e;
   }

   static private $IPHONE_PAYLOAD_PREFIX = "org.eduroam";

   private function list_ca_uuids($ca_array) {
       $retval = array();
       foreach ($ca_array as $ca)
           $retval[] = $ca['uuid'];
       return $retval;
   }

   private function all_wlan ($SSID_list, $CA_UUID_list, $eap_type, $realm = 0, $ca_list, $server_list) {
       $SSID_list[] = "eduroam";
       $retval = "";
       $serial = 0;
       foreach ($SSID_list as $SSID) { 
            $retval .= "
            <dict>
               <key>EAPClientConfiguration</key>
                  <dict>
                      <key>AcceptEAPTypes</key>
                         <array>
                            <integer>".$eap_type['OUTER']."</integer>
                         </array>";
         if ($realm !== 0)
                     $retval .= "
                     <key>OuterIdentity</key>
				 	 <string>mac_installer_roamer@$realm</string>";

		 $retval .= $this->all_ca($ca_list);

		 $retval .= $this->all_server_names($server_list);        

         $retval .= "             <key>TTLSInnerAuthentication</key>
                         <string>PAP</string>
                   </dict>
               <key>UserDefinedName</key>
               <string>".("Configuration $SSID")."</string>
               <key>UniqueIdentifier</key>
                <string>".uuid()."</string>
               <key>Wireless Network</key>
                  <string>$SSID</string>
               <key>Wireless Security</key>
				<string>WPA2 Enterprise</string>
            </dict>";
         $serial = $serial + 1;
       }
       return $retval;
   }

   /* Builds the list of certification authorities */
   private function all_ca ($ca_array) {
       $retval = "";
       $i = 0;
       foreach ($ca_array as $ca) {
           $retval .= $this->ca_blob($ca['uuid'], $ca['pem'],$i);
           $i = $i+1;
       }
       return $retval;      
   }
  
   /* Process the certificate(s) inside a pem file */
   private function ca_blob($uuid, $pem, $serial) {
           // cut lines with CERTIFICATE
    $pem = preg_replace('/-----BEGIN CERTIFICATE-----/','<data>', $pem);
           $pem = preg_replace('/-----END CERTIFICATE-----/','</data>', $pem);
           
    $pem = trim($pem);
           //return print_r($result);
	$stream ="
               <key>TLSTrustedCertificates</key>
               <array>".$pem."</array>";

		return $stream;
	}
	/** Builds the list of server trusted server names */
	private function all_server_names($server_list){
	  $retval .= " <key>TLSTrustedServerNames</key>
      <array>";
    foreach ($server_list as $cn)
      $retval .= "<string>$cn</string>";
    $retval .= "</array>";

		return $retval;
	}
}
