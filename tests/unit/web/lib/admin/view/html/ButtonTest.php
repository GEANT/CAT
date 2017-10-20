<?php
use web\lib\admin\view\html\Button;

class ButtonTest extends \PHPUnit_Framework_TestCase{
    
    private $title;
    
    protected function setUp(){
        $this->title = 'Some title';
    }
    
    public function testSubmitButton() {
        $button = new Button($this->title);
        $string = $button->__toString();
        
        $this->assertNotEquals(strpos($string, 'type="submit"'), false);
        $this->assertNotEquals(strpos($string, $this->title), false);
    }
    
    public function testCustomButton() {
        $type = Button::BUTTON_TYPE;
        $name = 'Some Name';
        $value = 'Some Value';
        $class = 'someClass';
        
        $button = new Button($this->title, $type, $name, $value, $class);
        $string = $button->__toString();
    
        $this->assertNotEquals(strpos($string, 'type="'.$type.'"'), false);
        $this->assertNotEquals(strpos($string, 'name="'.$name.'"'), false);
        $this->assertNotEquals(strpos($string, 'value="'.$value.'"'), false);
        $this->assertNotEquals(strpos($string, 'class="'.$class.'"'), false);
        $this->assertNotEquals(strpos($string, $this->title), false);
    }
    
}
