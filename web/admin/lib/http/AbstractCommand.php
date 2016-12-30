<?php
namespace lib\http;



use lib\domain\SilverbulletFactory;

abstract class AbstractCommand {

    /**
     * 
     * @var string
     */
    protected $command;
    
    /**
     * 
     * @var SilverbulletFactory
     */
    protected $factory;
    
    /**
     * 
     * @param string $command
     * @param SilverbulletFactory $factory
     */
    public function __construct($command, $factory){
        $this->command = $command;
        $this->factory = $factory;
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
    
    public abstract function execute();
    
}