<?php

use web\lib\admin\view\DefaultAjaxPage;
use web\lib\admin\view\html\CompositeTag;

class MockDefaultAjaxPage extends DefaultAjaxPage{
    
    /**
     * 
     * @var CompositeTag
     */
    private $response;
    
    /**
     *
     */
    public function __construct() {
        $this->response = new CompositeTag('response');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractPage::appendHtmlElement()
     */
    public function appendHtmlElement($name, $element){
        if($name==DefaultAjaxPage::SECTION_RESPONSE){
            $this->response->addTag($element);
        }
    }
    
    /**
     * 
     * @return CompositeTag
     */
    public function getResponse(){
        return $this->response;
    }
}
