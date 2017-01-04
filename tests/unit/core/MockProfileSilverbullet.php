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
     * @param DBConnection $databaseHandle
     */
    public function __construct(DBConnection $databaseHandle){
        $this->databaseHandle = $databaseHandle;
        if($this->databaseHandle->exec("INSERT INTO institution (country) VALUES('LT')")){
            $this->instId = $this->databaseHandle->lastID();
        }
        if($this->databaseHandle->exec("INSERT INTO profile (inst_id, realm) VALUES($this->instId, 'test.realm.tst')")){
            $this->identifier = $this->databaseHandle->lastID();
        }
    }
    
    public function delete(){
        $this->databaseHandle->exec("DELETE FROM `institution` WHERE `inst_id`='" . $this->instId . "'");
        $this->databaseHandle->exec("DELETE FROM `profile` WHERE `profile_id`='" . $this->identifier . "'");
    }
}