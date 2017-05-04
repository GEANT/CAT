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
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute(){
        if(isset($_GET[self::PARAM_ADDRESS])){
            $page = $this->controller->getPage();
            $address = $this->parseString($_GET[self::PARAM_ADDRESS]);
            $result = OutsideComm::mailAddressValidSecure($address);
            $message = $this->chooseMessage($result);
            $tokenTag = new Tag('email');
            $tokenTag->addAttribute('address', $address);
            $tokenTag->addAttribute('isValid', ($result > 0) ? 'true' : 'false');
            $tokenTag->addText($message);
            $page->appendResponse($tokenTag);
        }
    }
    
    /**
     * Chooses message based on returned constant value.
     * 
     * @param mixed $result Should be a number, but validation method signature indicates mixed value.
     * @return string
     */
    private function chooseMessage($result){
        $errorMessage = _("Email address validation failed. Sending is not possible!");
        $warningMessage = _("The invitation token is possibly going over the internet without transport encryption and can be intercepted by random third parties! Please consider sending the invitation token via a more secure transport!");
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
