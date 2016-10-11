<?php

use lib\domain\SilverbulletFactory;
use lib\domain\SilverbulletUser;

class SilverbulletFactoryTest extends PHPUnit_Framework_TestCase{
    
    private $userId = 'testuserid';
    
    private $institutionId = 1;
    
    private $institutionHandle;
    
    private $userHandle;
    
    private $factory;
    
    protected function setUp(){
        $this->institutionHandle = DBConnection::handle('INST');
        $this->userHandle = DBConnection::handle('USER');
        
        $this->factory = new SilverbulletFactory(new IdP($this->institutionId));
    }
    
    public function testNewUser() {
        $_POST[SilverbulletFactory::COMMAND_ADD_USER] = $this->userId;
        $this->factory->parseRequest();
        
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
    
    public function testDeleteUser() {
        $user = new SilverbulletUser($this->userId, $this->institutionId);
        $_POST[SilverbulletFactory::COMMAND_DELETE_USER] = $this->userId;
        $this->factory->parseRequest();
    
        $result = $this->userHandle->exec("SELECT * FROM `user_options` WHERE user_id = '".$this->userId."'");
        $this->assertEquals(0, mysqli_num_rows($result));
    }
    
    public function testNewCertificate() {
        $user = new SilverbulletUser($this->userId, $this->institutionId);
        $_POST[SilverbulletFactory::COMMAND_ADD_CERTIFICATE] = $this->userId;
        $this->factory->parseRequest();
    
        $result = $this->institutionHandle->exec("SELECT * FROM `certificate` WHERE user_id = '".$this->userId."'");
        $this->assertEquals(1, mysqli_num_rows($result));
        
    }
    
    public function testRevokeCertificate() {
        $user = new SilverbulletUser($this->userId, $this->institutionId);
        $this->institutionHandle->exec("INSERT INTO `certificate` (`inst_id`,`user_id`, `expiry`, `document`) VALUES ('".$this->institutionId."', '".$this->userId."', NOW() + INTERVAL 1 YEAR,'Testing certificate file contents..')");
        $_POST[SilverbulletFactory::COMMAND_REVOKE_CERTIFICATE] = $this->institutionHandle->lastID();
        $this->factory->parseRequest();
    
        $result = $this->institutionHandle->exec("SELECT * FROM `certificate` WHERE user_id = '".$this->userId."'");
        $this->assertEquals(0, mysqli_num_rows($result));
    
    }
    
    protected function tearDown(){
        $this->institutionHandle->exec("DELETE FROM `certificate` WHERE user_id='".$this->userId."'");
        $this->userHandle->exec("DELETE FROM `user_options` WHERE `user_id`='".$this->userId."';");
    }
}