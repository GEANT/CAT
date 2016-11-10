<?php
namespace lib\view\html;

class CompositeTag extends Tag {
    
    /**
     * 
     * @var Tag []
     */
    protected $tags = array();
    
    /**
     * 
     * @param Tag $tag
     */
    public function addTag($tag) {
        $this->tags [] = $tag;
    }
    
    /**
     * 
     * @return Tag []
     */
    public function getTags(){
        return $this->tags;
    }
    
    /**
     * 
     * @return number
     */
    public function size(){
        return count($this->tags);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \lib\html\Tag::composeInnerString()
     */
    protected function composeInnerString(){
        $innerString = "\n\t" . $this->tab . $this->text;
        foreach ($this->tags as $tag) {
            $tag->setTab("\t".$this->tab);
            $innerString .= $tag;
        }
        return $innerString;
    }
    
}
