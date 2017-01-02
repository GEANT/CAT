<?php
require_once(__DIR__ . '/../../core/MockProfileSilverbullet.php');

use lib\domain\SilverbulletFactory;
use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;
use lib\http\AddUserValidator;
use lib\http\DeleteUserValidator;
use lib\http\AddCertificateValidator;
use lib\http\RevokeCertificateValidator;

class SilverbulletFactoryTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $profile;
    
    private $user = null;
    
    private $databaseHandle;
    
    private $factory;
    
    protected function setUp(){
        $this->databaseHandle = DBConnection::handle('INST');
        
        $this->profile = new MockProfileSilverbullet($this->databaseHandle);
        $this->factory = new SilverbulletFactory($this->profile);
        
        $this->user = new SilverbulletUser($this->profile->identifier, $this->username);
    }
    
    public function testNewUser() {
        $usersBefore = count(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST[AddUserValidator::COMMAND] = $this->username;
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertFalse($usersAfter > $usersBefore);
        
        $_POST[AddUserValidator::PARAM_EXPIRY] = date('Y-m-d',strtotime("tomorrow"));
        $this->factory->parseRequest();

        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertTrue($usersAfter > $usersBefore);
        
    }
    
    public function testDeleteUser() {
        $this->user->save();
        
        $usersBefore = count(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST[DeleteUserValidator::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        
        $this->assertTrue($usersBefore > $usersAfter);
    }
    
    public function testNewCertificate() {
        $this->user->save();
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));
        
        $_POST[AddCertificateValidator::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
        
        $this->assertTrue($certificatesAfter > $certificatesBefore);
    }
    
    public function testRevokeCertificate() {
        $this->user->save();
        $certificate = new SilverbulletCertificate($this->user);
        $certificate->save();
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));
        
        $_POST[RevokeCertificateValidator::COMMAND] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
        $this->assertTrue($certificatesBefore > $certificatesAfter);
    }
    
    protected function tearDown(){
        $this->user->delete();
        $this->databaseHandle->exec("DELETE FROM `".SilverbulletUser::TABLE."` WHERE `".SilverbulletUser::USERNAME."`='".$this->username."'");
        $this->profile->delete();
    }
}