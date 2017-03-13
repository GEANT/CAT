<?php
namespace web\lib\admin\domain;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Attribute {
    
    /**
     * 
     * @var string
     */
    const TYPE_STRING = 's';
    /**
     * 
     * @var string
     */
    const TYPE_INTEGER = 'i';
    /**
     * 
     * @var string
     */
    const TYPE_DOUBLE = 'd';
    /**
     * 
     * @var string
     */
    const TYPE_BLOB = 'b';
    
    /**
     * 
     * @var string
     */
    public $key = '';
    
    /**
     * 
     * @var string|integer|double
     */
    public $value = '';
    
    /**
     * 
     * @var string
     */
    private $type = self::TYPE_STRING;
    
    /**
     * 
     * @param string $key
     * @param string|integer|double $value
     * @param string $type
     */
    public function __construct($key, $value, $type = 's') {
        $this->key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $this->value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $this->type = $type;
    }
    
    /**
     * 
     * @return string
     */
    public function getType(){
        return $this->type;
    }
    
    
    
}