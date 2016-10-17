<?php
namespace lib\view;
/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface PageElement {
    const INFOBLOCK_CLASS = 'infobox';
    const EDITABLEBLOCK_CLASS = 'sb-editable-block';
    /**
     * 
     */
    public function render();
}