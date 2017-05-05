<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface MessageDistributor {
    /**
     * Distributes messages from particular invoker to a requested receiver
     *
     * @param string $commandToken
     * @param MessageReceiverInterface $receiver
     */
    public function distributeMessages($commandToken, $receiver);
}
