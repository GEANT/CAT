<?php

use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\domain\SilverbulletInvitation;

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
    
    private $profile;
    
    /**
     * 
     * @var int|string
     */
    private $profileId;
    
    
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
        $this->profile = new MockProfileSilverbullet(\core\DBConnection::handle('INST'));
        $this->profileId = $this->profile->identifier;
        
        $this->newUser = new SilverbulletUser($this->profileId, 'testusername');
        $this->newUser->save();
        
        $this->faultyUser = new SilverbulletUser($this->profileId, 'faultytestusername');
        
        $newUserInvitation = new SilverbulletInvitation($this->newUser);
        $newUserInvitation->save();
        $this->newCertificate = new SilverbulletCertificate($newUserInvitation);
        $faultyUserInvitation = new SilverbulletInvitation($this->faultyUser);
        $faultyUserInvitation->save();
        $this->faultyCertificate = new SilverbulletCertificate($faultyUserInvitation);
    }
    
    public function testNewCertificateSuccess() {
        $this->newCertificate->save();
        $this->assertNotEmpty($this->newCertificate->getIdentifier());
        
        $existingCertificate = SilverbulletCertificate::prepare($this->newCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertNotEmpty($existingCertificate->getIdentifier());
        
        $expiry = $existingCertificate->get(SilverbulletCertificate::EXPIRY);
        $this->assertNotEmpty($expiry);
        
        $list = SilverbulletCertificate::getList($this->newUser);
        $found = false;
        foreach ($list as $certificate) {
            if($certificate->getIdentifier() == $this->newCertificate->getIdentifier()){
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        
        $this->newCertificate->setCertificateDetails('0498230984238023', 'testcommonname', "+1 week");
        $this->assertTrue($this->newCertificate->save());
        
        $existingCertificate = SilverbulletCertificate::prepare($this->newCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertEquals('0498230984238023', $existingCertificate->getSerialNumber());
        $this->assertEquals('testcommonname', $existingCertificate->getCommonName());
        
        $result = $this->newCertificate->delete();
        $this->assertTrue($result);
    }
    
    public function testNewCertificateFailure(){
        $this->faultyCertificate->save();
        $this->assertEmpty($this->faultyCertificate->getIdentifier());
        
        $existingCertificate = SilverbulletCertificate::prepare($this->faultyCertificate->getIdentifier());
        $existingCertificate->load();
        
        $list = SilverbulletCertificate::getList($this->faultyUser);
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
    
    public function testSetRevoked(){
        $this->newCertificate->save();
        $this->assertNotEmpty($this->newCertificate->getIdentifier());
        
        $this->newCertificate->setRevoked(true);
        $this->newCertificate->save();
        
        $existingCertificate = SilverbulletCertificate::prepare($this->newCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertEquals(SilverbulletCertificate::REVOKED, $existingCertificate->get(SilverbulletCertificate::REVOCATION_STATUS));

        $revocationTime = strtotime($existingCertificate->get(SilverbulletCertificate::REVOCATION_TIME));
        $revocationExpectedTime = strtotime("now");
        $difference = abs($revocationTime - $revocationExpectedTime);
        $this->assertTrue($difference < 10000);
        
        $this->newCertificate->setRevoked(false);
        $this->newCertificate->save();
        
        $existingCertificate = SilverbulletCertificate::prepare($this->newCertificate->getIdentifier());
        $existingCertificate->load();
        $this->assertEquals(SilverbulletCertificate::NOT_REVOKED, $existingCertificate->get(SilverbulletCertificate::REVOCATION_STATUS));
    }
    
    protected function tearDown(){
        $this->newUser->delete();
        if(!empty($this->faultyCertificate)){
            $this->faultyCertificate->delete();
        }
        $this->profile->delete();
    }
    
}
