<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsPeapProfile extends MsEapProfile
{
    const MS_MSCHAP_NS = 'http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1';
    const MS_PEAP_NS1 = 'http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1';
    const MS_PEAP_NS2 = 'http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2';

    public function __construct() {
        $this->type = \core\common\EAP::PEAP;
        $this->authorId = 0;
    }
    
    protected function getConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Eap', $this->getPeapEap(), self::MS_BASEEAPCONN_NS);
        return($element);
    }
    
    private function getPeapEap()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Type', \core\common\EAP::PEAP);
        $element->setChild('EapType', $this->getPeapEapType(), self::MS_PEAP_NS1);
        return($element);        
    }
    
    private function getPeapEapType()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('ServerValidation', $this->getPeapServerValidation());
        $element->setChild('FastReconnect','true');
        $element->setChild('InnerEapOptional', 'false');
        $element->setChild('Eap', $this->getMsChapV2(), self::MS_BASEEAPCONN_NS);
        $element->setChild('EnableQuarantineChecks', $this->nea);
        $element->setChild('RequireCryptoBinding', 'false');
        if ($this->outerId !== NULL) {
            $element->setChild('PeapExtensions', $this->getPrivacyExtensions());
        }
        return($element);                
    }
    
    private function getPeapServerValidation()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('DisableUserPromptForServerValidation', 'true');
        $element->setChild('ServerNames', $this->serverNames);
        foreach ($this->caList as $ca) {
            $element->setChild('TrustedRootCA', $ca['sha1']);
        }
        return($element);
    }
    
    private function getMsChapV2()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Type', \core\common\EAP::MSCHAP2);
        $element->setChild('EapType', $this->getWinLogonCred(), self::MS_MSCHAP_NS);
        return($element);
    }
    
    private function getPrivacyExtensions() {
        $element = new \core\DeviceXMLmain();
        $element->setChild('IdentityPrivacy', $this->getIdentityPrivacy(), self::MS_PEAP_NS2);
        return($element);
    }
    
    private function getIdentityPrivacy() {
        $element = new \core\DeviceXMLmain();
        preg_match('/^[^@]*/', $this->outerId, $matches);
        $outerUser = empty($matches[0]) ? '@' : $matches[0];
        $element->setChild('EnableIdentityPrivacy', 'true');
        $element->setChild('AnonymousUserName', $outerUser);
        return($element);

    }
    
    private function getWinLogonCred()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('UseWinLogonCredentials', 'false');
        return($element);
    }
}
