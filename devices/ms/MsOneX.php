<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace devices\ms;

class MsOneX
{
    const MS_EAPHOST_NS = 'http://www.microsoft.com/provisioning/EapHostConfig';
    const MS_EAPCOMMON_NS = 'http://www.microsoft.com/provisioning/EapCommon';
    private $eapConfig;

    public function getOneX($eapConfig)
    {
        $this->eapConfig = $eapConfig;
        $element = new \core\DeviceXMLmain();
        $element->setChild('cacheUserData', 'true');
        $element->setChild('authMode', 'user');
        $element->setChild('EAPConfig', $this->getEAPConfig());
        return($element);
    }
    
    private function getEAPConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EapHostConfig', $this->getEapHostConfig(), self::MS_EAPHOST_NS);
        return($element);
    }
    
    private function getEapHostConfig()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('EapMethod', $this->getEapMethod());
        $element->setChild('Config', $this->eapConfig->config, self::MS_EAPHOST_NS);
        return($element);
    }
    
    private function getEapMethod()
    {
        $element = new \core\DeviceXMLmain();
        $element->setChild('Type', $this->eapConfig->type, self::MS_EAPCOMMON_NS);
        $element->setChild('VendorId',0, self::MS_EAPCOMMON_NS);
        $element->setChild('VendorType',0, self::MS_EAPCOMMON_NS);
        $element->setChild('AuthorId', $this->eapConfig->authorId, self::MS_EAPCOMMON_NS);
        return($element);
    }
}