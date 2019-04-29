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

/**
 * 
 * @param \SimpleXMLElement $key   where to append the new element
 * @param \SimpleXMLElement $value the value to append
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
        $key->addChild($value->getName(), trim((string) $value));
    }
}

/**
 * 
 * @param \SimpleXMLElement   $node   the XML node to marshal
 * @param EAPIdentityProvider $object the Object
 * @return void
 */
function marshalObject($node, $object) {
    $qualClassName = get_class($object);
    // remove namespace qualifier
    $pos = strrpos($qualClassName, '\\');
    $className = substr($qualClassName, $pos + 1);
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
