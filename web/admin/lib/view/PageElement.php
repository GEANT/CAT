<?php
namespace lib\view;
/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface PageElement {
    const INFOBLOCK_CLASS = 'infobox';
    const EDITABLEBLOCK_CLASS = 'option_container';
    /**
     * 
     */
    public function render();
}