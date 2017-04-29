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
    const TABS_CLASS = 'tabs';
    const MESSAGEBOX_CLASS = 'sb-message-box';
    const MESSAGEPOPUP_CLASS = 'sb-popup-message';
    const COMPOSE_EMAIL_CLASS = 'sb-compose-email';
    
    /**
     * 
     */
    public function render();
}
