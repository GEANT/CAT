<?php
use web\lib\admin\view\html\Attribute;

class AttributeTest extends \PHPUnit_Framework_TestCase{
    
    private function validateString($attribute){
        $string = $attribute->__toString();
        $this->assertNotEmpty($string);
    
        $space = substr($string, 0, 1);
        $this->assertEquals($space,' ');
    
        $spaceRemoved = substr($string, 1);
        $pair = explode('=', $spaceRemoved, 2);
    
        if(count($pair)>1){
            $this->assertTrue(strlen($pair[0]) > 0);
            $this->assertTrue(strlen($pair[1]) > 2);
            $this->assertEquals(substr_count($pair[0], '"'), 0);
            $this->assertEquals(substr_count($pair[1], '"'), 2);
        }
    }
    
    public function testEmptyConstructor() {
        $string = (new Attribute(null, null))->__toString();
        $this->assertEmpty($string);
        $string = (new Attribute("", ""))->__toString();
        $this->assertEmpty($string);
        $string = (new Attribute("test", null))->__toString();
        $this->assertEmpty($string);
        $string = (new Attribute(null, "test"))->__toString();
        $this->assertEmpty($string);
    }
    
    public function testValidToString(){
        $this->validateString(new Attribute("type", "button"));
    }
    
    public function testFaultyToString(){
        $this->validateString(new Attribute("t\"ype", "b=ut\"ton"));
    }
    
}
