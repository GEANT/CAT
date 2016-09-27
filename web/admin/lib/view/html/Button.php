<?php
namespace lib\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Button extends Tag{
    
    private $attributesString = "";
    private $title = "";
    protected $name = "";
    protected $value = "";
    
    public function __construct($type, $title, $name, $value, $class = ''){
        $this->attributesString .= new Attribute('type', $type);
        $this->attributesString .= new Attribute('class', $class);
        $this->title = $title;
        $this->name = $name;
        $this->value = $value;
    }
    
    /**
     * 
     * @return string
     */
    protected function composeNameValueString(){
        return new Attribute('name', $this->name).new Attribute('value', $this->value);
    }
    
    public function __toString(){
        $this->attributesString .= $this->composeNameValueString();
        return "\n".$this->tab."<button".$this->attributesString.">" ."\n\t".$this->tab.$this->title. "\n".$this->tab."</button>";
    }
}