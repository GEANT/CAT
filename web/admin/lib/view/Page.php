<?php
namespace lib\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
interface Page{
    
    /**
     * 
     * @param string $title
     */
    public function setTitle($title);
    
    /**
     * @return string
     */
    public function getTitle();
    
    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function append($name, $value);
    
    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function assign($name, $value);
    
    /**
     * 
     * @param string $name
     * @return string
     */
    public function fetch($name);
    
}
