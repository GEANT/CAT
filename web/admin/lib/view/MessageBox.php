<?php
namespace lib\view;

use lib\view\html\HtmlElementInterface;
use lib\view\html\CompositeTag;
use lib\view\html\Tag;
use lib\domain\http\ValidatorMessage;

class MessageBox implements HtmlElementInterface, MessageContainerInterface{
    
    private $class = '';
    
    /**
     * 
     * @var CompositeTag
     */
    private $box = new CompositeTag('div');
    
   /**
    * 
    * @param string $class
    */
    public function __construct($class) {
        $this->class = $class;
        $this->box->addAttribute('class', $class);
    }
    
    /**
     * 
     * @param ValidatorMessage $message
     * {@inheritDoc}
     * @see \lib\view\MessageContainerInterface::addMessage()
     */
    public function addMessage($message){
        $p = new Tag('p');
        $p->addAttribute('class', $message->getClass($this->class));
        $p->addText($message->getText());
        $this->box->addTag($p);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\view\html\HtmlElementInterface::__toString()
     */
    public function __toString(){
        if($this->box->size() > 0){
            return $this->box->__toString();
        }else{
            return '';
        }
    }
    
}