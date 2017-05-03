<?php
namespace web\lib\admin\http;



/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractCommand {
    
    /**
     *
     * @var string
     */
    protected $command;
    

    /**
     *
     * @param string $command
     */
    public function __construct($command){
        $this->command = $command;
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
