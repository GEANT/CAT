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
     * @param string $action
     * @param string $text
     */
    public function __construct($action, $text) {
        parent::__construct($action);
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
