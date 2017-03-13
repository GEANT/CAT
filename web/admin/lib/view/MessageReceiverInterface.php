<?php
namespace lib\view;

use lib\domain\http\Message;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageReceiverInterface {
    
    /**
     * 
     * @param Message $message
     */
    public function receiveMessage($message);
    
}
