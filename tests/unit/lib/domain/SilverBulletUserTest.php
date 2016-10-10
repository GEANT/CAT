<?php
require_once(__DIR__ . '../../../../../core/EntityWithDBProperties.php');
require_once(__DIR__ . '../../../../../core/DBConnection.php');
require_once(__DIR__ . '../../../../../core/User.php');

use lib\domain\SilverbulletUser;

class SilverbulletUserTest extends PHPUnit_Framework_TestCase{
    
    private $userId = 'testuserid';
    
    private $institutionId = 1;
    
    private $institutionHandle;
    
    private $userHandle;
    
    
    protected function setup() {
        $this->institutionHandle = DBConnection::handle('INST');
        $this->userHandle = DBConnection::handle('USER');
    }
    
    public function testUserConstructor(){
        $newUser = new SilverbulletUser($this->userId, $this->institutionId);
        
        $existingUser = new SilverbulletUser($this->userId, $this->institutionId);
        $this->assertEquals($newUser->identifier, $existingUser->identifier);
        
        $result = $this->userHandle->exec("SELECT * FROM `user_options` WHERE user_id = '".$this->userId."'");
        $this->assertTrue(mysqli_num_rows($result)>0);
        
        $found = false;
        while($row = mysqli_fetch_assoc($result)){
            if($row['option_name'] == SilverbulletUser::OPTION_SILVERBULLET_USER && $row['option_value'] == 1){
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
    
    public function testInactiveUser(){
        $newUser = new SilverbulletUser($this->userId, $this->institutionId);
        $this->assertFalse($newUser->isActive());
    }
    
    public function testActiveUser(){
        $remove = array();
        $this->institutionHandle->exec("INSERT INTO `certificate` (`inst_id`,`user_id`, `expiry`, `document`) VALUES ('1', '" . $this->userId . "', NOW() + INTERVAL 1 YEAR,'Test certificate contents...')");
        $remove [] = $this->institutionHandle->lastID();
        $this->institutionHandle->exec("INSERT INTO `certificate` (`inst_id`,`user_id`, `expiry`, `document`) VALUES ('1', '" . $this->userId . "', NOW() + INTERVAL 1 YEAR,'Test certificate contents...')");
        $remove [] = $this->institutionHandle->lastID();
        $newUser = new SilverbulletUser($this->userId, $this->institutionId);
        $this->assertTrue($newUser->isActive());
        
        $certificates = $newUser->getCertificates();
        $this->assertEquals(2, count($certificates));
    }
    
    protected function tearDown(){
        $this->institutionHandle->exec("DELETE FROM `certificate` WHERE user_id='".$this->userId."'");
        $this->userHandle->exec("DELETE FROM `user_options` WHERE `user_id`='".$this->userId."';");
    }
    
}