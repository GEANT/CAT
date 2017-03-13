<?php
namespace web\lib\admin\view;

class TitledBlockDecorator extends PageElementDecorator{
    
    /**
     * 
     * @var string
     */
    private $title = "";
    
    /**
     * 
     * @param PageElementInterface $element
     * @param string $class
     * @param string $title
     */
    public function __construct($element, $title, $class = ""){
        parent::__construct($element, $class);
        $this->title = $title;
        
    }
    
    public function render() {
    ?>
    <div<?php echo $this->class; ?>>
        <h2>
            <?php echo $this->title; ?>
        </h2>
            <?php 
                $this->element->render();
            ?>
    </div>
    <?php
    }
    
}
