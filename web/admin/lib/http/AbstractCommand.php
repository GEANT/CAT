<?php
namespace lib\http;

use lib\storage\SessionStorage;
use lib\http\SilverbulletController;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractCommand implements MessageInvokerInterface{
    
    /**
     *
     * @var string
     */
    protected $command;
    
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
    public function __construct($command, $controller){
        $this->command = $command;
        $this->controller = $controller;
        $this->session = $controller->getSession();
    }
    
    /**
     *
     * @param string $command
     * @return boolean
     */
    public function isCommand($command){
        return ($this->command == $command);
    }
    
    public function getCommand(){
        return $this->command;
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
    
    /**
     * 
     * @param string[] $values
     * @return string[]
     */
    protected function parseArray($values){
        $r = array();
        foreach ($values as $key => $value) {
            $r[$key] = $this->parseString($value);
        }
        return $r;
    }

    /**
     * 
     * @param string $value
     * @return string
     */
    protected function parseString($value){
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 
     * @param string $value
     * @return number
     */
    protected function parseInt($value){
        return intval($this->parseString($value));
    }
    
    public abstract function execute();
    
}
