<?php
namespace lib\view;

use lib\domain\http\ValidatorMessage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageContainerInterface {
    
    /**
     * 
     * @param ValidatorMessage $message
     */
    public function addMessage($message);
    
}