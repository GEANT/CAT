<?php

use lib\domain\SilverbulletFactory;
use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

class SilverbulletFactoryTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $institutionId = 1;
    
    private $user = null;
    
    private $databaseHandle;
    
    private $factory;
    
    protected function setUp(){
        $this->databaseHandle = DBConnection::handle('INST');
        $this->factory = new SilverbulletFactory(new IdP($this->institutionId));
        
        $this->user = new SilverbulletUser($this->institutionId, $this->username);
    }
    
    public function testNewUser() {
        $usersBefore = count(SilverbulletUser::list($this->institutionId));
        
        $_POST[SilverbulletFactory::COMMAND_ADD_USER] = $this->username;
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::list($this->institutionId));
        $this->assertTrue($usersAfter > $usersBefore);
    }
    
    public function testDeleteUser() {
        $this->user->save();
        
        $usersBefore = count(SilverbulletUser::list($this->institutionId));
        
        $_POST[SilverbulletFactory::COMMAND_DELETE_USER] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::list($this->institutionId));
        
        $this->assertTrue($usersBefore > $usersAfter);
    }
    
    public function testNewCertificate() {
        $this->user->save();
        
        $certificatesBefore = count(SilverbulletCertificate::list($this->user));
        
        $_POST[SilverbulletFactory::COMMAND_ADD_CERTIFICATE] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        
        $certificatesAfter = count(SilverbulletCertificate::list($this->user));
        
        $this->assertTrue($certificatesAfter > $certificatesBefore);
    }
    
    public function testRevokeCertificate() {
        $this->user->save();
        $certificate = new SilverbulletCertificate($this->user);
        $certificate->save();
        
        $certificatesBefore = count(SilverbulletCertificate::list($this->user));
        
        $_POST[SilverbulletFactory::COMMAND_REVOKE_CERTIFICATE] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::list($this->user));
        $this->assertTrue($certificatesBefore > $certificatesAfter);
    }
    
    protected function tearDown(){
        $this->user->delete();
        $this->databaseHandle->exec("DELETE FROM `".SilverbulletUser::TABLE."` WHERE `".SilverbulletUser::USERNAME."`='".$this->username."'");
        
    }
}