<?php
require_once(dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) . "/core/PHPMailer/src/PHPMailer.php");
use PHPMailer\PHPMailer\PHPMailer;

class MockPHPMailer extends PHPMailer{
    
    /**
     * 
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \PHPMailer\PHPMailer\PHPMailer::send()
     */
    public function send(){
        if($this->Subject && $this->Body && count($this->to)>0){
            return true;
        }else{
            $this->ErrorInfo = "Missing subject, body or email address!";
            return false;
        }
    }
    
}
