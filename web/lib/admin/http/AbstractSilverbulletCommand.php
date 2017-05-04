<?php
namespace web\lib\admin\http;

/**
 * 
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractSilverbulletCommand extends AbstractCommand implements MessageInvokerInterface{

    /**
     *
     * @var SilverbulletController
     */
    protected $controller;
    
    /**
     *
     * @var SessionStorage
     */
    protected $session;
    
    /**
     *
     * @param string $command
     * @param SilverbulletController $controller
     */
    public function __construct($command, $controller) {
        parent::__construct($command);
        $this->controller = $controller;
        $this->session = $controller->getSession();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::storeErrorMessage()
     */
    public function storeErrorMessage($text){
        $this->session->add($this->command, new Message($text, Message::ERROR));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::storeInfoMessage()
     */
    public function storeInfoMessage($text){
        $this->session->add($this->command, new Message($text, Message::INFO));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\MessageInvokerInterface::publishMessages()
     */
    public function publishMessages($receiver){
        $messages = $this->session->get($this->command);
        foreach ($messages as $message) {
            $receiver->receiveMessage($message);
        }
        $this->session->delete($this->command);
    }
    
}
