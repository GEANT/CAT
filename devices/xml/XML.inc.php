<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
    public static $AuthMethodElements = [
        'server' => [
            TLS => ['CA', 'ServerID'],
            FAST => ['CA', 'ServerID'],
            PEAP => ['CA', 'ServerID'],
            TTLS => ['CA', 'ServerID'],
            PWD => [],
        ],
        'client' => [
            TLS => ['UserName', 'Password', 'ClientCertificate'],
            MSCHAP2 => ['UserName', 'Password', 'OuterIdentity'],
            GTC => ['UserName', 'OneTimeToken'],
            NE_PAP => ['UserName', 'Password', 'OuterIdentity'],
        ]
    ];

    public function __construct() {
        $this->attributes = [];
        $this->value = [];
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
        return empty($this->attributes) ? 0 : 1;
    }

    public function setAttribute($attribute, $value) {
        if (!isset($this->attributes)) {
            $this->attributes = [];
        }
        $this->attributes[$attribute] = $value;
    }

    public function setProperty($property, $value) {
        $this->$property = $value;
    }

    public function getAll() {
        $elems = get_object_vars($this);
        $objvars = [];
        foreach ($elems as $key => $val) {
            if (($key != 'attributes') && ($key != 'value')) {
                $objvars[$key] = $val;
            }
        }
        return $objvars;
    }

}

class EAPIdentityProvider extends XMLElement {

    protected $ValidUntil;
    protected $AuthenticationMethods;
    protected $ProviderInfo;
    protected $VendorSpecific;

}

class AuthenticationMethods extends XMLElement {

    protected $AuthenticationMethod;

}

class AuthenticationMethod extends XMLElement {

    protected $EAPMethod;
    protected $ServerSideCredential;
    protected $ClientSideCredential;
    protected $InnerAuthenticationMethod;

}

class EAPMethod extends XMLElement {

    protected $Type;
    protected $TypeSpecific;
    protected $VendorSpecific;

}

class NonEAPAuthMethod extends XMLElement {

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

class ServerSideCredential extends XMLElement {

    protected $CA; // multi
    protected $ServerID; //multi

    public function getAll() {
        if (isset(XMLElement::$AuthMethodElements['server'][$this->EAPType]) && XMLElement::$AuthMethodElements['server'][$this->EAPType]) {
            $E = XMLElement::$AuthMethodElements['server'][$this->EAPType];
            $out = get_object_vars($this);
            $OUT = [];
            foreach ($out as $o => $v) {
                if (in_array($o, $E)) {
                    $OUT[$o] = $v;
                }
            }
            return($OUT);
        }
    }

}

class ServerID extends XMLElement {
    
}

class ClientSideCredential extends XMLElement {

    protected $OuterIdentity;
    protected $UserName;
    protected $Password;
    protected $ClientCertificate;
    protected $Passphrase;
    protected $PAC;
    protected $ProvisionPAC;

    public function getAll() {
        if (isset(XMLElement::$AuthMethodElements['client'][$this->EAPType]) && XMLElement::$AuthMethodElements['client'][$this->EAPType]) {
            $E = XMLElement::$AuthMethodElements['client'][$this->EAPType];
            $out = get_object_vars($this);
            $OUT = [];
            $loggerInstance = new Logging();
            $loggerInstance->debug(4, "EEE:" . $this->EAPType . ":\n");
            $loggerInstance->debug(4, $E);
            foreach ($out as $o => $v) {
                if (in_array($o, $E)) {
                    $OUT[$o] = $v;
                }
            }
            return($OUT);
        }
    }

}

class ClientCertificate extends XMLElement {
    
}

class CA extends XMLElement {
    
}

class InnerAuthenticationMethod extends XMLElement {

    protected $EAPMethod;
    protected $NonEAPAuthMethod;
    protected $ServerSideCredential;
    protected $ClientSideCredential;

}

class ProviderInfo extends XMLElement {

    protected $DisplayName;
    protected $Description;
    protected $ProviderLocation;
    protected $ProviderLogo;
    protected $TermsOfUse;
    protected $Helpdesk;

}

class DisplayName extends XMLElement {
    
}

class Description extends XMLElement {
    
}

class ProviderLocation extends XMLElement {

    protected $Longitude;
    protected $Latitude;

}

class ProviderLogo extends XMLElement {
    
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

/*
  class CompatibleUses  extends XMLElement {
  protected $IEEE80211;
  protected $IEEE8023;
  protected $ABFAB;
  }
  class IEEE80211 extends XMLElement {
  protected $SSID;
  protected $ConsortiumOID;
  protected $MinRSNProto;
  }

  class IEEE8023 extends XMLElement {
  protected $NetworkID;
  }
  class ABFAB extends XMLElement {
  protected $ServiceIdentifier;
  }

 */

function SimpleXMLElement_append($key, $value) {
    if (trim((string) $value) == '') {
        $element = $key->addChild($value->getName());
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
    $val = '';
    $className = get_class($object);
    $name = preg_replace("/_/", "-", $className);
    if ($object->getValue()) {
        $val = $object->getValue();
    }
    $simplexmlelement = '';
    if ($val instanceof SimpleXMLElement) {
        $simplexmlelement = $val;
        $val = '';
    }
    if ($val) {
        if (getType($val) == 'string') {
            $val = preg_replace('/&/', '&amp;', $val);
        }
        $node = $node->addChild($name, $val);
    } else {
        $node = $node->addChild($name);
    }
    if ($object->areAttributes()) {
        $attrs = $object->getAttributes();
        foreach ($attrs as $attrt => $attrv) {
            $node->addAttribute($attrt, $attrv);
        }
    }
    if ($simplexmlelement != '') {
        SimpleXMLElement_append($node, $simplexmlelement);
        return;
    }
    $fields = $object->getAll();
    if (empty($fields)) {
        return;
    }

    foreach ($fields as $name => $value) {
        if (getType($value) == 'string' || getType($value) == 'integer' || getType($value) == 'double') {
            $node->addChild($name, $value);
        } else {
            if (getType($value) == 'array') {
                foreach ($value as $v)
                    if (is_object($v)) {
                        marshalObject($node, $v);
                    }
            } else {
                if (getType($value) == 'object') {
                    marshalObject($node, $value);
                }
            }
        }
    }
}
