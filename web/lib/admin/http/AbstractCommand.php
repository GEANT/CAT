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
    protected $commandToken;
    

    /**
     *
     * @param string $commandToken
     */
    public function __construct($commandToken){
        $this->commandToken = $commandToken;
    }
    
    /**
     *
     * @param string $commandToken
     * @return boolean
     */
    public function isCommand($commandToken){
        return ($this->commandToken == $commandToken);
    }
    
    /**
     * 
     * @return string
     */
    public function getCommand(){
        return $this->commandToken;
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
    
    /**
     * 
     */
    public abstract function execute();
    
}
