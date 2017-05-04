<?php

use web\lib\admin\http\SendTokenByEmail;

class MockSendTokenByEmail extends SendTokenByEmail{
    
    public function __construct($command, $controller){
        $this->command = $command;
        $this->controller = $controller;
        $this->mail = new MockPHPMailer();
    }
    
    /**
     * 
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    public function getMailer(){
        return $this->mail;
    }
}
