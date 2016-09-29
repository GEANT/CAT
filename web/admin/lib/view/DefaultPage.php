<?php
namespace lib\view;

use lib\view\html\Tag;
use lib\view\html\UnaryTag;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class DefaultPage implements Page{
    
    private $title = "";
    
    private $blocks = array();
    
    public function __construct($title = "Unknown Page") {
        $this->setTitle($title);
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
        $script->addAttribute('src', $url);
        $this->append('script', $script);
    }
    
    public function fetchScript(){
        return $this->fetch('script');
    }
    
    public function appendCss($url){
        $css = new UnaryTag('link');
        $css->addAttribute('rel', 'stylesheet');
        $css->addAttribute('type', 'text/css');
        $css->addAttribute('href', $url);
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
