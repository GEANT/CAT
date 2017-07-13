<?php
namespace web\lib\admin\http;

use web\lib\admin\view\MessageReceiverInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageDistributor {
    /**
     * Registers message invoker and maps it to a particular command token
     * 
     * @param string $commandToken
     * @param MessageInvokerInterface $invoker
     */
    public function addMessageInvoker($commandToken, $invoker);
    /**
     * Distributes messages from particular invoker to a requested receiver
     *
     * @param string $commandToken
     * @param MessageReceiverInterface $receiver
     */
    public function distributeMessages($commandToken, $receiver);
}
