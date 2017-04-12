<?php
namespace web\lib\admin\view;

use web\lib\admin\http\AddUserCommand;
use web\lib\admin\http\SilverbulletController;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddNewUserForm implements TabbedElementInterface{
    
    const ADDNEWUSER_CLASS = 'sb-add-new-user';
    
    /**
     * @var string
     */
    private $action;

    /**
     *
     * @var string
     */
    private $description;
    
    /**
     *
     * @var MessageBox
     */
    private $addUserMessageBox;
    
    /**
     * 
     * @param SilverbulletController $controller
     * @param string $description
     */
    public function __construct($controller, $description) {
        $this->action = $controller->addQuery($_SERVER['SCRIPT_NAME']);
        $this->description = $description;
        $this->addUserMessageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $controller->distributeMessages(AddUserCommand::COMMAND, $this->addUserMessageBox);
    }
    
    /**
     * 
     * @return boolean
     */
    public function isActive(){
        return $this->addUserMessageBox->hasMessages();
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
                <?php $this->addUserMessageBox->render();?>
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