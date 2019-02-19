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
 * This file contains class definitions and procedures for 
 * generation of a generic XML description of a 802.1x configurator
 *
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 *
 * @package ModuleWriting
 */
namespace devices\xml;
use Exception;

/**
 * base class extended by every element
 */
class XMLElement {

    /**
     * attributes of this object instance
     * 
     * @var array
     */
    private $attributes;
    
    /**
     * The value of the element.
     * @var string
     */
    private $value;

    /**
     * return object variables for a given object
     * 
     * @param object $obj the object
     * @return array
     */
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
    public static $authMethodElements = [
        'server' => [
            \core\common\EAP::TLS => ['CA', 'ServerID'],
            \core\common\EAP::FAST => ['CA', 'ServerID'],
            \core\common\EAP::PEAP => ['CA', 'ServerID'],
            \core\common\EAP::TTLS => ['CA', 'ServerID'],
            \core\common\EAP::PWD => ['ServerID'],
        ],
        'client' => [
            \core\common\EAP::TLS => ['UserName', 'Password', 'ClientCertificate'],
            \core\common\EAP::MSCHAP2 => ['UserName', 'Password', 'OuterIdentity', 'InnerIdentitySuffix', 'InnerIdentityHint'],
            \core\common\EAP::GTC => ['UserName', 'OneTimeToken'],
            \core\common\EAP::NE_PAP => ['UserName', 'Password', 'OuterIdentity', 'InnerIdentitySuffix', 'InnerIdentityHint'],
            \core\common\EAP::NE_SILVERBULLET => ['UserName', 'ClientCertificate'],
        ]
    ];

    /**
     * constructor, initialises empty set of attributes and value
     */
    public function __construct() {
        $this->attributes = [];
        $this->value = '';
    }

    /**
     * sets a list of attributes in the current object instance
     * 
     * @param array $attributes the list of attributes
     * @return void
     */
    public function setAttributes($attributes) {
        $this->attributes = $attributes;
    }

    /**
     * retrieves list of attributes from object instance
     * @return array
     */
    public function getAttributes() {
        return $this->attributes;
    }

    /**
     * 
     * @param scalar $value
     * @return void
     */
    public function setValue($value) {
        if (is_scalar($value)) {
            $this->value = strval($value);
        } else {
            throw new Exception("unexpected value type passed" . gettype($value));
        }
    }

    /**
     * retrieves value of object instance
     * 
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * does this object have attributes?
     * 
     * @return int
     */
    public function areAttributes() {
        return empty($this->attributes) ? 0 : 1;
    }

    /**
     * adds an attribute with the given value to the set of attributes
     * @param string $attribute attribute to set
     * @param mixed  $value     value to set
     * @return void
     */
    public function setAttribute($attribute, $value) {
        if (!isset($this->attributes)) {
            $this->attributes = [];
        }
        $this->attributes[$attribute] = $value;
    }

    /**
     * adds a property to the current object instance
     * 
     * @param string $property property to set
     * @param mixed  $value    value to set
     * @return void
     */
    public function setProperty($property, $value) {
        $this->$property = $value;
    }

    /**
     * retrieve all properties of the object instance (attributes and value)
     * 
     * @return array
     */
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
    protected $CredentialApplicability;
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
    protected $EAPType;

    /**
     * 
     * @return array
     */
    public function getAll() {
        if (isset(XMLElement::$authMethodElements['server'][$this->EAPType]) && XMLElement::$authMethodElements['server'][$this->EAPType]) {
            $element = XMLElement::$authMethodElements['server'][$this->EAPType];
            $objectVariables = get_object_vars($this);
            $outArray = [];
            foreach ($objectVariables as $o => $v) {
                if (in_array($o, $element)) {
                    $outArray[$o] = $v;
                }
            }
            return $outArray;
        }
    }

}

class ServerID extends XMLElement {
    
}

class ClientSideCredential extends XMLElement {

    protected $OuterIdentity;
    protected $InnerIdentityPrefix;
    protected $InnerIdentitySuffix;
    protected $InnerIdentityHint;
    protected $UserName;
    protected $Password;
    protected $ClientCertificate;
    protected $Passphrase;
    protected $PAC;
    protected $ProvisionPAC;
    protected $EAPType;


    /**
     * 
     * @return array
     */
    public function getAll() {
        if (isset(XMLElement::$authMethodElements['client'][$this->EAPType]) && XMLElement::$authMethodElements['client'][$this->EAPType]) {
            $element = XMLElement::$authMethodElements['client'][$this->EAPType];
            $objectVars = get_object_vars($this);
            $outputArray = [];
            foreach ($objectVars as $name => $value) {
                if (in_array($name, $element)) {
                    $outputArray[$name] = $value;
                }
            }
            return $outputArray;
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

class CredentialApplicability extends XMLElement {
    protected $IEEE80211;
    protected $IEEE8023;
}

class IEEE80211 extends XMLElement {
    protected $SSID;
    protected $ConsortiumOID;
    protected $MinRSNProto;
}

class SSID extends XMLElement {
    
}

class ConsortiumOID extends XMLElement {
    
}

class MinRSNProto extends XMLElement {
    
}

class IEEE8023 extends XMLElement {
    protected $NetworkID;
}

class NetworkID extends XMLElement {
    
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


/**
 * 
 * @param \SimpleXMLElement $key
 * @param \SimpleXMLElement $value
 * @return void
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

/**
 * 
 * @param \SimpleXMLElement   $node
 * @param EAPIdentityProvider $object
 * @return void
 */
function marshalObject($node, $object) {
    $qualClassName = get_class($object);
    // remove namespace qualifier
    $pos = strrpos($qualClassName, '\\');
    $className =  substr($qualClassName, $pos + 1);
    $name = preg_replace("/_/", "-", $className);
    if ($object->getValue()) {
        $val = preg_replace('/&/', '&amp;', $object->getValue());
        $childNode = $node->addChild($name, $val);
    } else {
        $childNode = $node->addChild($name);
    }
    if ($object->areAttributes()) {
        $attrs = $object->getAttributes();
        foreach ($attrs as $attrt => $attrv) {
            $childNode->addAttribute($attrt, $attrv);
        }
    }
    $fields = $object->getAll();
    if (empty($fields)) {
        return;
    }
    foreach ($fields as $name => $value) {
        if (is_scalar($value)) {
            $childNode->addChild($name, strval($value));
            continue;
        } 
        if (gettype($value) == 'array') {
            foreach ($value as $insideValue) {
                if (is_object($insideValue)) {
                    marshalObject($childNode, $insideValue);
                }
            }
            continue;
        } 
        if (gettype($value) == 'object') {
            marshalObject($childNode, $value);
        }
    }
}
