<?php
/* *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php

/**
 * This file defines an abstract class used for generic XML
 * devices
 * actual modules only define available EAP types.
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */


require_once('DeviceConfig.php');
require_once('XML.inc.php');

/**
  * This class implements full functionality of the generic XML device
  * the only fuction of the extenstions of this class is to specify
  * supported EAP methods.
  * Instead of specifying supported EAPS an extension can set $all_eaps to true
  * this will cause the installer to configure all EAP methods supported by 
  * the current profile and declared by the given device.
  */
abstract class Device_XML extends DeviceConfig {
    
    public function __construct() {
        parent::__construct();
    }

/**
 * $lang_scope can be 'global' wheb all lang and all lang-specific information
 * is dumped or 'single' when only the selected lang (and defaults) are passed
 * NOTICE: 'global' is not yet supported
 */
public $lang_scope;
public $all_eaps = FALSE;
public $VendorSpecific;

public function writeDeviceInfo() {
    $ssid_ct=count($this->attributes['internal:SSID']);
    $out = "<p>";
    $out .= sprintf(_("This is a generic configuration file in the IETF <a href='%s'>EAP Metadata -00</a> XML format."),"https://tools.ietf.org/html/draft-winter-opsawg-eap-metadata-00");
    return $out;
    }

public function writeInstaller() {
    $attr = $this->attributes;
    $NAMESPACE = 'urn:RFC4282:realm';
//EAPIdentityProvider  begin
    $eap_idp = new EAPIdentityProvider();
//    $eap_idp->setProperty('ValidUntil',$this->getValidUntil());
// ProviderInfo->
    $eap_idp->setProperty('ProviderInfo',$this->getProviderInfo());
// TODO    $eap_idp->setProperty('VendorSpecific',$this->getVendorSpecific());
//AuthenticationMethods
// TODO
//ID attribute
//lang attribute
    $authmethods = [];
    if($this->all_eaps) {
      $EAPs = [];
      foreach ($attr['all_eaps'] as $eap) {
         if(in_array($eap, $this->supportedEapMethods))
            $EAPs[] = $eap;
      }
    } else
      $EAPs = [ $this->selected_eap];

    foreach ($EAPs as $eap) {
       $authmethods[] = $this->getAuthMethod($eap);
    }
    $authenticationmethods = new AuthenticationMethods();
    $authenticationmethods->setProperty('AuthenticationMethods',$authmethods);
    $eap_idp->setProperty('AuthenticationMethods',$authenticationmethods);
    if(empty($attr['internal:realm'][0])) {
       $eap_idp->setAttribute('ID','undefined');
       $eap_idp->setAttribute('namespace','urn:undefined');
    } else {
       $eap_idp->setAttribute('ID',$attr['internal:realm'][0]);
       $eap_idp->setAttribute('namespace',$NAMESPACE);
    }
    if($this->lang_scope === 'single')
       $eap_idp->setAttribute('lang',$this->lang_index);
    $eap_idp->setAttribute('version','1');
    

// EAPIdentityProvider end

// Generate XML

    $rootname = 'EAPIdentityProviderList';
    $root = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><{$rootname} xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"eap-metadata.xsd\"></{$rootname}>");

    marshalObject($root,$eap_idp);
    $dom = dom_import_simplexml($root)->ownerDocument;
    $res = $dom->schemaValidate(ROOT .'/devices/xml/eap-metadata.xsd');
    $f = fopen($this->installerBasename.'.eap-config',"w");
    fwrite($f,$dom->saveXML());
    fclose($f);
    return($this->installerBasename.'.eap-config');
}

private $AttributeNames =  [
   'support:email' => 'EmailAddress',
   'support:url'   => 'WebAddress',
   'support:phone' => 'Phone',
   'profile:description' => 'Description',
   'support:info_file' => 'TermsOfUse',
   'general:logo_file' => 'ProviderLogo',
];

private function getSimpleAttribute($attr_name) {
   if(isset($this->attributes[$attr_name][0]) && $this->attributes[$attr_name][0]) {
      $a = $this->attributes[$attr_name];
      if(! isset($this->AttributeNames[$attr_name])) {
         $this->loggerInstance->debug(4,"Missing class definition for $attr_name\n");
         return;
      }
      $class_name = $this->AttributeNames[$attr_name];
      $obj = new $class_name();
      $obj->setValue($a[0]);
      return($obj);   
   } else
     return '';
}


private function getSimpleMLAttribute($attr_name) {
   if(isset($this->attributes[$attr_name][0]) && $this->attributes[$attr_name][0]) {
      $a = $this->attributes[$attr_name];
      if(! isset($this->AttributeNames[$attr_name])) {
         $this->loggerInstance->debug(4,"Missing class definition for $attr_name\n");
         return;
      }
      $class_name = $this->AttributeNames[$attr_name];
      $objs = [];
      if($this->lang_scope === 'global') {
         foreach( $a['langs'] as $l => $v ) {
            $l = ( $l === 'C' ? 'any' : $l );
            $obj = new $class_name();
            $obj->setValue($v);
            $obj->setAttributes(['lang' => $l]);
            $objs[] = $obj;
         }
       } else {
         $obj = new $class_name();
         $obj->setValue($a[0]);
         $objs[] = $obj;
      }

      return($objs);
      } else
     return '';
}

private function getDisplayName() {
   $attr = $this->attributes;
   $objs = [];
   if($this->lang_scope === 'global') {
      $I = $attr['general:instname']['langs'];
      if($attr['internal:profile_count'][0] > 1)
        $P = $attr['profile:name']['langs'];
      foreach( $I as $l => $v ) {
        $l = ( $l === 'C' ? 'any' : $l );
        $displayname = new DisplayName();
        if(isset($P)) {
          $p = isset($P[$l]) ? $P[$l] : $P['C'];  
          $v .= ' - '. $p;
        }
        $displayname->setValue($v);
        $displayname->setAttributes(['lang' => $l]);
        $objs[] = $displayname;
      }
   } else {
   $displayname = new DisplayName();
   $v = $attr['general:instname'][0];
   if($attr['internal:profile_count'][0] > 1)
       $v .= ' - '.$attr['profile:name'][0];
   $displayname->setValue($v);
     $objs[] = $displayname;
   }
   return $objs;
}

private function getProviderLogo() {
   $attr = $this->attributes;
   if(isset($attr['general:logo_file'][0])){
      $logo_string = base64_encode($attr['general:logo_file'][0]);
      $logo_mime = 'image/'.$attr['internal:logo_file'][0]['mime'];
      $providerlogo = new ProviderLogo();
      $providerlogo->setAttributes(['mime'=>$logo_mime, 'encoding'=>'base64']);
      $providerlogo->setValue($logo_string);
      return $providerlogo;
  }
}

private function getProviderInfo() {
   $providerinfo = new ProviderInfo();
   $providerinfo->setProperty('DisplayName',$this->getDisplayName());
   $providerinfo->setProperty('Description',$this->getSimpleMLAttribute('profile:description'));
   $providerinfo->setProperty('ProviderLocation',$this->getProvideLocation());
   $providerinfo->setProperty('ProviderLogo',$this->getProviderLogo());
   $providerinfo->setProperty('TermsOfUse',$this->getSimpleMLAttribute('support:info_file'));
   $providerinfo->setProperty('Helpdesk',$this->getHelpdesk());
   return $providerinfo;
}

private function getProvideLocation() {
   $attr = $this->attributes;
   if(isset($attr['general:geo_coordinates'])){
      $at = $attr['general:geo_coordinates'];
      if (count($at) > 1) {
          $at1 = [];
          foreach ($at as $a) {
               $providerlocation = new ProviderLocation();
               $b = unserialize($a);
               $providerlocation->setProperty('Longitude',$b['lon']);
               $providerlocation->setProperty('Latitude',$b['lat']);
               $at1[] = $providerlocation;
          }
         }
         else {
               $providerlocation = new ProviderLocation();
               $b = unserialize($at[0]);
               $providerlocation->setProperty('Longitude',$b['lon']);
               $providerlocation->setProperty('Latitude',$b['lat']);
               $at1 = $providerlocation;
         }
         return$at1;
    }
}
  
private function getHelpdesk() {
   $helpdesk = new Helpdesk();
   $helpdesk->setProperty('EmailAddress',$this->getSimpleMLAttribute('support:email'));
   $helpdesk->setProperty('WebAddress',$this->getSimpleMLAttribute('support:url'));
   $helpdesk->setProperty('Phone',$this->getSimpleMLAttribute('support:phone'));
   return $helpdesk;  
}

private function getCompatibleUses() {
   $SSIDs = $this->attributes['internal:SSID'];
   $compatibleuses = new CompatibleUses();
   $ieee80211s = [];
   foreach ($SSIDs as $ssid => $ciph) {
      $ieee80211 = new IEEE80211();
      $ieee80211->setProperty('SSID',$ssid);
      $ieee80211->setProperty('MinRSNProto', $ciph == 'AES' ? 'CCMP' : 'TKIP');
      $ieee80211s[] = $ieee80211;
   }
   $compatibleuses->setProperty('IEEE80211',$ieee80211s);
// TODO IEEE8023, ABFAB
   return($compatibleuses);
}

private function getAuthenticationMethodParams($eap) {
   $inner = EAP::innerAuth($eap);
   $outer_id = $eap["OUTER"];

   if(isset($inner["METHOD"]) && $inner["METHOD"]) {
      $innerauthmethod = new InnerAuthenticationMethod();
      $class_name = $inner["EAP"] ? 'EAPMethod' : 'NonEAPAuthMethod';
      $eapmethod = new $class_name();
      $eaptype = new  Type();
      $eaptype->setValue($inner['METHOD']); 
      $eapmethod->setProperty('Type',$eaptype);
      $innerauthmethod->setProperty($class_name,$eapmethod);
      return ['inner_method'=>$innerauthmethod,'methodID'=> $outer_id, 'inner_methodID'=>$inner['METHOD']];
   } else
   return ['inner_method'=>0,'methodID'=>$outer_id, 'inner_methodID'=>0];
}

private function getAuthMethod($eap) {
   $attr = $this->attributes;
   $eapParams = $this->getAuthenticationMethodParams($eap);
   $authmethod = new AuthenticationMethod();
   $eapmethod = new EAPMethod();
   $eaptype = new Type();
   $eaptype->setValue($eapParams['methodID']);
   $eapmethod->setProperty('Type',$eaptype);
   if(isset($this->VendorSpecific)) {
     $vendorspecifics = [];
     foreach($this->VendorSpecific as $vs) {
        $vendorspecific = new VendorSpecific();
        $vs['value']->addAttribute('xsi:noNamespaceSchemaLocation',"xxx.xsd");
        $vendorspecific->setValue($vs['value']);
        $vendorspecific->setAttributes(['vendor'=>$vs['vendor']]);
        $vendorspecifics[] = $vendorspecific;
     }
     $eapmethod->setProperty('VendorSpecific',$vendorspecifics);
   }
   $authmethod->setProperty('EAPMethod',$eapmethod);

// ServerSideCredentials
   $serversidecredential = new ServerSideCredential();

// Certificates and server names

   $CAs = [];
   $cas = $attr['internal:CAs'][0];
   foreach ($cas as $ca) {
      $CA = new CA();
      $CA->setValue(base64_encode($ca['der']));
      $CA->setAttributes(['format'=>'X.509', 'encoding'=>'base64']);
      $CAs[] = $CA;
   }

   $serverids = [];
   $servers = $attr['eap:server_name'];
   foreach ($servers as $server) {
      $serverid = new ServerID();
      $serverid->setValue($server);
      $serverids[] = $serverid; 
   }

   $serversidecredential->setProperty('EAPType',$eaptype->getValue());
   $serversidecredential->setProperty('CA',$CAs);
   $serversidecredential->setProperty('ServerID',$serverids);
   $authmethod->setProperty('ServerSideCredential',$serversidecredential);

// ClientSideCredentials

   $clientsidecredential = new ClientSideCredential();

// OuterIdentity 
   if($attr['internal:use_anon_outer'] [0])
      $clientsidecredential->setProperty('OuterIdentity',$attr['internal:anon_local_value'][0].'@'.$attr['internal:realm'][0]);
   $clientsidecredential->setProperty('EAPType',$eapParams['inner_methodID'] ? $eapParams['inner_methodID'] : $eapParams['methodID']);
   $authmethod->setProperty('ClientSideCredential',$clientsidecredential);
   if($eapParams['inner_method'])
      $authmethod->setProperty('InnerAuthenticationMethod',$eapParams['inner_method']);
   return $authmethod;
}


}
