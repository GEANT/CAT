<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class MockProfileSilverbullet extends \core\ProfileSilverbullet{
    
    /**
     * 
     * @var int
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
     * {@inheritDoc}
     * @see \core\ProfileSilverbullet::revokeCertificate()
     */
    public function revokeCertificate($serial){
        if(isset($this->generatedCertificates[$serial])){
            unset($this->generatedCertificates[$serial]);
            $nowSql = (new \DateTime())->format("Y-m-d H:i:s");
            $this->databaseHandle->exec("UPDATE silverbullet_certificate SET revocation_status = 'REVOKED', revocation_time = ? WHERE serial_number = ?", "si", $nowSql, $serial);
        }
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
