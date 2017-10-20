<?php
require_once 'MockPHPMailer.php';
require_once 'MockSendTokenByEmail.php';

use web\lib\admin\http\GetTokenEmailDetails;
use web\lib\admin\http\SilverbulletContext;
use web\lib\admin\http\ValidateEmailAddress;
use web\lib\admin\view\DefaultHtmlPage;
use web\lib\admin\view\InstitutionPageBuilder;

class MockEmptyPageBuilder extends InstitutionPageBuilder{

    public function __construct($page){
        $this->page = $page;
    }

}

class SendTokenByEmailTest extends PHPUnit_Framework_TestCase{
    
    /**
     * 
     * @var MockSendTokenByEmail
     */
    private $command;
    
    protected function setUp() {
        $builder = new MockEmptyPageBuilder(new DefaultHtmlPage("Test Page"));
        $context = new SilverbulletContext($builder);
        $this->command = new MockSendTokenByEmail(MockSendTokenByEmail::COMMAND, $context);
    }
    
    public function testExecute(){
        
        $this->command->execute();
        $mail = $this->command->getMailer();
        $this->assertEquals("", $mail->Subject);
        $this->assertEquals("", $mail->Body);
        $this->assertEmpty($mail->getAllRecipientAddresses());
        
        $_POST[GetTokenEmailDetails::PARAM_TOKENLINK] = 'testtokenlinkstring';
        $_POST[ValidateEmailAddress::PARAM_ADDRESS] = 'test@emailaddress.com';

        $this->command->execute();
        $this->assertEmpty($mail->ErrorInfo);
        $this->assertNotEquals("", $mail->Subject);
        $this->assertTrue(strpos($mail->Body, $_POST[GetTokenEmailDetails::PARAM_TOKENLINK])!==false);
        $this->assertNotEmpty($mail->getAllRecipientAddresses());
        $this->assertArrayHasKey($_POST[ValidateEmailAddress::PARAM_ADDRESS], $mail->getAllRecipientAddresses());
    }
}
