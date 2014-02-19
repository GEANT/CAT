<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/**
 * This file contains class definitions and procedures for 
 * generation of a generic XML description of a 802.1x configurator
 *
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 *
 * @package ModuleWriting
 */

require_once("EAP.php");

/**
 * base class extended by every element
 */
class XMLElement {
  private $attributes;
  private $value;

  protected function getObjectVars($obj) {
  return get_object_vars($obj);
  }
/**
 *  @var array $AuthMethodElements is used to limit
 *  XML elements present within ServerSideCredentials and
 *  ClientSideCredentials to ones which are relevant
 *  for a given EAP method.
 *  @var array of XLM element names which are allowed
 *  EAP method names are defined in core/EAP.php
 */
  public static $AuthMethodElements = array (
     'server' => array(
        TLS => array('CA', 'ServerID'),
        FAST => array('CA','ServerID'),
        PEAP => array('CA','ServerID'),
        TTLS => array('CA','ServerID'),
        PWD => array(),
     ),
     'client' => array(
       TLS => array('UserName','Password','ClientCertificate'), 
       MSCHAP2 => array('UserName','Password','AnonymousIdentity'), 
       GTC => array('UserName','OneTimeToken'), 
       NE_PAP => array('UserName','Password','AnonymousIdentity'), 
     )
  );

  public function __construct() {
    $this->attributes = array();
    $this->value = array();
  }
  public function setAttributes($attributes) {
    $this->attributes = $attributes;
  }
  public function getAttributes() {
    return $this->attributes;
  }
  public function setValue($value) {
    $this->value = $value;
  }
  public function getValue() {
    return $this->value;
  }
  public function areAttributes() {
    return empty($this->attributes)?0:1;
  }
  public function setProperty($property,$value) {
    $this->$property = $value;
  }
  public function getAll() {
    $elems = get_object_vars($this);
    $objvars = array();
    foreach ($elems as $key=>$val) 
      if ( ($key!='attributes') && ($key!='value') ) 
        $objvars[$key] = $val;
    return $objvars;
  }
}
class EAPIdentityProvider extends XMLElement {
  protected $NameIDFormat;
  protected $DisplayName;
  protected $Description;
  protected $AuthenticationMethods;
  protected $CompatibleUses;
  protected $ProviderLocation;
  protected $ProviderLogo;
  protected $TermsOfUse;
  protected $Helpdesk;
}
class DisplayName extends XMLElement {
}
class Description extends XMLElement {
}
class TermsOfUse extends XMLElement {
}

class Helpdesk extends XMLElement {
  protected $EmailAddress;
  protected $WebAddress;
  protected $Phone;
}
class EmailAddress extends XMLElement {
}
class WebAddress extends XMLElement {
}
class Phone extends XMLElement {
}
class ProviderLocation extends XMLElement {
  protected $Longitude;
  protected $Latitude;
}
class ProviderLogo  extends XMLElement {
}
class CompatibleUses  extends XMLElement {
  protected $IEEE80211;
  protected $IEEE8023_NetworkID;
  public function setIEEE80211($ieee80211) {
    $this->IEEE80211 = $ieee80211;
  }
}
class IEEE80211 extends XMLElement {
  protected $SSID;
  protected $ConsortiumOID;
  protected $MinRSNProto;
}
class IEEE8023_NetworkID extends XMLElement {
}
class AuthenticationMethods extends XMLElement {
  protected $AuthenticationMethod;
}
class EAPMethod extends XMLElement {
  protected $Type;
  protected $TypeSpecific;
  protected $VendorSpecific;
}
class Type extends XMLElement {
}
class TypeSpecific extends XMLElement {
}
class VendorSpecific extends XMLElement {
}
class NonEAPAuthMethod extends XMLElement {
  protected $Type;
}
class AuthenticationMethod extends XMLElement {
  protected $EAPMethod;
  protected $ServerSideCredential;
  protected $ClientSideCredential;
  protected $InnerAuthenticationMethod;
}
class ServerSideCredential extends XMLElement {
  protected $CA; // multi
  protected $ServerID; //multi
  protected $EAPType;
  public function getAll() {
    if(isset(XMLElement::$AuthMethodElements['server'][$this->EAPType]) && XMLElement::$AuthMethodElements['server'][$this->EAPType]) {
    $E = XMLElement::$AuthMethodElements['server'][$this->EAPType];
    $out = get_object_vars($this);
    $OUT = array();
    foreach ($out as $o => $v) {
       if(in_array($o, $E)) 
         $OUT[$o] = $v;
    }
    return($OUT);
  }
  }
}
class ServerID extends XMLElement {
}
class ClientSideCredential extends XMLElement {
  protected $AnonymousIdentity;
  protected $UserName;
  protected $Password;
  protected $ClientCertificate;
  protected $OneTimeToken;
  protected $PAC;
  protected $EAPType;
  public function getAll() {
    if(isset(XMLElement::$AuthMethodElements['client'][$this->EAPType]) && XMLElement::$AuthMethodElements['client'][$this->EAPType]) {
    $E = XMLElement::$AuthMethodElements['client'][$this->EAPType];
    $out = get_object_vars($this);
    $OUT = array();
debug(4,"EEE:".$this->EAPType.":\n");
debug(4,$E);
    foreach ($out as $o => $v) {
       if(in_array($o, $E)) 
          $OUT[$o] = $v;
    }
    return($OUT);
    }
  }
}
class CA extends XMLElement {
}
class InnerAuthenticationMethod extends XMLElement {
  protected $EAPMethod;
  protected $NonEAPAuthMethod;
  protected $ClientSideCredential;
}

function SimpleXMLElement_append($key, $value) {
  if (trim((string) $value) == '') {
#print '<pre>'; print_r($value); print '</pre>';
    $element = $key->addChild($value->getName());
//print 'addChild '.$value->getName() .'<br>';
    foreach ($value->attributes() as $attKey => $attValue) {
      $element->addAttribute($attKey, $attValue);
    }
    foreach ($value->children() as $child) {
      SimpleXMLElement_append($element, $child);
    }
  } else {
    $element = $key->addChild($value->getName(), trim((string) $value));
  }
}

function marshalObject($node, $object) {

  $name = get_class($object);
  $name = preg_replace("/_/", "-", $name);
  if ($object->getValue()) $val = $object->getValue();
  else $val == '';
  $simplexmlelement = '';
  if ($val instanceof SimpleXMLElement) {
    $simplexmlelement = $val;
    $val = '';
  }
  if ($val) $node = $node->addChild($name, $val);
  else $node = $node->addChild($name);
  if ($object->areAttributes()) {
    $attrs = $object->getAttributes();
    foreach ($attrs as $attrt=>$attrv)
      $node->addAttribute($attrt, $attrv);
  }
  if ($simplexmlelement == '') {
    $fields = $object->getAll();
    if (!empty($fields)) {
      foreach ($fields as $name=>$value) {
        if (getType($value)=='string' || getType($value)=='integer' || getType($value)=='double') {
          $node->addChild($name, $value);
        } else {
          if (getType($value)=='array') {
            foreach ($value as $v)
              if (is_object($v))
                marshalObject($node, $v);
          } else if (getType($value)=='object')  {
            marshalObject($node, $value);
          }
        }
      }
    }
  } else {
    SimpleXMLElement_append($node, $simplexmlelement);
  }
}

