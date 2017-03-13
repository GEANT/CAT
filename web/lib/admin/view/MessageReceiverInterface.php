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
     * 
     * @param Message $message
     */
    public function receiveMessage($message);
    
}
