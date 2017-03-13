<?php
namespace web\lib\admin\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Button extends Tag{
    
    const SUBMIT_TYPE = 'submit';
    const RESET_TYPE = 'reset';
    const BUTTON_TYPE = 'button';
    
    public function __construct($title, $type = self::SUBMIT_TYPE, $name = '', $value = '', $class = '', $id = ''){
        parent::__construct('button');
        $this->addAttribute('type', $type);
        $this->addAttribute('name', $name);
        $this->addAttribute('value', $value);
        $this->addAttribute('class', $class);
        $this->addAttribute('id', $id);
        $this->addText($title);
    }
    
}
