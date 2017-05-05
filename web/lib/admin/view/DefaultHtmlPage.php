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
class DefaultHtmlPage extends AbstractPage{
    
    /**
     * Manage users base page type.
     *
     * @var string
     */
    const ADMIN_IDP_USERS = 'ADMIN-IDP-USERS';
    
    const SECTION_SCRIPT = 'script';
    const SECTION_CSS = 'css';
    const SECTION_META = 'meta';
    const SECTION_PRELUDE = 'prelude';
    const SECTION_CONTENT = 'content';
    
    /**
     * 
     * @var string
     */
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
     * @param string $type Page type token.
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
     * Sets page type token.
     * 
     * @param string $type 
     */
    public function setType($type){
        $this->type = $type;
    }
    
    /**
     * Retrieves page type token.
     * 
     * @return string
     */
    public function getType(){
        return $this->type;
    }
    
    /**
     * Allows to change page title.
     * 
     * @param string $title Page title.
     */
    public function setTitle($title){
        $this->title = $title;
    }
    
    /**
     * Every HTML page has title.
     * 
     * @return string
     */
    public function getTitle(){
        return $this->title;
    }

    /**
     * Appends page element to 'prelude' page elements section.
     *
     * @param PageElementInterface $element Any page element.
     */
    public function appendPrelude($element){
        $this->append(self::SECTION_PRELUDE, $element);
    }
    
    /**
     * Retrieves 'prelude' section page elements.
     *
     * @return PageElementInterface
     */
    public function fetchPrelude(){
        return $this->fetch(self::SECTION_PRELUDE);
    }
    
    /**
     * Appends page element to 'content' page elements section.
     * 
     * @param PageElementInterface $element Any page element.
     */
    public function appendContent($element){
        $this->append(self::SECTION_CONTENT, $element);
    }
    
    /**
     * Retrieves 'content' section page elements.
     * 
     * @return PageElementInterface
     */
    public function fetchContent(){
        return $this->fetch(self::SECTION_CONTENT);
    }
    
    /**
     * Appends JavaScript source file element to a 'script' page section.
     * 
     * @param string $url Path to a JavaScript file.
     */
    public function appendScript($url){
        $script = new Tag('script');
        $script->addAttribute('type', 'text/javascript');
        $script->addAttribute('src', $this->decorateVersion($url));
        $this->appendHtmlElement(self::SECTION_SCRIPT, $script);
    }
    
    /**
     * Retrieves 'script' section page element.
     * 
     * @return PageElementInterface
     */
    public function fetchScript(){
        return $this->fetch(self::SECTION_SCRIPT);
    }
    
    /**
     * Appends CSS source file element to a 'css' page section.
     * 
     * @param string $url Path to a CSS file.
     */
    public function appendCss($url){
        $css = new UnaryTag('link');
        $css->addAttribute('rel', 'stylesheet');
        $css->addAttribute('type', 'text/css');
        $css->addAttribute('href', $this->decorateVersion($url));
        $this->appendHtmlElement(self::SECTION_CSS, $css);
    }
    
    /**
     * Retrieves 'css' section page element.
     * 
     * @return PageElementInterface
     */
    public function fetchCss(){
        return $this->fetch(self::SECTION_CSS);
    }
    
    /**
     * Appends meta element to a 'meta' page section.
     * 
     * @param string[] $attributes Associative list of attributes for meta element.
     */
    public function appendMeta($attributes = array()){
        $meta = new Tag('meta');
        foreach ($attributes as $name => $value) {
            $meta->addAttribute($name, $value);
        }
        $this->appendHtmlElement(self::SECTION_META, $meta);
    }
    
    /**
     * Retrieves 'meta' section page element.
     *
     * @return PageElementInterface
     */
    public function fetchMeta(){
        return $this->fetch(self::SECTION_META);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        $content = $this->fetch(self::SECTION_CONTENT);
        $content->render();
    }
}
