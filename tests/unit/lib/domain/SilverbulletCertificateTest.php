<?php
require_once(__DIR__ . '../../../../../config/config.php');
require_once(__DIR__ . '../../../../../core/EntityWithDBProperties.php');
require_once(__DIR__ . '../../../../../core/DBConnection.php');
use lib\domain\SilverbulletCertificate;

class SilverBulletCertificateTest extends PHPUnit_Framework_TestCase {
    
    private $institutionHandle;
    
    private $newCertificate;
    
    private $faultyCertificate;
    
    private $userId = 'testuserid';
    
    protected function setUp(){
        $this->institutionHandle = DBConnection::handle('INST');
        $this->newCertificate = new SilverbulletCertificate();
        $this->faultyCertificate = new SilverbulletCertificate(-1);
    }
    
    public function testNewCertificateSuccess() {
        $this->newCertificate->setFields(1, $this->userId);
        $this->newCertificate->save();
        $this->assertNotEmpty($this->newCertificate->identifier);
        
        $existingCertificate = new SilverbulletCertificate($this->newCertificate->identifier);
        $existingCertificate->load();
        $expiry = $existingCertificate->getExpiry();
        $this->assertNotEmpty($expiry);

        $expiryTime = strtotime($expiry);
        $expectedTime = strtotime("+1 year");
        $currentTime = time();
        $difference = abs($expiryTime - $currentTime - $expectedTime + $currentTime);
        $this->assertTrue($difference < 10000);
        
        $list = SilverbulletCertificate::list($this->userId);
        $found = false;
        foreach ($list as $certificate) {
            if($certificate->identifier == $this->newCertificate->identifier){
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        
        $result = $this->newCertificate->delete();
        $this->assertTrue($result);
    }
    
    public function testNewCertificateFailure(){
        $this->newCertificate->save();
        $this->assertEmpty($this->newCertificate->identifier);
        
        $existingCertificate = new SilverbulletCertificate($this->newCertificate->identifier);
        $existingCertificate->load();
        $expiry = $existingCertificate->getExpiry();
        $this->assertEmpty($expiry);
        
        $list = SilverbulletCertificate::list($this->userId);
        $found = false;
        foreach ($list as $certificate) {
            if($certificate->identifier == $this->newCertificate->identifier){
                $found = true;
                break;
            }
        }
        $this->assertFalse($found);
    }
    
    public function testExistingCertificateFailure(){
        $this->faultyCertificate->load();
        $this->assertEmpty($this->faultyCertificate->identifier);
        
        $expiry = $this->faultyCertificate->getExpiry();
        $this->assertEmpty($expiry);
    }
    
    protected function tearDown(){
        $this->institutionHandle->exec("DELETE FROM `user_options` WHERE `user_id`='".$this->userId."';");
    }
    
}
