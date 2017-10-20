<?php

use web\lib\admin\http\SendTokenByEmail;
use web\lib\admin\http\SilverbulletContext;
use web\lib\admin\http\GetTokenEmailDetails;

class MockSendTokenByEmail extends SendTokenByEmail{
    
    /**
     * 
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        $this->session = $context->getSession();
        $this->mail = new MockPHPMailer();
        $this->detailsCommand = new GetTokenEmailDetails(GetTokenEmailDetails::COMMAND, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    public function getMailer(){
        return $this->mail;
    }
}
