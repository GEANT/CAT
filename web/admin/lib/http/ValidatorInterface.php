<?php
namespace lib\http;

use lib\view\MessageReceiverInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface ValidatorInterface {
    /**
     * 
     * @param MessageReceiverInterface $receiver
     */
    public function publishMessages($receiver);
}
