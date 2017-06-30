<?php
namespace web\lib\admin\view;
/**
 * Defines any element that can be added to page. All page elements must implement rendering feature.
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
    const INVITATION_QR_CODE_CLASS = 'sb-qr-code';
    const SEND_SMS_CLASS = 'sb-send-sms';
    
    
    /**
     * Rendering mainly involves generating string content and producing its output. This can be produced simply by echoing generated string element or by enclosing and starting php tags (later this could be upgraded to use HTML element templates instead).
     */
    public function render();
}
