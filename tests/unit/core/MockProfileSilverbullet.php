<?php
require_once(__DIR__ . '../../../../core/AbstractProfile.php');
require_once(__DIR__ . '../../../../core/ProfileSilverbullet.php');

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class MockProfileSilverbullet extends ProfileSilverbullet{
    
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