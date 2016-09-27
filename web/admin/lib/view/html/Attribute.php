<?php
namespace lib\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Attribute implements HtmlElement{
    
    public $name;
    public $value;
    
    public function __construct($name, $value){
        $this->name = $name;
        $this->value = $value;
    }
    
    public function __toString(){
        if(isset($this->name)&&isset($this->value)){
            return ' ' . $this->name . '="' . $this->value . '"';
        }else{
            return '';
        }
    }
}