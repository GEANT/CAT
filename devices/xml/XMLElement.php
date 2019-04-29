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
     * 
     * @var string
     */
    private $value;

    /**
     * return object variables for a given object
     * 
     * @param object $obj the object
     * 
     * @return array
     */
    protected function getObjectVars($obj) {
        return get_object_vars($obj);
    }

    /**
     * array $AuthMethodElements is used to limit XML elements present within 
     * ServerSideCredentials and ClientSideCredentials to ones which are 
     * relevant for a given EAP method.
     * array of XML element names which are allowed EAP method names are defined
     * in core/EAP.php
     */
    public const AUTHMETHODELEMENTS = [
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
     * sets a value in the XML object
     * 
     * @param scalar $value the value to set
     * @return void
     * @throws Exception
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
