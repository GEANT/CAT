<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsTlsProfile extends MsEapProfile
{
    const MS_TLS_NS = 'http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV1';    
        
    public function __construct() {
        $this->type = \core\common\EAP::TLS;
        $this->authorId = 0;
    }
    
    public function getConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Eap', $this->getTlsEap(), self::MS_BASEEAPCONN_NS);
        return($element);
    }
    
    private function getTlsEap()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Type', $this->type);
        $element->setChild('EapType', $this->getTlsEapType(), self::MS_TLS_NS);
        return($element);        
    }
    
    private function getTlsEapType()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('CredentialsSource', $this->getCredentialSource());
        $element->setChild('ServerValidation', $this->getTlsServerValidation());
        $element->setChild('DifferentUsername', $this->otherTlsName);
        $element->setChild('EnableQuarantineChecks', $this->nea);
        return($element);                
    }
    
    private function getCredentialSource()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('CertificateStore','');
        return($element);
    }
    
    private function getTlsServerValidation()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('DisableUserPromptForServerValidation', 'true');
        $element->setChild('ServerNames', $this->serverNames);
        foreach ($this->caList as $ca) {
            $element->setChild('TrustedRootCA', $ca['sha1']);
        }
        return($element);
    }
}
