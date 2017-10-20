<?php
namespace web\lib\admin\http;

use web\lib\admin\storage\SessionStorage;

/**
 * 
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractInvokerCommand extends AbstractCommand implements MessageInvokerInterface{

    /**
     *
     * @var SessionStorage
     */
    protected $session;
    
    /**
     *
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context) {
        parent::__construct($commandToken);
        $this->session = $context->getSession();
        $context->addMessageInvoker($commandToken, $this);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::storeErrorMessage()
     */
    public function storeErrorMessage($text){
        $this->session->add($this->commandToken, new Message($text, Message::ERROR));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::storeInfoMessage()
     */
    public function storeInfoMessage($text){
        $this->session->add($this->commandToken, new Message($text, Message::INFO));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::publishMessages()
     */
    public function publishMessages($receiver){
        $messages = $this->session->get($this->commandToken);
        foreach ($messages as $message) {
            $receiver->receiveMessage($message);
        }
        $this->session->delete($this->commandToken);
    }
    
}
