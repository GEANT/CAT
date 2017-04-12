<?php
namespace web\lib\admin\view;

use web\lib\admin\http\Message;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageReceiverInterface {
    
    /**
     * @return boolean
     */
    public function hasMessages();
    
    /**
     * 
     * @param Message $message
     */
    public function receiveMessage($message);
    
}
