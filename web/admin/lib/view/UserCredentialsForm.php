<?php
namespace lib\view;

use lib\view\html\Table;
use lib\view\html\Row;
use lib\view\html\JsButton;
use lib\view\html\Tag;
use lib\view\html\UnaryTag;
use lib\view\html\CompositeTag;

class UserCredentialsForm implements PageElement{
    
    /**
     *
     * @var Table
     */
    private $table;

    /**
     *
     * @var Table
     */
    private $decorator;
    
    /**
     * 
     * @param string $title
     */
    public function __construct($title) {
        $this->table = new Table();
        $this->table->addAttribute("cellpadding", 5);
        $this->decorator = new TitledFormDecorator($this->table, $title, $_SERVER['REQUEST_URI'], PageElement::EDITABLEBLOCK_CLASS);
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
        $this->table->addToCell($index, 'user', new JsButton('button', _('Delete user'), 'user_id', 1, JsButton::DELETE_USER_ACTION, 'delete'));
        $this->table->addToCell($index, 'user', new JsButton('button', _('New Credential'), '', '', JsButton::NEW_CREDENTIAL_ACTION));
        $this->table->addToCell($index, 'action', new JsButton('button', _('Revoke'), 'credential_id', 1, JsButton::REVOKE_CREDENTIAL_ACTION, 'delete'));
    }
    
    public function addCertificateRow($rowArray){
        $index = $this->table->size();
        $this->table->addRowArray($rowArray);
        $this->table->addToCell($index, 'action', new JsButton('button', _('Revoke'), 'credential_id', 1, JsButton::REVOKE_CREDENTIAL_ACTION, 'delete'));
    }
    
    public function render(){
        $this->decorator->render();
        
        $p = new Tag('p');
        //$p->addAttribute('style', 'font-weight:bold;');
        $p->addText(_("Provide user name and surname to create a new user:"));
        $p->render();
        
        $input = new UnaryTag('input');
        $input->addAttribute("type", "text");
        $p = new CompositeTag('p');
        $p->addTag($input);
        $p->render();
        
        echo new JsButton('button', _('Add new user'), '', '', JsButton::NEW_USER_ACTION);
    }
}