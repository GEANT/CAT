<?php
require_once(__DIR__ . '/../../../../core/MockProfileSilverbullet.php');

use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\http\SilverbulletController;
use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\http\AddCertificateCommand;
use web\lib\admin\http\AddUserCommand;
use web\lib\admin\http\DeleteUserCommand;
use web\lib\admin\http\RevokeCertificateCommand;
use web\lib\admin\view\InstitutionPageBuilder;
use web\lib\admin\http\SaveUsersCommand;
use web\lib\admin\domain\Attribute;
use web\lib\admin\view\DefaultHtmlPage;
use web\lib\admin\http\SilverbulletContext;

if ( !isset( $_SESSION ) ) $_SESSION = array();

class MockInstitutionPageBuilder extends InstitutionPageBuilder{
    
    private $profile;
    
    public function __construct($profile){
        parent::__construct(new DefaultHtmlPage("Test Page"));
        $this->profile = $profile; 
    }
    
    public function getProfile(){
        return $this->profile;
    }
    
}

class SilverbulletControllerTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $profile;
    
    private $user = null;
    
    private $databaseHandle;
    
    private $factory;
    
    protected function setUp(){
        $this->databaseHandle = \core\DBConnection::handle('INST');
        
        $this->profile = new MockProfileSilverbullet($this->databaseHandle);
        $builder = new MockInstitutionPageBuilder($this->profile);
        $context = new SilverbulletContext($builder);
        $this->factory = new SilverbulletController($context);
        $this->user = new SilverbulletUser($this->profile->identifier, $this->username);
    }
    
    public function testNewUser() {
        $usersBefore = count(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST['command'] = AddUserCommand::COMMAND;
        $_POST[AddUserCommand::PARAM_NAME] = $this->username;
        $this->factory->parseRequest();
        
        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertFalse($usersAfter > $usersBefore);
        
        $_POST[AddUserCommand::PARAM_EXPIRY] = date('Y-m-d',strtotime("tomorrow"));
        $this->factory->parseRequest();

        $usersAfter = count(SilverbulletUser::getList($this->profile->identifier));
        $this->assertTrue($usersAfter > $usersBefore);
        
    }

    private function countActiveUsers($users){
        $count = 0;
        foreach ($users as $user) {
            if(!$user->isDeactivated()){
                $count++;
            }
        }
        return $count;
    }
    
    public function testDeactivateUser() {
        $this->user->save();
        
        $usersBefore = $this->countActiveUsers(SilverbulletUser::getList($this->profile->identifier));
        
        $_POST['command'] = SaveUsersCommand::COMMAND;
        $_POST[DeleteUserCommand::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        $usersAfter = $this->countActiveUsers(SilverbulletUser::getList($this->profile->identifier));
        $this->assertFalse($usersBefore > $usersAfter);
        
        $_POST[DeleteUserCommand::PARAM_CONFIRMATION] = 'true';
        $this->factory->parseRequest();
        $usersAfter = $this->countActiveUsers(SilverbulletUser::getList($this->profile->identifier));
        
        $this->assertTrue($usersBefore > $usersAfter);
    }

    public function testFailedNewCertificate() {
        $this->user->save();
    
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));
    
        $_POST['command'] = SaveUsersCommand::COMMAND;
        $_POST[AddCertificateCommand::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
    
        $this->assertFalse($certificatesAfter > $certificatesBefore);
    }
    
    public function testNewCertificate() {
        $this->user->setExpiry('+1 week');
        $this->user->save();
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));

        $_POST['command'] = SaveUsersCommand::COMMAND;
        $_POST[AddCertificateCommand::COMMAND] = $this->user->getIdentifier();
        $this->factory->parseRequest();
        
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user));
        
        $this->assertTrue($certificatesAfter > $certificatesBefore);
    }
    
    public function testRevokeCertificate() {
        $this->user->save();
        $certificate = new SilverbulletCertificate($this->user);
        $certificate->save();
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user));
        
        $_POST['command'] = SaveUsersCommand::COMMAND;
        $_POST[RevokeCertificateCommand::COMMAND] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user, new Attribute(SilverbulletCertificate::REVOCATION_STATUS, SilverbulletCertificate::NOT_REVOKED)));
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
        
        $certificatesBefore = count(SilverbulletCertificate::getList($this->user, new Attribute(SilverbulletCertificate::REVOCATION_STATUS, SilverbulletCertificate::NOT_REVOKED)));
    
        $_POST['command'] = SaveUsersCommand::COMMAND;
        $_POST[RevokeCertificateCommand::COMMAND] = $certificate->getIdentifier();
        $this->factory->parseRequest();
    
        $certificatesAfter = count(SilverbulletCertificate::getList($this->user, new Attribute(SilverbulletCertificate::REVOCATION_STATUS, SilverbulletCertificate::NOT_REVOKED)));
        $this->assertTrue($certificatesBefore > $certificatesAfter);
        $this->assertFalse($this->profile->isGeneratedCertificate($serial, $cn));
        
    }
    
    protected function tearDown(){
        $this->user->delete();
        $this->databaseHandle->exec("DELETE FROM `".SilverbulletUser::TABLE."` WHERE `".SilverbulletUser::USERNAME."`='".$this->username."'");
        $this->profile->delete();
    }
}
