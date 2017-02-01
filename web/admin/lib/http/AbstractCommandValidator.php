<?php
namespace lib\http;

use lib\storage\SessionStorage;
use lib\domain\SilverbulletFactory;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractCommandValidator extends AbstractCommand implements ValidatorInterface{
    
    /**
     * 
     * @var SessionStorage
     */
    protected $session;
    
    /**
     * 
     * @param string $command
     * @param SilverbulletFactory $factory
     */
    public function __construct($command, $factory){
        parent::__construct($this->parseString($command), $factory);
        $this->session = $factory->getSession();
    }
    
    /**
     * 
     * * @param string $text
     */
    public function storeErrorMessage($text){
        $this->session->add($this->command, new ValidatorMessage($text, ValidatorMessage::ERROR));
    }

    /**
     *
     * * @param string $text
     */
    public function storeInfoMessage($text){
        $this->session->add($this->command, new ValidatorMessage($text, ValidatorMessage::INFO));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \lib\http\ValidatorInterface::publishMessages()
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
    
}
