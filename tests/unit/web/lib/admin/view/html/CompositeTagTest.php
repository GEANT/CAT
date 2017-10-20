<?php
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\Tag;

class CompositeTagTest extends \PHPUnit_Framework_TestCase{
    
    private $compositeTag;
    
    private $tag;
    
    protected function setUp(){
        $this->compositeTag = new CompositeTag('div');
        $this->tag = new Tag('p');
    }
    
    public function testAddTag() {
        $this->assertEquals(0, $this->compositeTag->size());
        
        $this->compositeTag->addTag($this->tag);
        $this->assertEquals(1, $this->compositeTag->size());
        
        $tags = $this->compositeTag->getTags();
        $this->assertTrue(in_array($this->tag, $tags));
    }
    
    public function testToString(){
        $string = $this->compositeTag->__toString();
        $this->assertEquals('<div>', substr(trim($string), 0, 5));
        $this->assertEquals('</div>', substr(trim($string), -6));
        
        $this->compositeTag->addTag($this->tag);
        $string = $this->compositeTag->__toString();
        $this->assertEquals('<div>', substr(trim($string), 0, 5));
        $this->assertEquals('</div>', substr(trim($string), -6));
        
        $string = str_replace(array('<div>','</div>'), '', $string);
        $this->assertEquals('<p>', substr(trim($string), 0, 3));
        $this->assertEquals('</p>', substr(trim($string), -4));
    }
    
}
