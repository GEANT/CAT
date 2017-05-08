<?php
namespace web\lib\admin\view;

/**
 * Defines a composite of page elements which can also be an element of other composite.
 * 
 * @author Zilvinas Vaira
 * 
 */
class PageElementGroup implements PageElementInterface{
    
    /**
     * List of contained elements. Default value is empty array.
     * 
     * @var PageElementInterface[]
     */
    protected $elements = array();
    
    /**
     * Adds an element to the list.
     * 
     * @param PageElementInterface $element Element must implement page element interface. Use page element adapter if HTML element needs to be added. Null element helps to avoid null pointer exceptions.
     * @see PageElementAdapter
     * @see PageElementNull
     */
    public function addElement($element) {
        $this->elements[] = $element;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() {
        foreach ($this->elements as $element) {
            $element->render();
        }
    }
    
}
