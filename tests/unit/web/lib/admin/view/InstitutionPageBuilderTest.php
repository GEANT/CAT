<?php
use web\lib\admin\view\DefaultPage;
use web\lib\admin\view\InstitutionPageBuilder;
use web\lib\admin\view\PageBuilder;

class MockValidateInstitutionPageBuilder extends InstitutionPageBuilder{
    
    protected function validateInstitution(){
        try {
            $this->institution = $this->valid_IdP($_GET['inst_id'], $_SESSION['user']);
        } catch (\Exception $e) {
            $this->headerTitle = $e->getMessage();
        }
    }
    
    private function valid_IdP($input, $owner){
        if ($input == 1){
            return new MockInstitution();
        }else{
            throw new Exception('IdP '.$input.' not found in database!');
        }
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
        $builder = new MockValidateInstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertTrue($builder->isReady());
    }
    
    public function testConstructorFailure(){
        $_GET['inst_id']=-1;
        $builder = new MockValidateInstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
        
        unset($_GET['inst_id']);
        $builder = new MockValidateInstitutionPageBuilder(new DefaultPage("Testing Page"), PageBuilder::ADMIN_IDP_USERS);
        $this->assertFalse($builder->isReady());
    }
    
}