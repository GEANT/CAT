<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;

/**
 * Allows to use HTML elements where page element interface is required.
 * 
 * @author Zilvinas Vaira
 *
 */
class PageElementAdapter implements PageElementInterface{
    
    /**
     * Wrapped HTML element.
     * 
     * @var HtmlElementInterface
     */
    private $element = null;
    
    /**
     * Wrapped string element.
     * 
     * @var string
     */
    private $text = '';
    
    /**
     * Creates page element adapter object.
     * 
     * @param HtmlElementInterface $element An HTML element which needs page element interface needs to be passed to constructor.
     */
    public function __construct($element = null) {
        $this->element = $element;
    }
    
    /**
     * Appends string value. Can be used as an adapter for string values.
     * 
     * @param string $text
     */
    public function addText($text){
        $this->text .= $text;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() {
        if($this->element != null){
            echo $this->element;
        }
        if($this->text != ''){
            echo $this->text;
        }
    }
    
}
