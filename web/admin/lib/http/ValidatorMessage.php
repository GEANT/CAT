<?php

namespace lib\domain\http;

use lib\view\MessageContainerInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class ValidatorMessage {
    
    /**
     * 
     * @var string
     */
    private $text = '';
    
    /**
     * 
     * @var string
     */
    private $level = '';
    
    /**
     * 
     * @param string $text
     * @param string $level
     */
    public function __construct($text, $level = MessageContainerInterface::MESSAGE) {
        $this->text = $text;
        $this->level = $level;
    }
    
    public function getText(){
        return _($this->text);
    }
    
    public function getClass($prefix = ''){
        return $prefix . '-' . $this->level;
    }
}