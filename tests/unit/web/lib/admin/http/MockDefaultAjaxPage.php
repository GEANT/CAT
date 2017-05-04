<?php

use web\lib\admin\view\DefaultAjaxPage;
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\PageElementAdapter;

class MockDefaultAjaxPage extends DefaultAjaxPage{
    
    public function __construct() {
        $this->response = new CompositeTag('response');
        $this->append('content', new PageElementAdapter($this->response));
    }
    
    /**
     * 
     * @return CompositeTag
     */
    public function getResponse(){
        return $this->response;
    }
}
