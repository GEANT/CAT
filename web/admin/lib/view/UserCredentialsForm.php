<?php
namespace lib\view;

use lib\view\html\Button;
use lib\view\html\Row;
use lib\view\html\Table;

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
        $row->addAttribute('style', 'background:lightgrey;');
        $this->table->addRow($row);
    }
    
    public function addUserCertificateRow($rowArray){
        $row = new Row($rowArray);
        $row->addAttribute('style', 'background:#F0F0F0;');
        $index = $this->table->size();
        $this->table->addRow($row);
        $this->table->addToCell($index, 'user', new Button(_('Delete user'),'button','','','delete'));
        $this->table->addToCell($index, 'user', new Button(_('New Credential'),'button'));
        $this->table->addToCell($index, 'action', new Button(_('Revoke'), 'button', '', '', 'delete'));
    }
    
    public function addCertificateRow($rowArray){
        $index = $this->table->size();
        $this->table->addRowArray($rowArray);
        $this->table->addToCell($index, 'action', new Button(_('Revoke'), 'button', '', '', 'delete'));
    }
    
    public function render(){
        $this->decorator->render();
        ?>
        <form method="post" action="<?php echo $this->action;?>" accept-charset="utf-8">
            <div class="sb-add-new-user">
                <label for="newUserName"><?php echo _("Provide user name and surname to create a new user:"); ?></label>
                <input type="text" name="newUserName">
                <button type="submit" ><?php echo _('Add new user'); ?></button>
            </div>
            
        </form>
        <?php
    }
}