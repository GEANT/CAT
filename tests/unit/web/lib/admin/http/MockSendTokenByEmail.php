<?php

use web\lib\admin\http\SendTokenByEmail;
use web\lib\admin\http\SilverbulletContext;

class MockSendTokenByEmail extends SendTokenByEmail{
    
    /**
     * 
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        $this->mail = new MockPHPMailer();
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
