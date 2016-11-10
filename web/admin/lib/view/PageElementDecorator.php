<?php
namespace lib\view;

use lib\view\html\Attribute;

abstract class PageElementDecorator implements PageElement{
    
    /**
     * 
     * @var PageElement
     */
    protected $element;
    
    /**
     *
     * @var Attribute
     */
    protected $class = "";
    
    /**
     * 
     * @param PageElement $element
     */
    public function __construct($element, $class = "") {
        $this->element = $element;
        $this->class = new Attribute('class', $class);
    }
    
}
