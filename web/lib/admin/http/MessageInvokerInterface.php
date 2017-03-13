<?php
namespace web\lib\admin\http;

use web\lib\admin\view\MessageReceiverInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageInvokerInterface {
    
    /**
     *
     * * @param string $text
     */
    public function storeErrorMessage($text);
    
    /**
     *
     * * @param string $text
     */
    public function storeInfoMessage($text);
    
    /**
     * 
     * @param MessageReceiverInterface $receiver
     */
    public function publishMessages($receiver);
}
