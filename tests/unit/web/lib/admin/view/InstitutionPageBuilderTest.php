<?php
use core\IdP;
use web\lib\admin\view\DefaultHtmlPage;
use web\lib\admin\view\InstitutionPageBuilder;

class MockValidateInstitutionPageBuilder extends InstitutionPageBuilder{
    
    protected function validateInstitution(){
        $this->institution = $this->valid_IdP($_GET['inst_id'], $_SESSION['user']);
    }
    
    /**
     * 
     * @param unknown $input
     * @param unknown $owner
     * @throws Exception
     * @return mixed
     */
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
        $builder = new MockValidateInstitutionPageBuilder(new DefaultHtmlPage("Testing Page"));
        $this->assertTrue($builder->isReady());
    }
    
    public function testConstructorFailure(){
        $_GET['inst_id']=-1;
        $builder = new MockValidateInstitutionPageBuilder(new DefaultHtmlPage("Testing Page"));
        $this->assertFalse($builder->isReady());
        
        unset($_GET['inst_id']);
        $builder = new MockValidateInstitutionPageBuilder(new DefaultHtmlPage("Testing Page"));
        $this->assertFalse($builder->isReady());
    }
    
}
