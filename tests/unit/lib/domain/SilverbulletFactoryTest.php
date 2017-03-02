<?php
require_once(__DIR__ . '/../../core/MockProfileSilverbullet.php');

use lib\domain\SilverbulletCertificate;
use lib\domain\SilverbulletFactory;
use lib\domain\SilverbulletUser;
use lib\http\AddCertificateValidator;
use lib\http\AddUserValidator;
use lib\http\DeleteUserValidator;
use lib\http\RevokeCertificateValidator;
use lib\view\InstitutionPageBuilder;
use lib\http\SaveUsersValidator;

class MockInstitutionPageBuilder extends InstitutionPageBuilder{
    
    private $profile;
    
    public function __construct($profile){
        $this->profile = $profile; 
    }
    
    public function getProfile(){
        return $this->profile;
    }
    
}

class SilverbulletFactoryTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $profile;
    
    private $user = null;
    
    private $databaseHandle;
    
    private $factory;
    
    protected function setUp(){
        $this->databaseHandle = \core\DBConnection::handle('INST');
        
        $this->profile = new MockProfileSilverbullet($this->databaseHandle);
        $builder = new MockInstitutionPageBuilder($this->profile);
        $this->factory = new SilverbulletFactory($builder);
        
        $this->user = new SilverbulletUser($this->profile->identifier, $this->username);
    }
    
    public function testNewUser() {
        $usersBefore = count(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST['command'] = AddUserValidator::COMMAND;
        $_POST[AddUserValidator::PARAM_NAME] = $this->username;
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertFalse($usersAfter > $usersBefore);
        
        $_POST[AddUserValidator::PARAM_EXPIRY] = date('Y-m-d',strtotime("tomorrow"));
        $this->factory->parseRequest();

        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertTrue($usersAfter > $usersBefore);
        
    }
    
    public function testDeactivateUser() {
        $this->user->save();
        
        $usersBefore = count(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST['command'] = SaveUsersValidator::COMMAND;
        $_POST[DeleteUserValidator::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertFalse($usersBefore > $usersAfter);
        
        $_POST[DeleteUserValidator::PARAM_CONFIRMATION] = 'true';
        $this->factory->parseRequest();
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        
        $this->assertTrue($usersBefore > $usersAfter);
    }
    
    public function testNewCertificate() {
        $this->user->save();
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));

        $_POST['command'] = SaveUsersValidator::COMMAND;
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
        
        $_POST['command'] = SaveUsersValidator::COMMAND;
        $_POST[RevokeCertificateValidator::COMMAND] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
        $this->assertTrue($certificatesBefore > $certificatesAfter);
    }

    public function testRevokeGeneratedCertificate() {
        $serial = '29837498273948';
        $cn = 'testCommonName';
        $expiry = date('Y-m-d',strtotime("tomorrow"));
        
        $this->user->save();

        $certificate = new SilverbulletCertificate($this->user);
        $certificate->save();
        
        $this->profile->generateCertificate($serial, $cn);
        $certificate->setCertificateDetails($serial, $cn, $expiry);
        $certificate->save();
    
        $this->assertTrue($this->profile->isGeneratedCertificate($serial, $cn));
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));
    
        $_POST['command'] = SaveUsersValidator::COMMAND;
        $_POST[RevokeCertificateValidator::COMMAND] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
        $this->assertTrue($certificatesBefore > $certificatesAfter);
        $this->assertFalse($this->profile->isGeneratedCertificate($serial, $cn));
        
    }
    
    protected function tearDown(){
        $this->user->delete();
        $this->databaseHandle->exec("DELETE FROM `".SilverbulletUser::TABLE."` WHERE `".SilverbulletUser::USERNAME."`='".$this->username."'");
        $this->profile->delete();
    }
}
