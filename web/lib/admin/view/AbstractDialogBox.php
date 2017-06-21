<?php

namespace web\lib\admin\view;

use web\lib\admin\view\PageElementInterface;

/**
 *
 * @author Zilvinas Vaira
 *        
 */
abstract class AbstractDialogBox implements PageElementInterface {
    
    protected $action = '';
    
    protected $params = array ();
    
    /**
     * 
     * @param string $action
     */
    public function __construct($action){
        $this->action = $action;
    }
    
    public function addParameter($key, $value) {
        $this->params [$key] = $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() {
        $this->renderContent();
        ?>
        <form action="<?php echo $this->action; ?>" method="post" accept-charset="UTF-8">
        <?php 
            foreach ($this->params as $key => $value) {
                ?><input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>"><?php
            }
            $this->renderControls(); 
        ?>
        </form>
        <?php
    }
    
    protected abstract function renderContent();
    
    protected abstract function renderControls();
    
}
