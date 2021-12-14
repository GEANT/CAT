<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsTtlsProfile extends MsEapProfile
{
    const MS_TTLS_NS = 'http://www.microsoft.com/provisioning/EapTtlsConnectionPropertiesV1';

    public function __construct()
    {
        $this->type = \core\common\EAP::TTLS;
        $this->authorId = 311;
    }
    
    public function getConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EapTtls', $this->getEapTtls(), self::MS_TTLS_NS);
        return($element);
    }
    
    private function getEapTtls()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('ServerValidation', $this->getTtlsServerValidation());
        $element->setChild('Phase2Authentication', $this->getPhase2Auth());
        $element->setChild('Phase1Identity', $this->getPhase1Identity());
        return($element);
    }
    
    private function getTtlsServerValidation()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('ServerNames', $this->serverNames);
        $element->setChild('TrustedRootCAHash', $this->getTrustedRootCAHash());
        $element->setChild('DisablePrompt', 'true');
        return($element);
    }
    
    private function getTrustedRootCAHash()
    {
        $retArray = [];
        foreach ($this->caList as $ca) {
            $hash = $ca['sha1'];
            $retArray[] = chunk_split($hash, 2, ' ');
        }
        return($retArray);
    }
    
    private function getPhase2Auth() {
        $element = new \core\DeviceXMLmain();
        if ($this->innerType == \core\common\EAP::MSCHAP2) {
            $element->setChild('MSCHAPv2Authentication', $this->getWinlogonCred());
        }
        if ($this->innerType == \core\common\EAP::NONE) {
            $element->setChild('PAPAuthentication', '');
        }
        return($element);
    }
    
    private function getWinlogonCred() {
        $element = new \core\DeviceXMLmain();
        $element->setChild('UseWinlogonCredentials', 'false');
        return($element);
    }

    private function getPhase1Identity()
    {
        $element = new \core\DeviceXMLmain();
        if ($this->outerId == NULL) {
            $element->setChild('IdentityPrivacy', 'false');
        } else {
            $element->setChild('IdentityPrivacy', 'true');
            $element->setChild('AnonymousIdentity', $this->outerId);
        }
        return($element);
    }
    
    private function getTtlsTustedRoot($hash)
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('TrustedRootCAHash', chunk_split($hash, 2, ' '));
        return($element);
    }    
}
