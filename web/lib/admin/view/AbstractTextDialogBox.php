<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
abstract class AbstractTextDialogBox extends AbstractDialogBox{
    
    /**
     * 
     * @var string
     */
    protected $text = '';
    
    /**
     * 
     * @param string $id
     * @param string $action
     * @param string $title
     * @param string $text
     * @param boolean $isVisible
     */
    public function __construct($id, $action, $title, $text, $isVisible = true) {
        parent::__construct($id, $action, $title, $isVisible);
        $this->text = $text;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderContent()
     */
    protected function renderContent(){
        ?><p><?php echo $this->text; ?></p><?php
    }

}
