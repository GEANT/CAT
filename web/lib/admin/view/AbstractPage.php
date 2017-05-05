<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;

/**
 * Represents abstract page which can be composed of page elements. These elements can be divided into named sections.
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractPage implements PageElementInterface{

    /**
     * Stores reusable null page element.
     * 
     * @var PageElementNull
     */
    protected $nullElement;
    
    /**
     * List of page element sections. An associative array that stores single element per section.
     *
     * @var PageElementInterface[]
     */
    protected $blocks = array();

    /**
     * List of page element group sections. An associative array that stores group of elements per section.
     *
     * @var PageElementGroup[]
     */
    protected $groups = array();
    
    /**
     * Instantiates common null element.
     */
    public function __construct(){
        $this->nullElement = new PageElementNull();
    }
    
    /**
     * Appends page element to a section. If section already contains an element it is transfromed to element group.
     * 
     * @param string $name Section name.
     * @param PageElementInterface $element Page element object.
     */
    public function append($name, $element){
        if(isset($this->groups [$name])){
            $group = $this->groups [$name];
            $group->addElement($element);
        }else if(isset($this->blocks[$name])){
            $group = new PageElementGroup();
            $group->addElement($this->blocks[$name]);
            $this->blocks[$name] = $this->groups[$name] = $group;
            $group->addElement($element);
        }else{
            $this->assign($name, $element);
        }
    }
    
    /**
     * Append() overload for HTML elements.
     * 
     * @param string $name
     * @param HtmlElementInterface $element
     */
    public function appendHtmlElement($name, $element){
        $this->append($name, new PageElementAdapter($element));
    }
    
    /**
     * Assigns single page element to a section. Unsets element group if there was one.
     * 
     * @param string $name Section name.
     * @param PageElementInterface $element Page element object.
     */
    public function assign($name, $element){
        $this->blocks [$name] = $element;
        if(isset($this->groups [$name])){
            unset($this->groups[$name]);
        }
    }

    /**
     * Assign() overload for HTML elements.
     * 
     * @param string $name
     * @param HtmlElementInterface $element
     */
    public function assignHtmlElement($name, $element){
        $this->assign($name, new PageElementAdapter($element));
    }
    
    /**
     * Retrieves and returns page element or group of page element objects for a particular section.
     * 
     * @param string $name Section name.
     * @return PageElementInterface Page element or group of page elements.
     */
    public function fetch($name){
        if(isset($this->blocks[$name])){
            return $this->blocks[$name];
        }else{
            return $this->nullElement;
        }
    }
    
}
