<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AbstractPage{

    /**
     * 
     * @var PageElementNull
     */
    protected $nullElement;
    
    /**
     *
     * @var PageElementInterface[]
     */
    protected $blocks = array();

    /**
     *
     * @var PageElementGroup[]
     */
    protected $groups = array();
    
    /**
     * 
     */
    public function __construct(){
        $this->nullElement = new PageElementNull();
    }
    
    /**
     * 
     * @param string $name
     * @param PageElementInterface $element
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
     * 
     * @param string $name
     * @param PageElementInterface $element
     */
    public function assign($name, $element){
        $this->blocks [$name] = $element;
    }
    
    /**
     * 
     * @param string $name
     * @return PageElementInterface
     */
    public function fetch($name){
        if(isset($this->blocks[$name])){
            return $this->blocks[$name];
        }else{
            return $this->nullElement;
        }
    }
    
}
