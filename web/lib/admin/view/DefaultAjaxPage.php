<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;
use web\lib\admin\view\html\CompositeTag;

/**
 * Represents default Ajax page object implementation.
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultAjaxPage extends AbstractPage{
    
    protected $response = null;
    
    /**
     * 
     */
    public function __construct() {
        parent::__construct();
        header('Content-Type: text/xml');
        $this->response = new CompositeTag('response');
        $this->append('content', new PageElementAdapter($this->response));
    }
    
    /**
     * 
     * @param HtmlElementInterface $element
     */
    public function appendResponse($element){
        $this->response->addTag($element);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        $content = $this->fetch('content');
        $content->render();
    }
}
