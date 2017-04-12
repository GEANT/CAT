<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Tag;
use web\lib\admin\view\html\UnaryTag;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultPage implements Page{
    
    private $title = "";
    
    private $version = "";
    
    private $blocks = array();
    
    public function __construct($title = "Unknown Page", $version = "1.0") {
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
    
    public function setTitle($title){
        $this->title = $title;
    }
    
    public function getTitle(){
        return $this->title;
    }
    
    public function append($name, $value){
        if(isset($this->blocks [$name])){
            $this->blocks [$name] .= "\n".$value;
        }else{
            $this->assign($name, $value);
        }
    }
    
    public function assign($name, $value){
        $this->blocks [$name] = $value;
    }
    
    public function fetch($name){
        if(isset($this->blocks[$name])){
            return $this->blocks[$name];
        }else{
            return '';
        }
    }
    
    public function appendScript($url){
        $script = new Tag('script');
        $script->addAttribute('type', 'text/javascript');
        $script->addAttribute('src', $this->decorateVersion($url));
        $this->append('script', $script);
    }
    
    public function fetchScript(){
        return $this->fetch('script');
    }
    
    public function appendCss($url){
        $css = new UnaryTag('link');
        $css->addAttribute('rel', 'stylesheet');
        $css->addAttribute('type', 'text/css');
        $css->addAttribute('href', $this->decorateVersion($url));
        $this->append('css', $css);
    }
    
    public function fetchCss(){
        return $this->fetch('css');
    }
    
    public function appendMeta($attributes = array()){
        $meta = new Tag('meta');
        foreach ($attributes as $name => $value) {
            $meta->addAttribute($name, $value);
        }
        $this->append('meta', $meta);
    }
    
    public function fetchMeta(){
        return $this->fetch('meta');
    }
    
}
