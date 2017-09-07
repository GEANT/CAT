<?php
namespace web\lib\admin\http;

use core\common\OutsideComm;
use web\lib\admin\view\html\Tag;

/**
 * Performs email address validation and prepares answer for an Ajax call.
 * 
 * @author Zilvinas Vaira
 *
 */
class ValidateEmailAddress extends AbstractAjaxCommand{

    const COMMAND = 'validateemailaddress';
    const PARAM_ADDRESS = 'address';
    
    /**
     * 
     * @param string $commandToken
     * @param DefaultContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute(){
        if(isset($_POST[self::PARAM_ADDRESS])){
            $address = $this->parseString(filter_input(INPUT_POST,self::PARAM_ADDRESS, FILTER_SANITIZE_STRING));
            $result = OutsideComm::mailAddressValidSecure($address);
            $message = $this->chooseMessage($result, $address);
            $tokenTag = new Tag('email');
            $tokenTag->addAttribute('address', $address);
            $tokenTag->addAttribute('isValid', ($result > 0) ? 'true' : 'false');
            $tokenTag->addText($message);
            
            $this->publish($tokenTag);
        }
    }
    
    /**
     * Chooses message based on returned constant value.
     * 
     * @param mixed $result Should be a number, but validation method signature indicates mixed value.
     * @param string $address
     * @return string
     */
    private function chooseMessage($result, $address){
        $errorMessage = sprintf(_("Email address '%s' validation failed. Sending is not possible!"), $address);
        $warningMessage = sprintf(_("The invitation token is possibly going over the internet without transport encryption and can be intercepted by random third parties for email '%s'! Please consider sending the invitation token via a more secure transport!"), $address);
        switch ($result) {
            case OutsideComm::MAILDOMAIN_NO_STARTTLS:
                return $warningMessage;
            case OutsideComm::MAILDOMAIN_STARTTLS:
                return "";
            case OutsideComm::MAILDOMAIN_INVALID:
                return $errorMessage;
            case OutsideComm::MAILDOMAIN_NO_CONNECT:
                return $errorMessage;
            case OutsideComm::MAILDOMAIN_NO_HOST:
                return $errorMessage;
            case OutsideComm::MAILDOMAIN_NO_MX:
                return $errorMessage;
            default:
                return $errorMessage;
        }
    }
}
