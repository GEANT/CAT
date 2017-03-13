<?php

namespace web\lib\admin\view;

use web\lib\admin\view\PageElementInterface;

/**
 *
 * @author Zilvinas Vaira
 *        
 */
abstract class AbstractDialogBox implements PageElementInterface {

    protected $id = '';
    
    protected $action = '';
    
    protected $title = '';
    
    protected $closeButtonClass = '';
    
    protected $params = array ();
    
    public function __construct($id, $action, $title){
        $this->id = $id;
        $this->action = $action;
        $this->title = $title;
        $this->setCloseButtonClass('close');
    }
    
    public function addParameter($key, $value) {
        $this->params [$key] = $value;
    }
    
    public function setCloseButtonClass($token){
        $this->closeButtonClass = $this->id . '-' . $token;
    }
    
    public function render() {
        ?>
        <div id="<?php echo $this->id; ?>">
            <div id="overlay"></div>
            <div id="msgbox">
                <div style="top: 100px;">
                    <div class="graybox">
                        <img class="<?php echo $this->closeButtonClass; ?>" src="../resources/images/icons/button_cancel.png" alt="cancel">
                        <h1><?php echo $this->title; ?></h1>
                        <div style="position: relative;">
                            <?php $this->renderContent(); ?>
                        </div>
                        <form action="<?php echo $this->action; ?>" method="post" accept-charset="UTF-8">
                        <?php 
                        foreach ($this->params as $key => $value) {
                           ?><input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>"><?php
                        }
                        $this->renderControls(); 
                        ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    protected abstract function renderContent();
    
    protected abstract function renderControls();
    
}
