<?php
namespace lib\view;

use lib\domain\SilverbulletFactory;
use lib\view\html\Button;
use lib\view\html\Row;
use lib\view\html\Table;
use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

class UserCredentialsForm implements PageElement{
    
    const EDITABLEBLOCK_CLASS = 'sb-editable-block';
    const TITLEROW_CLASS = 'sb-title-row';
    const USERROW_CLASS = 'sb-user-row';
    const ADDNEWUSER_CLASS = 'sb-add-new-user';
    
    /**
     *
     * @var Table
     */
    private $table;

    /**
     *
     * @var PageElementDecorator
     */
    private $decorator;
    
    /**
     * @var string
     */
    private $action;
    
    /**
     * 
     * @param string $title
     */
    public function __construct($title) {
        $this->action = $_SERVER['REQUEST_URI'];
        $this->table = new Table();
        $this->table->addAttribute("cellpadding", 5);
        $this->decorator = new TitledFormDecorator($this->table, $title, $this->action);
    }
    
    /**
     * 
     * @param array $rowArray
     */
    public function addTitleRow($rowArray){
        $row = new Row($rowArray);
        $row->addAttribute('class', self::TITLEROW_CLASS);
        $this->table->addRow($row);
    }
    
    /**
     * 
     * @param SilverbulletUser $user
     */
    public function addUserRow($user){
        $row = new Row(array('user' => $user->getUsername(), 'token' => $user->getOneTimeTokenLink(), 'expiry' => $user->getTokenExpiry()));
        $row->addAttribute('class', self::USERROW_CLASS);
        $index = $this->table->size();
        $this->table->addRow($row);
        $this->table->addToCell($index, 'action', new Button(_('Delete User'),'submit', SilverbulletFactory::COMMAND_DELETE_USER, $user->getIdentifier(), 'delete'));
        $this->table->addToCell($index, 'action', new Button(_('New Credential'),'submit', SilverbulletFactory::COMMAND_ADD_CERTIFICATE, $user->getIdentifier()));
    }
    
    /**
     * 
     * @param SilverbulletCertificate $certificate
     * @param int $count
     */
    public function addCertificateRow($certificate, $count){
        $index = $this->table->size();
        $this->table->addRowArray(array('user' => $certificate->getCertificateTitle($count), 'token' => $certificate->getSerialNumber(), 'expiry' => $certificate->getExpiry()));
        $this->table->addToCell($index, 'action', new Button(_('Revoke'), 'submit', SilverbulletFactory::COMMAND_REVOKE_CERTIFICATE, $certificate->getIdentifier(), 'delete'));
    }
    
    public function render(){
        
        ?>
        <div class="<?php echo self::EDITABLEBLOCK_CLASS;?>">
            <?php $this->decorator->render();?>
            <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
                <div class="<?php echo self::ADDNEWUSER_CLASS; ?>">
                    <label for="<?php echo SilverbulletFactory::COMMAND_ADD_USER; ?>"><?php echo _("Please enter a username of your choice to create a new user:"); ?></label>
                    <input type="text" name="<?php echo SilverbulletFactory::COMMAND_ADD_USER; ?>">
                    <label for="<?php echo SilverbulletFactory::PARAM_YEAR; ?>"><?php echo _("Enter new user expiry date:"); ?></label>
                    <div>
                        <input type="text" maxlength="4" style="width: 40px; display: inline;" name="<?php echo SilverbulletFactory::PARAM_YEAR; ?>"> - 
                        <input type="text" maxlength="2" style="width: 20px; display: inline;" name="<?php echo SilverbulletFactory::PARAM_MONTH; ?>"> - 
                        <input type="text" maxlength="2" style="width: 20px; display: inline;" name="<?php echo SilverbulletFactory::PARAM_DAY; ?>">
                    </div>
                    <button type="submit" ><?php echo _('Add new user'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}
