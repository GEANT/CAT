<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class PageElementAdapter implements PageElementInterface{
    
    /**
     * 
     * @var HtmlElementInterface
     */
    private $element;
    
    /**
     * 
     * @param HtmlElementInterface $element
     */
    public function __construct(HtmlElementInterface $element) {
        $this->element = $element;
    }
    
    public function render() {
        echo $this->element;
    }
    
}