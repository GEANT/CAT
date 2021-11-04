<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsLanProfile
{
    private $eapConfig;

    const MS_ONEX_NS = 'http://www.microsoft.com/networking/OneX/v1';

    /*
     * prepare data for the <EapConfig> element. The contents of this element
     * depends on the implementation of a particular EAP method and thus is
     * delivered by an EAP-specific class.
     */
    public function setEapConfig($eapConfig)
    {
        $this->eapConfig = $eapConfig;
    }
    
    public function writeLANprofile()
    {
        $rootname = 'LANProfile';        
        $dom = new \DOMDocument('1.0', 'utf-8');
        $root = $dom->createElement($rootname);
        $dom->appendChild($root);
        $ns = $dom->createAttributeNS( null, 'xmlns' );
        $ns->value = "hhttp://www.microsoft.com/networking/LAN/profile/v1";
        $root->appendChild($ns);        
        \core\DeviceXMLmain::marshalObject($dom, $root, 'WLANprofile', $this->getLANprofile(), '', true);
        $dom->formatOutput = true;
        $xml = $dom->saveXML();
        return($xml);
    }
  
    private function getLANprofile()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('MSM', $this->getMSM());
        return($element);
    }
    
    private function getMSM()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('security', $this->getSecurity());
        return($element);
    }
    
    private function getSecurity()
    {
        $element = new \core\DeviceXMLmain();
        $oneX = new \devices\ms\MsOneX();
        $element->setChild('OneXEnforced', 'false');
        $element->setChild('OneXEnabled', 'true');
        $element->setChild('OneX', $oneX->getOneX($this->eapConfig), self::MS_ONEX_NS);
        return($element);
    }
    

}