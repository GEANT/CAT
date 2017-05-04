<?php
namespace web\lib\admin\view;

/**
 * Implements null object pattern for page elements.
 * 
 * @author Zilvinas Vaira
 *
 */
class PageElementNull implements PageElementInterface{
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() { }
}
