<?php

use lib\view\PageBuilder;
use lib\view\InstitutionPageBuilder;

const CONFIG = ['APPEARANCE' => ['productname' => 'Test Product']];

function valid_IdP($input, $owner){
    if ($input == 1){
        return new MockInstitution();
    }else{
        throw new Exception('IdP '.$input.' not found in database!');
    }
}

class MockInstitution{
    public $name = "Test name";
}

class InstitutionPageBuilderTest extends \PHPUnit_Framework_TestCase{
    
    protected function setup() {
        $_SESSION['user'] = "user";
    }
    
    public function testConstructorSuccess(){
        $_GET['inst_id']=1;
        $builder = new InstitutionPageBuilder("Testing Page", PageBuilder::ADMIN_IDP_USERS);
        $this->assertTrue($builder->isReady());
    }
    
    public function testConstructorFailure(){
        $_GET['inst_id']=-1;
        $builder = new InstitutionPageBuilder("Testing Page", PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
        
        unset($_GET['inst_id']);
        $builder = new InstitutionPageBuilder("Testing Page", PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
    }
    
}