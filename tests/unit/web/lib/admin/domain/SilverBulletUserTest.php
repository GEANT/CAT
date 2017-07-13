<?php

use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\domain\Attribute;
use web\lib\admin\domain\SilverbulletInvitation;

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
        $invitation = new SilverbulletInvitation($this->newUser);
        $invitation->setQuantity(2);
        $invitation->save();
        $certificate1 = new SilverbulletCertificate($invitation);
        $certificate1->save();
        $certificate2 = new SilverbulletCertificate($invitation);
        $certificate2->save();
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertTrue($existingUser->hasCertificates());
        
        $certificates = $existingUser->getCertificates();
        $this->assertEquals(2, count($certificates));
    }
    
    public function testSetDeactivated(){
        $serial = '29837498273948';
        $cn = 'testCommonName';
        $expiry = date('Y-m-d',strtotime("tomorrow"));
        
        //Testing new user deactivation
        $this->newUser->save();
        $this->assertNotEmpty($this->newUser->getIdentifier());

        $invitation = new SilverbulletInvitation($this->newUser);
        $invitation->setQuantity(2);
        $invitation->save();
        $certificate = new SilverbulletCertificate($invitation);
        $certificate->save();
        $certificateGenerated = new SilverbulletCertificate($invitation);
        $this->profile->generateCertificate($serial, $cn);
        $certificateGenerated->setCertificateDetails($serial, $cn, $expiry);
        $certificateGenerated->save();

        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertEquals(SilverbulletUser::ACTIVE, $existingUser->get(SilverbulletUser::DEACTIVATION_STATUS));
        $this->assertTrue($existingUser->hasCertificates());
        
        $existingUser->setDeactivated(true, $this->profile);
        $existingUser->save();
        
        $this->assertFalse($this->profile->isGeneratedCertificate($serial, $cn));
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load(new Attribute(SilverbulletCertificate::REVOCATION_STATUS, SilverbulletCertificate::NOT_REVOKED));
        $this->assertEquals(SilverbulletUser::INACTIVE, $existingUser->get(SilverbulletUser::DEACTIVATION_STATUS));
        $this->assertFalse($existingUser->hasCertificates());
    
        $deactivationTime = strtotime($existingUser->get(SilverbulletUser::DEACTIVATION_TIME));
        $deactivationExpectedTime = strtotime("now");
        $difference = abs($deactivationTime - $deactivationExpectedTime);
        $this->assertTrue($difference < 10000);

        //Testing new user activation
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
