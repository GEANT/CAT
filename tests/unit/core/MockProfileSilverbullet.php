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
 * 
 * @author Zilvinas Vaira
 *
 */
class MockProfileSilverbullet extends \core\ProfileSilverbullet{
    
    /**
     * 
     * @var integer
     */
    private $instId;
    
    /**
     * 
     * @var array
     */
    private $generatedCertificates = array();
    
    /**
     * 
     * @param \core\DBConnection $databaseHandle
     */
    public function __construct(\core\DBConnection $databaseHandle){
        $this->databaseHandle = $databaseHandle;
        if($this->databaseHandle->exec("INSERT INTO institution (country) VALUES('LT')")){
            $this->instId = $this->databaseHandle->lastID();
        }
        if($this->databaseHandle->exec("INSERT INTO profile (inst_id, realm) VALUES($this->instId, 'test.realm.tst')")){
            $this->identifier = $this->databaseHandle->lastID();
        }
        $this->attributes = array(array('name' => 'hiddenprofile:tou_accepted'));
    }
    
    /**
     * 
     */
    public function delete(){
        $this->databaseHandle->exec("DELETE FROM `institution` WHERE `inst_id`='" . $this->instId . "'");
        $this->databaseHandle->exec("DELETE FROM `profile` WHERE `profile_id`='" . $this->identifier . "'");
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \core\ProfileSilverbullet::generateCertificate()
     */
    public function generateCertificate($serial, $cn){
        $this->generatedCertificates[$serial] = $cn;
    }
        
    /**
     * 
     * @param string $serial
     * @param string $cn
     * @return boolean
     */
    public function isGeneratedCertificate($serial, $cn){
        return isset($this->generatedCertificates[$serial]) && $this->generatedCertificates[$serial]==$cn;
    }
}
