<?php
require_once 'MockDefaultAjaxPage.php';
require_once 'MockPHPMailer.php';
require_once 'MockSendTokenByEmail.php';

use web\lib\admin\http\AjaxController;
use web\lib\admin\http\ValidateEmailAddress;

class SendTokenByEmailTest extends PHPUnit_Framework_TestCase{
    
    /**
     * 
     * @var MockSendTokenByEmail
     */
    private $command;
    
    /**
     * 
     * @var MockDefaultAjaxPage
     */
    private $page;
    
    protected function setUp() {
        $this->page = new MockDefaultAjaxPage();
        $this->command = new MockSendTokenByEmail(MockSendTokenByEmail::COMMAND, new AjaxController($this->page));
    }
    
    public function testExecute(){
        
        $this->command->execute();
        $mail = $this->command->getMailer();
        $response = $this->page->getResponse();
        $renderedResponse = $response->__toString();
        $this->assertFalse(strpos($renderedResponse, 'status="true"'));
        $this->assertEmpty($mail->getAllRecipientAddresses());
        
        $_GET[MockSendTokenByEmail::PARAM_TOKENLINK] = 'testtokenlinkstring';
        $_GET[ValidateEmailAddress::PARAM_ADDRESS] = 'test@emailaddress.com';

        $this->command->execute();
        $this->assertEmpty($mail->ErrorInfo);

        $renderedResponse = $response->__toString();
        $this->assertTrue(strpos($renderedResponse, 'status="true"')!==false);
        $this->assertTrue(strpos($mail->Body, $_GET[MockSendTokenByEmail::PARAM_TOKENLINK])!==false);
        $this->assertNotEmpty($mail->getAllRecipientAddresses());
        $this->assertArrayHasKey($_GET[ValidateEmailAddress::PARAM_ADDRESS], $mail->getAllRecipientAddresses());
    }
}
