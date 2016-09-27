<?php
namespace lib\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class Tag extends UnaryTag{
    
    /**
     *
     * @var string
     */
    protected $text = "";
    
    public function addText($text){
        $this->text .= $text;
    }
    
    /**
     * 
     * @return string
     */
    protected function composeInnerString(){
        return "\n\t" . $this->tab . $this->text;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see UnaryTag::composeTagString()
     */
    protected function composeTagString($attributeString){
        return "\n".$this->tab."<" . $this->name . $attributeString . ">" . $this->composeInnerString() . "\n".$this->tab."</" . $this->name . ">";
    }
    
}