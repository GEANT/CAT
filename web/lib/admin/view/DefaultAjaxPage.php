<?php
namespace web\lib\admin\view;



/**
 * Represents default Ajax page object implementation.
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultAjaxPage extends AbstractPage{

    const SECTION_RESPONSE = 'response';
    
    /**
     * 
     */
    public function __construct() {
        parent::__construct();
        header('Content-Type: text/xml');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        $response = $this->fetch(self::SECTION_RESPONSE);
        ?><response><?php $response->render(); ?></response><?php 
    }
}
