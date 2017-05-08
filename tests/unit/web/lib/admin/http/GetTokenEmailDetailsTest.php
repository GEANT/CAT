<?php
require_once 'MockDefaultAjaxPage.php';
require_once 'MockPHPMailer.php';

use web\lib\admin\http\DefaultContext;
use web\lib\admin\http\GetTokenEmailDetails;

class GetTokenEmailDetailsTest extends PHPUnit_Framework_TestCase{
    
    /**
     * 
     * @var GetTokenEmailDetails
     */
    private $command;
    
    /**
     * 
     * @var MockDefaultAjaxPage
     */
    private $page;
    
    protected function setUp() {
        $this->page = new MockDefaultAjaxPage();
        $this->command = new GetTokenEmailDetails(GetTokenEmailDetails::COMMAND, new DefaultContext($this->page));
    }
    
    public function testExecute(){
        
        $this->command->execute();
        $response = $this->page->getResponse();
        $renderedResponse = $response->__toString();
        $this->assertTrue(strpos($renderedResponse, '<response>')!==false);
        $this->assertTrue(strpos($renderedResponse, '</response>')!==false);
        $this->assertFalse(strpos($renderedResponse, 'subject='));
        
        $_POST[GetTokenEmailDetails::PARAM_TOKENLINK] = 'testtokenlinkstring';

        $this->command->execute();

        $renderedResponse = $response->__toString();
        
        $this->assertTrue(strpos($renderedResponse, '<response>')!==false);
        $this->assertTrue(strpos($renderedResponse, '</response>')!==false);
        $this->assertTrue(strpos($renderedResponse, 'subject=')!==false);
        $this->assertTrue(strpos($renderedResponse, $_POST[GetTokenEmailDetails::PARAM_TOKENLINK])!==false);
    }
}
