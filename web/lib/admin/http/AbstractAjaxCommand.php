<?php
namespace web\lib\admin\http;

use web\lib\admin\view\html\HtmlElementInterface;
use web\lib\admin\view\DefaultAjaxPage;
use web\lib\admin\view\AbstractPage;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractAjaxCommand extends AbstractCommand{
    
    /**
     *
     * @var AbstractPage
     */
    protected $page = null;
    
    /**
     *
     * @param string $commandToken
     * @param DefaultContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken);
        $this->page = $context->getPage();
    }
    
    /**
     * 
     * @param HtmlElementInterface $element
     */
    public function publish($element){
        $this->page->appendHtmlElement(DefaultAjaxPage::SECTION_RESPONSE, $element);
    }
    
}
