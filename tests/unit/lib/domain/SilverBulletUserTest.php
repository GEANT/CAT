<?php

use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

class SilverbulletUserTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $profile;
    
    private $profileId;
    
    /**
     * 
     * @var SilverbulletUser
     */
    private $newUser = null;
    
    protected function setUp() {
        $this->profile = new MockProfileSilverbullet(\core\DBConnection::handle('INST'));
        $this->profileId = $this->profile->identifier;
        $this->newUser = new SilverbulletUser($this->profileId, $this->username);
        $this->newUser->setExpiry('now');
    }
    
    public function testNewUser(){
        $this->newUser->save();
        $this->assertNotEmpty($this->newUser->getIdentifier());
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        
        $username = $existingUser->getUsername();
        $this->assertNotEmpty($username);
        
        $profileId = $existingUser->getProfileId();
        $this->assertNotEmpty($profileId);
        
        $userExpiry = $existingUser->getExpiry();
        $this->assertNotEmpty($userExpiry);
        
        $tokenExpiryTime = strtotime($userExpiry);
        $tokenExpectedTime = strtotime("today");
        $difference = abs($tokenExpiryTime - $tokenExpectedTime);
        $this->assertTrue($difference < 10000);
        
        $list = SilverbulletUser::getList($this->profileId);
        $found = false;
        foreach ($list as $user) {
            if($user->getIdentifier() == $this->newUser->getIdentifier()){
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        
        $result = $this->newUser->delete();
        $this->assertTrue($result);
    }
    
    public function testInactiveUser(){
        $this->newUser->save();
        $this->assertFalse($this->newUser->hasCertificates());
    }
    
    public function testActiveUser(){
        $this->newUser->save();
        $certificate1 = new SilverbulletCertificate($this->newUser);
        $certificate1->save();
        $certificate2 = new SilverbulletCertificate($this->newUser);
        $certificate2->save();
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertTrue($existingUser->hasCertificates());
        
        $certificates = $existingUser->getCertificates();
        $this->assertEquals(2, count($certificates));
    }
    
    public function testSetDeactivated(){
        $this->newUser->save();
        $this->assertNotEmpty($this->newUser->getIdentifier());
        
        $this->newUser->setDeactivated(true, $this->profile);
        $this->newUser->save();
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertEquals(SilverbulletUser::INACTIVE, $existingUser->get(SilverbulletUser::DEACTIVATION_STATUS));
        $this->assertFalse($existingUser->hasCertificates());
    
        $deactivationTime = strtotime($existingUser->get(SilverbulletUser::DEACTIVATION_TIME));
        $deactivationExpectedTime = strtotime("now");
        $difference = abs($deactivationTime - $deactivationExpectedTime);
        $this->assertTrue($difference < 10000);
    
        $this->newUser->setDeactivated(false, $this->profile);
        $this->newUser->save();
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertEquals(SilverbulletUser::ACTIVE, $existingUser->get(SilverbulletUser::DEACTIVATION_STATUS));
    }
    
    protected function tearDown(){
        $this->newUser->delete();
        $this->profile->delete();
    }
    
}