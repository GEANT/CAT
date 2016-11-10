<?php
require_once(__DIR__ . '../../../../../core/EntityWithDBProperties.php');
require_once(__DIR__ . '../../../../../core/DBConnection.php');
require_once(__DIR__ . '../../../../../core/User.php');

use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

class SilverbulletUserTest extends PHPUnit_Framework_TestCase{
    
    private $username = 'testusername';
    
    private $profileId = 1;
    
    /**
     * 
     * @var SilverbulletUser
     */
    private $newUser = null;
    
    protected function setup() {
        $this->newUser = new SilverbulletUser($this->profileId, $this->username);
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
        
        $oneTimeToken = $existingUser->getOneTimeToken();
        $this->assertNotEmpty($oneTimeToken);
        
        $tokenExpiry = $existingUser->getTokenExpiry();
        $this->assertNotEmpty($tokenExpiry);
        
        $tokenExpiryTime = strtotime($tokenExpiry);
        $tokenExpectedTime = strtotime("+1 week");
        $difference = abs($tokenExpiryTime - $tokenExpectedTime);
        $this->assertTrue($difference < 10000);
        
        $list = SilverbulletUser::list($this->profileId);
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
        $this->assertFalse($this->newUser->isActive());
    }
    
    public function testActiveUser(){
        $this->newUser->save();
        $certificate1 = new SilverbulletCertificate($this->newUser);
        $certificate1->save();
        $certificate2 = new SilverbulletCertificate($this->newUser);
        $certificate2->save();
        
        $existingUser = SilverbulletUser::prepare($this->newUser->getIdentifier());
        $existingUser->load();
        $this->assertTrue($existingUser->isActive());
        
        $certificates = $existingUser->getCertificates();
        $this->assertEquals(2, count($certificates));
    }
    
    protected function tearDown(){
        $this->newUser->delete();
    }
    
}