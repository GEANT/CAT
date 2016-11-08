<?php
namespace lib\view\html;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class JSButton extends Button{
    
    const REVOKE_CREDENTIAL_ACTION = 'revokeCredential';
    const DELETE_USER_ACTION = 'deleteUser';
    const NEW_CREDENTIAL_ACTION = 'newCredential';
    const NEW_USER_ACTION = 'newUser';
    const SAVE_ACTION = 'save';
    
    private $action = "";
    
    public function __construct($title, $action, $name = '', $value = '', $type = 'button', $class = ''){
        parent::__construct($title, $type, $name, $value, $class);
        $this->action = $action;
    }
    
    protected function composeNameValueString() {
        return ' onclick="'.$this->action.'(\''.$this->name.'\',\''.$this->value.'\')"';
    }
}
