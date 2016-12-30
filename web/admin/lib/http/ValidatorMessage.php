<?php

namespace lib\http;

use lib\view\MessageReceiverInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class ValidatorMessage {
    
    const INFO = 'info';
    
    const ERROR = 'error';
    
    
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
    public function __construct($text, $level = MessageReceiverInterface::INFO) {
        $this->text = $text;
        $this->level = $level;
    }
    
    public function getText(){
        return $this->text;
    }
    
    public function getClass($prefix = ''){
        return $prefix . '-' . $this->level;
    }
}