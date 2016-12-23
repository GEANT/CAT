<?php
namespace lib\view;

use lib\domain\SilverbulletFactory;

class FileUploadForm implements PageElement{
    
    /**
     * @var string
     */
    private $action;
    
    /**
     * 
     * @var string
     */
    private $description;
    
    public function __construct($factory, $description) {
        $this->action = $factory->addQuery($_SERVER['SCRIPT_NAME']);
        $this->description = $description;
    }
    
    public function render(){
        ?>
        <div>
            <form enctype="multipart/form-data" method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
                <div class="<?php echo UserCredentialsForm::ADDNEWUSER_CLASS; ?>">
                    <p><?php echo $this->description; ?></p>
                    <div style="margin: 5px 0px 10px 0px;">
                        <input type="file" name="<?php echo SilverbulletFactory::COMMAND_ADD_USERS; ?>">
                    </div>
                    <button type="submit" ><?php echo _('Import users'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}