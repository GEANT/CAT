<?php
use lib\view\PageBuilder;
use lib\view\InstitutionPageBuilder;
use lib\view\DefaultPage;

require_once(__DIR__ . '../../../../../core/User.php');

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
    
    protected function setUp() {
        $_SESSION['user'] = "user";
    }
    
    public function testConstructorSuccess(){
        $_GET['inst_id']=1;
        $builder = new InstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertTrue($builder->isReady());
    }
    
    public function testConstructorFailure(){
        $_GET['inst_id']=-1;
        $builder = new InstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
        
        unset($_GET['inst_id']);
        $builder = new InstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
    }
    
}