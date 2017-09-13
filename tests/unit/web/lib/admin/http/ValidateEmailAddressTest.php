<?php
require_once 'MockDefaultAjaxPage.php';
require_once 'MockPHPMailer.php';

use web\lib\admin\http\DefaultContext;
use web\lib\admin\http\ValidateEmailAddress;

class ValidateEmailAddressTest extends PHPUnit_Framework_TestCase{

    /**
     *
     * @var ValidateEmailAddress
     */
    private $command;

    /**
     *
     * @var MockDefaultAjaxPage
     */
    private $page;

    protected function setUp() {
        $this->page = new MockDefaultAjaxPage();
        $this->command = new ValidateEmailAddress(ValidateEmailAddress::COMMAND, new DefaultContext($this->page));
    }

    public function testExecute(){

        $this->command->execute();
        $response = $this->page->getResponse();
        $renderedResponse = $response->__toString();
        $this->assertFalse(strpos($renderedResponse, '<email'));
        $this->assertFalse(strpos($renderedResponse, 'isValid="true"'));

        $_POST[ValidateEmailAddress::PARAM_ADDRESS] = 'test@em@ailaddress.com';

        $this->command->execute();
        $renderedResponse = $response->__toString();
        $this->assertTrue(strpos($renderedResponse, '<email')!==false);
        // Is not going to work since the $_POST variables can't be modified at runtime for filter_input function
        //$this->assertTrue(strpos($renderedResponse, 'address="'.$_POST[ValidateEmailAddress::PARAM_ADDRESS].'"')!==false);
        $this->assertFalse(strpos($renderedResponse, 'isValid="true"'));
        
    }
}
