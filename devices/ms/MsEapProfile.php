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
 * This class contains properties which are used to set up individual EAP methods
 * Some of the roperties are used only by a subset of EAP handlers.
 * The properties are set up with public interface methods.
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\ms;

abstract class MsEapProfile
{
    public $type;
    public $authorId;
    public $config;


    protected $caList;
    protected $serverNames;
    protected $outerId;
    protected $nea;
    protected $otherTlsName;
    protected $displayName;
    protected $idpId;
    protected $innerType;
    protected $innerTypeDisplay;
   
    const MS_BASEEAPCONN_NS = 'http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1';
    
    public function setCAList($caList)
    {
        $this->caList = $caList;
    }
    
    public function setServerNames($serverNames)
    {
        $this->serverNames = $serverNames;
    }
    
    public function setOuterId($outerId)
    {
        $this->outerId = $outerId;
    }    
    
    public function setNea($nea)
    {
        $this->nea = $nea;
    }
    
    public function setOtherTlsName($otherTlsName)
    {
        $this->otherTlsName = $otherTlsName;
    }
    
    public function setConfig()
    {
        $this->config = $this->getConfig();
    }
    
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
    }
    
    public function setIdPId($id)
    {
        $this->idpId = $id;
    }
    
    public function setInnerType($type)
    {
        $this->innerType = $type;
    }
    
    public function setInnerTypeDisplay($type)
    {
        $this->innerTypeDisplay = $type;
    }

    /**
     * The getConfig method is required in every EAP handler
     */
    abstract protected function getConfig();
}
