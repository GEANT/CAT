<?php
namespace lib\view\html;

use lib\view\PageElement;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class UnaryTag implements HtmlElement, PageElement{
    /**
     *
     * @var string
     */
    protected $name = "";
    
    /**
     *
     * @var Attribute []
     */
    private $attributes = array();
    
    /**
     *
     * @param string $text
     */
    
    protected $tab = "\t";
    
    /**
     * 
     * @param string $name
     */
    public function __construct($name){
        $this->name = $name;
    }
    
    public function setTab($tab){
        $this->tab = $tab;
    }
    
    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function addAttribute($name, $value){
        $this->attributes [] = new Attribute($name, $value);
    }
    
    /**
     * 
     * @param string $attributeString
     * @return string
     */
    protected function composeTagString($attributeString){
        return "\n" . $this->tab . "<".$this->name.$attributeString.">";
    }
    
    public function __toString(){
        $attributeString = "";
        foreach ($this->attributes as $attribute) {
            $attributeString .= $attribute;
        }
        if(isset($this->name)){
            return $this->composeTagString($attributeString);
        }else{
            return "";
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\html\PageElement::render()
     */
    public function render(){
        echo $this->__toString();
    }
    
}
