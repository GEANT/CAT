<?php
use web\lib\admin\storage\SessionStorage;

if ( !isset( $_SESSION ) ) $_SESSION = array();

class SessionStorageTest extends PHPUnit_Framework_TestCase{
    protected $backupGlobalsBlacklist = array( '_SESSION' );
    private $package1 = "testpackage1";
    private $package2 = "testpackage2";
    
    public function testGetInstance(){
        $globalSession = SessionStorage::getInstance();
        $scopedSession1 = SessionStorage::getInstance($this->package1);
        $scopedSession2 = SessionStorage::getInstance($this->package2);
        
        $this->assertEquals($globalSession, SessionStorage::getInstance());
        $this->assertEquals($scopedSession1, SessionStorage::getInstance($this->package1));
        $this->assertEquals($scopedSession2, SessionStorage::getInstance($this->package2));
        $this->assertNotEquals($globalSession, $scopedSession1);
        $this->assertNotEquals($globalSession, $scopedSession2);
        $this->assertNotEquals($scopedSession1, $scopedSession2);
    }
    
    public function testPut(){
        $testPutGlobal = "testputglobal";
        $globalSession = SessionStorage::getInstance();
        $globalSession->put($testPutGlobal, $testPutGlobal);
        
        $testPutScoped1 = "testputscoped1";
        $scopedSession1 = SessionStorage::getInstance($this->package1);
        $scopedSession1->put($testPutScoped1, $testPutScoped1);

        $testPutScoped2 = "testputscoped2";
        $scopedSession2 = SessionStorage::getInstance($this->package2);
        $scopedSession2->put($testPutScoped2, $testPutScoped2);
        $this->assertEquals($_SESSION[SessionStorage::INDEX][SessionStorage::WIDE][$testPutGlobal], $testPutGlobal);
        $this->assertEquals($_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1][$testPutScoped1], $testPutScoped1);
        $this->assertEquals($_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2][$testPutScoped2], $testPutScoped2);
        $this->assertNotEquals($_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1][$testPutScoped1], $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE][$testPutGlobal]);
        $this->assertNotEquals($_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2][$testPutScoped2], $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE][$testPutGlobal]);
        $this->assertNotEquals($_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1][$testPutScoped1], $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2][$testPutScoped2]);
    }
    
    public function testGet(){
        $testGetGlobal = "testgetglobal";
        $globalSession = SessionStorage::getInstance();
        $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE][$testGetGlobal] = $testGetGlobal;
        
        $testGetScoped1 = "testgetscoped1";
        $scopedSession1 = SessionStorage::getInstance($this->package1);
        $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1][$testGetScoped1] = $testGetScoped1;
        
        $testGetScoped2 = "testgetscoped2";
        $scopedSession2 = SessionStorage::getInstance($this->package2);
        $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2][$testGetScoped2] = $testGetScoped2;
        
        $this->assertEquals($testGetGlobal, $globalSession->get($testGetGlobal));
        $this->assertEquals($testGetScoped1, $scopedSession1->get($testGetScoped1));
        $this->assertEquals($testGetScoped2, $scopedSession2->get($testGetScoped2));
        $this->assertNotEquals($globalSession->get($testGetGlobal), $scopedSession1->get($testGetScoped1));
        $this->assertNotEquals($globalSession->get($testGetGlobal), $scopedSession2->get($testGetScoped2));
        $this->assertNotEquals($scopedSession2->get($testGetScoped2), $scopedSession1->get($testGetScoped1));
    }
    
    public function testDelete(){
        $testGetGlobal = "testgetglobal";
        $globalSession = SessionStorage::getInstance();
        $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE][$testGetGlobal] = $testGetGlobal;
        $this->assertArrayHasKey($testGetGlobal, $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE]);
        $globalSession->delete($testGetGlobal);
        $this->assertArrayNotHasKey($testGetGlobal, $_SESSION[SessionStorage::INDEX][SessionStorage::WIDE]);
        
        $testGetScoped1 = "testaddpackage";
        $scopedSession1 = SessionStorage::getInstance($this->package1);
        $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1][$testGetScoped1] = $testGetScoped1;
        $this->assertArrayHasKey($testGetScoped1, $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1]);
        $scopedSession1->delete($testGetScoped1);
        $this->assertArrayNotHasKey($testGetScoped1, $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package1]);
        
        $testGetScoped2 = "testaddpackage";
        $scopedSession2 = SessionStorage::getInstance($this->package2);
        $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2][$testGetScoped2] = $testGetScoped2;
        $this->assertArrayHasKey($testGetScoped2, $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2]);
        $scopedSession2->delete($testGetScoped2);
        $this->assertArrayNotHasKey($testGetScoped2, $_SESSION[SessionStorage::INDEX][SessionStorage::SCOPED][$this->package2]);
    }
}
