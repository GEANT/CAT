<?php
namespace web\lib\admin\view;

use web\lib\admin\http\SilverbulletController;
use web\lib\admin\view\html\Button;
use web\lib\admin\view\html\Row;
use web\lib\admin\view\html\Table;
use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\view\html\Tag;
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\UnaryTag;
use web\lib\admin\http\SaveUsersCommand;
use web\lib\admin\http\AddCertificateCommand;
use web\lib\admin\http\AddUserCommand;
use web\lib\admin\http\DeleteUserCommand;
use web\lib\admin\http\RevokeCertificateCommand;

class UserCredentialsForm implements PageElementInterface{
    
    const EDITABLEBLOCK_CLASS = 'sb-editable-block';
    const TITLEROW_CLASS = 'sb-title-row';
    const USERROW_CLASS = 'sb-user-row';
    const CERTIFICATEROW_CLASS = 'sb-certificate-row';
    const ADDNEWUSER_CLASS = 'sb-add-new-user';
    const RESET_BUTTON_ID = 'sb-reset-dates';
    
    /**
     *
     * @var Table
     */
    private $table;

    /**
     *
     * @var TitledFormDecorator
     */
    private $decorator;
    
    /**
     * @var string
     */
    private $action;

    /**
     * 
     * @var MessageBox
     */
    private $addUserMessageBox;
    
    /**
     * 
     * @param string $title
     * @param SilverbulletController $controller
     */
    public function __construct($title, $controller, $isNotEmpty = false) {
        $this->action = $controller->addQuery($_SERVER['SCRIPT_NAME']);
        $this->table = new Table();
        $this->table->addAttribute("cellpadding", 5);
        $this->decorator = new TitledFormDecorator($this->table, $title, $this->action);

        $hiddenCommand = new UnaryTag('input');
        $hiddenCommand->addAttribute('type', 'hidden');
        $hiddenCommand->addAttribute('name', 'command');
        $hiddenCommand->addAttribute('value', SaveUsersCommand::COMMAND);
        $this->decorator->addHtmlElement($hiddenCommand, TitledFormDecorator::BEFORE);
        
        $saveMessageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $controller->distributeMessages(SaveUsersCommand::COMMAND, $saveMessageBox);
        $controller->distributeMessages(AddCertificateCommand::COMMAND, $saveMessageBox);
        $controller->distributeMessages(DeleteUserCommand::COMMAND, $saveMessageBox);
        $this->decorator->addHtmlElement($saveMessageBox, TitledFormDecorator::BEFORE);
        
        $this->addUserMessageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $controller->distributeMessages(AddUserCommand::COMMAND, $this->addUserMessageBox);
        
        if($isNotEmpty){
            $div = new CompositeTag('div');
            $div->addAttribute('style', 'padding-bottom: 20px;');
                $checkbox = new UnaryTag('input');
                $checkbox->addAttribute('type', 'checkbox');
                $checkbox->addAttribute('name', SaveUsersCommand::PARAM_ACKNOWLEDGE);
                $checkbox->addAttribute('value', 'true');
            $div->addTag($checkbox);
                $label = new Tag('label');
                $label->addText('I have verified that all configured users are still eligible for eduroam');
            $div->addTag($label);
            $this->decorator->addHtmlElement($div);
            $this->decorator->addHtmlElement(new Button(_('Save'),'submit', SaveUsersCommand::COMMAND, SaveUsersCommand::COMMAND));
            $this->decorator->addHtmlElement(new Button(_('Reset'),'reset', '', '', 'delete', self::RESET_BUTTON_ID));
        }
        $this->addTitleRow();
    }
    
    /**
     * 
     */
    private function addTitleRow(){
        $row = new Row(array('user' => 'User', 'token' => 'Token/Certificate details', 'expiry' => 'User Expiry/Certificate Expiry', 'action' => 'Actions'));
        $row->addAttribute('class', self::TITLEROW_CLASS);
        $this->table->addRow($row);
    }
    
    /**
     * 
     * @param SilverbulletUser $user
     */
    public function addUserRow($user){
        $row = new Row(array('user' => $user->getUsername(), 'expiry' => new DatePicker(SaveUsersCommand::PARAM_EXPIRY_MULTIPLE, $user->getExpiry()) ));
        $row->addAttribute('class', self::USERROW_CLASS);
        $acknowledgeLevel = $user->getAcknowledgeLevel();
        if($acknowledgeLevel == SilverbulletUser::LEVEL_YELLOW){
            $row->addAttribute('style', 'background-color:#F0EAC0;');
        }elseif ($acknowledgeLevel == SilverbulletUser::LEVEL_RED){
            $row->addAttribute('style', 'background-color:#F0C0C0;');
        }
        $index = $this->table->size();
        $this->table->addRow($row);
        $hiddenUserId = new Tag('input');
        $hiddenUserId->addAttribute('type', 'hidden');
        $hiddenUserId->addAttribute('name', SaveUsersCommand::PARAM_ID_MULTIPLE);
        $hiddenUserId->addAttribute('value', $user->getIdentifier());
        $this->table->addToCell($index, 'user', $hiddenUserId);
        $action = new CompositeTag('div');
        $action->addAttribute('style', 'min-width:150px');
        $action->addTag(new Button(_('Delete User'),'submit', DeleteUserCommand::COMMAND, $user->getIdentifier(), 'delete'));
        $action->addTag(new Button(_('New Credential'),'submit', AddCertificateCommand::COMMAND, $user->getIdentifier()));
        $this->table->addToCell($index, 'action', $action);
    }
    
    /**
     * 
     * @param SilverbulletCertificate $certificate
     */
    public function addCertificateRow($certificate){
        $row = new Row(array('token' => $certificate->getCertificateDetails(), 'expiry' => $certificate->getExpiry()));
        $row->addAttribute('class', self::CERTIFICATEROW_CLASS);
        $index = $this->table->size();
        $this->table->addRow($row);
        $this->table->addToCell($index, 'action', new Button(_('Revoke'), 'submit', RevokeCertificateCommand::COMMAND, $certificate->getIdentifier(), 'delete'));
    }
    
    public function render(){
        ?>
        <div class="<?php echo self::EDITABLEBLOCK_CLASS;?>">
            <?php $this->decorator->render();?>
            <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
                <div class="<?php echo self::ADDNEWUSER_CLASS; ?>">
                    <?php $this->addUserMessageBox->render();?>
                    <label for="<?php echo AddUserCommand::PARAM_NAME; ?>"><?php echo _("Please enter a username of your choice and user expiry date to create a new user:"); ?></label>
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
        </div>
        <?php
    }
}
