<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\HtmlElementInterface;
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\Tag;
use web\lib\admin\http\Message;

class MessageBox implements PageElementInterface, HtmlElementInterface, MessageReceiverInterface{
    
    private $class = '';
    
    /**
     * 
     * @var CompositeTag
     */
    private $box;
    
   /**
    * 
    * @param string $class
    */
    public function __construct($class) {
        $this->box = new CompositeTag('div');
        $this->class = $class;
        $this->box->addAttribute('class', $class);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\MessageReceiverInterface::hasMessages()
     */
    public function hasMessages(){
        return $this->box->size() > 0;
    }
    
    /**
     * 
     * @param Message $message
     * {@inheritDoc}
     * @see MessageReceiverInterface::receiveMessage()
     */
    public function receiveMessage($message){
        $p = new Tag('p');
        $p->addAttribute('class', $message->getClass($this->class));
        $p->addText($message->getText());
        $this->box->addTag($p);
    }
    
    /**
     * 
     */
    public function render(){
        echo $this->__toString();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see HtmlElementInterface::__toString()
     */
    public function __toString(){
        if($this->hasMessages()){
            return $this->box->__toString();
        }else{
            return '';
        }
    }
    
}
