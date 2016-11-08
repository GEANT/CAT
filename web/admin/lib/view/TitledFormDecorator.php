<?php
namespace lib\view;

use lib\view\html\Attribute;

class TitledFormDecorator extends PageElementDecorator{
    
    /**
     * 
     * @var string
     */
    private $title = "";
    /**
     * 
     * @var Attribute
     */
    private $action;
    /**
     * 
     * @var Attribute
     */
    private $method;
    /**
     * 
     * @var Attribute
     */
    private $charset;
    
    /**
     * 
     * @var RegularButton
     */
    private $elements = array();
    
    /**
     * 
     * @param PageElement $element
     * @param string $class
     * @param string $title
     */
    public function __construct($element, $title, $action, $class = '', $method = 'post', $charset = 'UTF-8'){
        parent::__construct($element, $class);
        $this->title = $title;
        $this->action = new Attribute('action', $action);
        $this->method = new Attribute('method', $method);
        $this->charset = new Attribute('accept-charset', $charset);
    }
    
    public function addHtmlElement($element){
        $this->elements [] = $element;
    }
    
    public function render() {
        ?>
        <form enctype="multipart/form-data"<?php echo $this->action.$this->method.$this->charset; ?>>
            <fieldset<?php echo $this->class; ?>>
                <legend>
                    <strong><?php echo $this->title; ?></strong>
                </legend>
                <?php 
                    $this->element->render();
                ?>
            </fieldset>
            <div>
                <?php
                    foreach ($this->elements as $element) {
                        echo "\n".$element;
                    }
                ?>
            </div>
        </form>
        <?php
    }
    
}
