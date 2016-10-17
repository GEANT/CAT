<?php
require_once(__DIR__ . '../../../../../config/config.php');
require_once(__DIR__ . '../../../../../core/EntityWithDBProperties.php');
require_once(__DIR__ . '../../../../../core/DBConnection.php');
use lib\domain\SilverbulletCertificate;
use lib\domain\SilverbulletUser;

class SilverBulletCertificateTest extends PHPUnit_Framework_TestCase {
    
    /**
     * 
     * @var SilverbulletCertificate
     */
    private $newCertificate;
    
    
    /**
     * 
     * @var SilverbulletCertificate
     */
    private $faultyCertificate;
    
    /**
     * 
     * @var integer
     */
    private $institutionId = 1;
    
    /**
     * 
     * @var SilverbulletUser
     */
    private $newUser = null;
    
    /**
     *
     * @var SilverbulletUser
     */
    
    private $faultyUser = null;
    
    protected function setUp(){
        $this->newUser = new SilverbulletUser($this->institutionId, 'testusername');
        $this->newUser->save();
        
        $this->faultyUser = new SilverbulletUser($this->institutionId, 'faultytestusername');
        
        $this->newCertificate = new SilverbulletCertificate($this->newUser);
        $this->faultyCertificate = new SilverbulletCertificate($this->faultyUser);
    }
    
    public function testNewCertificateSuccess() {
        $this->newCertificate->save();
        $this->assertNotEmpty($this->newCertificate->getIdentifier());
        
        $existingCertificate = SilverbulletCertificate::prepare($this->newCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertNotEmpty($existingCertificate->getIdentifier());
        
        $oneTimeToken = $existingCertificate->getOneTimeToken();
        $this->assertNotEmpty($oneTimeToken);
        
        $tokenExpiry = $existingCertificate->getTokenExpiry();
        $this->assertNotEmpty($tokenExpiry);
        
        $expiry = $existingCertificate->getExpiry();
        $this->assertEmpty($expiry);
        
        $tokenExpiryTime = strtotime($tokenExpiry);
        $tokenExpectedTime = strtotime("+1 week");
        $difference = abs($tokenExpiryTime - $tokenExpectedTime);
        $this->assertTrue($difference < 10000);
        
        $list = SilverbulletCertificate::list($this->newUser);
        $found = false;
        foreach ($list as $certificate) {
            if($certificate->getIdentifier() == $this->newCertificate->getIdentifier()){
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        
        $result = $this->newCertificate->delete();
        $this->assertTrue($result);
    }
    
    public function testNewCertificateFailure(){
        $this->faultyCertificate->save();
        $this->assertEmpty($this->faultyCertificate->getIdentifier());
        
        $existingCertificate = SilverbulletCertificate::prepare($this->faultyCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertEmpty($existingCertificate->getOneTimeToken());
        $this->assertEmpty($existingCertificate->getTokenExpiry());
        
        $list = SilverbulletCertificate::list($this->faultyUser);
        $found = false;
        foreach ($list as $certificate) {
            if($certificate->getIdentifier() == $this->faultyCertificate->getIdentifier()){
                $found = true;
                break;
            }
        }
        $this->assertFalse($found);
    }
    
    public function testFaultyCertificateLoadFailure(){
        $this->faultyCertificate->load();
        $this->assertEmpty($this->faultyCertificate->getIdentifier());
    }
    
    protected function tearDown(){
        $this->newUser->delete();
        if(!empty($this->faultyCertificate)){
            $this->faultyCertificate->delete();
        }
    }
    
}
