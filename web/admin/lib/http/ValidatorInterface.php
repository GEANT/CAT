<?php
namespace lib\domain\http;

use lib\view\MessageContainerInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface ValidatorInterface {
    /**
     * 
     */
    public function parseRequest();
    
    /**
     * 
     * @param MessageContainerInterface $messageContainer
     * @param string $command
     */
    public function provideMessages($messageContainer, $command);
}
