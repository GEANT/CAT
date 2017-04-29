<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class PageElementGroup {
    
    /**
     * 
     * @var PageElementInterface[]
     */
    private $elements = array();
    
    /**
     * 
     * @param PageElementInterface $element
     */
    public function addElement($element) {
        $this->elements[] = $element;
    }
    
    /**
     * 
     */
    public function render() {
        foreach ($this->elements as $element) {
            $element->render();
        }
    }
    
}