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
 * @contributor Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */
namespace core;
use Exception;

/**
 * Base class currently used by XML based devices lile MS profiles
 * and eap-config profiles. 
 * 
 * The leaf objects may have scalar values which are stored as the $value,
 * non-leaf objects have children stored as $children array
 * 
 * Nodes may also have attributes which are stored as elemens of te $attrinutes
 * array. That array is indexed with attribute names and holds attibute values.
 * 
 * The node name is not being set, it is the parent that knows that.
 *  
 */
class DeviceXMLmain
{
    /**
     * attributes of this object instance
     * 
     * @var array
     */
    private $attributes;
    /**
     * children of the current object
     * 
     * @var array
     */
    
    private $children;
    
    /**
     * The value of a basic element.
     * 
     * @var string
     */
    private $value;

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
            \core\common\EAP::NE_SILVERBULLET => ['UserName', 'ClientCertificate', 'OuterIdentity'],
        ]
    ];

    /**
     * constructor, initialises empty set of attributes and value
     */
    public function __construct()
    {
        $this->attributes = [];
        $this->value = '';
        $this->children = [];
    }
    
    /**
     * sets a list of attributes in the current object instance
     * 
     * @param array $attributes the list of attributes
     * @return void
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * retrieves list of attributes from object instance
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Used to set scalar values of basic XML elements
     * 
     * @param scalar $value
     * @return void
     */
    public function setValue($value)
    {
        if (is_scalar($value)) {
            $this->value = strval($value);
        } else {
            throw new Exception("unexpected value type passed".gettype($value));
        }
    }

    /**
     * retrieves value of a basic XML object instance
     * 
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * does this object have attributes?
     * 
     * @return boolean
     */
    public function areAttributes()
    {
        return empty($this->attributes) ? false : true;
    }

    /**
     * adds an attribute with the given value to the set of attributes
     * @param string $attribute attribute to set
     * @param mixed  $value     value to set
     * @return void
     */
    public function setAttribute($attribute, $value)
    {
        if (!isset($this->attributes)) {
            $this->attributes = [];
        }
        $this->attributes[$attribute] = $value;
    }

    /**
     * adds a child to the current object instance
     * 
     * @param string $name the child element name to set
     * @param mixed  $value    value to set
     * @return void
     */
    public function setChild($name, $value, $namespace = NULL)
    {
        $this->children[] = ['name' => $name, 'value' => $value, 'namespace' => $namespace];
    }

    /**
     * This method is used to generate 
     * 
     * @var $node an SimpleXMLElement object that will serve as the document root
     * 
     * marchalObject attaches all children transforming the DeviceXMLmain structure
     * to the root
     */
    public static function marshalObject($node, $name, $object, $namespace = NULL, $root = false)
    {
        if (is_null($object)) {
            return;
        }
        if ($root) {
            $childNode = $node;
        } else {
            if ($object->getValue()) {
                $val = preg_replace('/&/', '&amp;', $object->getValue());
                $childNode = $node->addChild($name, $val, $namespace);
            } else {
                $childNode = $node->addChild($name, '', $namespace);
            }
        }
        if ($object->areAttributes()) {
            $attrs = $object->getAttributes();
            foreach ($attrs as $attrt => $attrv) {
                $childNode->addAttribute($attrt, $attrv);
            }
        }
//        $fields = $object->children;
        if (empty($object->children)) {
            return;
        }
        foreach ($object->children as $child) {
            $nameC = $child['name'];
            $value = $child['value'];
            $namespace = $child['namespace'];
            if (is_scalar($value)) {
                $childNode->addChild($nameC, strval($value), $namespace);
                continue;
            } 
            if (gettype($value) == 'array') {
                foreach ($value as $insideValue) {
                    if (is_object($insideValue)) {
                        DeviceXMLmain::marshalObject($childNode, $nameC, $insideValue, $namespace);
                    }
                    if (is_scalar($insideValue)) {
                        $childNode->addChild($nameC, strval($insideValue), $namespace);
                    }
                }
                continue;
            } 
            if (gettype($value) == 'object') {
                DeviceXMLmain::marshalObject($childNode, $nameC, $value, $namespace);
            }
        }
    }
}


