<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsWlanProfile
{
    private $name;
    private $encryption;
    private $authentication;
    private $ssids;
    private $eapConfig;
    private $hs20 = false;
    private $domainName = '';
    private $ois = [];

    const MS_ONEX_NS = 'http://www.microsoft.com/networking/OneX/v1';
    
    public function setName($name)
    {
        $this->name = $name;     
    }
   

    public function setEncryption($authentication, $encryption)
    {
        $this->authentication = $authentication;
        $this->encryption = $encryption;
    }
    
    
    /*
     * Prepare data for <SSIDConfig> element
     */
    public function setSSIDs($ssids)
    {
        $this->ssids = $ssids;
    }
    
    /*
     * prepare data for the <EapConfig> element. The contents of this element
     * depends on the implementation of a particular EAP method and thus is
     * delivered by an EAP-specific class.
     */
    public function setEapConfig($eapConfig)
    {
        $this->eapConfig = $eapConfig;
    }
    
    public function setHS20($hs20)
    {
        $this->hs20 = $hs20;
    }

        public function setDomainName($domainName)
    {
        $this->domainName = $domainName;
    }

    public function setOIs($ois)
    {
        $this->ois = $ois;
    }

    public function writeWLANprofile()
    {
        $rootname = 'WLANProfile';
        $dom = new \DOMDocument('1.0', 'utf-8');
        $root = $dom->createElement($rootname);
        $dom->appendChild($root);
        $ns = $dom->createAttributeNS( null, 'xmlns' );
        $ns->value = "http://www.microsoft.com/networking/WLAN/profile/v1";
        $root->appendChild($ns);        
        \core\DeviceXMLmain::marshalObject($dom, $root, 'WLANprofile', $this->getWLANprofile(), '', true);
        $dom->formatOutput = true;
        $xml = $dom->saveXML();
        return($xml);
    }
    
    private function getWLANprofile()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('name', $this->name);
        $ssidElement = $this->getSSIDConfig();
        if (!empty($ssidElement)) {
            $element->setChild('SSIDConfig', $this->getSSIDConfig());
        }
        if ($this->hs20) {
            $element->setChild('Hotspot2', $this->getHotspot2());
        }
        $element->setChild('connectionType', 'ESS');
        $element->setChild('connectionMode', 'auto');
        $element->setChild('autoSwitch', 'false');
        $element->setChild('MSM', $this->getMSM());
        return($element);
    }

    private function getSSIDConfig()
    {
        $ssids = $this->getSSID();
        if (!empty($ssids)) {
            $element = new \core\DeviceXMLmain();
            $element->setChild('SSID', $this->getSSID());
            return($element);
        } else {
            return(NULL);
        }
    }
    
    private function getSSID()
    {
        $retArray = [];
        foreach ($this->ssids as $ssid) {
            $element = new \core\DeviceXMLmain();
            $element->setChild('name', $ssid);
            $retArray[] = $element;
        }
        return($retArray);
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
        $element->setChild('authEncryption', $this->getAuthEncryption());
        if ($this->authentication == 'WPA2') {
            $element->setChild('PMKCacheMode', 'enabled');
            $element->setChild('PMKCacheTTL', 720);
            $element->setChild('PMKCacheSize', 128);
            $element->setChild('preAuthMode', 'disabled');                                
        }
        $element->setChild('OneX', $oneX->getOneX($this->eapConfig), self::MS_ONEX_NS);
        return($element);
    }
    
    private function getAuthEncryption()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('authentication', $this->authentication);
        $element->setChild('encryption', $this->encryption);
        $element->setChild('useOneX', 'true');
        return($element);
    }
    
    private function getHotspot2()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('DomainName', $this->domainName);
        $element->setChild('RoamingConsortium', $this->getRoamingConsortium());
        return($element);

    }
    
    private function getRoamingConsortium()
    {
        $element = new \core\DeviceXMLmain();
        foreach ($this->ois as $oi) {
            $element->setChild('OUI', $oi);
        }
        return($element);
    }


}

