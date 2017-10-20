<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractForm implements TabbedElementInterface{
    
    /**
     * @var string
     */
    protected $action;
    
    /**
     *
     * @var string
     */
    protected $description;
    
    /**
     *
     * @var MessageBox
     */
    protected $messageBox;
    
    /**
     *
     * @param string $action
     * @param string $description
     */
    public function __construct($action, $description) {
        $this->action = $action;
        $this->description = $description;
        $this->messageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
    }
    
    /**
     *
     * @return boolean
     */
    public function isActive(){
        return $this->messageBox->hasMessages();
    }
}
