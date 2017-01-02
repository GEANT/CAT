<?php
namespace lib\view;
use lib\view\PageElementInterface;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TermosOfUseBox implements PageElementInterface{
    
    private $id = '';
    
    private $action = '';

    private $command = '';
    
    private $param = '';
    
    
    public function __construct($id, $action, $command, $param) {
        $this->id = $id;
        $this->action = $action;
        $this->command = $command;
        $this->param = $param;
    }
    
    public function render(){
        ?>
        <div id="<?php echo $this->id; ?>">
            <div id="overlay"></div>
            <div id="msgbox">
                <div style="top: 394.5px;">
                    <div class="graybox">
                        <img id="<?php echo $this->id . '-close'; ?>" src="../resources/images/icons/button_cancel.png" alt="cancel">
                        <h1><?php echo _('Terms of Use'); ?></h1>
                        <hr>
                        <div class="ca-summary" style="position:relative;"><?php echo _('Some text for terms of use...'); ?></div>
                        <hr>
                        <form action="<?php echo $this->action; ?>" method="post" accept-charset="UTF-8">
                            <div style="position:relative; padding-bottom: 5px;">
                                <input type="checkbox" name="<?php echo $this->param; ?>" value="true">
                                <label>I agree to terms...</label>
                            </div> 
                            <button type="submit" name="command" value="<?php echo $this->command; ?>" >Continue</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
