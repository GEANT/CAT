<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Attribute;

abstract class PageElementDecorator implements PageElementInterface{
    
    /**
     * 
     * @var PageElementInterface
     */
    protected $element;
    
    /**
     *
     * @var Attribute
     */
    protected $class = "";
    
    /**
     * 
     * @param PageElementInterface $element
     */
    public function __construct($element, $class = "") {
        $this->element = $element;
        $this->class = new Attribute('class', $class);
    }
    
}
