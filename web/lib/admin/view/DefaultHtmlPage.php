<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Tag;
use web\lib\admin\view\html\UnaryTag;

/**
 * Represents default HTML page object implementation.
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultHtmlPage extends AbstractPage implements HtmlPageInterface{
    
    private $type = "";
    
    /**
     * HTML page title.
     * 
     * @var string
     */
    private $title = "";
    
    /**
     * Textual representation of page version number.
     * 
     * @var string
     */
    private $version = "";
    
    /**
     * Instantiates HTML page object. Defines page title and page version.
     * 
     * @param string $title HTML page title.
     * @param string $version Page version is used as an argument when loading source files for CSS and JavaScript. It allows to avoid caching.
     */
    public function __construct($type, $title = "Unknown Page", $version = "1.0") {
        parent::__construct();
        $this->setType($type);
        $this->setTitle($title);
        $this->version = $version;
    }
    
    /**
     * Appends version number argument to file source url.
     * 
     * @param string $url File source url (must be clean url without query parameters).
     * @return string File source url with version query.
     */
    private function  decorateVersion($url){
        return $url.'?v='.$this->version;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::setType()
     */
    public function setType($type){
        $this->type = $type;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::getType()
     */
    public function getType(){
        return $this->type;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::setTitle()
     */
    public function setTitle($title){
        $this->title = $title;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::getTitle()
     */
    public function getTitle(){
        return $this->title;
    }

    /**
     *
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::appendPrelude()
     */
    public function appendPrelude($element){
        $this->append('prelude', $element);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::fetchPrelude()
     */
    public function fetchPrelude(){
        return $this->fetch('prelude');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::appendContent()
     */
    public function appendContent($element){
        $this->append('content', $element);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::fetchContent()
     */
    public function fetchContent(){
        return $this->fetch('content');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::appendScript()
     */
    public function appendScript($url){
        $script = new Tag('script');
        $script->addAttribute('type', 'text/javascript');
        $script->addAttribute('src', $this->decorateVersion($url));
        $this->append('script', new PageElementAdapter($script));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::fetchScript()
     */
    public function fetchScript(){
        return $this->fetch('script');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::appendCss()
     */
    public function appendCss($url){
        $css = new UnaryTag('link');
        $css->addAttribute('rel', 'stylesheet');
        $css->addAttribute('type', 'text/css');
        $css->addAttribute('href', $this->decorateVersion($url));
        $this->append('css', new PageElementAdapter($css));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::fetchCss()
     */
    public function fetchCss(){
        return $this->fetch('css');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::appendMeta()
     */
    public function appendMeta($attributes = array()){
        $meta = new Tag('meta');
        foreach ($attributes as $name => $value) {
            $meta->addAttribute($name, $value);
        }
        $this->append('meta', new PageElementAdapter($meta));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\HtmlPageInterface::fetchMeta()
     */
    public function fetchMeta(){
        return $this->fetch('meta');
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
