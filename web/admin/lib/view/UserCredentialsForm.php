<?php
namespace lib\view;

use lib\domain\SilverbulletFactory;
use lib\view\html\Button;
use lib\view\html\Row;
use lib\view\html\Table;
use lib\domain\SilverbulletUser;
use lib\domain\SilverbulletCertificate;

class UserCredentialsForm implements PageElement{
    
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
        $this->decorator = new TitledFormDecorator($this->table, $title, $this->action, PageElement::EDITABLEBLOCK_CLASS);
    }
    
    /**
     * 
     * @param array $rowArray
     */
    public function addTitleRow($rowArray){
        $row = new Row($rowArray);
        $row->addAttribute('class', 'sb-title-row');
        $this->table->addRow($row);
    }
    
    /**
     * 
     * @param SilverbulletUser $user
     */
    public function addUserRow($user){
        $row = new Row(array('user' => $user->getUsername()));
        $row->addAttribute('class', 'sb-user-row');
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
        $this->table->addRowArray(array('credentials' => 'cert' . $count, 'token' => $certificate->getOneTimeToken(), 'tokenExpiry' => $certificate->getTokenExpiry(), 'expiry' => $certificate->getExpiry()));
        $this->table->addToCell($index, 'action', new Button(_('Revoke'), 'submit', SilverbulletFactory::COMMAND_REVOKE_CERTIFICATE, $certificate->getIdentifier(), 'delete'));
    }
    
    public function render(){
        ?>
        <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
            <?php $this->decorator->render();?>
        </form>
        <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
            <div class="sb-add-new-user">
                <label for="<?php echo SilverbulletFactory::COMMAND_ADD_USER; ?>"><?php echo _("Please enter a username of your choice to create a new user:"); ?></label>
                <input type="text" name="<?php echo SilverbulletFactory::COMMAND_ADD_USER; ?>">
                <button type="submit" ><?php echo _('Add new user'); ?></button>
            </div>
        </form>
        <?php
    }
}