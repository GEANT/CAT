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
        $innerString = "";
        if(!empty($this->text)){
            $innerString = "\n\t" . $this->tab . $this->text;
        }
        return $innerString;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see UnaryTag::composeTagString()
     */
    protected function composeTagString($attributeString){
        $tagString = "";
        $innerString = $this->composeInnerString();
        if(!empty($innerString)){
            $tagString = "\n".$this->tab."<" . $this->name . $attributeString . ">" . $innerString . "\n".$this->tab."</" . $this->name . ">";
        }else{
            $tagString = "\n".$this->tab."<" . $this->name . $attributeString . "></" . $this->name . ">";
        }
        return $tagString;
    }
    
}
