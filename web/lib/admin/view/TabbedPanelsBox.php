<?php
namespace web\lib\admin\view;

use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\HtmlElementInterface;
use web\lib\admin\view\html\Tag;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TabbedPanelsBox implements PageElementInterface{

    /**
     * 
     * @var integer
     */
    private $active = 0;
    
    /**
     * 
     * @var integer
     */
    private $index = 0;
    
    /**
     *
     * @var HtmlElementInterface[]
     */
    private $titles = array();
    
    /**
     * 
     * @var PageElementInterface[]
     */
    private $elements = array();
    
    /**
     * 
     * @param integer $index
     * @return string
     */
    private function composeTabId($index){
        return PageElementInterface::TABS_CLASS.'-'.($index+1);
    }
    
    /**
     * 
     * @param string $title
     * @param TabbedElementInterface $element
     */
    public function addTabbedPanel($title, $element){
        $li = new CompositeTag('li');
        $a = new Tag('a');
        $a->addAttribute('href', '#'.$this->composeTabId($this->index));
        $a->addText($title);
        $li->addTag($a);
        $this->titles [$this->index] = $li;
        $this->elements [$this->index] = $element;
        if($element->isActive()){
            $this->active = $this->index;
        }
        $this->index++;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        ?>
        <div id="<?php echo PageElementInterface::TABS_CLASS; ?>" active="<?php echo $this->active; ?>">
            <ul>
                <?php foreach ($this->titles as $title) {
                    echo $title;
                }?>
            </ul>
            <?php foreach ($this->elements as $index => $element) { ?>
                <div id="<?php echo $this->composeTabId($index); ?>">
                    <?php $element->render(); ?>
                </div>
            <?php } ?>
        </div>
        <?php
    }
    
}
