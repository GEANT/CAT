<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class GeantLinkTtlsProfile extends MsEapProfile
{
    const GL_EAPMETAS_NS = 'urn:ietf:params:xml:ns:yang:ietf-eap-metadata';
    
    public function __construct()
    {
        $this->type = \core\common\EAP::TTLS;
        $this->authorId = 67532;
    }
    
    public function getConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EAPIdentityProviderList', $this->getEapIdpList(), self::GL_EAPMETAS_NS);
        return($element);
    }
    
    
    private function getEapIdpList()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EAPIdentityProvider', $this->getEapIdp());
        return($element);     
    }
    
    private function getEapIdp()
    {
        $element = new \core\DeviceXMLmain();
        $element->setAttribute('ID', $this->idpId);
        $element->setAttribute('namespace', 'urn:UUID');
        $element->setChild('ProviderInfo', $this->getProviderInfo());
        $element->setChild('AuthenticationMethods', $this->getAuthMethods());
        return($element);
    }
    
    private function getProviderInfo()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('DisplayName', $this->displayName);
        return($element);
    }
    
    private function getAuthMethods()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('AuthenticationMethod', $this->getAuthMethod());
        return($element);        
    }
    
    private function getAuthMethod()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EAPMethod', $this->type);
        $element->setChild('ClientSideCredential', $this->getClientSideCredential());
        $element->setChild('ServerSideCredential', $this->getServerSideCredential());
        $element->setChild('InnerAuthenticationMethod', $this->getInnerAuthenticationMethod());
        $element->setChild('VendorSpecific', $this->getVendorSpecific());
        return($element);        
    }
    
    private function getClientSideCredential()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('allow-save', 'true');
        if ($this->outerId !== NULL) {
            $element->setChild('AnonymousIdentity', $this->outerId);
        }
        return($element);        
    }
    
    private function getServerSideCredential()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('CA', $this->getCA());
        $element->setChild('ServerName', explode(';', $this->serverNames));
        return($element);        
       
    }
    
    private function getInnerAuthenticationMethod()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('NonEAPAuthMethod', $this->innerTypeDisplay);
        return($element);        
    }
    
    private function getVendorSpecific()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('SessionResumption', 'false');
        return($element);        
    }
    
    private function getCA()
    {
        $retArray = [];
        foreach ($this->caList as $ca) {
            if ($ca['root']) {
                $element = new \core\DeviceXMLmain();
                $element->setChild('format', 'PEM');
                $element->setChild('cert-data', base64_encode($ca['der']));
                $retArray[] = $element;
            }
        }
        return($retArray);
    }

}