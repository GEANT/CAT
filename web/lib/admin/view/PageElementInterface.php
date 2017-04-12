<?php
namespace web\lib\admin\view;
/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface PageElementInterface {
    const INFOBLOCK_CLASS = 'infobox';
    const OPTIONBLOCK_CLASS = 'option_container';
    const MESSAGEBOX_CLASS = 'sb-message-box';
    const TABS_CLASS = 'tabs';
    
    /**
     * 
     */
    public function render();
}
