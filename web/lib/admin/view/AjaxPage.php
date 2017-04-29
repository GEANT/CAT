<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AjaxPage extends AbstractPage{
    
    /**
     * 
     */
    public function __construct() {
        header('Content-Type: text/xml');
    }
    
    public function setTitle($title){
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\Page::getTitle()
     */
    public function getTitle(){
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\Page::append()
     */
    public function append($name, $value){
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\Page::assign()
     */
    public function assign($name, $value){
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\Page::fetch()
     */
    public function fetch($name){
        return '';
    }
    
}
