<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Tag;
use web\lib\admin\view\html\UnaryTag;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultPage extends AbstractPage{
    
    /**
     * 
     * @var string
     */
    private $title = "";
    
    /**
     * 
     * @var string
     */
    private $version = "";
    
    /**
     * 
     * @param string $title
     * @param string $version
     */
    public function __construct($title = "Unknown Page", $version = "1.0") {
        parent::__construct();
        $this->setTitle($title);
        $this->version = $version;
    }
    
    /**
     * 
     * @param string $url
     * @return string
     */
    private function  decorateVersion($url){
        return $url.'?v='.$this->version;
    }
    
    /**
     * 
     * @param string $title
     */
    public function setTitle($title){
        $this->title = $title;
    }
    
    /**
     * 
     * @return string
     */
    public function getTitle(){
        return $this->title;
    }
    
    /**
     * 
     * @param string $url
     */
    public function appendScript($url){
        $script = new Tag('script');
        $script->addAttribute('type', 'text/javascript');
        $script->addAttribute('src', $this->decorateVersion($url));
        $this->append('script', new PageElementAdapter($script));
    }
    
    /**
     * 
     * @return PageElementInterface
     */
    public function fetchScript(){
        return $this->fetch('script');
    }
    
    /**
     * 
     * @param string $url
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
     * @return PageElementInterface
     */
    public function fetchCss(){
        return $this->fetch('css');
    }
    
    /**
     * 
     * @param string[] $attributes
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
     * @return PageElementInterface
     */
    public function fetchMeta(){
        return $this->fetch('meta');
    }
    
}
