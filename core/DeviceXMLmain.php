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
    

    private $type;
    
    /**
     * constructor, initialises empty set of attributes and value
     */
    public function __construct()
    {
        $this->attributes = [];
        $this->value = '';
        $this->children = [];
        $this->type = NULL;
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
    
    public function setType($type)
    {
        if (empty($type)) {
            return;
        }
        $this->type = $type;
    }
    
    public function getType() {
        return $this->type;
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
    public function setChild($name, $value, $namespace = NULL, $type = NULL)
    {
        $this->children[] = ['name' => $name, 'value' => $value, 'namespace' => $namespace, 'type' => $type];
    }

    /**
     * This method is used to generate 
     * 
     * @param $node \SimpleXMLElement DOM object to which the node is to be attached
     * @param $name the tag name of the child node to be attached
     * @param $object the XXX object which is to be transfored to the DOM object
     *     and attached as a child to the $node
     * @param $namespace of the child
     * @param $root Boolean - if true treat the node as the tree root, i.e do not
     *     attach the resulting object to the parent (as there is none)
     * 
     * marchalObject attaches all children transforming the DeviceXMLmain structure
     * to the root
     */
    public static function marshalObject($domElement, $node, $name, $object, $namespace = NULL, $root = false)
    {
        if (is_null($object)) {
            return;
        }
        if ($root) {
            $childNode = $node;
        } else {
            if ($object->getValue()) {
                $val = preg_replace('/&/', '&amp;', $object->getValue());
                if ($object->getType() === 'cdata') {
                    $childNode = $domElement->createElement($name);
                    $cdata = $domElement->createCDATASection(strval($val));
                    $childNode->appendChild($cdata);
                    $node->appendChild($nextChild);
                } else {
                    $childNode = $domElement->createElement($name, $val);
                }
                $node->appendChild($childNode);
            } else {
                $childNode = $domElement->createElement($name);
                $node->appendChild($childNode);
            }
            if (!empty($namespace)) {
                $ns = $domElement->createAttributeNS(null,'xmlns');
                $ns->value = $namespace;
                $childNode->appendChild($ns);  
            }
        }
        
        if ($object->areAttributes()) {
            $attrs = $object->getAttributes();
            foreach ($attrs as $attrt => $attrv) {
                $attrE = $domElement->createAttribute($attrt);
                $attrE->value = $attrv;
                $childNode->appendChild($attrE);
            }
        }

        if (empty($object->children)) {
            return;
        }
        foreach ($object->children as $child) {
            $nameC = $child['name'];
            $valueC = $child['value'];
            $namespaceC = $child['namespace'];
            if (is_scalar($valueC)) {
                $cl = strval($valueC);
                $nextChild = $domElement->createElement($nameC, $cl);    
                $childNode->appendChild($nextChild);
                if (!empty($namespaceC)) {
                    $ns = $domElement->createAttributeNS(null,'xmlns');
                    $ns->value = $namespaceC;
                    $nextChild->appendChild($ns);  
                }
                continue;
            } 
            if (gettype($valueC) == 'array') {
                foreach ($valueC as $insideValue) {
                    if (is_object($insideValue)) {
                        DeviceXMLmain::marshalObject($domElement, $childNode, $nameC, $insideValue, $namespaceC);
                    }
                    if (is_scalar($insideValue)) {
                        if ($child['type'] === 'cdata') {
                            $nextChild = $domElement->createElement($nameC);
                            $cdata = $domElement->createCDATASection(strval($insideValue));
                            $nextChild->appendChild($cdata);
                            $childNode->appendChild($nextChild);
                        } else {
                            $nextChild = $domElement->createElement($nameC, strval($insideValue));
                            $childNode->appendChild($nextChild);
                        }
                        if (!empty($namespaceC)) {
                            $ns = $domElement->createAttributeNS(null,'xmlns');
                            $ns->value = $namespaceC;
                            $nextChild->appendChild($ns);  
                        }
                    }
                }
                continue;
            } 
            if (gettype($valueC) == 'object') {
                DeviceXMLmain::marshalObject($domElement, $childNode, $nameC, $valueC, $namespaceC);              
            }
        }
    }
}


