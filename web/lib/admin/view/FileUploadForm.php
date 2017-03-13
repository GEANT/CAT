<?php
namespace web\lib\admin\view;

use web\lib\admin\http\SilverbulletController;
use web\lib\admin\http\AddUsersCommand;

class FileUploadForm implements PageElementInterface{
    
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
    private $messageBox;
    
    /**
     * 
     * @param SilverbulletController $controller
     * @param string $description
     */
    public function __construct($controller, $description) {
        $this->action = $controller->addQuery($_SERVER['SCRIPT_NAME']);
        $this->description = $description;
        $this->messageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $controller->distributeMessages(AddUsersCommand::COMMAND, $this->messageBox);
    }
    
    public function render(){
        ?>
        <div>
            <form enctype="multipart/form-data" method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
                <div class="<?php echo UserCredentialsForm::ADDNEWUSER_CLASS; ?>">
                    <?php $this->messageBox->render(); ?>
                    <p><?php echo $this->description; ?></p>
                    <div style="margin: 5px 0px 10px 0px;">
                        <input type="file" name="<?php echo AddUsersCommand::COMMAND; ?>">
                    </div>
                    <button type="submit" name="command" value="<?php echo AddUsersCommand::COMMAND; ?>" ><?php echo _('Import users'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}
