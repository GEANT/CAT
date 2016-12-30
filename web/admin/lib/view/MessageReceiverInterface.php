<?php
namespace lib\view;

use lib\domain\http\ValidatorMessage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageReceiverInterface {
    
    /**
     * 
     * @param ValidatorMessage $message
     */
    public function receiveMessage($message);
    
}