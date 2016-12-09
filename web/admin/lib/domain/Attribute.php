<?php
namespace lib\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Attribute {
    
    const TYPE_STRING = 's';
    const TYPE_INTEGER = 'i';
    const TYPE_DOUBLE = 'd';
    const TYPE_BLOB = 'b';
    
    public $key = '';
    
    public $value = '';
    
    private $type = self::TYPE_STRING;
    
    /**
     * 
     * @param string $key
     * @param string|integer|double $value
     * @param string $type
     */
    public function __construct($key, $value, $type = 's') {
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
    }
    
    public function getType(){
        return $this->type;
    }
    
    
    
}