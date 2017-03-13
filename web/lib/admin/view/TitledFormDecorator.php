<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\Attribute;

class TitledFormDecorator extends PageElementDecorator{
    
    const BEFORE = 0;
    
    const AFTER = 1;
    
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
     * @var array
     */
    private $elements = array( self::BEFORE => array(), self::AFTER => array());
    
    /**
     * 
     * @param PageElementInterface $element
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
    
    public function addHtmlElement($element, $position = self::AFTER){
        $this->elements[$position][] = $element;
    }
    
    public function render() {
        ?>
        <form enctype="multipart/form-data"<?php echo $this->action.$this->method.$this->charset; ?>>
            <fieldset<?php echo $this->class; ?>>
                <legend>
                    <strong><?php echo $this->title; ?></strong>
                </legend>

                <?php
                    foreach ($this->elements[self::BEFORE] as $element) {
                        echo "\n".$element;
                    }
                ?>

                <?php 
                    $this->element->render();
                ?>

                <?php if(count($this->elements[self::AFTER]) > 0){ ?>
                <div style="padding: 20px;">
                <?php
                    foreach ($this->elements[self::AFTER] as $element) {
                        echo "\n".$element;
                    }
                ?>
                </div>
                <?php }?>

            </fieldset>
        </form>
        <?php
    }
    
}
