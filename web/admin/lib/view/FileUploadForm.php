<?php
namespace lib\view;

use lib\domain\SilverbulletFactory;
use lib\http\AddUsersValidator;

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
     * @param SilverbulletFactory $factory
     * @param string $description
     */
    public function __construct($factory, $description) {
        $this->action = $factory->addQuery($_SERVER['SCRIPT_NAME']);
        $this->description = $description;
        $this->messageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $factory->distributeMessages(AddUsersValidator::COMMAND, $this->messageBox);
    }
    
    public function render(){
        ?>
        <div>
            <form enctype="multipart/form-data" method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
                <div class="<?php echo UserCredentialsForm::ADDNEWUSER_CLASS; ?>">
                    <?php $this->messageBox->render(); ?>
                    <p><?php echo $this->description; ?></p>
                    <div style="margin: 5px 0px 10px 0px;">
                        <input type="file" name="<?php echo AddUsersValidator::COMMAND; ?>">
                    </div>
                    <button type="submit" ><?php echo _('Import users'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}
