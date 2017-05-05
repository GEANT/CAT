<?php
namespace web\lib\admin\view;

use web\lib\admin\http\AddUserCommand;
use web\lib\admin\http\MessageDistributor;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddNewUserForm extends AbstractForm{
    
    const ADDNEWUSER_CLASS = 'sb-add-new-user';
    
    /**
     * 
     * @param MessageDistributor $distributor
     * @param string $action
     * @param string $description
     */
    public function __construct($distributor, $action, $description) {
        parent::__construct($action, $description);
        $distributor->distributeMessages(AddUserCommand::COMMAND, $this->messageBox);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render(){
        ?>
        <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
            <div class="<?php echo self::ADDNEWUSER_CLASS; ?>">
                <?php $this->messageBox->render();?>
                <label for="<?php echo AddUserCommand::PARAM_NAME; ?>"><?php echo $this->description; ?></label>
                <div style="margin: 5px 0px 10px 0px;">
                    <input type="text" name="<?php echo AddUserCommand::PARAM_NAME; ?>">
                    <?php 
                        $datePicker = new DatePicker(AddUserCommand::PARAM_EXPIRY);
                        $datePicker->render(); 
                    ?>
                </div>
            <button type="submit" name="command" value="<?php echo AddUserCommand::COMMAND; ?>"><?php echo _('Add new user'); ?></button>
            </div>
        </form>
        <?php
    }
}
